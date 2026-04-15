@extends('layouts.app')
@section('modulo', 'Auditoría / Bitácora')

@section('contenido')
<div style="max-width:1200px;margin:0 auto;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
        <div>
            <h1 style="margin:0;font-size:1.4rem;color:#0f172a;font-weight:700;display:flex;align-items:center;gap:0.5rem;">
                <span style="background:rgba(59,130,246,0.1);color:#2563eb;padding:0.4rem;border-radius:10px;">👁️</span> 
                Bitácora de Auditoría
            </h1>
            <p style="margin:0.2rem 0 0 0;color:#64748b;font-size:0.85rem;">Historial de todas las acciones importantes (CRUD) en el sistema del aliado activo.</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div style="background:#fff;padding:1rem 1.25rem;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,0.05);margin-bottom:1.5rem;border:1px solid #e2e8f0;">
        <form method="GET" action="{{ route('admin.bitacora.index') }}" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto auto;gap:1rem;align-items:flex-end;">
            
            <div>
                <label style="display:block;font-size:0.7rem;font-weight:600;color:#475569;margin-bottom:0.2rem;">MÓDULO / MODELO</label>
                <select name="modelo" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    <option value="Cliente" {{ request('modelo')=='Cliente' ? 'selected' : '' }}>Cliente</option>
                    <option value="Beneficiario" {{ request('modelo')=='Beneficiario' ? 'selected' : '' }}>Beneficiario</option>
                    <option value="DocumentoCliente" {{ request('modelo')=='DocumentoCliente' ? 'selected' : '' }}>Documentos</option>
                </select>
            </div>
            
            <div>
                <label style="display:block;font-size:0.7rem;font-weight:600;color:#475569;margin-bottom:0.2rem;">ACCIÓN</label>
                <select name="accion" class="form-control form-control-sm">
                    <option value="">Todas</option>
                    <option value="created" {{ request('accion')=='created' ? 'selected' : '' }}>Creación (created)</option>
                    <option value="updated" {{ request('accion')=='updated' ? 'selected' : '' }}>Actualización (updated)</option>
                    <option value="deleted" {{ request('accion')=='deleted' ? 'selected' : '' }}>Eliminación (deleted)</option>
                </select>
            </div>
            
            <div>
                <label style="display:block;font-size:0.7rem;font-weight:600;color:#475569;margin-bottom:0.2rem;">DESDE</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
            </div>

            <div>
                <label style="display:block;font-size:0.7rem;font-weight:600;color:#475569;margin-bottom:0.2rem;">HASTA</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
            </div>

            <div>
                <button type="submit" class="btn btn-sm btn-primary">🔍 Filtrar</button>
            </div>
            <div>
                <a href="{{ route('admin.bitacora.index') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>

    {{-- Tabla --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,0.05);border:1px solid #e2e8f0;overflow:hidden;">
        <table class="table table-hover mb-0" style="font-size:0.85rem;border-collapse:collapse;width:100%;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:0.75rem 1rem;color:#475569;font-weight:600;">Fecha</th>
                    <th style="padding:0.75rem 1rem;color:#475569;font-weight:600;">Usuario</th>
                    <th style="padding:0.75rem 1rem;color:#475569;font-weight:600;">Acción</th>
                    <th style="padding:0.75rem 1rem;color:#475569;font-weight:600;">Módulo (ID)</th>
                    <th style="padding:0.75rem 1rem;color:#475569;font-weight:600;width:35%;">Descripción / IP</th>
                    <th style="padding:0.75rem 1rem;color:#475569;font-weight:600;text-align:center;">Detalles</th>
                </tr>
            </thead>
            <tbody>
                @forelse($registros as $reg)
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:0.75rem 1rem;">
                        <span style="font-weight:600;color:#0f172a;">{{ $reg->created_at->format('d/m/Y') }}</span><br>
                        <small style="color:#64748b;">{{ $reg->created_at->format('H:i:s') }}</small>
                    </td>
                    <td style="padding:0.75rem 1rem;font-weight:500;">
                        {{ $reg->user->nombre ?? 'Sistema' }}
                    </td>
                    <td style="padding:0.75rem 1rem;">
                        @php
                            $badge = match($reg->accion) {
                                'created' => ['bg'=>'#dcfce7','color'=>'#16a34a','txt'=>'Creación'],
                                'updated' => ['bg'=>'#fef3c7','color'=>'#d97706','txt'=>'Actualización'],
                                'deleted' => ['bg'=>'#fee2e2','color'=>'#dc2626','txt'=>'Eliminación'],
                                default   => ['bg'=>'#f1f5f9','color'=>'#475569','txt'=>$reg->accion]
                            };
                        @endphp
                        <span style="background:{{ $badge['bg'] }};color:{{ $badge['color'] }};padding:0.2rem 0.6rem;border-radius:99px;font-size:0.7rem;font-weight:700;">
                            {{ mb_strtoupper($badge['txt']) }}
                        </span>
                    </td>
                    <td style="padding:0.75rem 1rem;">
                        <span style="font-weight:600;color:#334155;">{{ $reg->modelo }}</span><br>
                        <small style="color:#94a3b8;">ID: {{ $reg->registro_id ?? 'N/A' }}</small>
                    </td>
                    <td style="padding:0.75rem 1rem;">
                        <div style="color:#1e293b;">{{ $reg->descripcion }}</div>
                        <small style="color:#94a3b8;font-family:monospace;">IP: {{ $reg->ip }}</small>
                    </td>
                    <td style="padding:0.75rem 1rem;text-align:center;">
                        @if($reg->detalle)
                            <button type="button" class="btn btn-xs btn-outline-info" onclick='verDetalle(@json($reg->detalle_array))'>🔍 Ver JSON</button>
                        @else
                            <span class="text-muted" style="font-size:0.7rem;">Sin det.</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="padding:2rem;text-align:center;color:#64748b;">
                        📭 No se encontraron registros de auditoría que coincidan con los filtros.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div style="margin-top:1rem;">
        {{ $registros->links('pagination::bootstrap-5') }}
    </div>

</div>

<!-- Modal Detalle JSON -->
<div id="modalDetalle" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;width:90%;max-width:700px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.2);overflow:hidden;">
        <div style="background:#f8fafc;padding:1rem 1.5rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <h4 style="margin:0;font-size:1.1rem;font-weight:700;color:#0f172a;">Detalle (JSON) de Cambios</h4>
            <button type="button" onclick="document.getElementById('modalDetalle').style.display='none'" style="background:transparent;border:none;font-size:1.2rem;cursor:pointer;color:#64748b;">✖</button>
        </div>
        <div style="padding:0;">
            <pre id="jsonContent" style="margin:0;padding:1.5rem;max-height:450px;overflow:auto;background:#0f172a;color:#10b981;font-size:0.85rem;font-family:monospace;white-space:pre-wrap;word-break:break-all;"></pre>
        </div>
    </div>
</div>

<script>
function verDetalle(jsonObj) {
    document.getElementById('jsonContent').textContent = JSON.stringify(jsonObj, null, 4);
    document.getElementById('modalDetalle').style.display = 'flex';
}
</script>
@endsection
