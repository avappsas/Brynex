<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Bitacora;
use App\Models\ConfiguracionBrynex;
use App\Models\TipoModalidad;

/**
 * Sube al mínimo legal los contratos vigentes que quedaron cotizando por debajo.
 *
 * El piso depende de la modalidad: Tiempo Parcial cotiza sobre una fracción del
 * SMMLV (¼, ½, ¾) y el resto sobre el SMMLV completo. UPC no depende del salario.
 * La fuente del piso es TipoModalidad::salarioMinimoPermitido(), la misma que usa
 * la validación de ContratoController.
 *
 * Dos orígenes distintos de contratos por debajo del mínimo:
 *   - id_legacy NO nulo → llegaron del sistema viejo, que sigue operando con el
 *     SMMLV del año anterior. Corregirlos aquí NO evita que la próxima corrida de
 *     migración/sincronización los vuelva a pisar: hay que corregirlos también allá.
 *   - id_legacy nulo → creados en BryNex (bug del formulario al salir de una
 *     modalidad de Tiempo Parcial, corregido en form.blade.php).
 */
class CorregirSalarioMinimoContratos extends Command
{
    protected $signature = 'contratos:corregir-salario-minimo
                            {--aliado_id=  : Limitar a un aliado específico (opcional)}
                            {--solo-brynex : Excluir los contratos que vienen del legacy (id_legacy)}
                            {--con-ibc     : Subir también el IBC cuando quedó bajo el piso}
                            {--dry-run     : Mostrar qué se haría sin ejecutar cambios}';

    protected $description = 'Corrige contratos vigentes cuyo salario quedó por debajo del mínimo legal de su modalidad.';

