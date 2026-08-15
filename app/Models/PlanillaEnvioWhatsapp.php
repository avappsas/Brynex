<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanillaEnvioWhatsapp extends BaseModel
{
    protected $table = 'planilla_envios_whatsapp';

    protected $fillable = [
        'aliado_id',
        'usuario_id',
        'plantilla_id',
        'mes',
        'anio',
        'tipo_envio',
        'total_destinatarios',
        'total_enviados',
        'total_fallidos',
        'total_omitidos',
        'estado',
    ];

    protected $casts = [
        'mes'                 => 'integer',
        'anio'                => 'integer',
        'total_destinatarios' => 'integer',
        'total_enviados'      => 'integer',
        'total_fallidos'      => 'integer',
        'total_omitidos'      => 'integer',
    ];

    // ── Relaciones ──────────────────────────────────────────────────

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // withTrashed: el histórico del envío conserva el nombre de la plantilla
    // aunque esta se haya retirado del catálogo después.
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(WhatsappPlantilla::class, 'plantilla_id')->withTrashed();
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PlanillaEnvioWhatsappDetalle::class, 'envio_id');
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function estaCompletado(): bool
    {
        return $this->estado === 'completado';
    }

    public function estaProcesando(): bool
    {
        return $this->estado === 'procesando';
    }

    public function porcentajeExito(): int
    {
        if ($this->total_destinatarios === 0) return 0;
        return (int) round(($this->total_enviados / $this->total_destinatarios) * 100);
    }

    public function etiquetaEstado(): string
    {
        return match($this->estado) {
            'pendiente'   => '⏳ Pendiente',
            'procesando'  => '🔄 Procesando...',
            'completado'  => '✅ Completado',
            'fallido'     => '❌ Fallido',
            default       => $this->estado,
        };
    }

    public function nombreMes(): string
    {
        $meses = [
            1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril',
            5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto',
            9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre',
        ];
        return ($meses[$this->mes] ?? '—') . ' ' . $this->anio;
    }
}
