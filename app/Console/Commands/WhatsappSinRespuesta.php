<?php

namespace App\Console\Commands;

use App\Models\Publicacion;
use App\Models\WhatsappConversacion;
use App\Models\WhatsappMensaje;
use App\Services\AlertaOperativaService;
use App\Services\Finanzas\TelefonosDeudores;
use App\Services\Ia\AsistenteIaService;
use Illuminate\Console\Command;

/**
 * Aviso diario de la gente que escribió por WhatsApp y está esperando que una persona le conteste.
 *
 * Existe porque ahí es donde se estaban perdiendo las ventas, no en los anuncios. Al revisar las
 * 48 conversaciones de clientes que llegaron por pauta (sep-2026) había gente lista para
 * afiliarse sin respuesta: una señora de 63 años que ya había pedido los requisitos llevaba 11
 * días esperando, un asesor con empresa propia 14, y otro cliente pidió "márcame porfa" y nadie
 * lo llamó. Ninguno se perdió por el anuncio ni por el bot: se perdieron porque nadie les volvió
 * a escribir, y en el panel no había nada que lo hiciera evidente.
 *
 * Cuenta como "esperando":
 *  - el último mensaje de la conversación es del cliente y ya pasaron unas horas, o
 *  - el bot la pasó a un asesor (pendiente_atencion) y sigue sin atenderse.
 * No cuenta un "ok" o un "gracias" de despedida: eso no pide respuesta, y un aviso lleno de
 * despedidas se deja de leer.
 *
 * Si no hay nadie esperando, no se manda nada: un aviso que siempre llega diciendo "todo bien"
 * termina ignorándose el día que sí importa.
 *
 * Ejecución manual: php artisan whatsapp:sin-respuesta --no-enviar
 */
class WhatsappSinRespuesta extends Command
{
    protected $signature = 'whatsapp:sin-respuesta
        {--aliado=2 : Aliado a revisar}
        {--horas=2 : Horas sin respuesta para que cuente como esperando}
        {--dias=14 : No mirar conversaciones más viejas que esto}
        {--no-enviar : Solo mostrarlo en pantalla}';

    protected $description = 'Avisa por WhatsApp quién escribió y lleva horas sin que una persona le responda';

    /**
     * Lo que se contesta para despedirse: no pide respuesta.
     *
     * Tiene que haber una palabra de despedida ("gracias", "igualmente") o un "ok" solo; y nunca
     * cuenta si trae una pregunta o habla de plata: "ok, ¿cuánto vale?" o "soporte de pago,
     * gracias" sí piden que alguien haga algo.
     */
    private const DESPEDIDA = '/^(?!.*(\?|cu[aá]nt|c[oó]mo|qu[eé]\b|d[oó]nde|cu[aá]ndo|precio|valor|pag|soporte|comprobante|planilla|necesit|quiero|ayuda))\s*((ok+|okey|oki|vale|listo|bueno|dale|perfecto|entendido)[\s,.!]*)?(((muchas|mil)\s+)?(gracia[s]?|igualmente|bendiciones)(\s+\p{L}+){0,4})?[\s\p{So}\p{Sk}\p{P}]*$/iu';

    /** El tope de la plantilla es 600 caracteres; lo que no quepa se resume en "y N más". */
    private const MAX_CARACTERES = 560;

