@extends('layouts.app')
@section('titulo', 'WhatsApp')
@section('modulo', 'Importar Plantillas WhatsApp')

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
.preview-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:.4rem .6rem; max-width:320px; word-break:break-word; white-space:pre-wrap; font-family:inherit; font-size:.78rem; line-height:1.4; color:#334155; }
.disabled-row { opacity:0.65; background:#f8fafc; }
</style>
@endpush

@section('contenido')
<div class="contenido">
    <div class="page-card">
        <form method="POST" action="{{ route('admin.whatsapp.plantillas.importar.store') }}">
            @csrf
            <div class="page-header">
                <div>
                    <div class="page-title">📥 Importar Plantillas desde Meta</div>
                    <small style="color:#64748b">Selecciona las plantillas de Meta que deseas guardar y utilizar en el sistema.</small>
                </div>
                <div style="display:flex;gap:.5rem">
                    <a href="{{ route('admin.whatsapp.plantillas.index') }}" class="btn btn-outline">Volver</a>
                    <button type="submit" class="btn btn-primary" id="btnImportar" disabled>📥 Guardar Seleccionadas</button>
                </div>
            </div>

            @if(empty($plantillasDisponibles))
                <div class="empty-state">
                    <div class="empty-icon">📥</div>
                    <p>No se encontraron plantillas en tu cuenta de Meta Cloud API o ya fueron todas importadas.</p>
                    <small>Verifica tu configuración o crea una plantilla directamente en Meta Business Suite.</small>
                </div>
            @else
                <table class="wa-table">
                    <thead>
                        <tr>
                            <th style="width:40px; text-align:center">
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                            </th>
                            <th>Plantilla (Meta)</th>
                            <th>Categoría</th>
                            <th>Vista Previa Cuerpo</th>
                            <th>Componentes Adicionales</th>
                            <th>Estado Meta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plantillasDisponibles as $plantilla)
                            <tr class="{{ $plantilla['ya_existe'] ? 'disabled-row' : '' }}">
                                <td style="text-align:center">
                                    @if($plantilla['ya_existe'])
                                        <span title="Ya importada">✅</span>
                                    @else
                                        <input type="checkbox" name="plantillas[]" value="{{ $plantilla['nombre'] }}" class="plantilla-checkbox" onclick="actualizarBotonImportar()">
                                    @endif
                                </td>
                                <td>
                                    <strong style="color:#0f172a">{{ $plantilla['nombre'] }}</strong><br>
                                    <span class="badge badge-secondary" style="font-size:.65rem; padding:.1rem .4rem; margin-top:.2rem">{{ $plantilla['idioma'] }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">{{ $plantilla['categoria'] }}</span>
                                </td>
                                <td>
                                    <div class="preview-box">{{ $plantilla['cuerpo'] }}</div>
                                </td>
                                <td>
                                    @if($plantilla['header_tipo'])
                                        <div><small><strong>Header:</strong> {{ $plantilla['header_tipo'] }}</small></div>
                                    @endif
                                    @if($plantilla['footer'])
                                        <div><small><strong>Footer:</strong> {{ $plantilla['footer'] }}</small></div>
                                    @endif
                                    @if(!empty($plantilla['botones']))
                                        <div style="margin-top:.25rem">
                                            @foreach($plantilla['botones'] as $btn)
                                                <span class="badge badge-secondary" style="font-size:.68rem" title="{{ $btn['tipo'] }}">{{ $btn['texto'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if(!$plantilla['header_tipo'] && !$plantilla['footer'] && empty($plantilla['botones']))
                                        <span style="color:#cbd5e1">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $color = match($plantilla['estado']) {
                                            'approved' => 'success',
                                            'pending'  => 'warning',
                                            'rejected' => 'danger',
                                            default    => 'secondary',
                                        };
                                        $label = match($plantilla['estado']) {
                                            'approved' => 'Aprobada',
                                            'pending'  => 'Pendiente',
                                            'rejected' => 'Rechazada',
                                            default    => $plantilla['estado'],
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $color }}">{{ $label }}</span>
                                    @if($plantilla['ya_existe'])
                                        <div style="margin-top:.25rem">
                                            <span class="badge badge-success" style="font-size:.65rem">Ya importada</span>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.plantilla-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
    });
    actualizarBotonImportar();
}

function actualizarBotonImportar() {
    const checkboxes = document.querySelectorAll('.plantilla-checkbox');
    const btn = document.getElementById('btnImportar');
    const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    
    btn.disabled = checkedCount === 0;
    
    // Sincronizar selectAll master
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
    }
}
</script>
@endpush
