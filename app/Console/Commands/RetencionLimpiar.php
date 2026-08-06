<?php

namespace App\Console\Commands;

use App\Models\AccesoUsuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Aplica la retención prometida en el aviso de tratamiento de datos.
 *
 * Existe porque prometer un plazo y guardar para siempre es peor que no
 * prometer nada: convierte el aviso en una declaración falsa. Los plazos de
 * aquí y los del documento tienen que moverse juntos —
 * ver docs/clausulas-y-aviso-datos.md.
 *
 *  · Accesos (IP, equipo, huella): 2 años. Es dato personal de vigilancia y el
 *    principio de temporalidad de la Ley 1581 pide guardarlo lo mínimo. Dos
 *    años bastan para establecer un patrón y cubrir una disputa.
 *  · Bitácora de operaciones (quién hizo qué): 5 años. Tiene que sobrevivir a
 *    la ventana en que se puede reclamar — las obligaciones del contrato de
 *    aliado duran 5 años tras terminarlo. Aquí no hay IP ni huella: es la
 *    trazabilidad de un acto, no la vigilancia de una persona.
 */
class RetencionLimpiar extends Command
{
    protected $signature = 'retencion:limpiar {--ejecutar : Borra de verdad (sin esto solo cuenta)}';

    protected $description = 'Elimina accesos y bitácora que superaron el plazo de retención';

    private const ANIOS_ACCESOS = 2;

    private const ANIOS_BITACORA = 5;

    /** Se borra por lotes: un DELETE de cientos de miles bloquea la tabla. */
    private const LOTE = 5000;

    public function handle(): int
    {
        $ejecutar = $this->option('ejecutar');

        if (! $ejecutar) {
            $this->warn('MODO SIMULACIÓN — no se borra nada. Agrega --ejecutar.');
        }

        $this->newLine();

        $corteAccesos = now()->subYears(self::ANIOS_ACCESOS);
        $corteBitacora = now()->subYears(self::ANIOS_BITACORA);

        $nAccesos = AccesoUsuario::where('created_at', '<', $corteAccesos)->count();
        $nBitacora = DB::table('bitacora')->where('created_at', '<', $corteBitacora)->count();

        $this->line(sprintf('  Accesos anteriores a %s (%d años): %s',
            $corteAccesos->format('Y-m-d'), self::ANIOS_ACCESOS, number_format($nAccesos)));
        $this->line(sprintf('  Bitácora anterior a %s (%d años): %s',
            $corteBitacora->format('Y-m-d'), self::ANIOS_BITACORA, number_format($nBitacora)));

        if (! $ejecutar) {
            $this->newLine();

            return self::SUCCESS;
        }

        $borrados = $this->borrarPorLotes(
            fn () => AccesoUsuario::where('created_at', '<', $corteAccesos)->limit(self::LOTE)->delete()
        );
        $this->info("  Accesos eliminados: {$borrados}");

        $borrados = $this->borrarPorLotes(
            fn () => DB::table('bitacora')->where('created_at', '<', $corteBitacora)->limit(self::LOTE)->delete()
        );
        $this->info("  Bitácora eliminada: {$borrados}");

        $this->newLine();

        return self::SUCCESS;
    }

    private function borrarPorLotes(callable $borrar): int
    {
        $total = 0;

        do {
            $n = $borrar();
            $total += $n;
        } while ($n > 0);

        return $total;
    }
}
