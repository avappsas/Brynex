@extends('layouts.app')
@section('titulo', 'WhatsApp')
@section('modulo', 'Plantillas WhatsApp')

@push('styles')
<style>
.page-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.5rem; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:.75rem; }
.page-title { font-size:1.05rem; font-weight:700; color:#0f172a; }
.btn { padding:.45rem 1rem; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:opacity .15s; }
.btn:hover { opacity:.87; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-success { background:#10b981; color:#fff; }
.btn-danger  { background:#ef4444; color:#fff; }
.btn-outline { background:transparent; border:1px solid #cbd5e1; color:#475569; }
.btn-sm { padding:.3rem .7rem; font-size:.76rem; }
.wa-table { width:100%; border-collapse:collapse; }
.wa-table th, .wa-table td { padding:.6rem .9rem; border-bottom:1px solid #f1f5f9; font-size:.82rem; }
.wa-table th { background:#f8fafc; font-weight:600; color:#475569; text-align:left; }
.wa-table tr:hover td { background:#fafafa; }
.badge { display:inline-flex; align-items:center; gap:.3rem; padding:.18rem .55rem; border-radius:999px; font-size:.71rem; font-weight:600; }
.badge-success { background:#d1fae5; color:#065f46; }
.badge-warning { background:#fef3c7; color:#92400e; }
.badge-danger  { background:#fee2e2; color:#991b1b; }
.badge-secondary { background:#f1f5f9; color:#475569; }
.empty-state { text-align:center; padding:3rem 1rem; color:#94a3b8; }
.empty-state .empty-icon { font-size:3rem; margin-bottom:.75rem; }
</style>
@endpush

@section('contenido')
<div class="contenido">
    @if(session('ok'))
        <div class="flash success">✅ {{ session('ok') }}</div>
    @endif
    @if(session('warning'))
        <div class="flash warning">⚠️ {{ session('warning') }}</div>
    @endif

    <div class="page-card">
        <div class="page-header">
            <div>
                <div class="page-title">📋 Plantillas de WhatsApp</div>
                <small style="color:#64748b">Gestiona las plantillas aprobadas por Meta para enviar mensajes.</small>
            </div>
            <div style="display:flex;gap:.5rem">
                <a href="{{ route('admin.whatsapp.plantillas.importar') }}" class="btn btn-outline">📥 Importar desde Meta</a>
                <form method="POST" action="{{ route('admin.whatsapp.plantillas.sincronizar') }}" style="display:inline"
                      onsubmit="return confirm('¿Sincronizar estado de plantillas con Meta?')">
                    @csrf
                    <button type="submit" class="btn btn-success">🔄 Sincronizar con Meta</button>
                </form>
                <a href="{{ route('admin.whatsapp.plantillas.create') }}" class="btn btn-primary">+ Nueva plantilla</a>
            </div>
        </div>

        @if($plantillas->isEmpty())
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>No hay plantillas configuradas aún.</p>
                <a href="{{ route('admin.whatsapp.plantillas.create') }}" class="btn btn-primary" style="margin-top:.75rem">Crear primera plantilla</a>
            </div>
        @else
            <table class="wa-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Nombre en Meta</th>
                        <th>Categoría</th>
                        <th>Estado</th>
                        <th>Botones</th>
                        <th>Creada en Meta</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($plantillas as $plantilla)
                    <tr>
                        <td>
                            <strong>{{ $plantilla->nombre_display }}</strong><br>
                            <small style="color:#94a3b8;font-size:.72rem">{{ $plantilla->idioma }}</small>
                        </td>
                        <td style="font-family:monospace;font-size:.75rem;color:#475569">{{ $plantilla->nombre }}</td>
                        <td><span class="badge badge-secondary">{{ $plantilla->categoria }}</span></td>
                        <td>
                            <span class="badge badge-{{ $plantilla->colorEstado() }}">
                                {{ $plantilla->etiquetaEstado() }}
                            </span>
                        </td>
                        <td style="text-align:center">
                            @if($plantilla->tieneBotones())
                                <span class="badge badge-secondary">{{ count($plantilla->botones) }} btn</span>
                            @else
                                <span style="color:#cbd5e1">—</span>
                            @endif
                        </td>
                        <td style="text-align:center">
                            {{ $plantilla->creado_en_meta ? '✅' : '⬜' }}
                        </td>
                        <td style="display:flex;gap:.4rem">
                            <a href="{{ route('admin.whatsapp.plantillas.edit', $plantilla->id) }}" class="btn btn-outline btn-sm">✏️ Editar</a>
                            <button class="btn btn-danger btn-sm" onclick="eliminarPlantilla({{ $plantilla->id }})">🗑</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- Formulario oculto para eliminar --}}
<form id="formEliminar" method="POST" style="display:none">
    @csrf @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
function eliminarPlantilla(id) {
    if (!confirm('¿Eliminar esta plantilla? No se podrá usar para enviar mensajes.')) return;
    const form = document.getElementById('formEliminar');
    form.action = `/admin/whatsapp/plantillas/${id}`;
    form.submit();
}
</script>
@endpush
