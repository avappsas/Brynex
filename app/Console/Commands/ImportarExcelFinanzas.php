<?php

namespace App\Console\Commands;

use App\Models\Finanzas\AppLiderAliado;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\Entrada;
use App\Models\Finanzas\FuenteIngreso;
use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\Prestamo;
use App\Models\Finanzas\PrestamoMovimiento;
use App\Models\Finanzas\Proyecto;
use App\Models\Finanzas\ProyectoMovimiento;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportarExcelFinanzas extends Command
{
    protected $signature = 'finanzas:import-excel {--file=Brayan_Garcia_2026.xlsx} {--force}';

    protected $description = 'Importa la contabilidad del archivo Excel Brayan_Garcia_2026.xlsx en la base de datos finanzas';

    public function handle()
    {
        $filePath = base_path($this->option('file'));

        if (! file_exists($filePath)) {
            $this->error("El archivo no existe en la ruta: {$filePath}");

            return 1;
        }

        // Buscar al usuario de Brayan García
        $user = User::where('cedula', config('finanzas.cedula_dueno'))->first();
        if (! $user) {
            $user = User::first();
        }

        if (! $user) {
            $this->error('No se encontró ningún usuario en la base de datos para asociar los registros.');

            return 1;
        }

        $this->info("Asociando registros al usuario: {$user->nombre} (Cédula: {$user->cedula})");

        if (! $this->option('force')) {
            if (! $this->confirm("¿Seguro que deseas limpiar las tablas de la base de datos 'finanzas' y realizar una nueva importación?")) {
                $this->info('Importación cancelada.');

                return 0;
            }
        }

        $this->info('Cargando archivo de Excel...');
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);

        DB::connection('finanzas')->transaction(function () use ($spreadsheet, $user) {
            $this->limpiarTablas();

            $this->importarFuentesYEntradas($spreadsheet, $user);
            $this->importarGastosYCategorias($spreadsheet, $user);
            $this->importarProyectosYMovimientos($spreadsheet, $user);
            $this->importarAppLideres($spreadsheet, $user);
            $this->importarPrestamosYHistorial($spreadsheet, $user);
        });

        $this->info('✅ Importación completada con éxito!');

        return 0;
    }

    private function limpiarTablas()
    {
        $this->info('Limpiando tablas de la base de datos de Finanzas...');

        // Desactivar restricciones FK temporalmente para SQL Server en conexión finanzas
        DB::connection('finanzas')->statement('EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT ALL"');

        // Eliminar registros en orden inverso de dependencia
        DB::connection('finanzas')->table('finanzas_proyecto_movimientos')->delete();
        DB::connection('finanzas')->table('finanzas_proyectos')->delete();
        DB::connection('finanzas')->table('finanzas_app_lideres_aliados')->delete();
        DB::connection('finanzas')->table('finanzas_patrimonio_gastos')->delete();
        DB::connection('finanzas')->table('finanzas_patrimonio')->delete();
        DB::connection('finanzas')->table('finanzas_inversion_movimientos')->delete();
        DB::connection('finanzas')->table('finanzas_inversiones')->delete();
        DB::connection('finanzas')->table('finanzas_prestamo_movimientos')->delete();
        DB::connection('finanzas')->table('finanzas_prestamos')->delete();
        DB::connection('finanzas')->table('finanzas_brynex_pagos')->delete();
        DB::connection('finanzas')->table('finanzas_gastos')->delete();
        DB::connection('finanzas')->table('finanzas_categorias_gasto')->delete();
        DB::connection('finanzas')->table('finanzas_entradas')->delete();
        DB::connection('finanzas')->table('finanzas_fuentes_ingreso')->delete();

        // Reactivar restricciones FK
        DB::connection('finanzas')->statement('EXEC sp_MSforeachtable "ALTER TABLE ? CHECK CONSTRAINT ALL"');
    }

    private function importarFuentesYEntradas($spreadsheet, $user)
    {
        $this->info('Importando Fuentes de Ingresos y Entradas (BRAYAN)...');
        $sheet = $spreadsheet->getSheetByName('BRAYAN');
        if (! $sheet) {
            $this->warn('Hoja BRAYAN no encontrada.');

            return;
        }

        $data = $sheet->toArray(null, false, false, true);
        $highestRow = count($data);

        // Seedar exactamente las 11 fuentes solicitadas
        $fuentesFijas = [
            ['nombre' => 'BRYNEX', 'tipo' => 'fijo', 'orden' => 1],
            ['nombre' => 'BRYGAR', 'tipo' => 'fijo', 'orden' => 2],
            ['nombre' => 'MEGATRANSPORTES JUAMVI', 'tipo' => 'fijo', 'orden' => 3],
            ['nombre' => 'CAMIONES JUAMVI', 'tipo' => 'fijo', 'orden' => 4],
            ['nombre' => 'ACUERDO LLAMADAS', 'tipo' => 'fijo', 'orden' => 5],
            ['nombre' => 'INTERESES PRESTAMOS', 'tipo' => 'fijo', 'orden' => 6],
            ['nombre' => 'CONGRESO', 'tipo' => 'fijo', 'orden' => 7],
            ['nombre' => 'APP LIDERES', 'tipo' => 'fijo', 'orden' => 8],
            ['nombre' => 'CONCEJO', 'tipo' => 'fijo', 'orden' => 9],
            ['nombre' => 'PROYECTOS', 'tipo' => 'proyecto', 'orden' => 10],
            ['nombre' => 'OTRAS ENTRADAS', 'tipo' => 'esporadico', 'orden' => 11],
        ];

        $fuentesMap = [];
        foreach ($fuentesFijas as $f) {
            $model = FuenteIngreso::create([
                'nombre' => $f['nombre'],
                'tipo' => $f['tipo'],
                'orden' => $f['orden'],
                'activo' => true,
                'user_id' => $user->id,
            ]);
            $fuentesMap[$f['nombre']] = $model->id;
        }

        // Mapeo detallado de conceptos del Excel a las 11 fuentes
        $mapping = [
            'SEGURIDAD SOCIAL' => 'BRYGAR',

            // Brynex Aliados
            'FECOP' => 'BRYNEX',
            'Halcon' => 'BRYNEX',
            'Mave integral -MARIANA' => 'BRYNEX',
            'Mave integral - ANDERSON' => 'BRYNEX',
            'MURO INTEGRAL' => 'BRYNEX',
            'FAGA - INTEGRAL' => 'BRYNEX',
            'BJSVISSION carlos herm' => 'BRYNEX',
            'Servidor Web' => 'BRYNEX',

            // Megatransportes
            'Mega-Transportes JUAMVI' => 'MEGATRANSPORTES JUAMVI',

            // Camiones
            'Megamudanzas CAMIONES' => 'CAMIONES JUAMVI',

            // Llamadas
            'Llamadas' => 'ACUERDO LLAMADAS',

            // Congreso
            'Congreso' => 'CONGRESO',

            // Concejo
            'Concejo rolo' => 'CONCEJO',
            'CONTRATO CONCEJO' => 'CONCEJO',

            // Proyectos
            'Cuenta Facil' => 'PROYECTOS',

            // Intereses préstamos
            'INTERESES PRESTAMOS' => 'INTERESES PRESTAMOS',

            // Otras entradas
            'OTROS ENTRADAS' => 'OTRAS ENTRADAS',
            'Internet' => 'OTRAS ENTRADAS',
            'Programa Org' => 'OTRAS ENTRADAS',
            'JOYERIA 18K' => 'OTRAS ENTRADAS',
            'maria torres' => 'OTRAS ENTRADAS',
            'leidy casierra' => 'OTRAS ENTRADAS',
            'efecty' => 'OTRAS ENTRADAS',
        ];

        // Inicializar acumuladores de montos por fuente, año y mes
        $acumulado = [];
        foreach ($fuentesMap as $nombre => $fid) {
            $acumulado[$fid] = [];
        }

        // Construir el mapeo de columnas del Excel (2020 a 2026)
        $monthsMap = [];

        // 2020: Cols 3 a 14 (C a N)
        for ($col = 3; $col <= 14; $col++) {
            $monthsMap[$col] = ['year' => 2020, 'month' => $col - 2];
        }
        // 2021: Cols 15 a 26 (O a Z)
        for ($col = 15; $col <= 26; $col++) {
            $monthsMap[$col] = ['year' => 2021, 'month' => $col - 14];
        }
        // 2022: Cols 27 a 38 (AA a AL)
        for ($col = 27; $col <= 38; $col++) {
            $monthsMap[$col] = ['year' => 2022, 'month' => $col - 26];
        }
        // 2023: Cols 39 a 50 (AM a AX)
        for ($col = 39; $col <= 50; $col++) {
            $monthsMap[$col] = ['year' => 2023, 'month' => $col - 38];
        }
        // 2024: Cols 51 a 62 (AY a BJ)
        for ($col = 51; $col <= 62; $col++) {
            $monthsMap[$col] = ['year' => 2024, 'month' => $col - 50];
        }
        // 2025: Cols 63 a 74 (BK a BV)
        for ($col = 63; $col <= 74; $col++) {
            $monthsMap[$col] = ['year' => 2025, 'month' => $col - 62];
        }
        // 2026: Cols 75 a 80 (BW a CB)
        for ($col = 75; $col <= 80; $col++) {
            $monthsMap[$col] = ['year' => 2026, 'month' => $col - 74];
        }

        $emptyCount = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $concepto = isset($data[$row]['A']) ? trim($data[$row]['A']) : '';
            if (empty($concepto)) {
                $emptyCount++;
                if ($emptyCount > 10) {
                    break;
                }

                continue;
            }
            $emptyCount = 0;
            if ($concepto === 'TOTAL' || $concepto === 'TOTAL ENTRADAS' || $concepto === 'CONCEPTO') {
                continue;
            }

            // Clasificar concepto
            $conceptoLower = strtolower($concepto);
            if (isset($mapping[$concepto])) {
                $destNombre = $mapping[$concepto];
            } elseif (
                str_contains($conceptoLower, 'interes')
                || str_contains($conceptoLower, 'lucy')
                || str_contains($conceptoLower, 'hector')
                || str_contains($conceptoLower, 'toño')
                || str_contains($conceptoLower, 'veronica')
                || str_contains($conceptoLower, 'jessica')
                || str_contains($conceptoLower, 'fabionelson')
                || str_contains($conceptoLower, 'jhon jairo')
                || str_contains($conceptoLower, 'viviana')
                || str_contains($conceptoLower, 'martin')
                || str_contains($conceptoLower, 'diana molina')
                || str_contains($conceptoLower, 'manzano')
                || str_contains($conceptoLower, 'johana')
                || str_contains($conceptoLower, 'casierra')
            ) {
                $destNombre = 'INTERESES PRESTAMOS';
            } else {
                $destNombre = 'OTRAS ENTRADAS';
            }

            $fid = $fuentesMap[$destNombre] ?? null;
            if (! $fid) {
                continue;
            }

            // Si el destino es BRYNEX, guardar en finanzas_brynex_pagos
            if ($destNombre === 'BRYNEX') {
                $aliadoId = $this->obtenerAliadoId($concepto, $user);
                foreach ($monthsMap as $colIndex => $period) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $val = isset($data[$row][$colLetter]) ? $data[$row][$colLetter] : 0;
                    if (is_string($val) && str_starts_with($val, '=')) {
                        $val = $this->evaluarFormulaAritmetica($val);
                    }
                    $monto = (float) $val;
                    if ($monto > 0) {
                        $y = $period['year'];
                        $m = $period['month'];

                        \App\Models\Finanzas\BrynexPago::updateOrCreate(
                            [
                                'aliado_id' => $aliadoId,
                                'anio' => $y,
                                'mes' => $m,
                            ],
                            [
                                'user_id' => $user->id,
                                'monto' => $monto,
                                'observacion' => "Importado de Excel ({$concepto})",
                            ]
                        );
                    }
                }

                continue;
            }

            // Leer columnas de meses y años
            foreach ($monthsMap as $colIndex => $period) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $val = isset($data[$row][$colLetter]) ? $data[$row][$colLetter] : 0;
                if (is_string($val) && str_starts_with($val, '=')) {
                    $val = $this->evaluarFormulaAritmetica($val);
                }
                $monto = (float) $val;
                if ($monto > 0) {
                    $y = $period['year'];
                    $m = $period['month'];
                    $acumulado[$fid][$y][$m] = ($acumulado[$fid][$y][$m] ?? 0.00) + $monto;
                }
            }
        }

        // Insertar acumulados en la BD
        $entradasToInsert = [];
        foreach ($acumulado as $fid => $years) {
            foreach ($years as $anioNum => $meses) {
                foreach ($meses as $mesNum => $monto) {
                    if ($monto > 0) {
                        $entradasToInsert[] = [
                            'fuente_id' => $fid,
                            'anio' => $anioNum,
                            'mes' => $mesNum,
                            'monto' => $monto,
                            'user_id' => $user->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        if (count($entradasToInsert) > 0) {
            foreach (array_chunk($entradasToInsert, 150) as $chunk) {
                Entrada::insert($chunk);
            }
        }
    }

    private function importarGastosYCategorias($spreadsheet, $user)
    {
        $this->info('Importando Categorías y Gastos...');
        $sheet = $spreadsheet->getSheetByName('GASTOS');
        if (! $sheet) {
            $this->warn('Hoja GASTOS no encontrada.');

            return;
        }

        $data = $sheet->toArray(null, false, false, true);
        $highestRow = count($data);

        $estilosCategorias = [
            'GASOLINA' => ['icono' => '🚗', 'color' => '#00838f', 'recurrente' => false],
            'OTROS' => ['icono' => '📦', 'color' => '#64748b', 'recurrente' => false],
            'COMIDA' => ['icono' => '🍔', 'color' => '#d84315', 'recurrente' => false],
            'TRABAJOS' => ['icono' => '💼', 'color' => '#6b21a8', 'recurrente' => false],
            'PARQUEADERO' => ['icono' => '🅿️', 'color' => '#0284c7', 'recurrente' => false],
            'SALIDAS' => ['icono' => '🍻', 'color' => '#ec4899', 'recurrente' => false],
            'SERVICIOS' => ['icono' => '🏠', 'color' => '#4527a0', 'recurrente' => true],
            'ARRIENDO' => ['icono' => '🔑', 'color' => '#2e7d32', 'recurrente' => true],
            'PRESTAMO' => ['icono' => '🤝', 'color' => '#f59e0b', 'recurrente' => false],
            'INVERSION' => ['icono' => '🪙', 'color' => '#0284c7', 'recurrente' => false],
        ];

        $categoriasCache = CategoriaGasto::where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn ($c) => [strtoupper(trim($c->nombre)) => $c->id])
            ->toArray();

        // Asegurar categoría para ingresos esporádicos
        if (! isset($categoriasCache['INGRESOS ESPORÁDICOS']) && ! isset($categoriasCache['INGRESOS ESPORADICOS'])) {
            $catEsporadica = CategoriaGasto::create([
                'user_id' => $user->id,
                'nombre' => 'Ingresos Esporádicos',
                'icono' => '💵',
                'color' => '#10b981',
                'es_recurrente' => false,
                'activo' => true,
                'orden' => 99,
            ]);
            $categoriasCache['INGRESOS ESPORÁDICOS'] = $catEsporadica->id;
        }

        $gastosToInsert = [];
        $emptyCount = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $fechaVal = $data[$row]['D'] ?? null;
            $entradaVal = $data[$row]['E'] ?? 0;
            $salidaVal = $data[$row]['F'] ?? 0;
            $entrada = (float) $entradaVal;
            $salida = (float) $salidaVal;
            $tipoName = isset($data[$row]['I']) ? strtoupper(trim($data[$row]['I'])) : '';
            $descripcion = isset($data[$row]['J']) ? trim($data[$row]['J']) : '';

            if (empty($tipoName) && $salida <= 0 && $entrada <= 0 && empty($fechaVal)) {
                $emptyCount++;
                if ($emptyCount > 15) {
                    break;
                }

                continue;
            }
            $emptyCount = 0;

            if (empty($tipoName) && $salida <= 0 && $entrada <= 0) {
                continue;
            }

            $fecha = now()->toDateString();
            if (is_numeric($fechaVal)) {
                $fecha = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fechaVal)->format('Y-m-d');
            }

            // Registrar entrada si tiene valor positivo
            if ($entrada > 0) {
                $catEsporadicaId = $categoriasCache['INGRESOS ESPORÁDICOS'] ?? null;
                $gastosToInsert[] = [
                    'categoria_id' => $catEsporadicaId,
                    'fecha' => $fecha,
                    'monto' => $entrada,
                    'descripcion' => empty($descripcion) ? 'Ingreso esporádico importado' : $descripcion,
                    'tipo_movimiento' => 'ingreso_esporadico',
                    'es_patrimonio' => false,
                    'patrimonio_id' => null,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Registrar salida si tiene valor positivo
            if ($salida > 0 && ! empty($tipoName)) {
                // Buscar en caché local o crear en base de datos
                if (! isset($categoriasCache[$tipoName])) {
                    $estilo = $estilosCategorias[$tipoName] ?? ['icono' => '📂', 'color' => '#'.substr(md5($tipoName), 0, 6), 'recurrente' => false];
                    $categoria = CategoriaGasto::create([
                        'nombre' => $tipoName,
                        'icono' => $estilo['icono'],
                        'color' => $estilo['color'],
                        'es_recurrente' => $estilo['recurrente'],
                        'orden' => 100,
                        'activo' => true,
                        'user_id' => $user->id,
                    ]);
                    $categoriasCache[$tipoName] = $categoria->id;
                }

                $catId = $categoriasCache[$tipoName];

                $tipoMovimiento = 'gasto';
                if (in_array($tipoName, ['PRESTAMO', 'PRESTAMOS'])) {
                    $tipoMovimiento = 'prestamo';
                } elseif (in_array($tipoName, ['INVERSION', 'INVERSIONES'])) {
                    $tipoMovimiento = 'inversion';
                }

                $gastosToInsert[] = [
                    'categoria_id' => $catId,
                    'fecha' => $fecha,
                    'monto' => $salida,
                    'descripcion' => $descripcion,
                    'tipo_movimiento' => $tipoMovimiento,
                    'es_patrimonio' => false,
                    'patrimonio_id' => null,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (count($gastosToInsert) > 0) {
            foreach (array_chunk($gastosToInsert, 150) as $chunk) {
                Gasto::insert($chunk);
            }
        }
    }

    private function importarProyectosYMovimientos($spreadsheet, $user)
    {
        $this->info('Importando Proyectos y sus Flujos de Caja...');
        $sheet = $spreadsheet->getSheetByName('PROYECTOS');
        if (! $sheet) {
            $this->warn('Hoja PROYECTOS no encontrada.');

            return;
        }

        $data = $sheet->toArray(null, false, false, true);
        $highestRow = count($data);
        $proyectosCache = Proyecto::where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn ($p) => [strtoupper(trim($p->nombre)) => $p->id])
            ->toArray();
        $movimientosToInsert = [];
        $emptyCount = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $fechaVal = $data[$row]['B'] ?? null;
            $entradaVal = $data[$row]['C'] ?? 0;
            $entrada = (float) $entradaVal;
            $salidaVal = $data[$row]['D'] ?? 0;
            $salida = (float) $salidaVal;
            $proyectoName = isset($data[$row]['E']) ? trim($data[$row]['E']) : '';
            $observacion = isset($data[$row]['F']) ? trim($data[$row]['F']) : '';

            if (empty($proyectoName) && $entrada <= 0 && $salida <= 0) {
                $emptyCount++;
                if ($emptyCount > 10) {
                    break;
                }

                continue;
            }
            $emptyCount = 0;

            if (empty($proyectoName) || ($entrada <= 0 && $salida <= 0)) {
                continue;
            }

            // Buscar en caché local o crear
            $projNameUpper = strtoupper($proyectoName);
            if (! isset($proyectosCache[$projNameUpper])) {
                $proyecto = Proyecto::create([
                    'nombre' => $proyectoName,
                    'descripcion' => 'Proyecto importado desde el Excel.',
                    'activo' => true,
                    'user_id' => $user->id,
                ]);
                $proyectosCache[$projNameUpper] = $proyecto->id;
            }
            $proyectoId = $proyectosCache[$projNameUpper];

            $fecha = now()->toDateString();
            if (is_numeric($fechaVal)) {
                $fecha = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fechaVal)->format('Y-m-d');
            }

            if ($entrada > 0) {
                $movimientosToInsert[] = [
                    'proyecto_id' => $proyectoId,
                    'tipo' => 'ingreso',
                    'monto' => $entrada,
                    'observacion' => $observacion ?: 'Ingreso del proyecto',
                    'fecha' => $fecha,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($salida > 0) {
                $movimientosToInsert[] = [
                    'proyecto_id' => $proyectoId,
                    'tipo' => 'egreso',
                    'monto' => $salida,
                    'observacion' => $observacion ?: 'Egreso del proyecto',
                    'fecha' => $fecha,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (count($movimientosToInsert) > 0) {
            foreach (array_chunk($movimientosToInsert, 150) as $chunk) {
                ProyectoMovimiento::insert($chunk);
            }
        }
    }

    private function importarAppLideres($spreadsheet, $user)
    {
        $this->info('Importando Aliados de App Líderes...');
        $sheet = $spreadsheet->getSheetByName('APP');
        if (! $sheet) {
            $this->warn('Hoja APP no encontrada.');

            return;
        }

        $data = $sheet->toArray(null, false, false, true);

        // Leer aliados de la fila 1 (Col B a J)
        $aliadosMap = [];
        for ($col = 'B'; $col <= 'J'; $col++) {
            $nombre = isset($data[1][$col]) ? trim($data[1][$col]) : '';
            if (! empty($nombre) && $nombre !== 'TOTAL MENSUAL') {
                $aliado = AppLiderAliado::create([
                    'nombre' => $nombre,
                    'valor_mensual' => 1000000,
                    'fecha_inicio' => '2026-01-01',
                    'activo' => true,
                    'user_id' => $user->id,
                ]);
                $aliadosMap[$col] = $aliado;
            }
        }

        $fuenteLideres = FuenteIngreso::where('user_id', $user->id)
            ->where('nombre', 'APP LIDERES')
            ->first();

        $mesesNombres = [
            'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
            'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
            'SEPTIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
        ];

        $entradasToInsert = [];

        for ($row = 2; $row <= 13; $row++) {
            $mesName = isset($data[$row]['A']) ? strtoupper(trim($data[$row]['A'])) : '';
            $mesNum = $mesesNombres[$mesName] ?? null;
            if (! $mesNum) {
                continue;
            }

            $totalMes = 0;
            foreach ($aliadosMap as $col => $aliado) {
                $val = isset($data[$row][$col]) ? trim($data[$row][$col]) : '';
                $amount = 0;
                if (strtolower($val) === 'x') {
                    $amount = $aliado->valor_mensual;
                } elseif (is_numeric($val)) {
                    $amount = (float) $val;
                }
                $totalMes += $amount;
            }

            if ($totalMes > 0) {
                $entradasToInsert[] = [
                    'fuente_id' => $fuenteLideres->id,
                    'anio' => 2026,
                    'mes' => $mesNum,
                    'monto' => $totalMes,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (count($entradasToInsert) > 0) {
            foreach (array_chunk($entradasToInsert, 150) as $chunk) {
                Entrada::insert($chunk);
            }
        }
    }

    private function importarPrestamosYHistorial($spreadsheet, $user)
    {
        $this->info('Importando Préstamos y reconstruyendo historial de movimientos...');
        $sheet = $spreadsheet->getSheetByName('PRESTAMOS');
        if (! $sheet) {
            $this->warn('Hoja PRESTAMOS no encontrada.');

            return;
        }

        // Desactivar cálculo de fórmulas y calcularlo en PHP para evitar cascading calculations lentas
        $data = $sheet->toArray(null, false, false, true);
        $highestRow = count($data);
        $prestamosCreados = [];
        $emptyCount = 0;

        // 1. Crear préstamos base
        for ($row = 3; $row <= $highestRow; $row++) {
            $nombre = isset($data[$row]['E']) ? trim($data[$row]['E']) : '';
            $montoVal = $data[$row]['F'] ?? 0;
            if (is_string($montoVal) && str_starts_with($montoVal, '=')) {
                $montoVal = $this->evaluarFormulaAritmetica($montoVal);
            }
            $tasaVal = $data[$row]['G'] ?? 0;
            if (is_string($tasaVal) && str_starts_with($tasaVal, '=')) {
                $tasaVal = $this->evaluarFormulaAritmetica($tasaVal);
            }
            $fechaVal = $data[$row]['D'] ?? null;
            $obs = isset($data[$row]['AQ']) ? trim($data[$row]['AQ']) : '';

            if (empty($nombre) && (empty($montoVal) || $montoVal <= 0)) {
                $emptyCount++;
                if ($emptyCount > 10) {
                    break;
                }

                continue;
            }
            $emptyCount = 0;

            if (empty($nombre) || $montoVal <= 0) {
                continue;
            }

            $fecha = '2024-01-01';
            if (is_numeric($fechaVal)) {
                $fecha = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fechaVal)->format('Y-m-d');
            }

            $tasa = (float) $tasaVal * 100;

            $esCC = false;
            $ccGrupo = null;
            if (str_contains(strtolower($nombre), 'trabajos') || str_contains(strtolower($nombre), 'cuenta corriente') || str_contains(strtolower($obs), 'cuenta corriente')) {
                $esCC = true;
                $ccGrupo = 'Trabajos Iniciales';
            }

            $prestamo = Prestamo::create([
                'nombre_deudor' => $nombre,
                'monto_original' => $montoVal,
                'tasa_interes_mensual' => $tasa,
                'fecha_desembolso' => $fecha,
                'saldo_actual' => $montoVal,
                'estado' => 'activo',
                'observaciones' => $obs,
                'alertas_activas' => true,
                'es_cuenta_corriente' => $esCC,
                'cuenta_corriente_grupo' => $ccGrupo,
                'user_id' => $user->id,
            ]);

            // Desembolso inicial
            PrestamoMovimiento::create([
                'prestamo_id' => $prestamo->id,
                'tipo' => 'desembolso',
                'monto' => $montoVal,
                'saldo_antes' => 0,
                'saldo_despues' => $montoVal,
                'fecha' => $fecha,
                'observacion' => 'Desembolso inicial importado.',
            ]);

            $prestamosCreados[] = [
                'model' => $prestamo,
                'row' => $row,
                'nombre' => $nombre,
            ];
        }

        // 2. Reconstruir historial de movimientos mensual acumulado
        $movimientosToInsert = [];

        foreach ($prestamosCreados as $pInfo) {
            $prestamo = $pInfo['model'];
            $rowIdx = $pInfo['row'];

            // Obtener la tasa de interés específica del préstamo
            $tasaVal = $data[$rowIdx]['G'] ?? 0;
            if (is_string($tasaVal) && str_starts_with($tasaVal, '=')) {
                $tasaVal = $this->evaluarFormulaAritmetica($tasaVal);
            }
            $tasaVal = (float) $tasaVal;

            $saldoActual = $prestamo->monto_original;
            $capitalVigente = (float) $prestamo->monto_original;

            for ($col = 8; $col <= 40; $col += 2) {
                $monthConfig = $this->getYearMonthFromCol($col);
                if (! $monthConfig) {
                    continue;
                }
                [$y, $m] = $monthConfig;

                $colLetter = Coordinate::stringFromColumnIndex($col);
                $colIntLetter = Coordinate::stringFromColumnIndex($col + 1);

                $newBalance = $data[$rowIdx][$colLetter] ?? null;

                if ($newBalance === null || $newBalance === '') {
                    continue;
                }

                $newBalance = (float) $newBalance;
                $interesGenerado = $newBalance * (float) $tasaVal;

                if ($interesGenerado > 0) {
                    $saldoAntes = $saldoActual;
                    $saldoActual += $interesGenerado;

                    $movimientosToInsert[] = [
                        'prestamo_id' => $prestamo->id,
                        'tipo' => 'interes_mensual',
                        'monto' => $interesGenerado,
                        'saldo_antes' => $saldoAntes,
                        'saldo_despues' => $saldoActual,
                        'fecha' => "{$y}-".str_pad($m, 2, '0', STR_PAD_LEFT).'-15',
                        'observacion' => 'Liquidación mensual de intereses.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (abs($saldoActual - $newBalance) > 1) {
                    if ($newBalance < $saldoActual) {
                        $montoAbono = $saldoActual - $newBalance;
                        $saldoAntes = $saldoActual;
                        $saldoActual = $newBalance;

                        $movimientosToInsert[] = [
                            'prestamo_id' => $prestamo->id,
                            'tipo' => 'abono_capital',
                            'monto' => $montoAbono,
                            'saldo_antes' => $saldoAntes,
                            'saldo_despues' => $saldoActual,
                            'fecha' => "{$y}-".str_pad($m, 2, '0', STR_PAD_LEFT).'-28',
                            'observacion' => 'Abono recibido (Importación automática).',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $capitalVigente = max(0.0, $capitalVigente - $montoAbono);
                    } else {
                        $montoExtra = $newBalance - $saldoActual;
                        $saldoAntes = $saldoActual;
                        $saldoActual = $newBalance;

                        $movimientosToInsert[] = [
                            'prestamo_id' => $prestamo->id,
                            'tipo' => 'capitalizacion',
                            'monto' => $montoExtra,
                            'saldo_antes' => $saldoAntes,
                            'saldo_despues' => $saldoActual,
                            'fecha' => "{$y}-".str_pad($m, 2, '0', STR_PAD_LEFT).'-05',
                            'observacion' => 'Capitalización o desembolso adicional.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        // Una capitalización es plata nueva prestada: tiene que subir el
                        // capital vigente igual que un desembolso. Sin esto, `monto_original`
                        // se queda en el desembolso inicial y todo lo capitalizado termina
                        // contándose como interés impago — así se desfasó el préstamo de
                        // Fabio Arroyave en $258M (ver finanzas:recalcular-capital-prestamos).
                        $capitalVigente += $montoExtra;
                    }
                }
            }

            // Actualizar saldo y capital finales del préstamo
            $prestamo->saldo_actual = $saldoActual;
            $prestamo->monto_original = round($capitalVigente, 2);
            if ($saldoActual <= 0) {
                $prestamo->estado = 'pagado';
            }
            $prestamo->save();
        }

        if (count($movimientosToInsert) > 0) {
            foreach (array_chunk($movimientosToInsert, 150) as $chunk) {
                PrestamoMovimiento::insert($chunk);
            }
        }
    }

    private function getYearMonthFromCol($colIndex)
    {
        if ($colIndex >= 8 && $colIndex <= 22) {
            $month = ($colIndex - 8) / 2 + 2; // Col 8 es Febrero (2)

            return [2024, $month];
        }
        if ($colIndex == 24) {
            return [2024, 12]; // dic
        }
        if ($colIndex >= 26 && $colIndex <= 40) {
            $month = ($colIndex - 26) / 2 + 1; // Col 26 es Enero (1)

            return [2025, $month];
        }

        return null;
    }

    private function evaluarFormulaAritmetica($formula)
    {
        $formula = trim($formula);
        if (str_starts_with($formula, '=')) {
            $formula = substr($formula, 1);
        }

        $formula = str_replace(' ', '', $formula);

        if (preg_match('/^[0-9+\\-*\/.]*$/', $formula)) {
            try {
                $val = null;
                if (str_contains($formula, '/0')) {
                    return 0;
                }
                eval('$val = '.$formula.';');

                return $val;
            } catch (\Throwable $e) {
                return 0;
            }
        }

        return 0;
    }

    private function obtenerAliadoId($concepto, $user)
    {
        $conceptoLower = strtolower($concepto);

        // Mapeos conocidos
        if (str_contains($conceptoLower, 'fecop')) {
            $nombreBusqueda = 'Grupo Fecop';
        } elseif (str_contains($conceptoLower, 'mave')) {
            $nombreBusqueda = 'GiMave';
        } elseif (str_contains($conceptoLower, 'faga')) {
            $nombreBusqueda = 'Faga';
        } elseif (str_contains($conceptoLower, 'brygar')) {
            $nombreBusqueda = 'BRYGAR';
        } else {
            $nombreBusqueda = $concepto;
        }

        $aliado = \App\Models\Aliado::where('nombre', 'like', "%{$nombreBusqueda}%")->first();

        if (! $aliado) {
            // Crear el aliado en la tabla principal de la base de datos BryNex
            $aliado = \App\Models\Aliado::create([
                'nombre' => $concepto,
                'razon_social' => $concepto,
                'nit' => 'TEMP-'.substr(md5($concepto), 0, 10),
                'activo' => true,
                'afiliaciones_brynex' => false,
            ]);
        }

        return $aliado->id;
    }
}
