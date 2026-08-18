<?php

namespace App\Services\Marketing;

use App\Jobs\WhatsappEnvioMasivoJob;
use App\Models\Aliado;
use App\Models\WhatsappEnvioMasivo;
use App\Models\WhatsappEnvioMasivoDetalle;
use App\Models\WhatsappPlantilla;
use App\Services\Cumplimiento\VentanaContactoLey2300;
use Illuminate\Support\Collection;

/**
 * Lanza una tanda de la campaña de reactivación.
 *
 * Existe aparte del comando porque ahora también se dispara desde el panel, y el envío no
 * puede tener dos implementaciones: la que se probó en la terminal tiene que ser exactamente
 * la que corre cuando alguien da clic.
 */
class EnvioReactivacion
{
    /**
     * @param  ?int  $usuarioId  Quién lo lanzó. Desde el panel es el usuario en sesión; desde
     *                           la consola no hay sesión y la columna no admite nulos, así que
     *                           hay que pasarlo o se cae el insert justo antes de enviar.
     * @return array{ok: bool, envio_id: ?int, enviados: int, mensaje: string}
     */
    public static function lanzar(Aliado $aliado, Collection $destinatarios, string $nombrePlantilla, array $ventana = [], ?int $usuarioId = null): array
    {
        if ($destinatarios->isEmpty()) {
            return ['ok' => false, 'envio_id' => null, 'enviados' => 0, 'mensaje' => 'No hay destinatarios.'];
        }

        $plantilla = WhatsappPlantilla::where('aliado_id', $aliado->id)
            ->where('nombre', $nombrePlantilla)
            ->where('estado', 'approved')
            ->first();

        if (!$plantilla) {
            return ['ok' => false, 'envio_id' => null, 'enviados' => 0,
                'mensaje' => "No hay una plantilla aprobada llamada '{$nombrePlantilla}'."];
        }

        $envio = WhatsappEnvioMasivo::create([
            'aliado_id'           => $aliado->id,
            'plantilla_id'        => $plantilla->id,
            'usuario_id'          => $usuarioId ?? auth()->id() ?? \App\Models\User::where('aliado_id', $aliado->id)->value('id'),
            'tipo_envio'          => 'reactivacion',
            'mes'                 => (int) now('America/Bogota')->format('m'),
            'anio'                => (int) now('America/Bogota')->format('Y'),
            'total_destinatarios' => $destinatarios->count(),
            'estado'              => 'en_proceso',
            'parametros_json'     => $ventana,
        ]);

        foreach ($destinatarios as $d) {
            WhatsappEnvioMasivoDetalle::create([
                'envio_id'            => $envio->id,
                'contrato_id'         => $d->contrato_id ?? null,
                'wa_numero'           => $d->telefono,
                'nombre_destinatario' => $d->nombre,
                'estado'              => 'pendiente',
            ]);
        }

        // UN job por envío, no uno por destinatario: el job recibe el id del ENVÍO y recorre
        // sus detalles pendientes. Despacharlo por detalle le pasaba el id equivocado, buscaba
        // un envío que no existe y terminaba en 4 ms sin mandar nada — y si ese id hubiera
        // coincidido con otra campaña, la habría reprocesado.
        WhatsappEnvioMasivoJob::dispatch($envio->id);

        // La ley no distingue entre una campaña y un envío suelto. Si está cerrado, los
        // mensajes quedan encolados y el worker los despacha en la próxima apertura.
        $aviso = VentanaContactoLey2300::permite()
            ? ''
            : ' Fuera del horario de la Ley 2300: salen en la próxima apertura ('
              . VentanaContactoLey2300::proximaApertura()->format('d/m H:i') . ').';

        return [
            'ok'       => true,
            'envio_id' => $envio->id,
            'enviados' => $destinatarios->count(),
            'mensaje'  => "Tanda #{$envio->id} encolada: {$destinatarios->count()} mensajes." . $aviso,
        ];
    }
}
