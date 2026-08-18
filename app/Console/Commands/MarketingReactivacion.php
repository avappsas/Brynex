<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\ConsentimientoDato;
use App\Models\Contrato;
use App\Models\WhatsappEnvioMasivo;
use App\Models\WhatsappEnvioMasivoDetalle;
use App\Models\WhatsappPlantilla;
use App\Services\Cumplimiento\VentanaContactoLey2300;
use Illuminate\Console\Command;

/**
 * Campaña de reactivación: le escribe a quien se retiró y no ha vuelto.
 *
 * Sale del hallazgo de que este negocio es TRANSACCIONAL RECURRENTE y no una suscripción:
 * el 33% de los clientes vuelve, y la mayoría dentro de los tres meses. Traer de vuelta a
 * alguien que ya compró cuesta una fracción de los ~$7.500 por conversación que cuesta un
 * desconocido en pauta.
 *
 * La ventana por defecto NO es 15-30 días, aunque suene a lo obvio: los retiros se registran
 * con rezago —el 18-ago-2026 el retiro más reciente era del 3-ago y en esa ventana había UNA
 * persona— así que apuntar ahí no dispara nunca. El valor está en el rezago acumulado: 166
 * personas entre 31 y 90 días, 537 entre 91 y 365.
 *
 * Arranca en simulación a propósito. Es un envío masivo a gente real, con costo por plantilla
 * de marketing y con la Ley 2300 encima: se mira la lista antes de mandarla.
 */
class MarketingReactivacion extends Command
{
    protected $signature = 'marketing:reactivacion
        {--aliado=brygar : Slug del aliado}
        {--desde=31 : Días mínimos desde el retiro}
        {--hasta=90 : Días máximos desde el retiro}
        {--plantilla= : Nombre de la plantilla de WhatsApp aprobada (categoría MARKETING)}
        {--limite=50 : Máximo de destinatarios por corrida}
        {--enviar : Enviar de verdad. Sin esto solo simula y muestra a quién le llegaría}';

    protected $description = 'Escribe por WhatsApp a los clientes retirados que no han vuelto';

    public function handle(): int
    {
        $aliado = Aliado::where('slug', $this->option('aliado'))->first();
        if (!$aliado) {
            $this->error('No existe ese aliado.');
            return self::FAILURE;
        }

        $desde = (int) $this->option('desde');
        $hasta = (int) $this->option('hasta');
        $limite = (int) $this->option('limite');
        $enviarDeVerdad = (bool) $this->option('enviar');

        $candidatos = $this->candidatos($aliado->id, $desde, $hasta);
        $this->line("Retirados hace {$desde}-{$hasta} días: {$candidatos->count()}");

        if ($candidatos->isEmpty()) {
            $this->warn('Nadie en esa ventana. Probar con --desde/--hasta más amplios.');
            return self::SUCCESS;
        }

        // Se filtra por consentimiento ANTES de cualquier otra cosa: quien pidió no recibir
        // más publicidad no entra ni a la simulación, para que la lista que se revisa sea la
        // lista que de verdad se manda.
        $telefonos = $candidatos->pluck('telefono')->all();
        // Devuelve ['contactables' => [...], 'excluidos' => [...]], no una lista plana.
        $filtro = ConsentimientoDato::filtrarContactables($aliado->id, $telefonos);
        $contactables = $filtro['contactables'] ?? [];

        // Quien contestó "por ahora no" queda fuera hasta que venza su aplazamiento. Volver a
        // escribirle antes es lo que convierte un "todavía no" en una baja.
        $aplazados = array_flip(\App\Models\MarketingAplazado::vigentesDe($aliado->id, $contactables));
        $contactables = array_values(array_filter($contactables, fn ($t) => !isset($aplazados[$t])));

        $mapa = array_flip($contactables);

        $destinatarios = $candidatos->filter(
            fn ($c) => isset($mapa[ConsentimientoDato::normalizarTelefono($c->telefono)])
        )->take($limite)->values();

        $bloqueados = $candidatos->count() - $destinatarios->count();
        $this->line("Contactables: {$destinatarios->count()}" . ($bloqueados > 0 ? "  (excluidos {$bloqueados} por baja, aplazamiento, repetido o teléfono inválido)" : ''));

        if ($destinatarios->isEmpty()) {
            return self::SUCCESS;
        }

        $this->table(
            ['Contrato', 'Nombre', 'Teléfono', 'Retiro', 'Días'],
            $destinatarios->take(10)->map(fn ($c) => [
                $c->contrato_id,
                mb_substr((string) $c->nombre, 0, 28),
                $c->telefono,
                substr((string) $c->fecha_retiro, 0, 10),
                $c->dias,
            ])->all()
        );
        if ($destinatarios->count() > 10) {
            $this->line('  … y ' . ($destinatarios->count() - 10) . ' más.');
        }

        if (!$enviarDeVerdad) {
            $this->newLine();
            $this->info('SIMULACIÓN — no se envió nada. Agregar --enviar para mandarlo de verdad.');
            return self::SUCCESS;
        }

        return $this->enviar($aliado, $destinatarios, $desde, $hasta);
    }

