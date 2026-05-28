@extends('layouts.app')
@section('titulo', 'WhatsApp')
@section('modulo', 'Historial de Envíos Masivos')

@push('styles')
<style>
.page-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.5rem; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:.75rem; }
.page-title { font-size:1.05rem; font-weight:700; color:#0f172a; }
.wa-table { width:100%; border-collapse:collapse; }
.wa-table th, .wa-table td { padding:.6rem .85rem; border-bottom:1px solid #f1f5f9; font-size:.81rem; }
.wa-table th { background:#f8fafc; font-weight:600; color:#475569; text-align:left; }
.wa-table tr:hover td { background:#fafafa; }
.badge { display:inline-flex; align-items:center; gap:.25rem; padding:.18rem .55rem; border-radius:999px; font-size:.7rem; font-weight:600; }
.badge-success { background:#d1fae5; color:#065f46; }
.badge-warning { background:#fef3c7; color:#92400e; }
.badge-danger  { background:#fee2e2; color:#991b1b; }
.badge-secondary { background:#f1f5f9; color:#475569; }
.badge-info { background:#e0f2fe; color:#0369a1; }
.progress-bar { height:6px; border-radius:999px; background:#e2e8f0; overflow:hidden; width:80px; }
.progress-fill { height:100%; border-radius:999px; background:#10b981; }
.btn-sm { padding:.28rem .65rem; border-radius:7px; font-size:.75rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:.3rem; transition:opacity .15s; }
.btn-sm:hover { opacity:.87; }
.btn-outline { background:transparent; border:1px solid #cbd5e1; color:#475569; }
</style>
@endpush

@section('content')
<div class="contenido">
    <div class="page-card">
        <div class="page-header">
            <div>
                <div class="page-title">📊 Historial de Envíos Masivos WhatsApp</div>
                <small style="color:#64748b">Registro de todos los envíos masivos realizados.</small>
            </div>
        </div>

        @if($envios->isEmpty())
            <div style="text-align:center;padding:3rem 1rem;color:#94a3b8">
                <div style="font-size:3rem;margin-bottom:.75rem">📊</div>
                <p>No hay envíos masivos registrados aún.</p>
            </div>
        @else
            <table class="wa-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Plantilla</th>
                        <th>Período</th>
                        <th>Tipo</th>
                        <th>Progreso</th>
                        <th>Estado</th>
                        <th>Usuario</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($envios as $envio)
                    <tr>
                        <td>{{ $envio->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <strong>{{ $envio->plantilla?->nombre_display ?? '—' }}</strong>
                        </td>
                        <td>{{ $envio->nombreMes() }}</td>
                        <td>
                            <span class="badge badge-secondary">
                                {{ $envio->tipo_envio === 'empresa' ? '🏢 Empresa' : '👤 Individual' }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width:{{ $envio->porcentajeExito() }}%"></div>
                                </div>
                                <span style="font-size:.72rem;color:#475569">
                                    {{ $envio->total_enviados }}/{{ $envio->total_destinatarios }}
                                </span>
                            </div>
                            @if($envio->total_fallidos > 0)
                                <div style="font-size:.68rem;color:#ef4444;margin-top:.2rem">
                                    {{ $envio->total_fallidos }} fallidos
                                </div>
                            @endif
                        </td>
                        <td>
                            @php
                                $badgeClass = match($envio->estado) {
                                    'completado' => 'badge-success',
                                    'procesando' => 'badge-info',
                                    'pendiente'  => 'badge-warning',
                                    default      => 'badge-danger',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $envio->etiquetaEstado() }}</span>
                        </td>
                        <td style="font-size:.75rem;color:#475569">{{ $envio->usuario?->nombre ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.whatsapp.masivo.detalle', $envio->id) }}" class="btn-sm btn-outline">Ver detalle</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top:1rem">{{ $envios->links() }}</div>
        @endif
    </div>
</div>
@endsection
