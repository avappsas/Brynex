<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Bitacora;

class CorregirRetirosInconsistentes extends Command
{
    protected $signature = 'brynex:corregir-retiros
                            {--aliado_id= : Limitar a un aliado específico (opcional)}
                            {--dry-run    : Mostrar qué se haría sin ejecutar cambios}';

    protected $description = 'Corrige contratos vigentes que tienen factura de retiro registrada pero el estado no se actualizó a retirado.';

    public function handle(): int
    {
        $isDryRun  = $this->option('dry-run');
        $alidoFilt = $this->option('aliado_id') ? (int) $this->option('aliado_id') : null;

        $this->info($isDryRun ? '🔍 Modo DRY-RUN (sin cambios)' : '⚙️  Ejecutando corrección REAL en producción');
        $this->newLine();

        // ── Query: contratos vigentes/activos con factura de retiro (numero_factura=0)
        // que NO fue anulada (deleted_at IS NULL) y tiene un plano tipo_reg='retiro'
        $query = DB::table('contratos as c')
            ->join('facturas as f', 'f.contrato_id', '=', 'c.id')
            ->leftJoin('planos as p', 'p.factura_id', '=', 'f.id')
            ->whereIn('c.estado', ['vigente', 'activo'])
            ->where('f.numero_factura', 0)
            ->where('f.tipo', 'planilla')
            ->whereNull('f.deleted_at')
            ->where(function ($q) {
                // Solo facturas que claramente son de retiro
                $q->where('p.tipo_reg', 'retiro')
                  ->orWhereNotNull('p.fecha_ret');
            })
            ->when($alidoFilt, fn($q) => $q->where('c.aliado_id', $alidoFilt))
            ->select([
                'c.id         as contrato_id',
                'c.aliado_id',
                'c.cedula',
                'c.estado     as estado_actual',
                'c.fecha_retiro as fecha_retiro_actual',
                'f.id         as factura_id',
                'f.mes        as f_mes',
                'f.anio       as f_anio',
                'p.fecha_ret  as plano_fecha_ret',
                'p.id         as plano_id',
            ])
            ->orderBy('c.aliado_id')
            ->orderBy('c.id')
            ->get();

        if ($query->isEmpty()) {
            $this->info('✅ No se encontraron contratos inconsistentes. Todo está correcto.');
            return Command::SUCCESS;
        }

        $this->warn("Se encontraron {$query->count()} contratos para corregir:");
        $this->newLine();

        $headers = ['contrato_id', 'aliado_id', 'cédula', 'estado_actual', 'factura_id', 'mes/año retiro', 'fecha_ret plano'];
        $rows = $query->map(fn($r) => [
            $r->contrato_id,
            $r->aliado_id,
            $r->cedula,
            $r->estado_actual,
            $r->factura_id,
            "{$r->f_mes}/{$r->f_anio}",
            $r->plano_fecha_ret ?? '—',
        ])->toArray();

        $this->table($headers, $rows);
        $this->newLine();

        if ($isDryRun) {
            $this->comment('Modo DRY-RUN: ningún cambio fue realizado. Ejecuta sin --dry-run para aplicar.');
            return Command::SUCCESS;
        }

        // ── Confirmación final interactiva
        if (!$this->confirm("¿Confirmas que deseas marcar estos {$query->count()} contratos como RETIRADOS?")) {
            $this->info('Operación cancelada.');
            return Command::SUCCESS;
        }

        $corregidos = 0;
        $errores    = 0;

        DB::transaction(function () use ($query, &$corregidos, &$errores) {
            foreach ($query as $row) {
                try {
                    // Determinar fecha_retiro: preferir plano.fecha_ret, sino null
                    $fechaRetiro = $row->plano_fecha_ret ?? null;

                    DB::table('contratos')
                        ->where('id', $row->contrato_id)
                        ->whereIn('estado', ['vigente', 'activo']) // doble seguridad
                        ->update([
                            'estado'       => 'retirado',
                            'fecha_retiro' => $fechaRetiro,
                        ]);

                    // Registrar en bitácora
                    Bitacora::registrar(
                        accion:      'updated',
                        modelo:      'Contrato',
                        registroId:  $row->contrato_id,
                        descripcion: "Contrato corregido a retirado por comando brynex:corregir-retiros. "
                                   . "Tenía factura de retiro #{$row->factura_id} pero estado quedó vigente.",
                        detalle: [
                            'estado_anterior' => $row->estado_actual,
                            'estado_nuevo'    => 'retirado',
                            'fecha_retiro'    => $fechaRetiro,
                            'factura_id'      => $row->factura_id,
                            'plano_id'        => $row->plano_id,
                            'origen'          => 'correccion_bug_retiro_inconsistente',
                        ],
                        alidoId: $row->aliado_id
                    );

                    $corregidos++;
                } catch (\Throwable $e) {
                    $this->error("❌ Error en contrato #{$row->contrato_id}: " . $e->getMessage());
                    $errores++;
                    throw $e; // Rollback de toda la transacción
                }
            }
        });

        $this->newLine();
        $this->info("✅ Corrección completada: {$corregidos} contratos actualizados a 'retirado'.");
        if ($errores > 0) {
            $this->error("❌ Errores: {$errores}");
        }

        return $errores > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
