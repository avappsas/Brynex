<?php

namespace App\Console\Commands\Finanzas;

use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\Patrimonio;
use App\Models\Finanzas\PatrimonioGasto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LigarGastosPatrimonio extends Command
{
    /**
     * Liga a su bien los gastos de tenencia que ya estaban registrados sueltos,
     * de cuando el módulo de patrimonio no existía.
     *
     * Solo gastos de TENENCIA: lo que se paga por ser dueño —predial,
     * administración, impuesto, SOAT, mantenimiento mayor— y no lo que se paga
     * por usar el bien (servicios públicos, gasolina, peajes), que depende de
     * quién viva ahí o de cuánto se maneje, no del bien.
     *
     * Ligar no cambia ninguna cifra: el gasto sigue siendo gasto del mes y sigue
     * restando de su cuenta (`es_patrimonio` queda en false a propósito). Lo
     * único que hace es que la ficha del bien pueda mostrar cuánto ha costado
     * tenerlo. Por eso el gasto se copia también a `finanzas_patrimonio_gastos`,
     * que es de donde lee la ficha.
     *
     * Los ids van escritos uno por uno y no por patrón: las descripciones
     * mezclan el SOAT de terceros, el apartamento de la madre y vehículos que ya
     * no existen, y un `like` se los llevaría por delante.
     *
     * Sin --apply solo simula. Ejecución: php artisan finanzas:ligar-gastos-patrimonio --apply
     */
    protected $signature = 'finanzas:ligar-gastos-patrimonio
                            {--user=2 : Id del dueño de las finanzas}
                            {--apply : Escribe los cambios; sin esta opción solo simula}';

    protected $description = 'Liga los gastos de tenencia ya registrados con el bien al que pertenecen';

    /**
     * Gastos por bien. La llave es el nombre del bien tal como está cargado.
     */
    private const GASTOS = [
        'Apartamento Las Américas' => [
            80520, // Pintura del apartamento (dic-2022)
            80572, // Pintura apartamento Las Américas (ene-2023)
            80846, // Administración (may-2023)
            81052, // Administración enero (2024)
            81053, // Administración febrero (2024)
            81361, // Administración marzo (2024)
            81566, // Administración julio (2024)
            81573, // Administración (jul-2024)
            82367, // Predial 2025
            83181, // Administración hasta agosto (2026)
        ],
        // Solo lo que nombra la placa KUY188, más las llantas y la lavada que no
        // dejan duda por fecha y monto. Los "cambio de aceite" y "lavada carro"
        // sin placa se quedan fuera: había otros vehículos en circulación.
        'Camioneta' => [
            81059, // Lavada (feb-2024)
            81681, // GPS KUY188 (sep-2024)
            81739, // Llantas KUY188 (oct-2024)
            81860, // Cambio de aceite KUY188 (dic-2024)
            82118, // Impuesto KUY188 (mar-2025)
            82285, // Llantas (may-2025)
            82575, // SOAT KUY188 (sep-2025)
            82627, // Cambio de aceite KUY188 (oct-2025)
            82953, // Impuesto KUY188 (may-2026)
        ],
        // El camión 2 es SPK313 y no tiene gastos registrados a su nombre.
    ];

    public function handle(): int
    {
        $userId = (int) $this->option('user');
        $aplicar = (bool) $this->option('apply');

        $filas = [];
        $porLigar = [];

        foreach (self::GASTOS as $nombreBien => $ids) {
            $bien = Patrimonio::where('user_id', $userId)->where('nombre', $nombreBien)->first();

            if (! $bien) {
                $this->error("No existe el bien \"{$nombreBien}\". ¿Se cargó el patrimonio inicial?");

                return self::FAILURE;
            }

            foreach ($ids as $gastoId) {
                $gasto = Gasto::where('user_id', $userId)->find($gastoId);

                if (! $gasto) {
                    $filas[] = [$nombreBien, $gastoId, '—', '—', '⚠ no existe'];

                    continue;
                }

                $yaLigado = $gasto->patrimonio_id !== null;

                $filas[] = [
                    $nombreBien,
                    $gastoId,
                    \Carbon\Carbon::parse($gasto->fecha)->format('d/m/Y'),
                    number_format($gasto->monto),
                    $yaLigado ? 'ya ligado' : 'se liga',
                ];

                if (! $yaLigado) {
                    $porLigar[] = ['gasto' => $gasto, 'bien' => $bien];
                }
            }
        }

        $this->table(['Bien', 'Gasto', 'Fecha', 'Monto', 'Estado'], $filas);

        $total = collect($porLigar)->sum(fn ($l) => (float) $l['gasto']->monto);
        $this->line('Por ligar: '.count($porLigar).' gastos, $'.number_format($total).'.');

        if (empty($porLigar)) {
            return self::SUCCESS;
        }

        if (! $aplicar) {
            $this->warn('Simulación: no se escribió nada. Repite con --apply.');

            return self::SUCCESS;
        }

        DB::connection('finanzas')->transaction(function () use ($porLigar) {
            foreach ($porLigar as $liga) {
                $gasto = $liga['gasto'];

                // `es_patrimonio` se queda en false: esto es un gasto de verdad,
                // se consumió. Marcarlo lo sacaría de los gastos del mes, que es
                // el trato de una COMPRA de bien, no de su mantenimiento.
                $gasto->update(['patrimonio_id' => $liga['bien']->id]);

                PatrimonioGasto::create([
                    'patrimonio_id' => $liga['bien']->id,
                    'concepto' => $gasto->descripcion ?: 'Gasto del bien',
                    'monto' => $gasto->monto,
                    'fecha' => $gasto->fecha,
                    'observacion' => 'Ligado desde el gasto #'.$gasto->id.' ya registrado.',
                ]);
            }
        });

        $this->info('Listo. Gastos ligados: '.count($porLigar).'.');

        return self::SUCCESS;
    }
}
