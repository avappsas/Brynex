<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Bitácora de autorizaciones y bajas para contacto comercial (Ley 1581 + Ley 2300).
 *
 * Opera como LISTA DE SUPRESIÓN (opt-out), por decisión de negocio: se contacta por
 * defecto y solo se excluye a quien pidió expresamente no ser contactado. No es el modelo
 * más conservador frente a la SIC — la ley pide autorización previa — pero sí es el que
 * hace que la baja sea absoluta e inmediata cuando el titular la pide.
 *
 * Para una campaña puramente publicitaria a desconocidos, usar `tieneAutorizacionExpresa()`
 * en vez de `puedeContactar()`: ahí sí conviene exigir el opt-in.
 *
 * Tabla de solo-agregar: `otorgar()` y `revocar()` insertan filas nuevas, nunca actualizan.
 * El estado vigente de una persona en un canal es su fila más reciente — así queda la traza
 * completa de cuándo autorizó y cuándo se dio de baja, que es lo que hay que poder mostrar.
 */
class ConsentimientoDato extends BaseModel
{
    protected $table = 'consentimientos_datos';

    public const CANAL_WHATSAPP = 'whatsapp';
    public const CANAL_SMS      = 'sms';
    public const CANAL_EMAIL    = 'email';
    public const CANAL_LLAMADA  = 'llamada';

    protected $fillable = [
        'aliado_id', 'cedula', 'telefono', 'email', 'nombre',
        'canal', 'finalidad', 'otorgado',
        'texto_mostrado', 'origen', 'evidencia', 'fecha_evento',
    ];

    protected $casts = [
        'otorgado'     => 'boolean',
        'evidencia'    => 'array',
        'fecha_evento' => 'datetime',
    ];

    /**
     * Normaliza un número al formato con que se guarda y se compara: solo dígitos, con
     * indicativo 57. Sin esto, "320 540 0870", "+573205400870" y "3205400870" serían tres
     * personas distintas y la baja de una no cubriría a las otras.
     */
    public static function normalizarTelefono(?string $telefono): ?string
    {
        $solo = preg_replace('/\D/', '', $telefono ?? '');
        if (!$solo) {
            return null;
        }
        if (!str_starts_with($solo, '57')) {
            $solo = '57' . $solo;
        }

        return $solo;
    }

    /**
     * ¿Se le puede escribir publicidad? Modelo opt-out: solo dice que NO si la persona pidió
     * la baja. Nunca haber sido contactada no la excluye.
     *
     * Consulta TAMBIÉN `marketing_bloqueados`, que es donde ya caen el botón "No me interesa"
     * de las plantillas y la herramienta `no_contactar` del Asistente IA. Son dos puertas de
     * entrada al mismo "no" del cliente: si esta función ignorara una, la baja que el cliente
     * pidió por ahí no serviría de nada.
     */
    public static function puedeContactar(int $aliadoId, ?string $telefono, string $canal = self::CANAL_WHATSAPP): bool
    {
        $tel = self::normalizarTelefono($telefono);
        if (!$tel) {
            return false;
        }

        if (MarketingBloqueado::estaBloqueado($aliadoId, $tel)) {
            return false;
        }

        return !self::estaRevocado($aliadoId, $tel, $canal);
    }

    /** ¿Pidió expresamente que no le escribieran (y no ha vuelto a autorizar después)? */
    public static function estaRevocado(int $aliadoId, ?string $telefono, string $canal = self::CANAL_WHATSAPP): bool
    {
        $tel = self::normalizarTelefono($telefono);
        if (!$tel) {
            return false;
        }

        $ultimo = self::where('aliado_id', $aliadoId)
            ->where('telefono', $tel)
            ->where('canal', $canal)
            ->orderByDesc('fecha_evento')
            ->orderByDesc('id')          // desempate si dos eventos caen en el mismo segundo
            ->value('otorgado');

        // null = nunca hubo evento → no está revocado.
        return $ultimo !== null && !$ultimo;
    }

