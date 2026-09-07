<?php

namespace App\Console\Commands\Finanzas;

use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\Patrimonio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CargarPatrimonioInicial extends Command
{
    /**
     * Carga por primera vez los bienes del dueño en el módulo de patrimonio,
     * que estaba vacío mientras la lista vivía en una hoja de Excel aparte.
     *
     * Dos clases de bien:
     *  - Los comprados antes de que existiera el módulo (apartamento, vehículos,
     *    electrónicos): no hay gasto que ligar, solo se crea el bien.
     *  - Los comprados en 2026, que sí están en `finanzas_gastos`: el gasto se
     *    conserva —esa plata salió de la cuenta de verdad— pero se marca como
     *    patrimonio y se liga al bien, para que deje de contar como gasto del mes.
     *
     * Idempotente: un bien ya cargado (mismo nombre y fecha) se salta.
     *
     * Sin --apply solo simula. Ejecución: php artisan finanzas:cargar-patrimonio-inicial --apply
     */
    protected $signature = 'finanzas:cargar-patrimonio-inicial
                            {--user=2 : Id del dueño de las finanzas}
                            {--apply : Escribe los cambios; sin esta opción solo simula}';

    protected $description = 'Carga los bienes iniciales del módulo de patrimonio y liga las compras de 2026';

    /**
     * Lo que el dueño confirmó que tiene hoy. `valor_actual` solo va cuando hay
     * un valor real conocido; si no, el motor lo estima desde el precio de compra.
     * `gasto_id` liga la compra que ya está registrada en finanzas_gastos.
     */
    private const BIENES = [
        // Las escrituras ($5.700.000) van dentro del costo del apartamento: no
        // son un bien que se pueda vender aparte.
        ['nombre' => 'Apartamento Las Américas', 'categoria' => 'inmueble', 'valor_compra' => 255700000, 'fecha' => '2022-11-03', 'valor_actual' => 350000000, 'gasto_id' => null, 'nota' => 'Incluye $5.700.000 de escrituras. Avalúo de septiembre de 2026.'],
        ['nombre' => 'Camioneta', 'categoria' => 'vehiculo', 'valor_compra' => 129000000, 'fecha' => '2023-08-30', 'valor_actual' => 96000000, 'gasto_id' => null, 'nota' => 'Valor comercial de septiembre de 2026.'],
        ['nombre' => 'Camión 2', 'categoria' => 'vehiculo', 'valor_compra' => 40000000, 'fecha' => '2020-10-01', 'valor_actual' => 30000000, 'gasto_id' => null, 'nota' => 'Valor comercial de septiembre de 2026.'],
        ['nombre' => 'MacBook Pro', 'categoria' => 'electronico', 'valor_compra' => 6800000, 'fecha' => '2025-03-20', 'valor_actual' => null, 'gasto_id' => null, 'nota' => null],
        ['nombre' => 'iPhone 16 Pro', 'categoria' => 'electronico', 'valor_compra' => 4600000, 'fecha' => '2024-11-14', 'valor_actual' => null, 'gasto_id' => null, 'nota' => 'Año de compra por confirmar.'],
        ['nombre' => 'TV 65', 'categoria' => 'electronico', 'valor_compra' => 2800000, 'fecha' => '2025-09-28', 'valor_actual' => null, 'gasto_id' => null, 'nota' => 'Año de compra por confirmar.'],

        // Compras de 2026 que hoy están como gasto del mes.
        ['nombre' => 'Cama', 'categoria' => 'otro', 'valor_compra' => 3800000, 'fecha' => '2026-02-20', 'valor_actual' => null, 'gasto_id' => 82855, 'nota' => null],
        ['nombre' => 'TV sala', 'categoria' => 'electronico', 'valor_compra' => 2900000, 'fecha' => '2026-03-20', 'valor_actual' => null, 'gasto_id' => 82886, 'nota' => null],
        ['nombre' => 'Portátil', 'categoria' => 'electronico', 'valor_compra' => 3250000, 'fecha' => '2026-07-14', 'valor_actual' => null, 'gasto_id' => 83067, 'nota' => null],
    ];

    public function handle(): int
    {
        $userId = (int) $this->option('user');
        $aplicar = (bool) $this->option('apply');

        $filas = [];
        $porCrear = [];

        foreach (self::BIENES as $bien) {
            $existe = Patrimonio::where('user_id', $userId)
                ->where('nombre', $bien['nombre'])
                ->whereDate('fecha_adquisicion', $bien['fecha'])
                ->exists();

            $gasto = $bien['gasto_id']
                ? Gasto::where('user_id', $userId)->find($bien['gasto_id'])
                : null;

            $estadoGasto = match (true) {
                $bien['gasto_id'] === null => '—',
                $gasto === null => '⚠ no existe',
                (float) $gasto->monto !== (float) $bien['valor_compra'] => '⚠ monto distinto',
                default => 'se liga',
            };

            $filas[] = [
                $bien['nombre'],
                $bien['categoria'],
                number_format($bien['valor_compra']),
                $bien['fecha'],
                $bien['valor_actual'] ? number_format($bien['valor_actual']) : '(estimado)',
                $estadoGasto,
                $existe ? 'ya existe' : 'se crea',
            ];

            if (! $existe) {
                $porCrear[] = ['bien' => $bien, 'gasto' => $estadoGasto === 'se liga' ? $gasto : null];
            }
        }

        $this->table(['Bien', 'Categoría', 'Costó', 'Fecha', 'Vale hoy', 'Gasto', 'Estado'], $filas);

        if (empty($porCrear)) {
            $this->info('Nada por cargar: todos los bienes ya están.');

            return self::SUCCESS;
        }

        if (! $aplicar) {
            $this->warn('Simulación: no se escribió nada. Repite con --apply para cargar los '.count($porCrear).' bienes.');

            return self::SUCCESS;
        }

        DB::connection('finanzas')->transaction(function () use ($porCrear, $userId) {
            foreach ($porCrear as $item) {
                $bien = $item['bien'];

                $patrimonio = Patrimonio::create([
                    'user_id' => $userId,
                    'nombre' => $bien['nombre'],
                    'categoria' => $bien['categoria'],
                    'valor_compra' => $bien['valor_compra'],
                    'fecha_adquisicion' => $bien['fecha'],
                    'valor_actual' => $bien['valor_actual'] ?? $bien['valor_compra'],
                    // Un valor real se sabe hoy; el precio de compra, el día que se compró.
                    'valor_actual_fecha' => $bien['valor_actual'] ? now()->toDateString() : $bien['fecha'],
                    'activo' => true,
                    'observaciones' => $bien['nota'],
                ]);

                // El gasto no se borra: la plata salió de la cuenta y el saldo del
                // bolsillo debe seguir reflejándolo. Solo deja de ser "gasto".
                $item['gasto']?->update([
                    'es_patrimonio' => true,
                    'patrimonio_id' => $patrimonio->id,
                ]);
            }
        });

        $this->info('Listo. Bienes cargados: '.count($porCrear).'.');
        $this->line('Recuerda limpiar la caché del dashboard: php artisan cache:clear');

        return self::SUCCESS;
    }
}