    /**
     * Retirados en la ventana que NO tienen otro contrato vigente: si ya volvió por otro lado,
     * escribirle "vuelve" es la mejor forma de quedar mal.
     */
    private function candidatos(int $aliadoId, int $desde, int $hasta)
    {
        return Contrato::query()
            ->where('contratos.aliado_id', $aliadoId)
            ->whereNotNull('contratos.fecha_retiro')
            ->whereBetween('contratos.fecha_retiro', [
                now()->subDays($hasta)->toDateString(),
                now()->subDays($desde)->toDateString(),
            ])
            // El vínculo con el cliente es por CÉDULA, no por un id — ver Contrato::cliente().
            ->whereNotExists(function ($q) use ($aliadoId) {
                $q->selectRaw('1')
                  ->from('contratos as vigentes')
                  ->whereColumn('vigentes.cedula', 'contratos.cedula')
                  ->where('vigentes.aliado_id', $aliadoId)
                  ->whereNull('vigentes.fecha_retiro');
            })
            ->join('clientes', 'clientes.cedula', '=', 'contratos.cedula')
            ->selectRaw("contratos.id as contrato_id, contratos.cedula, contratos.fecha_retiro,
                         LTRIM(RTRIM(CONCAT(clientes.primer_nombre, ' ', clientes.primer_apellido))) as nombre,
                         COALESCE(clientes.celular, clientes.telefono) as telefono,
                         DATEDIFF(day, contratos.fecha_retiro, GETDATE()) as dias")
            ->where(function ($q) {
                $q->whereNotNull('clientes.celular')->orWhereNotNull('clientes.telefono');
            })
            ->orderBy('contratos.fecha_retiro', 'desc')
            ->get()
            // Una persona pudo retirar varios contratos: se le escribe UNA vez.
            ->unique('cedula')
            ->values();
    }

    private function enviar(Aliado $aliado, $destinatarios, int $desde, int $hasta): int
    {
        $nombrePlantilla = $this->option('plantilla');
        if (!$nombrePlantilla) {
            $this->error('Falta --plantilla. Es un mensaje fuera de la ventana de 24h: Meta exige una plantilla aprobada.');
            return self::FAILURE;
        }

        $plantilla = WhatsappPlantilla::where('aliado_id', $aliado->id)
            ->where('nombre', $nombrePlantilla)
            ->where('estado', 'approved')
            ->first();

        if (!$plantilla) {
            $this->error("No hay una plantilla aprobada llamada '{$nombrePlantilla}' en este aliado.");
            $this->line('Aprobadas hoy: ' . WhatsappPlantilla::where('aliado_id', $aliado->id)->where('estado', 'approved')->pluck('nombre')->implode(', '));
            return self::FAILURE;
        }

        // La ley no distingue entre "mi campaña" y "un envío suelto": si no es hora de
        // contactar, no se contacta. El envío queda creado y se despacha cuando abra.
        if (!VentanaContactoLey2300::permite()) {
            $this->warn('Fuera del horario de la Ley 2300: ' . VentanaContactoLey2300::motivoBloqueo());
            $this->warn('Los mensajes quedan encolados y salen en la próxima apertura (' . VentanaContactoLey2300::proximaApertura()->format('d/m H:i') . ').');
        }

        $envio = WhatsappEnvioMasivo::create([
            'aliado_id'           => $aliado->id,
            'plantilla_id'        => $plantilla->id,
            'tipo_envio'          => 'reactivacion',
            'mes'                 => (int) now('America/Bogota')->format('m'),
            'anio'                => (int) now('America/Bogota')->format('Y'),
            'total_destinatarios' => $destinatarios->count(),
            'estado'              => 'en_proceso',
            'parametros_json'     => ['dias_desde' => $desde, 'dias_hasta' => $hasta],
        ]);

        foreach ($destinatarios as $d) {
            $detalle = WhatsappEnvioMasivoDetalle::create([
                'envio_id'            => $envio->id,
                'contrato_id'         => $d->contrato_id,
                'wa_numero'           => $d->telefono,
                'nombre_destinatario' => $d->nombre,
                'estado'              => 'pendiente',
            ]);

            \App\Jobs\WhatsappEnvioMasivoJob::dispatch($detalle->id);
        }

        $this->info("Campaña #{$envio->id} encolada: {$destinatarios->count()} mensajes.");
        $this->line('El worker los despacha respetando el horario y las bajas.');

        return self::SUCCESS;
    }
}
