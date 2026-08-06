<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\Canario;
use App\Models\Cliente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Siembra un cliente trampa por aliado.
 *
 * El canario es un cliente que existe en la lista y en las exportaciones, pero
 * NO en el mundo. Si aparece en otro sistema, la copia queda demostrada: nadie
 * pudo haberlo tecleado por su cuenta.
 *
 * Dos decisiones que lo hacen seguro:
 *
 *  · **Sin contrato.** Facturación, cobros y los planos PILA recorren
 *    contratos, no clientes. Un cliente suelto no entra en ningún proceso
 *    operativo: no factura, no cotiza, no se le liquida planilla.
 *  · **Cédula en el rango 99XXXXXXXX**, que la Registraduría no emite y que
 *    hoy está vacío en la base. Un canario nunca puede pisar a una persona real.
 *
 * Va en seco por defecto: escribir clientes falsos en la base de producción no
 * es algo que deba pasar por descuido.
 *
 *   php artisan canarios:sembrar             → muestra qué haría
 *   php artisan canarios:sembrar 4 --ejecutar
 */
class CanariosSembrar extends Command
{
    protected $signature = 'canarios:sembrar
                            {aliado? : Id del aliado; si se omite, todos los activos}
                            {--ejecutar : Escribe de verdad (sin esto solo simula)}';

    protected $description = 'Siembra un cliente trampa por aliado para poder probar una copia de datos';

    /** Rango que la Registraduría no emite: un canario jamás pisa a alguien real. */
    private const CEDULA_MIN = 9900000000;

    private const CEDULA_MAX = 9999999999;

    /**
     * Nombres y apellidos se indexan por separado para que la combinación sea
     * única por aliado. Si dos aliados compartieran nombre, un canario filtrado
     * probaría que hubo copia pero no de dónde salió — que es media prueba.
     */
    private const NOMBRES = [
        ['MARLENY', 'ESTHER'],
        ['ARNULFO', 'DE JESUS'],
        ['YURANY', 'PATRICIA'],
        ['EDILBERTO', 'ANTONIO'],
        ['NOHEMI', 'DEL CARMEN'],
        ['HERNANDO', 'JOSE'],
        ['LUZ DARY', 'ELENA'],
        ['GILBERTO', 'RAMON'],
    ];

    private const APELLIDOS = [
        ['QUIROGA', 'ZAMBRANO'],
        ['BETANCUR', 'OSPINA'],
        ['CIFUENTES', 'MOSQUERA'],
        ['PALOMINO', 'CARVAJAL'],
        ['ARBELAEZ', 'TRUJILLO'],
        ['VILLAMIZAR', 'PAREDES'],
        ['ECHAVARRIA', 'PINZON'],
        ['SANTACRUZ', 'BEDOYA'],
    ];

    public function handle(): int
    {
        $ejecutar = $this->option('ejecutar');
        $idAliado = $this->argument('aliado');

        $aliados = $idAliado
            ? Aliado::where('id', $idAliado)->get()
            : Aliado::where('activo', true)->orderBy('id')->get();

        if ($aliados->isEmpty()) {
            $this->error('No hay aliados que coincidan.');

            return self::FAILURE;
        }

        if (! $ejecutar) {
            $this->warn('MODO SIMULACIÓN — no se escribe nada. Agrega --ejecutar para aplicar.');
        }

        $this->newLine();

        foreach ($aliados as $aliado) {
            $yaTiene = Canario::activos()->where('aliado_id', $aliado->id)->where('tipo', 'cliente')->first();

            if ($yaTiene) {
                $this->line(sprintf('  %-24s ya tiene canario (CC %s)', $aliado->nombre, $yaTiene->cedula));

                continue;
            }

            $cedula = $this->cedulaLibre();
            $nombre = array_merge(
                self::NOMBRES[$aliado->id % count(self::NOMBRES)],
                self::APELLIDOS[intdiv($aliado->id, count(self::NOMBRES)) % count(self::APELLIDOS)]
            );

            $this->line(sprintf(
                '  %-24s → %s %s %s (CC %s)',
                $aliado->nombre,
                $nombre[0],
                $nombre[2],
                $nombre[3],
                $cedula
            ));

            if (! $ejecutar) {
                continue;
            }

            DB::transaction(function () use ($aliado, $cedula, $nombre) {
                // clientes.id no es IDENTITY (tabla legacy): se asigna a mano.
                $id = (int) DB::table('clientes')->max('id') + 1;

                Cliente::create([
                    'id' => $id,
                    'aliado_id' => $aliado->id,
                    'tipo_doc' => 'CC',
                    'cedula' => $cedula,
                    'primer_nombre' => $nombre[0],
                    'segundo_nombre' => $nombre[1],
                    'primer_apellido' => $nombre[2],
                    'segundo_apellido' => $nombre[3],
                    'observacion' => 'Prospecto sin gestionar.',
                ]);

                Canario::create([
                    'aliado_id' => $aliado->id,
                    'tipo' => 'cliente',
                    'referencia_id' => $id,
                    'cedula' => (string) $cedula,
                    'nombre' => implode(' ', $nombre),
                    'notas' => 'Sembrado por canarios:sembrar. Sin contrato: no entra en facturación ni en planos.',
                    'activo' => true,
                ]);
            });
        }

        $this->newLine();

        if ($ejecutar) {
            $this->info('Listo. Consulta el inventario con: php artisan canarios:listar');
            $this->warn('No los borres desde la pantalla de clientes sin retirarlos también de `canarios`.');
        }

        return self::SUCCESS;
    }

    /**
     * Cédula del rango reservado que no exista ya (ni como cliente ni como canario).
     */
    private function cedulaLibre(): int
    {
        for ($i = 0; $i < 50; $i++) {
            $cedula = random_int(self::CEDULA_MIN, self::CEDULA_MAX);

            $existe = Cliente::where('cedula', $cedula)->exists()
                || Canario::where('cedula', (string) $cedula)->exists();

            if (! $existe) {
                return $cedula;
            }
        }

        throw new \RuntimeException('No encontré una cédula libre en el rango de canarios.');
    }
}
