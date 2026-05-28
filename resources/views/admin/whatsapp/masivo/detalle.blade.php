@extends('layouts.app')
@section('titulo', 'WhatsApp')
@section('modulo', 'Detalle de Envío Masivo')

@push('styles')
<style>
.page-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.5rem; margin-bottom:1rem; }
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.25rem; }
.stat-card { background:#f8fafc; border-radius:10px; padding:1rem; text-align:center; }
.stat-value { font-size:1.8rem; font-weight:800; color:#0f172a; line-height:1; }
.stat-label { font-size:.72rem; color:#94a3b8; margin-top:.25rem; font-weight:500; }
.stat-card.success { background:#d1fae5; } .stat-card.success .stat-value { color:#065f46; }
.stat-card.danger  { background:#fee2e2; } .stat-card.danger  .stat-value { color:#991b1b; }
.stat-card.warning { background:#fef3c7; } .stat-card.warning .stat-value { color:#92400e; }
.wa-table { width:100%; border-collapse:collapse; }
.wa-table th, .wa-table td { padding:.55rem .85rem; border-bottom:1px solid #f1f5f9; font-size:.8rem; }
.wa-table th { background:#f8fafc; font-weight:600; color:#475569; text-align:left; }
.badge { display:inline-flex; align-items:center; gap:.25rem; padding:.18rem .5rem; border-radius:999px; font-size:.69rem; font-weight:600; }
.badge-success { background:#d1fae5; color:#065f46; }
.badge-danger { background:#fee2e2; color:#991b1b; }
.badge-warning { background:#fef3c7; color:#92400e; }
.badge-secondary { background:#f1f5f9; color:#475569; }
.btn-sm { padding:.3rem .7rem; border-radius:7px; font-size:.75rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:.3rem; transition:opacity .15s; }
.btn-outline { background:transparent; border:1px solid #cbd5e1; color:#475569; }
</style>
@endpush

@section('contenido')
<div class="contenido">
    <div style="margin-bottom:1rem">
        <a href="{{ route('admin.whatsapp.masivo.historial') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">← Volver al historial</a>
    </div>

    <div class="page-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem">
            <div>
                <h2 style="font-size:1rem;font-weight:700;color:#0f172a">
                    📊 Envío #{{ $envio->id }} — {{ $envio->plantilla?->nombre_display }}
                </h2>
                <small style="color:#64748b">
                    {{ $envio->nombreMes() }} · {{ $envio->tipo_envio === 'empresa' ? '🏢 Empresas' : '👤 Individuales' }} ·
                    Lanzado por <strong>{{ $envio->usuario?->nombre }}</strong> el {{ $envio->created_at->format('d/m/Y H:i') }}
                </small>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">{{ $envio->total_destinatarios }}</div>
                <div class="stat-label">Total destinatarios</div>
            </div>
            <div class="stat-card success">
                <div class="stat-value">{{ $envio->total_enviados }}</div>
                <div class="stat-label">✅ Enviados</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-value">{{ $envio->total_fallidos }}</div>
                <div class="stat-label">❌ Fallidos</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-value">{{ $envio->total_omitidos }}</div>
                <div class="stat-label">⏭ Omitidos</div>
            </div>
        </div>
    </div>

    <div class="page-card">
        <h3 style="font-size:.9rem;font-weight:700;color:#0f172a;margin-bottom:1rem">Detalle por destinatario</h3>

        <table class="wa-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Número WA</th>
                    <th>Estado</th>
                    <th>Message ID (Meta)</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                @foreach($envio->detalles as $det)
                <tr>
                    <td>{{ $det->nombre_destinatario }}</td>
                    <td style="font-family:monospace;font-size:.75rem">{{ $det->wa_numero }}</td>
                    <td>
                        <span class="badge badge-{{ match($det->estado) {
                            'enviado'  => 'success',
                            'fallido'  => 'danger',
                            'omitido'  => 'warning',
                            default    => 'secondary',
                        } }}">{{ ucfirst($det->estado) }}</span>
                    </td>
                    <td style="font-family:monospace;font-size:.7rem;color:#94a3b8">{{ $det->wa_message_id ?: '—' }}</td>
                    <td style="color:#ef4444;font-size:.74rem">{{ $det->error ?: '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
