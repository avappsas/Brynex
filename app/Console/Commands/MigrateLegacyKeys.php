<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyKeys extends Command
{
    protected $signature = 'legacy:migrate-keys {--dry-run : Simular la migración sin guardar cambios}';
    protected $description = 'Migra las contraseñas/claves desde Base_De_Datos del legacy hacia clave_accesos u observaciones de clientes';

    private array $dbsMap = [
        2 => 'Brygar_BD',
        4 => 'GiMave_Integral',
        7 => 'Mave_Anderson',
        8 => 'SS_Faga',
        9 => 'LuisLopez',
    ];

    public function handle(): void
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️ MODO SIMULACIÓN (DRY-RUN) ACTIVO — No se guardarán cambios en la base de datos.');
        } else {
            $this->warn('⚠️ EJECUCIÓN REAL — Se modificarán los datos de producción. Asegúrate de tener respaldo.');
            if (!$this->confirm('¿Deseas continuar con la migración real?', false)) {
                $this->info('Cancelado por el usuario.');
                return;
            }
        }

        foreach ($this->dbsMap as $aliadoId => $db) {
            $this->info("\nProcessing Aliado ID: $aliadoId ($db)...");
            
            try {
                $rows = DB::connection('sqlsrv_legacy')
                    ->select("SELECT Cedula, claves FROM [$db].dbo.Base_De_Datos WHERE claves IS NOT NULL AND claves != ''");
            } catch (\Exception $e) {
                $this->error("❌ Error al consultar la base legacy '$db': " . $e->getMessage());
                continue;
            }

            $totalRows = count($rows);
            $this->line("  🔍 Encontrados $totalRows registros con texto en 'claves' en legacy.");

            // Cargar en memoria todos los clientes de este aliado
            $clientes = DB::table('clientes')
                ->where('aliado_id', $aliadoId)
                ->select('id', 'cedula', 'observacion')
                ->get()
                ->keyBy('cedula')
                ->all();

            // Cargar en memoria todas las claves existentes para evitar duplicados
            $existingClaves = DB::table('clave_accesos')
                ->where('aliado_id', $aliadoId)
                ->select('cedula', 'tipo', 'entidad')
                ->get()
                ->groupBy('cedula')
                ->map(function ($group) {
                    return $group->map(function ($item) {
                        return strtolower($item->tipo . '|' . $item->entidad);
                    })->toArray();
                })
                ->all();

            $createdKeysCount = 0;
            $appendedObservationsCount = 0;
            $skippedCount = 0;

            foreach ($rows as $r) {
                $cedulaLegacy = trim($r->Cedula);
                $cedula = is_numeric($cedulaLegacy) && $cedulaLegacy > 0 ? (string)(int)$cedulaLegacy : null;

                if (!$cedula) {
                    $skippedCount++;
                    continue;
                }

                // Buscar cliente en memoria
                if (!isset($clientes[$cedula])) {
                    $skippedCount++;
                    continue;
                }

                $cliente = $clientes[$cedula];
                $text = trim($r->claves);
                
                // Dividir solo por saltos de línea
                $lines = preg_match('/[\n\r]+/', $text) ? preg_split('/[\n\r]+/', $text) : [$text];

                $matchedAny = false;
                $unmatchedLines = [];

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strlen($line) < 3) continue;

                    $parsed = $this->parseCredential($line);

                    if ($parsed) {
                        $matchedAny = true;

                        // Verificar en memoria si ya existe
                        $keyMatchStr = strtolower($parsed['tipo'] . '|' . $parsed['entidad']);
                        $alreadyExists = isset($existingClaves[$cedula]) && in_array($keyMatchStr, $existingClaves[$cedula]);

                        if (!$alreadyExists) {
                            $createdKeysCount++;
                            if (!$dryRun) {
                                DB::table('clave_accesos')->insert([
                                    'aliado_id' => $aliadoId,
                                    'cedula' => $cedula,
                                    'tipo' => $parsed['tipo'],
                                    'entidad' => $parsed['entidad'],
                                    'usuario' => null,
                                    'contrasena' => $parsed['contrasena'],
                                    'observacion' => $parsed['observacion'],
                                    'activo' => 1,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                                // Registrar en memoria para evitar insertar duplicados si hay líneas repetidas
                                $existingClaves[$cedula][] = $keyMatchStr;
                            }
                        }
                    } else {
                        $unmatchedLines[] = $line;
                    }
                }

                // Si no se interpretó ninguna credencial en todo el texto, o si hay líneas sin interpretar
                if (!$matchedAny || !empty($unmatchedLines)) {
                    $observationToAppend = !$matchedAny ? $text : implode("\n", $unmatchedLines);
                    $existingObs = trim($cliente->observacion ?? '');
                    
                    if (stripos($existingObs, $observationToAppend) === false) {
                        $appendedObservationsCount++;
                        
                        if (!$dryRun) {
                            $newObs = $existingObs 
                                ? ($existingObs . "\n[Legacy Claves]: " . $observationToAppend) 
                                : "[Legacy Claves]: " . $observationToAppend;

                            DB::table('clientes')
                                ->where('id', $cliente->id)
                                ->update(['observacion' => $newObs, 'updated_at' => now()]);

                            // Actualizar en el mapa de memoria por si acaso
                            $cliente->observacion = $newObs;
                        }
                    }
                }
            }

            $this->info("  📊 Resultados para $db:");
            $this->line("    🔑 Credenciales nuevas a crear en clave_accesos: $createdKeysCount");
            $this->line("    📝 Clientes cuyas observaciones se actualizarán: $appendedObservationsCount");
            $this->line("    ⏭️ Omitidos (no existen en BryNex o cédula inválida): $skippedCount");
        }

        $this->info("\n✅ Proceso finalizado.");
    }

    private function parseCredential(string $text): ?array
    {
        $text = trim($text);
        if (empty($text)) return null;

        $rules = [
            // EPS
            ['pattern' => '/sura/i', 'tipo' => 'EPS', 'entidad' => 'Sura', 'is_arl' => false],
            ['pattern' => '/sanitas/i', 'tipo' => 'EPS', 'entidad' => 'Sanitas'],
            ['pattern' => '/salud\s*total/i', 'tipo' => 'EPS', 'entidad' => 'Salud Total'],
            ['pattern' => '/coosalud/i', 'tipo' => 'EPS', 'entidad' => 'Coosalud'],
            ['pattern' => '/nueva\s*eps/i', 'tipo' => 'EPS', 'entidad' => 'Nueva EPS'],
            ['pattern' => '/famisanar/i', 'tipo' => 'EPS', 'entidad' => 'Famisanar'],
            ['pattern' => '/sos/i', 'tipo' => 'EPS', 'entidad' => 'S.O.S'],
            ['pattern' => '/compensar/i', 'tipo' => 'EPS', 'entidad' => 'Compensar', 'is_caja' => false],
            ['pattern' => '/eps/i', 'tipo' => 'EPS', 'entidad' => 'EPS'],

            // ARL
            ['pattern' => '/arl/i', 'tipo' => 'ARL', 'entidad' => 'ARL'],
            ['pattern' => '/positiva/i', 'tipo' => 'ARL', 'entidad' => 'Positiva ARL'],
            ['pattern' => '/colpatria/i', 'tipo' => 'ARL', 'entidad' => 'Colpatria ARL'],

            // Correo
            ['pattern' => '/gmail/i', 'tipo' => 'Correo', 'entidad' => 'Gmail'],
            ['pattern' => '/hotmail/i', 'tipo' => 'Correo', 'entidad' => 'Hotmail'],
            ['pattern' => '/outlook/i', 'tipo' => 'Correo', 'entidad' => 'Outlook'],
            ['pattern' => '/yahoo/i', 'tipo' => 'Correo', 'entidad' => 'Yahoo'],
            ['pattern' => '/correo|email/i', 'tipo' => 'Correo', 'entidad' => 'Correo'],

            // DIAN
            ['pattern' => '/dian|muisca|rut/i', 'tipo' => 'DIAN', 'entidad' => 'DIAN'],

            // AFP (Pensión)
            ['pattern' => '/colpensiones/i', 'tipo' => 'AFP', 'entidad' => 'Colpensiones'],
            ['pattern' => '/porvenir/i', 'tipo' => 'AFP', 'entidad' => 'Porvenir'],
            ['pattern' => '/proteccion|protección/i', 'tipo' => 'AFP', 'entidad' => 'Protección'],
            ['pattern' => '/colfondos/i', 'tipo' => 'AFP', 'entidad' => 'Colfondos'],
            ['pattern' => '/pension|pensión|afp/i', 'tipo' => 'AFP', 'entidad' => 'Pensión'],

            // CAJA
            ['pattern' => '/comfenalco/i', 'tipo' => 'CAJA', 'entidad' => 'Comfenalco'],
            ['pattern' => '/comfama/i', 'tipo' => 'CAJA', 'entidad' => 'Comfama'],
            ['pattern' => '/cafam/i', 'tipo' => 'CAJA', 'entidad' => 'Cafam'],
            ['pattern' => '/colsubsidio/i', 'tipo' => 'CAJA', 'entidad' => 'Colsubsidio'],
            ['pattern' => '/comfacauca/i', 'tipo' => 'CAJA', 'entidad' => 'Comfacauca'],
            ['pattern' => '/caja/i', 'tipo' => 'CAJA', 'entidad' => 'Caja'],

            // Operadores / PILA
            ['pattern' => '/sat|pila|operador|soi|arus|simple|miplanilla|asopagos|aportes/i', 'tipo' => 'Operadores', 'entidad' => 'PILA'],
        ];

        if (preg_match('/sura/i', $text) && preg_match('/arl/i', $text)) {
            return [
                'tipo' => 'ARL',
                'entidad' => 'Sura ARL',
                'contrasena' => $this->extractPassword($text, 'Sura ARL'),
                'observacion' => $text
            ];
        }

        if (preg_match('/compensar/i', $text) && preg_match('/caja/i', $text)) {
            return [
                'tipo' => 'CAJA',
                'entidad' => 'Compensar Caja',
                'contrasena' => $this->extractPassword($text, 'Compensar Caja'),
                'observacion' => $text
            ];
        }

        foreach ($rules as $r) {
            if (preg_match($r['pattern'], $text)) {
                if (isset($r['is_arl']) && !$r['is_arl'] && preg_match('/arl/i', $text)) continue;
                if (isset($r['is_caja']) && !$r['is_caja'] && preg_match('/caja/i', $text)) continue;

                return [
                    'tipo' => $r['tipo'],
                    'entidad' => $r['entidad'],
                    'contrasena' => $this->extractPassword($text, $r['entidad']),
                    'observacion' => $text
                ];
            }
        }

        return null;
    }

    private function extractPassword(string $text, string $entityName): ?string
    {
        $textClean = preg_replace('/\b' . preg_quote($entityName, '/') . '\b/i', '', $text);

        if (preg_match('/(?:clave|contraseña|contrasena|pass|password|pw|sat)[:\s]+([^\s\-:;]+)/i', $textClean, $matches)) {
            return trim($matches[1]);
        }

        $words = preg_split('/[\s\-\:\,]+/', trim($textClean));
        if (!empty($words)) {
            $lastWord = end($words);
            if (strlen($lastWord) >= 3 && preg_match('/[a-zA-Z0-9]/', $lastWord)) {
                return $lastWord;
            }
        }

        return null;
    }
}