    public function handle(): int
    {
        $isDryRun    = (bool) $this->option('dry-run');
        $soloBrynex  = (bool) $this->option('solo-brynex');
        $conIbc      = (bool) $this->option('con-ibc');
        $alidoFilt   = $this->option('aliado_id') ? (int) $this->option('aliado_id') : null;

        $smmlv = ConfiguracionBrynex::salarioMinimo();

        $this->info($isDryRun ? '🔍 Modo DRY-RUN (sin cambios)' : '⚙️  Ejecutando corrección REAL en producción');
        $this->line('   SMMLV configurado: $' . number_format($smmlv, 0, ',', '.'));
        if ($soloBrynex) $this->line('   Filtro: solo contratos creados en BryNex (sin id_legacy)');
        if ($conIbc)     $this->line('   Se ajustará también el IBC cuando esté bajo el piso');
        $this->newLine();

        // Piso por modalidad, calculado una vez (depende de dias_afp, no se puede en SQL)
        $pisos = TipoModalidad::all()->keyBy('id')
            ->map(fn($m) => $m->salarioMinimoPermitido());

        $contratos = DB::table('contratos as c')
            ->join('aliados as a', 'a.id', '=', 'c.aliado_id')
            ->leftJoin('tipo_modalidad as tm', 'tm.id', '=', 'c.tipo_modalidad_id')
            ->leftJoin('clientes as cl', function ($j) {
                $j->on('cl.cedula', '=', 'c.cedula')->on('cl.aliado_id', '=', 'c.aliado_id');
            })
            ->where('c.estado', 'vigente')
            ->where('c.salario', '<', $smmlv)   // filtro grueso; el piso fino se aplica abajo
            ->when($alidoFilt,  fn($q) => $q->where('c.aliado_id', $alidoFilt))
            ->when($soloBrynex, fn($q) => $q->whereNull('c.id_legacy'))
            ->select([
                'c.id', 'c.aliado_id', 'c.cedula', 'c.salario', 'c.ibc',
                'c.tipo_modalidad_id', 'c.id_legacy',
                'a.nombre as aliado',
                DB::raw("(ISNULL(cl.primer_nombre,'')+' '+ISNULL(cl.primer_apellido,'')) as cliente"),
                DB::raw("ISNULL(tm.observacion, tm.tipo_modalidad) as modalidad"),
            ])
            ->orderBy('a.nombre')->orderBy('c.id')
            ->get();

        // Aplicar el piso real de cada modalidad
        $aCorregir = [];
        foreach ($contratos as $c) {
            $piso = $pisos[$c->tipo_modalidad_id] ?? $smmlv;

            if ($piso <= 0) {
                continue;  // UPC: el valor sale de la edad/zona, no del salario
            }
            // Misma tolerancia de $1 que la validación, por el redondeo de la fracción
            if ((float) $c->salario >= $piso - 1) {
                continue;
            }

            $c->piso     = $piso;
            $c->ibcNuevo = ($conIbc && (float) $c->ibc > 0 && (float) $c->ibc < $piso - 1) ? $piso : null;
            $aCorregir[] = $c;
        }

        if (empty($aCorregir)) {
            $this->info('✅ No hay contratos vigentes por debajo del mínimo de su modalidad.');
            return Command::SUCCESS;
        }

        $legacy = collect($aCorregir)->whereNotNull('id_legacy')->count();
        $propios = count($aCorregir) - $legacy;

        $this->warn('Se encontraron ' . count($aCorregir) . ' contratos por debajo del mínimo:');
        $this->newLine();

        $this->table(
            ['#', 'aliado', 'cédula', 'cliente', 'modalidad', 'salario', '→ nuevo', 'ibc', '→ nuevo', 'origen'],
            collect($aCorregir)->map(fn($c) => [
                $c->id,
                substr($c->aliado, 0, 16),
                $c->cedula,
                substr(trim($c->cliente), 0, 22),
                substr((string) $c->modalidad, 0, 20),
                number_format((float) $c->salario, 0, ',', '.'),
                number_format($c->piso, 0, ',', '.'),
                (float) $c->ibc > 0 ? number_format((float) $c->ibc, 0, ',', '.') : '—',
                $c->ibcNuevo ? number_format($c->ibcNuevo, 0, ',', '.') : '—',
                $c->id_legacy ? 'legacy' : 'brynex',
            ])->toArray()
        );

        $this->newLine();
        $this->line("   Del legacy (id_legacy): $legacy   |   Creados en BryNex: $propios");

        if ($legacy > 0) {
            $this->newLine();
            $this->warn("⚠️  $legacy contratos vienen del sistema legacy, que sigue operando con el");
            $this->warn('   salario mínimo anterior. Si no se corrigen también allá, la próxima');
            $this->warn('   corrida de migración/sincronización los volverá a dejar por debajo.');
            $this->warn('   Usa --solo-brynex para corregir únicamente los creados en BryNex.');
        }

        if (!$conIbc) {
            $conIbcBajo = collect($aCorregir)
                ->filter(fn($c) => (float) $c->ibc > 0 && (float) $c->ibc < $c->piso - 1)->count();
            if ($conIbcBajo > 0) {
                $this->newLine();
                $this->comment("ℹ️  $conIbcBajo contratos tienen además el IBC por debajo del piso.");
                $this->comment('   El IBC no se toca sin --con-ibc: es la base de cotización y afecta planillas.');
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->comment('Modo DRY-RUN: ningún cambio fue realizado. Ejecuta sin --dry-run para aplicar.');
            return Command::SUCCESS;
        }

        if (!$this->confirm('¿Confirmas subir el salario de estos ' . count($aCorregir) . ' contratos?')) {
            $this->info('Operación cancelada.');
            return Command::SUCCESS;
        }

        $corregidos = 0;
        $omitidos   = [];

        DB::transaction(function () use ($aCorregir, &$corregidos, &$omitidos, $smmlv) {
            foreach ($aCorregir as $c) {
                $cambios = ['salario' => $c->piso];
                if ($c->ibcNuevo) {
                    $cambios['ibc'] = $c->ibcNuevo;
                }

                $afectadas = DB::table('contratos')
                    ->where('id', $c->id)
                    ->where('estado', 'vigente')          // doble seguridad
                    ->where('salario', $c->salario)       // no pisar si cambió entre el listado y el update
                    ->update($cambios);

                // La guarda no encontró el registro como se listó: alguien lo tocó
                // en el intervalo. Se omite y se reporta, no se cuenta como corregido.
                if ($afectadas === 0) {
                    $omitidos[] = $c->id;
                    continue;
                }

                Bitacora::registrar(
                    accion:      'updated',
                    modelo:      'Contrato',
                    registroId:  (int) $c->id,
                    descripcion: "Salario ajustado al mínimo legal por comando contratos:corregir-salario-minimo. "
                               . 'Tenía $' . number_format((float) $c->salario, 0, ',', '.')
                               . ' con modalidad ' . ($c->modalidad ?? 'sin definir') . '.',
                    detalle: [
                        'salario_anterior' => (float) $c->salario,
                        'salario_nuevo'    => $c->piso,
                        'ibc_anterior'     => (float) $c->ibc,
                        'ibc_nuevo'        => $c->ibcNuevo,
                        'smmlv_vigente'    => $smmlv,
                        'modalidad_id'     => $c->tipo_modalidad_id,
                        'id_legacy'        => $c->id_legacy,
                        'origen'           => 'correccion_salario_bajo_minimo',
                    ],
                    alidoId: (int) $c->aliado_id
                );

                $corregidos++;
            }
        });

        $this->newLine();
        $this->info("✅ Corrección completada: $corregidos contratos actualizados.");

        if (!empty($omitidos)) {
            $this->warn('⚠️  ' . count($omitidos) . ' omitidos porque cambiaron durante la corrida: '
                . implode(', ', $omitidos));
            $this->warn('   Vuelve a correr el comando en --dry-run para revisarlos.');
        }

        return Command::SUCCESS;
    }
}