    /** Opt-in estricto: solo true si autorizó expresamente. Para campañas a desconocidos. */
    public static function tieneAutorizacionExpresa(int $aliadoId, ?string $telefono, string $canal = self::CANAL_WHATSAPP): bool
    {
        $tel = self::normalizarTelefono($telefono);
        if (!$tel) {
            return false;
        }

        return (bool) self::where('aliado_id', $aliadoId)
            ->where('telefono', $tel)
            ->where('canal', $canal)
            ->orderByDesc('fecha_evento')
            ->orderByDesc('id')
            ->value('otorgado');
    }

    /**
     * Quita del lote a quienes pidieron la baja. Resuelve la lista completa en una consulta:
     * con ~250ms de latencia al SQL Server, preguntar uno por uno haría inviable un envío de
     * 3.000 destinatarios.
     *
     * @param  array<string> $telefonos
     * @return array{contactables: array<string>, excluidos: array<string>} Normalizados.
     */
    public static function filtrarContactables(int $aliadoId, array $telefonos, string $canal = self::CANAL_WHATSAPP): array
    {
        $normalizados = array_values(array_unique(array_filter(
            array_map([self::class, 'normalizarTelefono'], $telefonos)
        )));

        if (!$normalizados) {
            return ['contactables' => [], 'excluidos' => []];
        }

        // SQL Server no acepta más de 2.100 parámetros por consulta, y un segmento de
        // ex-clientes pasa de 2.500 teléfonos: el whereIn hay que partirlo o revienta.
        $lotes = array_chunk($normalizados, 1000);

        $revocados  = [];
        $bloqueados = [];

        foreach ($lotes as $lote) {
            // Última fila por teléfono; solo interesan las que terminan en baja.
            $revocados = array_merge($revocados, self::where('aliado_id', $aliadoId)
                ->where('canal', $canal)
                ->whereIn('telefono', $lote)
                ->orderBy('telefono')
                ->orderByDesc('fecha_evento')
                ->orderByDesc('id')
                ->get(['telefono', 'otorgado'])
                ->groupBy('telefono')
                ->filter(fn ($filas) => !$filas->first()->otorgado)
                ->keys()
                ->all());

            // Mismo lote contra la lista del botón "No me interesa" / Asistente IA.
            $bloqueados = array_merge($bloqueados, MarketingBloqueado::where('aliado_id', $aliadoId)
                ->whereIn('celular', $lote)
                ->pluck('celular')
                ->all());
        }

        $excluidos = array_values(array_unique(array_merge($revocados, $bloqueados)));

        return [
            'contactables' => array_values(array_diff($normalizados, $excluidos)),
            'excluidos'    => $excluidos,
        ];
    }

    public static function otorgar(
        int $aliadoId,
        ?string $telefono,
        string $origen,
        ?string $textoMostrado = null,
        array $evidencia = [],
        string $canal = self::CANAL_WHATSAPP,
        ?string $cedula = null,
        ?string $nombre = null,
        ?string $email = null
    ): self {
        return self::create([
            'aliado_id'      => $aliadoId,
            'cedula'         => $cedula,
            'telefono'       => self::normalizarTelefono($telefono),
            'email'          => $email,
            'nombre'         => $nombre,
            'canal'          => $canal,
            'otorgado'       => true,
            'texto_mostrado' => $textoMostrado,
            'origen'         => $origen,
            'evidencia'      => $evidencia ?: null,
            'fecha_evento'   => now(),
        ]);
    }

    /** Baja / STOP. Se registra igual que el otorgamiento, con `otorgado = false`. */
    public static function revocar(
        int $aliadoId,
        ?string $telefono,
        string $origen = 'mensaje_baja',
        array $evidencia = [],
        string $canal = self::CANAL_WHATSAPP
    ): self {
        return self::create([
            'aliado_id'    => $aliadoId,
            'telefono'     => self::normalizarTelefono($telefono),
            'canal'        => $canal,
            'otorgado'     => false,
            'origen'       => $origen,
            'evidencia'    => $evidencia ?: null,
            'fecha_evento' => now(),
        ]);
    }

    public function scopeDelAliado(Builder $q, int $aliadoId): Builder
    {
        return $q->where('aliado_id', $aliadoId);
    }
}