    public function handle(AlertaOperativaService $alertas): int
    {
        $aliadoId = (int) $this->option('aliado');
        $horas = (int) $this->option('horas');
        $numeroDueno = preg_replace('/\D/', '', (string) config('finanzas.whatsapp_personal_dueno'));

        // Los que el bot pasó a un asesor se miran con el doble de margen: son justo los casos que
        // alguien prometió atender. Un asesor con empresa propia llevaba 14 días así y, con la
        // ventana normal, ya no habría salido en el aviso.
        $dias = (int) $this->option('dias');
        $conversaciones = WhatsappConversacion::where('aliado_id', $aliadoId)
            ->where(fn ($q) => $q->where('ultimo_mensaje_at', '>=', now()->subDays($dias))
                ->orWhere(fn ($q2) => $q2->where('pendiente_atencion', true)
                    ->where('ultimo_mensaje_at', '>=', now()->subDays($dias * 2))))
            ->get();

        $esperando = [];
        foreach ($conversaciones as $cv) {
            $tel = preg_replace('/\D/', '', (string) $cv->wa_contact_id);

            // El número del dueño y los deudores de sus préstamos personales no son clientes: los
            // del préstamo ya le llegan reenviados uno por uno.
            if ($tel === $numeroDueno || TelefonosDeudores::esDeudor($cv->wa_contact_id)) {
                continue;
            }

            $ultimo = WhatsappMensaje::where('conversacion_id', $cv->id)->orderByDesc('id')->first();
            if (! $ultimo) {
                continue;
            }

            $escribioUltimo = $ultimo->direccion === 'entrante';
            $pasadoAAsesor = (bool) $cv->pendiente_atencion;
            if (! $escribioUltimo && ! $pasadoAAsesor) {
                continue;
            }
            if ($ultimo->created_at->gt(now()->subHours($horas))) {
                continue; // todavía está fresco: el bot o alguien del equipo lo está atendiendo
            }

            $texto = $this->textoDe($ultimo);
            if ($escribioUltimo && ! $pasadoAAsesor && preg_match(self::DESPEDIDA, $texto)) {
                continue;
            }

            $pieza = $cv->origen_publicacion_id ? Publicacion::find($cv->origen_publicacion_id) : null;

            $esperando[] = [
                'nombre' => $this->nombreCorto($cv),
                'desde' => $ultimo->created_at,
                // Si el último mensaje fue nuestro pero el caso quedó en manos de un asesor, lo que
                // importa es por qué se pasó, no lo último que dijo el bot.
                'dijo' => $escribioUltimo ? $texto : ($cv->pendiente_motivo ?: 'pasado a un asesor'),
                // Primero los asesores (traen cartera), después los que el bot ya pasó a una
                // persona, después el resto. Dentro de cada grupo, lo más reciente primero: es lo
                // que todavía se puede recuperar.
                'prioridad' => ($pieza && AsistenteIaService::esPiezaDeAsesores($pieza)) ? 0 : ($pasadoAAsesor ? 1 : 2),
                'asesor' => $pieza && AsistenteIaService::esPiezaDeAsesores($pieza),
            ];
        }

        usort($esperando, fn ($a, $b) => [$a['prioridad'], -$a['desde']->timestamp] <=> [$b['prioridad'], -$b['desde']->timestamp]);

        $this->mostrar($esperando);

        if (empty($esperando)) {
            $this->info('Nadie esperando respuesta. No se envía nada.');

            return self::SUCCESS;
        }

        if (! $this->option('no-enviar')) {
            $texto = $this->resumen($esperando);
            foreach ($this->destinatarios() as $numero) {
                $ok = $alertas->enviarA($numero, 'Esperando respuesta', $texto);
                $this->line($ok ? "  → enviado a {$numero}" : "  → no se pudo enviar a {$numero} (ver el log).");
            }
        }

        return self::SUCCESS;
    }

    /** @return string[] */
    private function destinatarios(): array
    {
        $crudos = explode(',', (string) config('services.whatsapp.pendientes_numeros'));
        $numeros = array_filter(array_map(fn ($n) => preg_replace('/\D/', '', $n), $crudos));

        return array_values(array_unique($numeros));
    }

    private function textoDe(WhatsappMensaje $m): string
    {
        $t = trim(preg_replace('/\s+/', ' ', (string) $m->contenido));

        if ($t === '' || str_starts_with($t, '[Tipo de mensaje no soportado')) {
            return match ($m->tipo) {
                'audio' => '[nota de voz]',
                'image' => '[foto]',
                'document' => '[documento]',
                'video' => '[video]',
                default => '[mensaje sin texto]',
            };
        }

        return $t;
    }

    private function nombreCorto(WhatsappConversacion $cv): string
    {
        $n = trim((string) $cv->nombre_contacto);
        // Los nombres de perfil vienen de todo tipo ("te quiero hija 😘"). Se toman las dos
        // primeras palabras; si no hay nombre, los últimos dígitos del número.
        $n = $n !== '' ? implode(' ', array_slice(preg_split('/\s+/', $n), 0, 2)) : '…'.substr((string) $cv->wa_contact_id, -4);

        return mb_substr($n, 0, 22);
    }

    private function hace(\Carbon\Carbon $f): string
    {
        $h = (int) $f->diffInHours(now());

        return $h < 24 ? "{$h}h" : intdiv($h, 24).'d';
    }

    /** Una sola tira: la plantilla aplana los saltos de línea. */
    private function resumen(array $esperando): string
    {
        $total = count($esperando);
        $cabeza = $total === 1 ? '1 persona espera respuesta: ' : "{$total} personas esperan respuesta: ";

        $partes = [];
        $largo = mb_strlen($cabeza);
        foreach ($esperando as $i => $e) {
            $item = ($i + 1).') '.($e['asesor'] ? 'ASESOR ' : '').$e['nombre'].' '.$this->hace($e['desde'])
                .' «'.mb_substr($e['dijo'], 0, 38).'»';
            // Se reserva sitio para el "y N más" del final.
            if ($largo + mb_strlen($item) + 20 > self::MAX_CARACTERES) {
                break;
            }
            $partes[] = $item;
            $largo += mb_strlen($item) + 1;
        }

        $faltan = $total - count($partes);

        return $cabeza.implode(' ', $partes).($faltan > 0 ? " y {$faltan} más en el chat." : '');
    }

    private function mostrar(array $esperando): void
    {
        $this->table(
            ['', 'Quién', 'Hace', 'Lo último'],
            array_map(fn ($e) => [
                $e['asesor'] ? 'ASESOR' : ($e['prioridad'] === 1 ? 'pasado' : ''),
                $e['nombre'],
                $this->hace($e['desde']),
                mb_substr($e['dijo'], 0, 70),
            ], $esperando)
        );

        if (! empty($esperando)) {
            $this->line('Aviso: '.$this->resumen($esperando));
        }
    }
}
