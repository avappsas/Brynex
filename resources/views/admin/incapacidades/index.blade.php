@extends('layouts.app')
@section('titulo','Incapacidades')
@section('modulo','Módulo de Incapacidades')

@push('styles')
<style>
:root{--v:#10b981;--a:#f59e0b;--r:#ef4444;--g:#6b7280;}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:.6rem;}
.page-header h1{font-size:1.35rem;font-weight:700;color:#1e293b;}
.kpi-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin-bottom:1.2rem;}
.kpi{background:#fff;border-radius:12px;padding:.9rem 1.1rem;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.kpi .num{font-size:1.7rem;font-weight:700;line-height:1;}
.kpi .lbl{font-size:.72rem;color:#64748b;margin-top:.25rem;}
.kpi.warn .num{color:#d97706;} .kpi.danger .num{color:#dc2626;} .kpi.ok .num{color:#059669;}
.filter-bar{background:#fff;border-radius:12px;padding:.85rem 1rem;margin-bottom:1rem;border:1px solid #e2e8f0;display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;}
.filter-bar select,.filter-bar input{border:1px solid #cbd5e1;border-radius:8px;padding:.38rem .65rem;font-size:.8rem;background:#f8fafc;}
.filter-bar label{font-size:.72rem;color:#64748b;display:block;margin-bottom:.2rem;}
.btn{display:inline-flex;align-items:center;gap:.35rem;padding:.42rem .9rem;border-radius:8px;font-size:.8rem;font-weight:600;border:none;cursor:pointer;text-decoration:none;transition:all .15s;}
.btn-primary{background:#2563eb;color:#fff;} .btn-primary:hover{background:#1d4ed8;}
.btn-sm{padding:.3rem .65rem;font-size:.75rem;}
.btn-success{background:#059669;color:#fff;} .btn-success:hover{background:#047857;}
.btn-warning{background:#d97706;color:#fff;}
.btn-danger{background:#dc2626;color:#fff;}
.btn-secondary{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;}
.btn-info{background:#0891b2;color:#fff;}
.card{background:#fff;border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,.07);border:1px solid #e2e8f0;overflow:hidden;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.82rem;}
thead th{background:#f8fafc;color:#475569;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:.65rem .85rem;border-bottom:2px solid #e2e8f0;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .1s;}
tbody tr:hover{background:#f8fafc;}
tbody td{padding:.6rem .85rem;vertical-align:middle;}
.semaforo{display:inline-flex;align-items:center;gap:.3rem;font-size:.78rem;font-weight:600;padding:.25rem .6rem;border-radius:999px;}
.sem-verde{background:rgba(16,185,129,.12);color:#059669;}
.sem-amarillo{background:rgba(245,158,11,.12);color:#d97706;}
.sem-rojo{background:rgba(239,68,68,.12);color:#dc2626;}
.sem-gris{background:rgba(107,114,128,.12);color:#6b7280;}
.badge{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.7rem;font-weight:600;}
.badge-warning{background:#fef3c7;color:#92400e;}
.badge-success{background:#d1fae5;color:#065f46;}
.badge-info{background:#dbeafe;color:#1e40af;}
.badge-danger{background:#fee2e2;color:#991b1b;}
.badge-secondary{background:#f1f5f9;color:#475569;}
.badge-primary{background:#eff6ff;color:#1d4ed8;}
.alerta-180{background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.3rem .6rem;font-size:.72rem;color:#991b1b;font-weight:600;}

/* ── Modal profesional ──────────────────────────────────────── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);z-index:1000;align-items:flex-start;justify-content:center;padding:1.5rem;overflow-y:auto;}
.modal-overlay.open{display:flex;animation:fadeInOverlay .18s ease;}
@keyframes fadeInOverlay{from{opacity:0}to{opacity:1}}
.modal{background:#fff;border-radius:20px;width:100%;max-width:820px;box-shadow:0 32px 80px rgba(15,23,42,.22),0 0 0 1px rgba(0,0,0,.06);margin:auto;animation:slideUpModal .22s cubic-bezier(.16,1,.3,1);}
@keyframes slideUpModal{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid #f1f5f9;background:linear-gradient(135deg,#1e40af 0%,#2563eb 60%,#0891b2 100%);border-radius:20px 20px 0 0;}
.modal-header h3{font-size:1.05rem;font-weight:700;color:#fff;}
.modal-header .modal-subtitle{font-size:.75rem;color:rgba(255,255,255,.75);margin-top:.2rem;}
.modal-body{padding:1.35rem;}
.modal-footer{padding:.9rem 1.35rem;border-top:1px solid #f1f5f9;display:flex;gap:.6rem;justify-content:flex-end;background:#f8fafc;border-radius:0 0 20px 20px;}
.btn-close-modal{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);font-size:1rem;cursor:pointer;color:#fff;padding:.25rem .55rem;border-radius:8px;line-height:1;transition:background .15s;}
.btn-close-modal:hover{background:rgba(255,255,255,.32);}

/* Header del modal detalle (color azul) */
#modalDetalle .modal-header{background:linear-gradient(135deg,#1e293b 0%,#334155 100%);}

.form-group{margin-bottom:.85rem;}
.form-group label{display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.3rem;}
.form-group input,.form-group select,.form-group textarea{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:.45rem .7rem;font-size:.83rem;font-family:inherit;transition:border-color .15s,box-shadow .15s;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);}
.form-group textarea{min-height:70px;resize:vertical;}
.form-control{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:.45rem .7rem;font-size:.83rem;font-family:inherit;transition:border-color .15s,box-shadow .15s;}
.form-control:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem;}
.section-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;border-bottom:1px solid #f1f5f9;padding-bottom:.4rem;margin-bottom:.85rem;margin-top:.6rem;}

/* Timeline gestiones */
.timeline{display:flex;flex-direction:column;gap:.6rem;max-height:300px;overflow-y:auto;padding-right:.25rem;}
.timeline-item{display:flex;gap:.75rem;align-items:flex-start;}
.tl-dot{width:34px;height:34px;border-radius:50%;background:#eff6ff;border:2px solid #bfdbfe;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
.tl-content{flex:1;background:#f8fafc;border-radius:10px;padding:.6rem .85rem;border:1px solid #e2e8f0;}
.tl-content .tl-tipo{font-size:.7rem;font-weight:700;color:#2563eb;text-transform:uppercase;letter-spacing:.04em;}
.tl-content .tl-tramite{font-size:.82rem;color:#374151;margin:.25rem 0;}
.tl-content .tl-meta{font-size:.68rem;color:#94a3b8;}

/* Tabs */
.tabs{display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:1.1rem;}
.tab-btn{padding:.55rem 1.1rem;font-size:.82rem;font-weight:600;background:none;border:none;cursor:pointer;color:#64748b;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;border-radius:6px 6px 0 0;}
.tab-btn:hover{color:#2563eb;background:#f8fafc;}
.tab-btn.active{color:#2563eb;border-bottom-color:#2563eb;background:#eff6ff;}
.tab-pane{display:none;} .tab-pane.active{display:block;}
.proroga-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:.85rem 1rem;margin-bottom:.6rem;}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:.6rem;}
.page-header h1{font-size:1.35rem;font-weight:700;color:#1e293b;}
.kpi-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin-bottom:1.2rem;}
.kpi{background:#fff;border-radius:12px;padding:.9rem 1.1rem;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.kpi .num{font-size:1.7rem;font-weight:700;line-height:1;}
.kpi .lbl{font-size:.72rem;color:#64748b;margin-top:.25rem;}
.kpi.warn .num{color:#d97706;} .kpi.danger .num{color:#dc2626;} .kpi.ok .num{color:#059669;}
.filter-bar{background:#fff;border-radius:12px;padding:.85rem 1rem;margin-bottom:1rem;border:1px solid #e2e8f0;display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;}
.filter-bar select,.filter-bar input{border:1px solid #cbd5e1;border-radius:8px;padding:.38rem .65rem;font-size:.8rem;background:#f8fafc;}
.filter-bar label{font-size:.72rem;color:#64748b;display:block;margin-bottom:.2rem;}
.btn{display:inline-flex;align-items:center;gap:.35rem;padding:.42rem .9rem;border-radius:8px;font-size:.8rem;font-weight:600;border:none;cursor:pointer;text-decoration:none;transition:all .15s;}
.btn-primary{background:#2563eb;color:#fff;} .btn-primary:hover{background:#1d4ed8;}
.btn-sm{padding:.3rem .65rem;font-size:.75rem;}
.btn-success{background:#059669;color:#fff;} .btn-success:hover{background:#047857;}
.btn-warning{background:#d97706;color:#fff;}
.btn-danger{background:#dc2626;color:#fff;}
.btn-secondary{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;}
.btn-info{background:#0891b2;color:#fff;}
.card{background:#fff;border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,.07);border:1px solid #e2e8f0;overflow:hidden;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.82rem;}
thead th{background:#f8fafc;color:#475569;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:.65rem .85rem;border-bottom:2px solid #e2e8f0;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .1s;}
tbody tr:hover{background:#f8fafc;}
tbody td{padding:.6rem .85rem;vertical-align:middle;}
.semaforo{display:inline-flex;align-items:center;gap:.3rem;font-size:.78rem;font-weight:600;padding:.25rem .6rem;border-radius:999px;}
.sem-verde{background:rgba(16,185,129,.12);color:#059669;}
.sem-amarillo{background:rgba(245,158,11,.12);color:#d97706;}
.sem-rojo{background:rgba(239,68,68,.12);color:#dc2626;}
.sem-gris{background:rgba(107,114,128,.12);color:#6b7280;}
.badge{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.7rem;font-weight:600;}
.badge-warning{background:#fef3c7;color:#92400e;}
.badge-success{background:#d1fae5;color:#065f46;}
.badge-info{background:#dbeafe;color:#1e40af;}
.badge-danger{background:#fee2e2;color:#991b1b;}
.badge-secondary{background:#f1f5f9;color:#475569;}
.badge-primary{background:#eff6ff;color:#1d4ed8;}
.alerta-180{background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.3rem .6rem;font-size:.72rem;color:#991b1b;font-weight:600;}

</style>
@endpush

@section('contenido')
<div class="page-header">
    <h1>🏥 Incapacidades</h1>
    <button class="btn btn-primary" onclick="abrirModalCrear()">➕ Nueva Incapacidad</button>
</div>

{{-- KPIs --}}
<div class="kpi-bar">
    <div class="kpi ok"><div class="num">{{ $totalActivas }}</div><div class="lbl">Activas</div></div>
    <div class="kpi danger"><div class="num">{{ $sinGestion7dias }}</div><div class="lbl">Sin gestión +7 días</div></div>
    <div class="kpi"><div class="num">{{ $resumen->get('recibido',0) }}</div><div class="lbl">Recibidas</div></div>
    <div class="kpi warn"><div class="num">{{ $resumen->get('radicada',0) }}</div><div class="lbl">Radicadas</div></div>
    <div class="kpi ok"><div class="num">{{ $resumen->get('pagada',0) }}</div><div class="lbl">Pagadas</div></div>
</div>

{{-- Filtros Alpine.js auto-submit --}}
<div class="filter-bar" x-data="filtrosInc()" x-init="init()">
    <form id="filtro-form" method="GET" style="display:contents">
        <div style="flex:1;min-width:220px">
            <label>🔍 Nombre o cédula</label>
            <input id="inp-busqueda" name="busqueda" value="{{ $busqueda }}"
                   placeholder="Buscar..." x-model="busqueda"
                   @input="debouncedSubmit()" autocomplete="off">
        </div>
        <div>
            <label>Entidad</label>
            <select name="tipo_entidad" @change="$el.form.submit()">
                <option value="">Todas</option>
                @foreach(\App\Models\Incapacidad::TIPOS_ENTIDAD as $k=>$v)
                <option value="{{ $k }}" @selected(request('tipo_entidad')==$k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Estado</label>
            <select name="estado" @change="$el.form.submit()">
                <option value="">Todos</option>
                @foreach(\App\Models\Incapacidad::ESTADOS as $k=>$cfg)
                <option value="{{ $k }}" @selected(request('estado')==$k)>{{ $cfg['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Vista</label>
            <select name="vista" @change="$el.form.submit()">
                <option value="agrupada" @selected($vista=='agrupada')>📁 Agrupada</option>
                <option value="plana"    @selected($vista=='plana')>📋 Plana</option>
            </select>
        </div>
        <div style="display:flex;align-items:flex-end">
            <label style="display:flex;align-items:center;gap:.3rem;font-size:.78rem;cursor:pointer">
                <input type="checkbox" name="con_cerradas" value="1"
                       @checked(request('con_cerradas')) @change="$el.form.submit()"> Ver pagadas
            </label>
        </div>
        <div style="display:flex;align-items:flex-end">
            <a href="{{ route('admin.incapacidades.index') }}" class="btn btn-secondary btn-sm">✕</a>
        </div>
    </form>
</div>

@if($busqueda)
<div style="background:#dbeafe;border:1px solid #93c5fd;border-radius:8px;padding:.5rem .9rem;font-size:.8rem;color:#1e40af;margin-bottom:.75rem">
    🔍 Mostrando <strong>todos</strong> los resultados para "<strong>{{ $busqueda }}</strong>"
    — <a href="{{ route('admin.incapacidades.index') }}" style="color:#1d4ed8;font-weight:600">Limpiar</a>
</div>
@endif

{{-- VISTA AGRUPADA --}}
@if($vista === 'agrupada')
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>🚦</th>
                <th>Cliente</th>
                <th>Entidad</th>
                <th>Estado</th>
                <th style="text-align:center">Días</th>
                <th style="text-align:right">Valor Esperado</th>
                <th style="text-align:center">Familia</th>
                <th>Última Gestión</th>
                <th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($incapacidades as $inc)
            @php
                $color       = $inc->_color_semaforo_cache;
                $diasGestion = $inc->_dias_gestion_cache;
                $totalDias   = $inc->_total_dias_familia_cache;
                $numPrr      = $inc->_num_prorrogas_cache;
                $alert180    = ($inc->tipo_entidad === 'eps') && ($totalDias >= 180);
                $icono       = match($color) { 'verde'=>'🟢','amarillo'=>'🟡','rojo'=>'🔴',default=>'⚫' };
                $estadoGrupo = $inc->estado_grupo;
                $estadoCfg   = \App\Models\Incapacidad::ESTADOS[$estadoGrupo] ?? ['label'=>$estadoGrupo,'color'=>'secondary'];
                $ult         = $inc->latestGestion;
            @endphp
            <tr style="{{ $estadoGrupo === 'pagada' ? 'opacity:.65' : '' }}">
                <td>
                    <span class="semaforo sem-{{ $color }}" title="{{ $diasGestion }} días sin gestión">
                        {{ $icono }}
                    </span>
                    @if($alert180)<br><span class="alerta-180" title="Más de 180 días en EPS">⚠️180d</span>@endif
                </td>
                <td>
                    <div style="font-weight:600;font-size:.83rem">{{ $inc->_nombre_cliente_cache ?? $inc->cedula_usuario }}</div>
                    <div style="font-size:.72rem;color:#64748b">{{ $inc->cedula_usuario }}</div>
                </td>
                <td>
                    <span class="badge badge-secondary">{{ strtoupper($inc->entidad_grupo) }}</span>
                    <div style="font-size:.7rem;color:#64748b;margin-top:.2rem">{{ Str::limit($inc->entidad_nombre,22) }}</div>
                </td>
                <td><span class="badge badge-{{ $estadoCfg['color'] }}">{{ $estadoCfg['label'] }}</span></td>
                <td style="text-align:center">
                    <strong>{{ $totalDias }}</strong><span style="color:#94a3b8;font-size:.7rem">d</span>
                    <div style="font-size:.68rem;color:#64748b">{{ $inc->fecha_inicio?->format('d/m/y') }}</div>
                </td>
                <td style="text-align:right">
                    @if($inc->valor_esperado)
                    <span style="font-weight:700;color:#059669;font-size:.83rem">${{ number_format($inc->valor_esperado,0,',','.') }}</span>
                    @else<span style="color:#94a3b8;font-size:.75rem">—</span>@endif
                </td>
                <td style="text-align:center">
                    @if($numPrr > 0)
                    <span class="badge badge-primary">+{{ $numPrr }} prórr.</span>
                    @else<span style="color:#94a3b8;font-size:.72rem">Original</span>@endif
                </td>
                <td>
                    @if($ult)
                    <div style="font-size:.72rem;font-weight:600;color:#2563eb">{{ $ult->tipoIcono() }} {{ $ult->tipoLabel() }}</div>
                    <div style="font-size:.68rem;color:#94a3b8">{{ $ult->created_at->format('d/m/y H:i') }}</div>
                    @else<span style="color:#ef4444;font-size:.72rem">Sin gestión</span>@endif
                </td>
                <td>
                    <div style="display:flex;gap:.3rem;flex-wrap:wrap">
                        <button class="btn btn-info btn-sm" onclick="verDetalle({{ $inc->id }})">👁 Ver</button>
                        <button class="btn btn-success btn-sm" onclick="abrirModalGestion({{ $inc->id }},true)"
                                title="Gestión de seguimiento">📞</button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" style="text-align:center;padding:2.5rem;color:#94a3b8">
                No hay incapacidades. @if($busqueda)<a href="{{ route('admin.incapacidades.index') }}">Limpiar búsqueda</a>@endif
            </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:.85rem 1.1rem;border-top:1px solid #f1f5f9">{{ $incapacidades->links() }}</div>
</div>

{{-- VISTA PLANA --}}
@else
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>🚦</th><th>Cliente</th><th>Tipo</th><th>Entidad</th>
                <th>Días</th><th>Estado</th><th>Valor</th><th>Última Gestión</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($incapacidades as $inc)
            @php
                $color       = $inc->_color_semaforo_cache;
                $diasGestion = $inc->_dias_gestion_cache;
                $totalDias   = $inc->_total_dias_familia_cache;
                $numPrr      = $inc->_num_prorrogas_cache;
                $icono       = match($color) { 'verde'=>'🟢','amarillo'=>'🟡','rojo'=>'🔴',default=>'⚫' };
                $estadoCfg   = \App\Models\Incapacidad::ESTADOS[$inc->estado] ?? ['label'=>$inc->estado,'color'=>'secondary'];
                $ult         = $inc->latestGestion;
            @endphp
            <tr>
                <td><span class="semaforo sem-{{ $color }}" title="{{ $diasGestion }} días sin gestión">{{ $icono }} {{ $diasGestion }} días</span></td>
                <td>
                    <div style="font-weight:600;font-size:.82rem">{{ $inc->_nombre_cliente_cache ?? $inc->cedula_usuario }}</div>
                    <div style="font-size:.72rem;color:#64748b">{{ $inc->cedula_usuario }}</div>
                </td>
                <td style="font-size:.78rem">{{ $inc->tipoIncapacidadLabel() }}<br>
                    @if($inc->incapacidad_padre_id)<span class="badge badge-info">Prórroga {{ $inc->numero_proroga }}</span>@endif
                </td>
                <td><span class="badge badge-secondary">{{ strtoupper($inc->tipo_entidad) }}</span>
                    <div style="font-size:.7rem;color:#64748b">{{ Str::limit($inc->entidad_nombre,20) }}</div>
                </td>
                <td style="text-align:center"><strong>{{ $inc->dias_incapacidad }}</strong>d<br>
                    @if($numPrr>0)<span style="font-size:.68rem;color:#2563eb">Total:{{ $totalDias }}d</span>@endif
                </td>
                <td><span class="badge badge-{{ $estadoCfg['color'] }}">{{ $estadoCfg['label'] }}</span></td>
                <td>@if($inc->valor_esperado)<span style="font-weight:700;color:#059669">${{ number_format($inc->valor_esperado,0,',','.') }}</span>@else—@endif</td>
                <td>@if($ult)<div style="font-size:.72rem;font-weight:600;color:#2563eb">{{ $ult->tipoLabel() }}</div>
                    <div style="font-size:.68rem;color:#94a3b8">{{ $ult->created_at->format('d/m/y') }}</div>
                    @else<span style="color:#ef4444;font-size:.72rem">Sin gestión</span>@endif
                </td>
                <td>
                    <div style="display:flex;gap:.3rem;flex-wrap:wrap">
                        <button class="btn btn-info btn-sm" onclick="verDetalle({{ $inc->id }})">👁 Ver</button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8">No hay incapacidades.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:.85rem 1.1rem;border-top:1px solid #f1f5f9">{{ $incapacidades->links() }}</div>
</div>
@endif


{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- MODALES (partials reutilizables)                           --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
@include('admin.incapacidades.partials._modal_crear', ['trabajadores' => $trabajadores, 'razonesSociales' => $razonesSociales])
@include('admin.incapacidades.partials._modal_detalle')

@endsection

@push('scripts')
<script>
const TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const EPS_LIST   = @json($epsList->map(fn($e)=>['id'=>$e->id,'nombre'=>$e->nombre]));
const ARL_LIST   = @json($arlList->map(fn($e)=>['id'=>$e->id,'nombre'=>$e->nombre_arl]));
const AFP_LIST   = @json($pensionList->map(fn($e)=>['id'=>$e->id,'nombre'=>$e->razon_social]));

// Cache para docs de familia
let _docsFamiliaLoaded = null;

// Mapas de labels para mostrar en el frontend
const TIPOS_INCAPACIDAD = @json(\App\Models\Incapacidad::TIPOS_INCAPACIDAD);
const TIPOS_ENTIDAD     = @json(\App\Models\Incapacidad::TIPOS_ENTIDAD);
const ESTADOS_INC       = @json(\App\Models\Incapacidad::ESTADOS);
const ESTADOS_PAGO_INC  = @json(\App\Models\Incapacidad::ESTADOS_PAGO);

function labelTipo(key){ return TIPOS_INCAPACIDAD[key] || key; }
function labelEstado(key){ const v = ESTADOS_INC[key]; return (v && v.label) ? v.label : (key || '—'); }
function labelEstadoPago(key){ const v = ESTADOS_PAGO_INC[key]; return (v && v.label) ? v.label : (key || '—'); }
function colorEstado(key){ const v = ESTADOS_INC[key]; return (v && v.color) ? v.color : 'secondary'; }
function colorEstadoPago(key){ const v = ESTADOS_PAGO_INC[key]; return (v && v.color) ? v.color : 'secondary'; }
function formatFecha(str){ return str ? str.substring(0,10) : '—'; }
function formatFechaLarga(str){
    if(!str) return '—';
    const meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    const d = new Date(str.substring(0,10)+'T12:00:00');
    return `${d.getDate()}-${meses[d.getMonth()]}-${d.getFullYear()}`;
}

// ── Modales ──────────────────────────────────────────────────────────────────
function cerrarModal(id){
    document.getElementById(id).classList.remove('open');
    if (id === 'modalDetalle') _docsFamiliaLoaded = null;
    if (id === 'modalCrear') { const p = document.getElementById('editarDocsPanel'); if(p) p.remove(); }
}
function abrirModal(id) { document.getElementById(id).classList.add('open'); }

function abrirModalCrear(){
    document.getElementById('modalCrearTitle').textContent = '➕ Nueva Incapacidad';
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('formId').value = '';
    document.getElementById('padreId').value = '';
    document.getElementById('razonSocialHidden').value = '';
    document.getElementById('formCrear').reset();
    // Restaurar valores que reset() borra
    const qrs = document.getElementById('quienRecibeSelect');
    if(qrs) qrs.value = qrs.dataset.authId || '';
    const fr = document.querySelector('[name=fecha_recibido]');
    if(fr) fr.value = new Date().toISOString().substring(0,10);
    // Reset info boxes
    const b1=document.getElementById('contratoInfoBox'); if(b1) b1.style.display='none';
    const b2=document.getElementById('contratoInfoBoxInactivo'); if(b2) b2.style.display='none';
    const qr=document.getElementById('quienRemiteSelect'); if(qr) qr.innerHTML='<option value="">Seleccionar...</option>';
    const cs=document.getElementById('contratoSelect'); if(cs) cs.innerHTML='<option value="">Sin contrato</option>';
    _contratosData=[]; _clienteEpsId=null; _clienteArlId=null; _clienteNombre=''; _empresaNombre='';
    abrirModal('modalCrear');
}


function abrirModalProroga(padreId){
    abrirModalCrear();
    document.getElementById('modalCrearTitle').textContent = '➕ Registrar Prórroga';
    document.getElementById('padreId').value = padreId;
}

function abrirModalEditar(id){
    fetch(`/admin/incapacidades/${id}/show`)
        .then(r=>r.json()).then(data=>{
            const inc = data.incapacidad;
            const esPrrroga = !!inc.incapacidad_padre_id;
            document.getElementById('modalCrearTitle').textContent =
                esPrrroga ? `✏️ Editar Prórroga #${inc.numero_proroga}` : '✏️ Editar Incapacidad';
            document.getElementById('formCrear').action = `/admin/incapacidades/${id}`;
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('formId').value = id;
            document.getElementById('formCrear').reset();
            // Poblar campos básicos
            const f = document.getElementById('formCrear');
            f.querySelector('[name=cedula_usuario]').value          = inc.cedula_usuario;
            f.querySelector('[name=dias_incapacidad]').value        = inc.dias_incapacidad;
            f.querySelector('[name=fecha_inicio]').value            = inc.fecha_inicio?.substring(0,10)||'';
            f.querySelector('[name=fecha_terminacion]').value       = inc.fecha_terminacion?.substring(0,10)||'';
            f.querySelector('[name=fecha_recibido]').value          = inc.fecha_recibido?.substring(0,10)||'';
            f.querySelector('[name=tipo_incapacidad]').value        = inc.tipo_incapacidad;
            f.querySelector('[name=tipo_entidad]').value            = inc.tipo_entidad;
            f.querySelector('[name=diagnostico]') && (f.querySelector('[name=diagnostico]').value = inc.diagnostico||'');
            f.querySelector('[name=observacion]') && (f.querySelector('[name=observacion]').value = inc.observacion||'');

            // Número radicado y fecha radicado
            const nrEl = f.querySelector('[name=numero_radicado]');
            if (nrEl) nrEl.value = inc.numero_radicado || '';
            const frEl = f.querySelector('[name=fecha_radicado]');
            if (frEl) frEl.value = inc.fecha_radicado?.substring(0,10) || '';

            // Razón social hidden
            const rsH = document.getElementById('razonSocialHidden');
            if (rsH) rsH.value = inc.razon_social_id || '';

            // Contrato — inyectar la opción actual en el select para que quede seleccionada
            const csEl = document.getElementById('contratoSelect');
            if (csEl && inc.contrato_id) {
                const label = inc.razon_social_nombre
                    ? `Contrato #${inc.contrato_id} — ${inc.razon_social_nombre}`
                    : `Contrato #${inc.contrato_id}`;
                // Limpiar y añadir la opción del contrato actual
                csEl.innerHTML = `<option value="">Sin contrato</option>
                    <option value="${inc.contrato_id}" selected>${label}</option>`;
                // Mostrar info del contrato
                const infoBox = document.getElementById('contratoInfoBox');
                const infoTxt = document.getElementById('contratoInfoText');
                if (infoBox && infoTxt) {
                    infoTxt.textContent = label;
                    infoBox.style.display = 'block';
                }
            }

            // Encargado
            const qrSel = document.getElementById('quienRecibeSelect');
            if (qrSel && inc.quien_recibe_id) qrSel.value = inc.quien_recibe_id;

            // Quién remite — inyectar opción si tiene valor
            const qrmSel = document.getElementById('quienRemiteSelect');
            if (qrmSel && inc.quien_remite) {
                if (!qrmSel.querySelector(`option[value="${inc.quien_remite}"]`)) {
                    const opt = document.createElement('option');
                    opt.value = inc.quien_remite;
                    opt.textContent = inc.quien_remite;
                    qrmSel.appendChild(opt);
                }
                qrmSel.value = inc.quien_remite;
            }

            // Nombre cliente
            document.getElementById('nombreCliente').value = data.cliente
                ? [data.cliente.primer_nombre, data.cliente.primer_apellido].filter(Boolean).join(' ')
                : inc.cedula_usuario;

            actualizarListaEntidades(inc.tipo_entidad, inc.entidad_responsable_id);

            // Panel de documentos de esta incapacidad específica
            mostrarDocsEnEditar(id, esPrrroga ? `Prórroga #${inc.numero_proroga}` : 'Incapacidad Original');

            abrirModal('modalCrear');
        });
}

function mostrarDocsEnEditar(incId, labelGrupo) {
    // Buscar o crear el panel de docs dentro del modal crear
    let panel = document.getElementById('editarDocsPanel');
    if (!panel) {
        const footer = document.querySelector('#modalCrear .modal-footer');
        panel = document.createElement('div');
        panel.id = 'editarDocsPanel';
        panel.style.cssText = 'border-top:1px solid #e2e8f0;padding:.75rem 1.35rem;background:#f8fafc;max-height:220px;overflow-y:auto';
        footer.parentNode.insertBefore(panel, footer);
    }
    panel.innerHTML = `<div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:.05em;margin-bottom:.5rem">
        📎 Documentos de ${labelGrupo}
        <button class="btn btn-sm" style="background:#e0e7ff;color:#3730a3;font-size:.7rem;padding:.15rem .45rem;margin-left:.5rem"
            onclick="subirDocumento(${incId});_docsFamiliaLoaded=null">+ Subir</button>
    </div>
    <div id="editarDocsList" style="font-size:.78rem;color:#94a3b8">⏳ Cargando...</div>`;

    fetch(`/admin/incapacidades/${incId}/documentos-familia`)
        .then(r=>r.json()).then(data=>{
            const lista = document.getElementById('editarDocsList');
            if (!lista) return;
            // Solo mostrar documentos del grupo con ese incId
            const grupo = (data.familia||[]).find(g=>g.incapacidad_id==incId);
            const docs = grupo?.documentos || [];
            if (!docs.length) { lista.textContent = 'Sin documentos en este grupo.'; return; }
            const TIPOS_DOC = {
                incapacidad_original:'📄 Inc. Original', historia_clinica:'📋 Hist. Clínica',
                radicado_entidad:'📮 Radicado', soporte_pago:'💳 Soporte Pago',
                transcripcion:'🏥 Transcripción', cedula:'🪪 Cédula', examen:'🔬 Examen', otro:'📎 Otro'
            };
            lista.innerHTML = docs.map(d=>`
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.3rem .5rem;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:.3rem;background:#fff">
                    <span style="font-weight:600;color:#374151">${TIPOS_DOC[d.tipo_documento]||d.tipo_documento}</span>
                    <div style="display:flex;gap:.3rem">
                        ${d.es_pdf
                            ? `<button class="btn btn-info btn-sm" style="font-size:.7rem;padding:.15rem .4rem" onclick="verPdfDoc('${d.url_ver}','${TIPOS_DOC[d.tipo_documento]||d.tipo_documento}')">👁 Ver</button>`
                            : `<a href="${d.url_ver}" target="_blank" class="btn btn-info btn-sm" style="font-size:.7rem;padding:.15rem .4rem">👁 Ver</a>`
                        }
                        <a href="${d.url_descargar}" class="btn btn-secondary btn-sm" style="font-size:.7rem;padding:.15rem .4rem">⬇</a>
                    </div>
                </div>`).join('');
        }).catch(()=>{ const l=document.getElementById('editarDocsList'); if(l) l.textContent='Error al cargar.'; });
}


// ── Ver detalle completo ─────────────────────────────────────────────────────
function verDetalle(id){
    abrirModal('modalDetalle');
    document.getElementById('detalleCuerpo').innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8">⏳ Cargando...</div>';

    fetch(`/admin/incapacidades/${id}/show`)
        .then(r=>r.json()).then(data=>{
            const inc = data.incapacidad;
            const cl  = data.cliente;

            // Header
            const nombre = cl ? `${cl.primer_nombre||''} ${cl.primer_apellido||''}`.trim() : inc.cedula_usuario;
            document.getElementById('detalleTitle').textContent = `🏥 Incapacidad #${inc.id} — ${nombre}`;
            document.getElementById('detalleSubtitle').innerHTML =
                `Cédula: ${inc.cedula_usuario} ${data.empresa?`· Empresa: ${data.empresa}`:''} ` +
                `· Recibida: ${formatFechaLarga(inc.fecha_recibido)}`;

            const colClass = {verde:'sem-verde',amarillo:'sem-amarillo',rojo:'sem-rojo',gris:'sem-gris'}[data.semaforo]||'sem-gris';
            document.getElementById('detalleSemaforo').innerHTML =
                `<span class="semaforo ${colClass}">${data.icono} ${data.dias_gestion} días sin gestión</span>`;

            // Alerta 180 días
            const al180 = data.alerta_180 ? `<div class="alerta-180" style="margin-bottom:.8rem">⚠️ Esta familia suma ${data.familia_dias} días — supera el límite de 180 días EPS. Debe radicar al AFP/Pensión.</div>` : '';

            // Resumen de la familia
            const resumenFam = data.num_prorrogas > 0
                ? `<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:.8rem">
                    <span class="badge badge-primary">📁 ${data.num_prorrogas} prórroga(s)</span>
                    <span class="badge badge-info">📅 Total familia: ${data.familia_dias} días</span>
                   </div>` : '';

            // Gestiones
            const gestiones = (inc.gestiones||[]).map(g=>`
                <div class="timeline-item">
                    <div class="tl-dot">${iconoTipoGestion(g.tipo)}</div>
                    <div class="tl-content">
                        <div class="tl-tipo">${g.tipo}${g.aplica_a_familia?' <span style="color:#d97706">· Familia</span>':''}</div>
                        <div class="tl-tramite">${g.tramite}</div>
                        ${g.respuesta?`<div class="tl-tramite" style="color:#059669">↳ ${g.respuesta}</div>`:''}
                        <div class="tl-meta">${g.user?.nombre||'Sistema'} · ${formatFechaLarga(g.created_at)} ${g.fecha_recordar?`· 🔔 Recordar: ${formatFechaLarga(g.fecha_recordar)}`:''}${g.estado_resultado?` · Estado: ${labelEstado(g.estado_resultado)}`:''}</div>
                    </div>
                </div>`).join('');

            // Prórrogas — tabla compacta con numeración y botón editar
            const prorrogas = (inc.prorrogas||[]).length === 0 ? '<p style="color:#94a3b8;font-size:.82rem">Sin prórrogas.</p>' : `
            <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                        <th style="padding:.5rem .7rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;white-space:nowrap">#</th>
                        <th style="padding:.5rem .7rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b">Tipo</th>
                        <th style="padding:.5rem .7rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b">Días</th>
                        <th style="padding:.5rem .7rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b">Período</th>
                        <th style="padding:.5rem .7rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b">Entidad</th>
                        <th style="padding:.5rem .7rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b">Estado</th>
                        <th style="padding:.5rem .7rem;text-align:right;font-size:.7rem;text-transform:uppercase;color:#64748b">Valor Esp.</th>
                        <th style="padding:.5rem .7rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                ${(inc.prorrogas||[]).map((p,i)=>{
                    const ec = colorEstadoPago(p.estado_pago);
                    const badgeColors = {success:'#d1fae5;color:#065f46',danger:'#fee2e2;color:#991b1b',warning:'#fef3c7;color:#92400e',primary:'#dbeafe;color:#1e40af',info:'#cffafe;color:#155e75',secondary:'#f1f5f9;color:#475569'};
                    const bc = badgeColors[ec]||badgeColors.secondary;
                    return `<tr style="border-bottom:1px solid #f1f5f9;transition:background .1s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''"> 
                        <td style="padding:.55rem .7rem;font-weight:700;color:#2563eb">${p.numero_proroga}</td>
                        <td style="padding:.55rem .7rem;color:#374151">${labelTipo(p.tipo_incapacidad)}</td>
                        <td style="padding:.55rem .7rem;text-align:center;font-weight:600">${p.dias_incapacidad}d</td>
                        <td style="padding:.55rem .7rem;font-size:.75rem;color:#64748b;white-space:nowrap">${formatFechaLarga(p.fecha_inicio)}<br>→ ${formatFechaLarga(p.fecha_terminacion)}</td>
                        <td style="padding:.55rem .7rem;font-size:.75rem">${TIPOS_ENTIDAD[p.tipo_entidad]||p.tipo_entidad?.toUpperCase()}<br><span style="color:#64748b">${p.entidad_nombre||''}</span></td>
                        <td style="padding:.55rem .7rem"><span style="display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.68rem;font-weight:600;background:${bc.split(';')[0]};${bc.split(';')[1]}">${labelEstadoPago(p.estado_pago)}</span></td>
                        <td style="padding:.55rem .7rem;text-align:right;font-weight:600;color:#059669">${p.valor_esperado?'$'+Number(p.valor_esperado).toLocaleString('es-CO'):'—'}</td>
                        <td style="padding:.55rem .7rem;text-align:center">
                            <div style="display:flex;gap:.3rem;justify-content:center;flex-wrap:wrap">
                                <button class="btn btn-info btn-sm" onclick="registrarGestion(${p.id})" title="Gestión">📞</button>
                                <button class="btn btn-primary btn-sm" onclick="registrarPago(${p.id})" title="Pago">💰</button>
                                <button class="btn btn-warning btn-sm" onclick="cerrarModal('modalDetalle'); abrirModalEditar(${p.id})" title="Editar prórroga">✏️</button>
                            </div>
                        </td>
                    </tr>`;
                }).join('')}
                </tbody>
            </table>
            </div>`;

            // Valor esperado
            const val = inc.valor_esperado ? `$${Number(inc.valor_esperado).toLocaleString('es-CO')}` : '—';

            document.getElementById('detalleCuerpo').innerHTML = `
                ${al180}${resumenFam}
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab(this,'tabInfo')">📋 Datos</button>
                    <button class="tab-btn" onclick="switchTab(this,'tabDocumentos');cargarDocsFamilia(${inc.id})">📎 Documentos</button>
                    ${data.num_prorrogas>0?`<button class="tab-btn" onclick="switchTab(this,'tabProrrogas')">📄 Prórrogas (${data.num_prorrogas})</button>`:''}
                    <button class="tab-btn" onclick="switchTab(this,'tabGestiones')">📞 Gestiones (${(inc.gestiones||[]).length})</button>
                    <button class="tab-btn" onclick="switchTab(this,'tabPago')">💰 Pago</button>
                </div>

                <div id="tabInfo" class="tab-pane active">
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.65rem;margin-bottom:.8rem">
                        <div style="background:#eff6ff;border-radius:10px;padding:.7rem .85rem;border:1px solid #bfdbfe">
                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:#3b82f6;letter-spacing:.06em;margin-bottom:.25rem">🏷️ Tipo</div>
                            <div style="font-size:.85rem;font-weight:600;color:#1e3a8a">${labelTipo(inc.tipo_incapacidad)}</div>
                        </div>
                        <div style="background:#f0fdf4;border-radius:10px;padding:.7rem .85rem;border:1px solid #bbf7d0">
                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:#059669;letter-spacing:.06em;margin-bottom:.25rem">🏦 Entidad</div>
                            <div style="font-size:.85rem;font-weight:600;color:#065f46">${TIPOS_ENTIDAD[inc.tipo_entidad]||inc.tipo_entidad?.toUpperCase()||'—'} — <strong>${inc.entidad_nombre||'N/A'}</strong></div>
                        </div>
                        <div style="background:#faf5ff;border-radius:10px;padding:.7rem .85rem;border:1px solid #e9d5ff">
                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:#7c3aed;letter-spacing:.06em;margin-bottom:.25rem">📅 Días</div>
                            <div style="font-size:1.4rem;font-weight:800;color:#4c1d95;line-height:1">${inc.dias_incapacidad}</div>
                            ${data.familia_dias>inc.dias_incapacidad?`<div style="font-size:.65rem;color:#7c3aed">Total familia: ${data.familia_dias} días</div>`:''}
                        </div>
                        <div style="background:#fff7ed;border-radius:10px;padding:.7rem .85rem;border:1px solid #fed7aa">
                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:#ea580c;letter-spacing:.06em;margin-bottom:.25rem">🗓️ Período</div>
                            <div style="font-size:.78rem;font-weight:600;color:#7c2d12">${formatFechaLarga(inc.fecha_inicio)}</div>
                            <div style="font-size:.72rem;color:#9a3412">→ ${formatFechaLarga(inc.fecha_terminacion)}</div>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.65rem;margin-bottom:.8rem">
                        <div style="background:#f8fafc;border-radius:10px;padding:.65rem .85rem;border:1px solid #e2e8f0">
                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:.06em;margin-bottom:.2rem">📋 Radicado</div>
                            <div style="font-size:.83rem;color:${inc.numero_radicado?'#1e293b':'#94a3b8'}">${inc.numero_radicado||'Sin radicar'}</div>
                            ${inc.fecha_radicado?`<div style="font-size:.72rem;color:#64748b">${formatFechaLarga(inc.fecha_radicado)}</div>`:''}
                        </div>
                        <div style="background:#f8fafc;border-radius:10px;padding:.65rem .85rem;border:1px solid #e2e8f0">
                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:.06em;margin-bottom:.2rem">🏢 Razón Social</div>
                            <div style="font-size:.83rem;font-weight:600;color:#1e293b">${inc.razon_social_nombre||'—'}</div>
                        </div>
                        <div style="background:#f0fdf4;border-radius:10px;padding:.65rem .85rem;border:1px solid #bbf7d0">
                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:#059669;letter-spacing:.06em;margin-bottom:.2rem">💰 Valor Esperado</div>
                            <div style="font-size:1.05rem;font-weight:800;color:#059669">${val}</div>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.5rem;background:#f8fafc;padding:.75rem;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:.6rem">
                        <div><span style="font-size:.63rem;color:#64748b;display:block;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">Estado</span><span class="badge badge-${colorEstado(inc.estado)}">${labelEstado(inc.estado)}</span></div>
                        <div><span style="font-size:.63rem;color:#64748b;display:block;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">Pago</span><span class="badge badge-${colorEstadoPago(inc.estado_pago)}">${labelEstadoPago(inc.estado_pago)}</span></div>
                        <div><span style="font-size:.63rem;color:#64748b;display:block;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">Recibido</span><span style="font-size:.82rem">${formatFechaLarga(inc.fecha_recibido)}</span></div>
                        <div><span style="font-size:.63rem;color:#64748b;display:block;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">Diagnóstico</span><span style="font-size:.82rem;color:${inc.diagnostico?'#374151':'#94a3b8'}">${inc.diagnostico||'—'}</span></div>
                        ${inc.prorroga?'<div><span style="font-size:.72rem;color:#2563eb;font-weight:700;background:#eff6ff;padding:.2rem .5rem;border-radius:6px;display:inline-block">✓ Doc. prórroga</span></div>':''}
                    </div>
                    ${inc.observacion?`<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.6rem .85rem;font-size:.82rem;color:#92400e;margin-bottom:.6rem"><strong>📝 Observación:</strong> ${inc.observacion}</div>`:''}
                    <div style="display:flex;gap:.5rem;margin-top:1rem;flex-wrap:wrap;padding-top:.75rem;border-top:1px solid #f1f5f9">
                        <button class="btn btn-primary btn-sm" onclick="registrarGestion(${inc.id})">📞 Nueva Gestión</button>
                        <button class="btn btn-warning btn-sm" onclick="cerrarModal('modalDetalle'); abrirModalEditar(${inc.id})">✏️ Editar Incapacidad</button>
                        <button class="btn btn-secondary btn-sm" onclick="cerrarModal('modalDetalle'); abrirModalProroga(${inc.id})">➕ Agregar Prórroga</button>
                    </div>
                </div>

                <div id="tabGestiones" class="tab-pane">
                    <button class="btn btn-primary btn-sm" style="margin-bottom:.8rem" onclick="registrarGestion(${inc.id})">📞 Nueva Gestión</button>
                    <div class="timeline">${gestiones||'<div style="color:#94a3b8;font-size:.82rem">Sin gestiones aún.</div>'}</div>
                </div>

                ${data.num_prorrogas>0?`<div id="tabProrrogas" class="tab-pane">${prorrogas}</div>`:''}

                <div id="tabPago" class="tab-pane">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-bottom:1rem">
                        <div class="kpi"><div class="num" style="font-size:.95rem">${labelEstadoPago(inc.estado_pago)}</div><div class="lbl">Estado Pago</div></div>
                        <div class="kpi"><div class="num" style="font-size:1.1rem">${inc.valor_pago?'$'+Number(inc.valor_pago).toLocaleString('es-CO'):'—'}</div><div class="lbl">Valor Pagado</div></div>
                        <div class="kpi"><div class="num" style="font-size:1.1rem">${val}</div><div class="lbl">Valor Esperado</div></div>
                        <div class="kpi"><div class="num" style="font-size:.95rem">${inc.pagado_a==='cliente'?'Afiliado':inc.pagado_a==='empresa'?'Empresa':'—'}</div><div class="lbl">Pagado a</div></div>
                    </div>
                    ${inc.detalle_pago?`<p style="font-size:.82rem;color:#374151"><strong>Detalle:</strong> ${inc.detalle_pago}</p>`:''}
                    <button class="btn btn-success" onclick="registrarPago(${inc.id})" style="margin-top:.8rem">💰 Registrar Pago al Afiliado</button>
                </div>

                <div id="tabDocumentos" class="tab-pane">
                    <div id="docsFamiliaContainer" style="min-height:80px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:.85rem">
                        Haz clic en la pestaña Documentos para cargar.
                    </div>
                </div>`;

            // Guardar ID activo
            document.getElementById('modalDetalle').dataset.incId = id;
        });
}

function switchTab(btn, tabId){
    btn.closest('.modal-body').querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    btn.closest('.modal-body').querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
    const pane = document.getElementById(tabId);
    if(pane) pane.classList.add('active');
}

function iconoTipoGestion(tipo){
    const m={llamada:'📞',correo:'📧',whatsapp:'💬',portal:'🌐',radico:'📋',tutela:'⚖️',
             transcripcion_ips:'🏥',respuesta_entidad:'📩',autorizacion:'✅',
             liquidacion:'💰',pago_afiliado:'🏦',otro:'📝'};
    return m[tipo]||'📝';
}

// ── Gestión inline ───────────────────────────────────────────────────────────
function registrarGestion(incId) {
    // Cargar en paralelo: datos de la incapacidad + familia
    Promise.all([
        fetch(`/admin/incapacidades/${incId}/show`).then(r => r.json()).catch(() => ({})),
        fetch(`/admin/incapacidades/${incId}/documentos-familia`).then(r => r.json()).catch(() => ({}))
    ]).then(([showData, familiaData]) => {
        const inc    = showData.incapacidad || {};
        const familia = familiaData.familia || [];
        _mostrarModalGestion(incId, familia, inc);
    });
}

function _mostrarModalGestion(incId, familia, inc = {}) {
    const TIPOS = {
        llamada:  '📞 Llamada',
        correo:   '📧 Correo',
        whatsapp: '💬 WhatsApp',
        portal:   '🌐 Portal Web',
        otro:     '📝 Otro',
    };
    const ESTADOS = @json(\App\Models\Incapacidad::ESTADOS);

    // Estado actual de la incapacidad (para pre-seleccionar)
    const estadoActual     = inc.estado || '';
    // Si ya tiene número de radicado, no pedir de nuevo
    const yaRadicada       = !!(inc.numero_radicado);

    // Opciones de alcance
    let optsAlcance = '';
    if (familia.length <= 1) {
        optsAlcance = `<option value="esta_incapacidad" selected>📋 Esta incapacidad</option>`;
    } else {
        familia.forEach(g => {
            const selected = g.incapacidad_id == incId ? 'selected' : '';
            const icon = g.es_padre ? '🏥' : '🔁';
            optsAlcance += `<option value="incapacidad_${g.incapacidad_id}" ${selected}>${icon} ${g.label} #${g.incapacidad_id}</option>`;
        });
        optsAlcance += `<option value="toda_la_familia">👨‍👩‍👧 Toda la familia (seguimiento global)</option>`;
    }

    const optTipos = Object.entries(TIPOS)
        .map(([k,v]) => `<option value="${k}">${v}</option>`).join('');

    // Opciones de estado — pre-selecciona el estado actual
    const optEstados = Object.entries(ESTADOS)
        .map(([k,v]) => `<option value="${k}" ${k === estadoActual ? 'selected' : ''}>${v.label||v}</option>`).join('');

    const html = `
    <div class="modal-header" style="background:linear-gradient(135deg,#1e40af,#0891b2);border-radius:16px 16px 0 0">
        <div>
            <h3 style="color:#fff;font-size:1rem;font-weight:700;margin:0">📞 Registrar Gestión</h3>
            <div id="gModalSubtitle" style="font-size:.75rem;color:rgba(255,255,255,.75);margin-top:.1rem">Incapacidad #${incId}</div>
        </div>
        <button class="btn-close-modal" onclick="document.getElementById('modalGestion').classList.remove('open')">×</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:.75rem">

        ${familia.length > 1 ? `
        <div class="form-group" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.65rem .85rem">
            <label style="font-size:.72rem;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.04em">
                📋 ¿A qué incapacidad aplica esta gestión? *
            </label>
            <select id="gAlcance" class="form-control" style="margin-top:.3rem" onchange="_actualizarSubtituloGestion(this)">
                ${optsAlcance}
            </select>
        </div>` : `<input type="hidden" id="gAlcance" value="esta_incapacidad">`}

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
            <div class="form-group">
                <label>Canal de Gestión *</label>
                <select id="gTipo" class="form-control">${optTipos}</select>
            </div>
            <div class="form-group">
                <label>Actualizar Estado <span style="font-size:.7rem;color:#94a3b8">(opcional)</span></label>
                <select id="gEstado" class="form-control">
                    <option value="">— Sin cambio de estado —</option>
                    ${optEstados}
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Gestión realizada *</label>
            <textarea id="gTramite" class="form-control" style="min-height:70px" placeholder="Describe qué se hizo: llamada a EPS, consulta en portal, etc."></textarea>
        </div>
        <div class="form-group">
            <label>Respuesta / Resultado <span style="font-size:.7rem;color:#94a3b8">(opcional)</span></label>
            <textarea id="gRespuesta" class="form-control" style="min-height:48px" placeholder="Qué respondió la entidad, cliente, etc."></textarea>
        </div>

        <div id="gAlertaCierre" style="display:none;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:.6rem .85rem;font-size:.78rem;color:#92400e">
            ⚠️ Para el <strong>Cierre Exitoso</strong> se requiere haber registrado previamente <em>Pagada a Razón Social</em> y <em>Pagada al Afiliado</em>.
        </div>

        <div id="gPanelPagoRS" style="display:none;background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:.75rem .9rem">
            <div style="font-size:.72rem;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.6rem">
                🏢 Pago recibido por Razón Social
            </div>
            <div id="gCuentasRSContent">
                <div style="font-size:.8rem;color:#94a3b8">⏳ Cargando cuentas...</div>
            </div>
        </div>

        <div id="gCamposRadicada" style="display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.65rem .85rem">
            <div style="font-size:.72rem;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem">📋 Datos del Radicado</div>
            ${yaRadicada ? `
            <div style="font-size:.82rem;color:#065f46;background:#dcfce7;border-radius:6px;padding:.45rem .7rem">
                ✅ Ya tiene radicado: <strong>${inc.numero_radicado}</strong>
                ${inc.fecha_radicado ? `<span style="color:#047857;margin-left:.5rem">(${inc.fecha_radicado.substring(0,10)})</span>` : ''}
            </div>` : `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
                <div class="form-group" style="margin:0">
                    <label>Número Radicado *</label>
                    <input type="text" id="gNumRadicado" class="form-control" placeholder="Ej: 2026-12345">
                </div>
                <div class="form-group" style="margin:0">
                    <label>Fecha Radicado *</label>
                    <input type="date" id="gFechaRadicado" class="form-control" value="${new Date().toISOString().substring(0,10)}">
                </div>
            </div>`}
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="document.getElementById('modalGestion').classList.remove('open')">Cancelar</button>
        <button class="btn btn-primary" onclick="enviarGestion(${incId})">💾 Guardar Gestión</button>
    </div>`;

    let overlay = document.getElementById('modalGestion');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'modalGestion';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `<div class="modal" style="max-width:580px">${html}</div>`;
        document.body.appendChild(overlay);
    } else {
        overlay.querySelector('.modal').innerHTML = html;
    }

    // Listener para mostrar/ocultar campos según estado seleccionado
    setTimeout(() => {
        const sel = document.getElementById('gEstado');
        if (sel) {
            const _toggle = (val) => {
                const alerta   = document.getElementById('gAlertaCierre');
                const radicado = document.getElementById('gCamposRadicada');
                const pagoRS   = document.getElementById('gPanelPagoRS');
                if (alerta)   alerta.style.display  = val === 'cierre_exitoso'     ? 'block' : 'none';
                if (radicado) radicado.style.display = val === 'radicada'           ? 'block' : 'none';
                if (pagoRS) {
                    pagoRS.style.display = val === 'pagada_razon_social' ? 'block' : 'none';
                    if (val === 'pagada_razon_social') {
                        _cargarCuentasRS(incId);
                    }
                }
            };
            _toggle(sel.value);
            sel.addEventListener('change', function() { _toggle(this.value); });
        }
    }, 100);

    overlay.classList.add('open');
}

// Carga las cuentas bancarias de la Razón Social vía API
function _cargarCuentasRS(incId) {
    const box = document.getElementById('gCuentasRSContent');
    if (!box) return;
    box.innerHTML = '<div style="font-size:.8rem;color:#94a3b8">⏳ Cargando cuentas...</div>';
    fetch(`/admin/incapacidades/${incId}/cuentas-rs`)
        .then(r => r.json()).then(d => {
            if (!d.ok) { box.innerHTML = '<div style="color:#ef4444;font-size:.8rem">Error al cargar cuentas.</div>'; return; }
            const cuentas = d.cuentas || [];
            if (!cuentas.length) {
                box.innerHTML = `
                <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:.6rem .8rem;font-size:.8rem;color:#c2410c">
                    ⚠️ <strong>Sin cuenta configurada</strong> para ${d.rs_nombre || 'esta Razón Social'}.<br>
                    <a href="/admin/configuracion/cuentas" target="_blank" style="color:#2563eb;text-decoration:underline">
                        → Ir a Configuración → Cuentas Bancarias
                    </a>
                </div>`;
                return;
            }
            const opts = cuentas.map(c =>
                `<option value="${c.id}">${c.banco} · ${c.tipo_cuenta||''} · ****${(c.numero_cuenta||'').slice(-4)} (${c.nombre})</option>`
            ).join('');
            box.innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.55rem;margin-bottom:.5rem">
                <div class="form-group" style="margin:0;grid-column:1/-1">
                    <label>Cuenta que recibió el pago *</label>
                    <select id="gBancoCuentaId" class="form-control">${opts}</select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Valor recibido *</label>
                    <input type="number" id="gValorPagoRS" class="form-control" placeholder="0" min="0" step="1000">
                </div>
                <div class="form-group" style="margin:0">
                    <label>Fecha del ingreso *</label>
                    <input type="date" id="gFechaPagoRS" class="form-control" value="${new Date().toISOString().substring(0,10)}">
                </div>
            </div>
            <div style="font-size:.72rem;color:#0369a1;background:#e0f2fe;border-radius:5px;padding:.3rem .6rem">
                💡 Se registrará una consignación en el banco y un abono a esta incapacidad.
            </div>`;
        }).catch(() => {
            box.innerHTML = '<div style="color:#ef4444;font-size:.8rem">Error de red al cargar cuentas.</div>';
        });
}


// Actualiza el subtítulo del modal al cambiar la incapacidad seleccionada
function _actualizarSubtituloGestion(sel) {
    const sub = document.getElementById('gModalSubtitle');
    if (!sub) return;
    const txt = sel.options[sel.selectedIndex]?.text || '';
    sub.textContent = txt.includes('familia') ? '👨‍👩‍👧 Gestión para toda la familia' : txt;
}

function enviarGestion(incId) {
    const alcanceEl = document.getElementById('gAlcance');
    const alcanceVal = alcanceEl?.value || 'esta_incapacidad';
    const tramite = document.getElementById('gTramite').value.trim();

    if (!tramite) { alert('Por favor ingresa la gestión realizada.'); return; }

    // Determinar incapacidad destino y alcance a enviar
    let targetId = incId;
    let alcance = 'esta_incapacidad';

    if (alcanceVal === 'toda_la_familia') {
        alcance = 'toda_la_familia';
    } else if (alcanceVal.startsWith('incapacidad_')) {
        targetId = parseInt(alcanceVal.replace('incapacidad_', ''));
        alcance = 'esta_incapacidad';
    }

    const estadoNuevo = document.getElementById('gEstado').value || null;

    const body = {
        tipo:            document.getElementById('gTipo').value,
        tramite:         tramite,
        respuesta:       document.getElementById('gRespuesta').value,
        estado_nuevo:    estadoNuevo,
        alcance:         alcance,
        numero_radicado: estadoNuevo === 'radicada' ? (document.getElementById('gNumRadicado')?.value || null) : null,
        fecha_radicado:  estadoNuevo === 'radicada' ? (document.getElementById('gFechaRadicado')?.value || null) : null,
        // Pago a Razón Social
        banco_cuenta_id: estadoNuevo === 'pagada_razon_social' ? (document.getElementById('gBancoCuentaId')?.value || null) : null,
        valor_pago_rs:   estadoNuevo === 'pagada_razon_social' ? (document.getElementById('gValorPagoRS')?.value || null) : null,
        fecha_pago_rs:   estadoNuevo === 'pagada_razon_social' ? (document.getElementById('gFechaPagoRS')?.value || null) : null,
        _token:          TOKEN,
    };

    // Validar campos de radicado solo si el input existe (no hay radicado previo)
    const numRadicadoInput = document.getElementById('gNumRadicado');
    if (estadoNuevo === 'radicada' && numRadicadoInput && !body.numero_radicado) {
        alert('Por favor ingresa el Número Radicado.');
        return;
    }

    const btn = document.querySelector('#modalGestion .btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Guardando...'; }

    fetch(`/admin/incapacidades/${targetId}/gestion`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TOKEN, 'Accept': 'application/json' },
        body: JSON.stringify(body),
    }).then(r => r.json()).then(d => {
        if (btn) { btn.disabled = false; btn.textContent = '💾 Guardar Gestión'; }
        if (d.ok) {
            document.getElementById('modalGestion').classList.remove('open');
            // Refrescar el modal de detalle con el ID original
            const detalleId = document.getElementById('modalDetalle')?.dataset?.incId || incId;
            verDetalle(parseInt(detalleId));
        } else {
            alert('Error: ' + (d.message || 'Error desconocido'));
        }
    }).catch(e => {
        if (btn) { btn.disabled = false; btn.textContent = '💾 Guardar Gestión'; }
        alert('Error de red: ' + e.message);
    });
}



// ── Pago al afiliado ─────────────────────────────────────────────────────────
function registrarPago(incId){
    const html = `<div style="padding:1rem">
        <h4 style="margin-bottom:1rem">💰 Registrar Pago — Incapacidad #${incId}</h4>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
            <div class="form-group"><label>Valor Pagado *</label><input type="number" id="pValor" style="width:100%;padding:.4rem;border:1px solid #d1d5db;border-radius:6px" min="0" step="100"></div>
            <div class="form-group"><label>Fecha de Pago *</label><input type="date" id="pFecha" value="${new Date().toISOString().substring(0,10)}" style="width:100%;padding:.4rem;border:1px solid #d1d5db;border-radius:6px"></div>
        </div>
        <div class="form-group"><label>Pagado a *</label>
            <select id="pPagadoA" style="width:100%;padding:.4rem;border:1px solid #d1d5db;border-radius:6px">
                <option value="cliente">Cliente (afiliado)</option>
                <option value="empresa">Empresa</option>
            </select></div>
        <div class="form-group"><label>Detalle / Observación</label><textarea id="pDetalle" style="width:100%;min-height:50px;padding:.4rem;border:1px solid #d1d5db;border-radius:6px"></textarea></div>
        <div style="display:flex;gap:.5rem;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('open')">Cancelar</button>
            <button class="btn btn-success" onclick="enviarPago(${incId})">💾 Registrar Pago</button>
        </div>
    </div>`;

    let overlay = document.getElementById('modalPago');
    if(!overlay){
        overlay = document.createElement('div');
        overlay.id = 'modalPago';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `<div class="modal" style="max-width:520px">${html}</div>`;
        document.body.appendChild(overlay);
    } else { overlay.querySelector('.modal').innerHTML = html; }
    overlay.classList.add('open');
}

function enviarPago(incId){
    fetch(`/admin/incapacidades/${incId}/pago`,{
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN},
        body: JSON.stringify({
            valor_pago: document.getElementById('pValor').value,
            fecha_pago: document.getElementById('pFecha').value,
            pagado_a:   document.getElementById('pPagadoA').value,
            detalle_pago: document.getElementById('pDetalle').value,
            _token: TOKEN
        })
    }).then(r=>r.json()).then(d=>{
        if(d.ok){ document.getElementById('modalPago').classList.remove('open'); verDetalle(incId); }
        else alert('Error: '+(d.message||''));
    });
}

// ── Subir documento (modal persistente con lista de docs) ───────────────────
let _docIncId = null;
function subirDocumento(incId){
    _docIncId = incId;
    let overlay = document.getElementById('modalDoc');
    if(!overlay){
        overlay = document.createElement('div');
        overlay.id = 'modalDoc';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `<div class="modal" style="max-width:580px">
            <div class="modal-header" style="background:linear-gradient(135deg,#059669,#0891b2)">
                <div><h3 style="color:#fff;font-size:1rem;font-weight:700;margin:0">📎 Documentos de Incapacidad</h3>
                <div id="docModalSubtitle" style="font-size:.75rem;color:rgba(255,255,255,.8);margin-top:.1rem">Incapacidad #${incId}</div></div>
                <button class="btn-close-modal" onclick="cerrarModalDoc()">✕</button>
            </div>
            <div class="modal-body">
                <div id="docListaExistente" style="margin-bottom:1rem"></div>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem">
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:.05em;margin-bottom:.7rem">➕ Agregar Documento</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
                        <div class="form-group">
                            <label>Tipo de Documento *</label>
                            <select id="docTipo" class="form-control">
                                <option value="incapacidad_original">📄 Incapacidad Original</option>
                                <option value="historia_clinica">📋 Historia Clínica</option>
                                <option value="radicado_entidad">📮 Radicado Entidad</option>
                                <option value="soporte_pago">💳 Soporte de Pago</option>
                                <option value="transcripcion">🏥 Transcripción IPS</option>
                                <option value="cedula">🪪 Cédula</option>
                                <option value="examen">🔬 Examen / Diagnóstico</option>
                                <option value="otro">📎 Otro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Observación</label>
                            <input type="text" id="docObs" class="form-control" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Archivo * <span style="font-size:.72rem;color:#64748b">(PDF, JPG, PNG)</span></label>
                        <input type="file" id="docArchivo" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control">
                    </div>
                    <div id="docUploadMsg" style="font-size:.8rem;margin-bottom:.5rem"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="cerrarModalDoc()">Cerrar</button>
                <button class="btn btn-primary" id="btnSubirDoc" onclick="enviarDocumento()">📤 Subir Documento</button>
            </div>
        </div>`;
        document.body.appendChild(overlay);
    }
    document.getElementById('docModalSubtitle').textContent = `Incapacidad #${incId}`;
    overlay.classList.add('open');
    cargarDocumentosExistentes(incId);
}

function cargarDocumentosExistentes(incId){
    const box = document.getElementById('docListaExistente');
    if(!box) return;
    box.innerHTML = '<div style="font-size:.78rem;color:#94a3b8">⏳ Cargando documentos...</div>';
    fetch(`/admin/incapacidades/${incId}/show`)
        .then(r=>r.json()).then(data=>{
            const docs = data.incapacidad?.documentos || [];
            if(!docs.length){ box.innerHTML='<div style="font-size:.78rem;color:#94a3b8;padding:.5rem 0">Sin documentos aún.</div>'; return; }
            box.innerHTML = `<div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:.05em;margin-bottom:.5rem">📁 Documentos cargados (${docs.length})</div>`
                + docs.map(d=>`<div style="display:flex;align-items:center;justify-content:space-between;padding:.45rem .65rem;background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:.35rem;font-size:.8rem">
                    <div><span style="font-weight:600;color:#374151">📄 ${d.tipo_documento||'Documento'}</span><span style="color:#64748b;margin-left:.5rem">${formatFechaLarga(d.created_at)}</span>${d.observacion?`<div style="color:#94a3b8;font-size:.72rem">${d.observacion}</div>`:''}</div>
                    <a href="/storage/${d.archivo}" target="_blank" style="color:#2563eb;font-size:.78rem;white-space:nowrap">👁 Ver</a>
                </div>`).join('');
        });
}

function enviarDocumento(){
    const incId = _docIncId;
    const archivo = document.getElementById('docArchivo').files[0];
    if(!archivo){ alert('Selecciona un archivo'); return; }
    const btn = document.getElementById('btnSubirDoc');
    const msg = document.getElementById('docUploadMsg');
    btn.disabled = true; btn.textContent = '⏳ Subiendo...';
    msg.textContent = '';
    const fd = new FormData();
    fd.append('tipo_documento', document.getElementById('docTipo').value);
    fd.append('observacion', document.getElementById('docObs').value);
    fd.append('archivo', archivo);
    fd.append('_token', TOKEN);
    fetch(`/admin/incapacidades/${incId}/documento`,{method:'POST',body:fd})
        .then(r=>r.json()).then(d=>{
            btn.disabled=false; btn.textContent='📤 Subir Documento';
            if(d.ok){
                // Cerrar automáticamente y refrescar la pestaña Documentos
                cerrarModalDoc();
            } else {
                msg.innerHTML=`<span style="color:#ef4444">Error: ${d.message||'Error al subir'}</span>`;
            }
        }).catch(e=>{
            btn.disabled=false;
            btn.textContent='📤 Subir Documento';
            msg.innerHTML=`<span style="color:#ef4444">Error: ${e.message}</span>`;
        });
}


function cerrarModalDoc() {
    document.getElementById('modalDoc')?.classList.remove('open');

    // Resetear cache para que la pestaña Documentos recargue al volver
    _docsFamiliaLoaded = null;

    // Si la pestaña Documentos del modal de detalle está activa, recargarla ahora
    const tabDocBtn = document.querySelector('#modalDetalle .tab-btn.active');
    const incId = document.getElementById('modalDetalle')?.dataset?.incId;
    if (incId && tabDocBtn && tabDocBtn.textContent.includes('Documentos')) {
        cargarDocsFamilia(parseInt(incId));
    }

    // Si el panel de docs del modal editar está visible, refrescarlo
    if (_docIncId && document.getElementById('editarDocsList')) {
        mostrarDocsEnEditar(_docIncId, '');
    }
}

// ── Autocompletado de cliente ────────────────────────────────────────────────
let clienteTimeout;
function buscarCliente(val){
    clearTimeout(clienteTimeout);
    if(val.length < 3) return;
    clienteTimeout = setTimeout(()=>{
        fetch(`/admin/incapacidades/api/clientes?cedula=${encodeURIComponent(val)}`)
            .then(r=>r.json()).then(data=>{
                const box = document.getElementById('clienteSugerencias');
                if(!data.length){ box.style.display='none'; return; }
                box.innerHTML = data.map(c=>{
                    const emp = (c.empresa_nombre||'').replace(/'/g,"\\'");
                    const nom = `${c.primer_nombre||''} ${c.primer_apellido||''}`.trim().replace(/'/g,"\\'");
                    return `<div onclick="seleccionarCliente('${c.cedula}','${nom}','${emp}')"
                         style="padding:.45rem .75rem;cursor:pointer;font-size:.82rem;border-bottom:1px solid #f1f5f9"
                         onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                        <strong>${c.cedula}</strong> \u2014 ${c.primer_nombre||''} ${c.primer_apellido||''}
                        ${c.empresa_nombre?`<span style="color:#64748b;font-size:.75rem"> \u00b7 ${c.empresa_nombre}</span>`:''}
                    </div>`;
                }).join('');
                box.style.display='block';
            });
    }, 350);
}

// Stored contracts data for quick lookup
let _contratosData = [];
let _clienteEpsId  = null;
let _clienteArlId  = null;
let _clienteNombre = '';
let _empresaNombre = '';

function seleccionarCliente(cedula, nombre, empresaNombre){
    document.getElementById('cedulaInput').value = cedula;
    document.getElementById('nombreCliente').value = nombre;
    document.getElementById('clienteSugerencias').style.display='none';
    _clienteNombre = nombre;
    _empresaNombre = empresaNombre || '';
    // Poblar Quien Remite inmediatamente con afiliado y empresa
    const qrSel = document.getElementById('quienRemiteSelect');
    if(qrSel){
        qrSel.innerHTML = `<option value="${nombre}">${nombre} (Afiliado)</option>`
            + (_empresaNombre ? `<option value="${_empresaNombre}">${_empresaNombre} (Empresa)</option>` : '');
    }
    // Cargar contratos
    fetch(`/admin/incapacidades/api/contratos?cedula=${encodeURIComponent(cedula)}`)
        .then(r=>r.json()).then(contratos=>{
            _contratosData = contratos;
            const sel = document.getElementById('contratoSelect');
            sel.innerHTML = '<option value="">Sin contrato especifico</option>';
            let primeraVigente = null;
            contratos.forEach(c=>{
                const vigente = c.estado === 'vigente';
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `#${c.id} - ${c.estado.charAt(0).toUpperCase()+c.estado.slice(1)} (${c.fecha_ingreso?.substring(0,10)||''})`;
                if(vigente){ opt.style.color='#059669'; opt.style.fontWeight='600'; if(!primeraVigente) primeraVigente = c.id; }
                sel.appendChild(opt);
            });
            if(primeraVigente){ sel.value = primeraVigente; contratoSeleccionado(sel); }
        });
}


function contratoSeleccionado(sel){
    const id = parseInt(sel.value);
    const c  = _contratosData.find(x=>x.id===id);
    const boxOk   = document.getElementById('contratoInfoBox');
    const boxWarn = document.getElementById('contratoInfoBoxInactivo');
    const txtOk   = document.getElementById('contratoInfoText');
    const txtWarn = document.getElementById('contratoInfoTextInactivo');
    if(c){
        const rsH = document.getElementById('razonSocialHidden');
        if(rsH) rsH.value = c.razon_social_id || '';
        _clienteEpsId = c.eps_id || null;
        _clienteArlId = c.arl_id || null;
        // rsNombre es solo para mostrar en el info box — NO sobreescribe _empresaNombre
        const rsNombre = c.razon_social_nombre || 'Sin razón social';
        const salario  = c.salario ? '$'+Number(c.salario).toLocaleString('es-CO') : 'No registrado';
        const txt = `${rsNombre} — Ingreso: ${c.fecha_ingreso?.substring(0,7)||'?'} — Salario: ${salario}`;
        if(c.estado === 'vigente'){
            if(boxOk){ boxOk.style.display='block'; if(txtOk) txtOk.textContent=txt; }
            if(boxWarn) boxWarn.style.display='none';
        } else {
            if(boxWarn){ boxWarn.style.display='block'; if(txtWarn) txtWarn.textContent=txt; }
            if(boxOk) boxOk.style.display='none';
        }
        const tipoEnt = document.getElementById('tipoEntidadSelect')?.value;
        if(tipoEnt === 'eps' && _clienteEpsId) actualizarListaEntidades('eps', _clienteEpsId);
        if(tipoEnt === 'arl' && _clienteArlId) actualizarListaEntidades('arl', _clienteArlId);
        // Quien Remite: usa _empresaNombre (empresa real del cliente), NO razón social
        const qrSel = document.getElementById('quienRemiteSelect');
        if(qrSel){
            qrSel.innerHTML = `<option value="${_clienteNombre}">${_clienteNombre} (Afiliado)</option>`
                + (_empresaNombre ? `<option value="${_empresaNombre}">${_empresaNombre} (Empresa)</option>` : '');
        }
    } else {
        _clienteEpsId = null;
        if(boxOk)   boxOk.style.display='none';
        if(boxWarn) boxWarn.style.display='none';
    }
}


function tipoIncapacidadCambiado(val){
    const MAP = {'accidente_laboral':'arl','accidente_transito':'arl','enfermedad_laboral':'arl'};
    const tipoEnt = MAP[val];
    const s = document.getElementById('tipoEntidadSelect');
    if(s){
        if(tipoEnt){
            s.value = tipoEnt;
            // Auto-seleccionar entidad del contrato (ARL o EPS)
            const autoId = tipoEnt==='arl' ? _clienteArlId : (tipoEnt==='eps' ? _clienteEpsId : null);
            actualizarListaEntidades(tipoEnt, autoId);
        } else if(val && s.value==='arl'){
            s.value=''; document.getElementById('entidadSelect').innerHTML='<option value="">Seleccionar...</option>';
        }
    }
}

async function guardarIncapacidad(){
    const form   = document.getElementById('formCrear');
    const isEdit = document.getElementById('formMethod').value === 'PUT';
    const incId  = document.getElementById('formId').value;
    const url    = isEdit ? `/admin/incapacidades/${incId}` : form.dataset.storeUrl;
    const fd = new FormData(form);
    if(isEdit) fd.set('_method','PUT');
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': TOKEN,
                'Accept': 'application/json'
            },
            body: fd
        });
        let data;
        try { data = await resp.json(); } catch(e){ throw new Error('Respuesta inesperada del servidor (HTTP '+resp.status+')'); }
        if(resp.ok && data.ok){
            cerrarModal('modalCrear');
            if (!isEdit && data.incapacidad_id) {
                setTimeout(() => { verDetalle(data.incapacidad_id); }, 250);
            } else {
                location.reload();
            }
        } else if(resp.status === 422 && data.errors) {
            // Mostrar errores de validación
            const msgs = Object.entries(data.errors).map(([f,e])=>`• ${f}: ${e[0]}`).join('\n');
            alert('⚠️ Hay campos requeridos incompletos:\n\n'+msgs);
        } else {
            alert('❌ Error: '+(data.message||JSON.stringify(data)));
        }
    } catch(e){ alert('❌ Error de red: '+e.message); }
}

// Lista dinamica de entidades
function actualizarListaEntidades(tipo, selId=null){
    const sel = document.getElementById('entidadSelect');
    const listas = {eps:EPS_LIST, arl:ARL_LIST, afp:AFP_LIST};
    const lista = listas[tipo]||[];
    sel.innerHTML = '<option value="">Seleccionar...</option>';
    lista.forEach(e=>{ sel.innerHTML+=`<option value="${e.id}" ${selId&&e.id==selId?'selected':''}>${e.nombre}</option>`; });
    if(tipo === 'eps' && _clienteEpsId && !selId) sel.value = _clienteEpsId;
}


// ── Cálculo automático de fecha fin ─────────────────────────────────────────
function calcularFechaFin(){
    const inicio = document.querySelector('[name=fecha_inicio]').value;
    const dias   = parseInt(document.querySelector('[name=dias_incapacidad]').value||0);
    if(inicio && dias>0){
        const d = new Date(inicio);
        d.setDate(d.getDate()+dias-1);
        document.getElementById('fechaFinInput').value = d.toISOString().substring(0,10);
    }
}

// Cerrar sugerencias al hacer clic fuera
document.addEventListener('click', e=>{
    if(!e.target.closest('#cedulaInput')) document.getElementById('clienteSugerencias').style.display='none';
});

// ── Alpine.js: filtros con debounce ─────────────────────────────────────────
function filtrosInc(){
    return {
        busqueda: document.getElementById('inp-busqueda')?.value || '',
        timer: null,
        init(){},
        debouncedSubmit(){
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                document.getElementById('filtro-form').submit();
            }, 400);
        }
    };
}

// ── Gestión rápida desde lista ───────────────────────────────────────────────
function abrirModalGestion(id, solo_seguimiento = false){
    verDetalle(id); // Abre el detalle y dentro se puede registrar gestión
}

// ── Documentos de familia (pestaña en modal detalle) ─────────────────────────
function cargarDocsFamilia(incId) {
    const box = document.getElementById('docsFamiliaContainer');
    if (!box) return;
    if (_docsFamiliaLoaded === incId) return; // ya cargado
    // Resetear flex del placeholder para que el contenido sea block
    box.style.cssText = 'padding:.25rem 0';
    box.innerHTML = '<div style="text-align:center;padding:1.5rem;color:#94a3b8">⏳ Cargando documentos...</div>';
    fetch(`/admin/incapacidades/${incId}/documentos-familia`)
        .then(r => r.json()).then(data => {
            _docsFamiliaLoaded = incId;
            if (!data.ok) {
                box.innerHTML = '<div style="text-align:center;padding:1.5rem;color:#ef4444">Error al cargar.</div>';
                return;
            }
            const TIPOS_DOC = {
                incapacidad_original: '📄 Inc. Original',
                historia_clinica:     '📋 Hist. Clínica',
                radicado_entidad:     '📮 Radicado Entidad',
                soporte_pago:         '💳 Soporte Pago',
                transcripcion:        '🏥 Transcripción',
                cedula:               '🪪 Cédula',
                examen:               '🔬 Examen',
                otro:                 '📎 Otro',
            };
            const iconExt = e => ({pdf:'📄',jpg:'🖼️',jpeg:'🖼️',png:'🖼️',webp:'🖼️'}[e]||'📎');

            let html = '';

            // ── Documentos globales del cliente ──────────────────────────────
            const globales = data.docs_globales || [];
            if (globales.length > 0) {
                html += `<div style="border:1px solid #bbf7d0;border-radius:10px;overflow:hidden;margin-bottom:.75rem">
                    <div style="background:#f0fdf4;padding:.55rem .85rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.4rem">
                        <span style="font-weight:700;color:#059669;font-size:.83rem">
                            🗂️ Documentos Globales del Cliente
                            <span style="font-size:.7rem;color:#64748b;font-weight:400;margin-left:.5rem">cargados en afiliación / contrato</span>
                        </span>
                        <span style="font-size:.7rem;color:#064e3b;background:#d1fae5;padding:.15rem .5rem;border-radius:999px">${globales.length} doc(s)</span>
                    </div>`;
                globales.forEach(doc => {
                    const tipoLabel = doc.tipo_label || doc.tipo_documento;
                    html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem .85rem;border-top:1px solid #f1f5f9;gap:.5rem">
                        <div style="flex:1;min-width:0">
                            <span style="font-weight:600;color:#374151;font-size:.82rem">${tipoLabel}</span>
                            <div style="font-size:.68rem;color:#94a3b8;margin-top:.1rem">
                                🕐 ${formatFechaLarga(doc.fecha)} · 👤 ${doc.subido_por||'Sistema'} · <span style="color:#059669">✓ Global</span>
                            </div>
                        </div>
                        <div style="display:flex;gap:.3rem;flex-shrink:0">
                            ${doc.url_ver
                                ? (doc.es_pdf
                                    ? `<button class="btn btn-info btn-sm" onclick="verPdfDoc('${doc.url_ver}','${tipoLabel}')" title="Ver PDF">👁 Ver</button>`
                                    : `<a href="${doc.url_ver}" target="_blank" class="btn btn-info btn-sm">👁 Ver</a>`)
                                : '<span style="font-size:.72rem;color:#94a3b8">Sin archivo</span>'
                            }
                        </div>
                    </div>`;
                });
                html += `</div>`;
            }

            // ── Documentos por incapacidad / prórroga ─────────────────────────
            const familia = data.familia || [];
            if (!familia.length && !globales.length) {
                html = '<div style="text-align:center;padding:1.5rem;color:#94a3b8">Sin documentos registrados.</div>';
                box.innerHTML = html;
                return;
            }

            familia.forEach(grupo => {
                const esPadre = grupo.es_padre;
                const headerBg     = esPadre ? '#eff6ff' : '#fdf4ff';
                const headerBorder = esPadre ? '#bfdbfe' : '#e9d5ff';
                const headerColor  = esPadre ? '#1e40af' : '#7c3aed';
                const periodo = grupo.fecha_inicio
                    ? `${grupo.fecha_inicio.substring(0,10)} → ${(grupo.fecha_terminacion||'').substring(0,10)}`
                    : '';

                html += `<div style="border:1px solid ${headerBorder};border-radius:10px;overflow:hidden;margin-bottom:.75rem">
                    <div style="background:${headerBg};padding:.55rem .85rem">
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.4rem">
                            <div>
                                <span style="font-weight:700;color:${headerColor};font-size:.83rem">
                                    ${esPadre ? '🏥 Incapacidad Original' : `🔁 ${grupo.label}`}
                                    <span style="font-size:.7rem;color:#64748b;font-weight:400;margin-left:.4rem">#${grupo.incapacidad_id}</span>
                                </span>
                                ${periodo ? `<div style="font-size:.7rem;color:#64748b;margin-top:.15rem">📅 ${periodo} · ${grupo.dias_incapacidad} días</div>` : ''}
                            </div>
                            <div style="display:flex;align-items:center;gap:.5rem">
                                <span style="font-size:.72rem;color:${headerColor};background:${esPadre?'#dbeafe':'#ede9fe'};padding:.15rem .5rem;border-radius:999px;font-weight:600">${grupo.total_docs} doc(s)</span>
                                <button class="btn btn-sm" style="background:#e0e7ff;color:#3730a3;font-size:.72rem;padding:.22rem .6rem;border-radius:6px"
                                    onclick="subirDocumentoEn(${grupo.incapacidad_id})">+ Agregar</button>
                            </div>
                        </div>
                    </div>`;

                if (!grupo.documentos?.length) {
                    html += `<div style="padding:.65rem .85rem;font-size:.78rem;color:#94a3b8;border-top:1px solid #f1f5f9">Sin documentos en este grupo.</div>`;
                } else {
                    grupo.documentos.forEach(doc => {
                        const tipoLabel = TIPOS_DOC[doc.tipo_documento] || doc.tipo_documento;
                        const ext = doc.extension || '';
                        html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem .85rem;border-top:1px solid #f1f5f9;gap:.5rem">
                            <div style="flex:1;min-width:0">
                                <span style="font-weight:600;color:#374151;font-size:.82rem">${iconExt(ext)} ${tipoLabel}</span>
                                ${doc.observacion ? `<span style="color:#94a3b8;font-size:.72rem;margin-left:.4rem">— ${doc.observacion}</span>` : ''}
                                <div style="font-size:.68rem;color:#94a3b8;margin-top:.1rem">
                                    🕐 ${formatFechaLarga(doc.fecha)} · 👤 ${doc.subido_por||'Sistema'}
                                </div>
                            </div>
                            <div style="display:flex;gap:.3rem;flex-shrink:0">
                                ${doc.es_pdf
                                    ? `<button class="btn btn-info btn-sm" onclick="verPdfDoc('${doc.url_ver}','${tipoLabel}')" title="Ver PDF">👁 Ver</button>`
                                    : `<a href="${doc.url_ver}" target="_blank" class="btn btn-info btn-sm" title="Ver imagen">👁 Ver</a>`
                                }
                                <a href="${doc.url_descargar}" class="btn btn-secondary btn-sm" title="Descargar">⬇</a>
                            </div>
                        </div>`;
                    });
                }
                html += `</div>`;
            });

            // Botón subir al FINAL, con ancho completo
            const totalDocs = data.total_documentos || 0;
            html += `<div style="margin-top:.5rem;padding-top:.5rem;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between">
                <span style="font-size:.72rem;color:#64748b">📁 ${totalDocs} doc(s) en incapacidades${globales.length?` · ${globales.length} global(es)`:''}</span>
                <button class="btn btn-success btn-sm" onclick="subirDocumento(${incId})">📤 Subir Documento</button>
            </div>`;

            box.innerHTML = html;
        }).catch(() => {
            box.style.cssText = '';
            box.innerHTML = '<div style="text-align:center;padding:1rem;color:#ef4444">Error al cargar documentos.</div>';
        });
}


// Subir doc en una incapacidad específica de la familia
function subirDocumentoEn(incId) {
    _docsFamiliaLoaded = null; // forzar recarga
    subirDocumento(incId);
}

// Visor de PDF en modal ligero
function verPdfDoc(url, titulo) {
    let ov = document.getElementById('modalPdfViewer');
    if (!ov) {
        ov = document.createElement('div');
        ov.id = 'modalPdfViewer';
        ov.className = 'modal-overlay';
        ov.innerHTML = `<div class="modal" style="max-width:860px;height:90vh;display:flex;flex-direction:column">
            <div class="modal-header">
                <h3 id="pdfViewerTitle" style="color:#fff;font-size:1rem">📄 Documento</h3>
                <button class="btn-close-modal" onclick="document.getElementById('modalPdfViewer').classList.remove('open')">✕</button>
            </div>
            <div style="flex:1;overflow:hidden;background:#525659">
                <iframe id="pdfViewerFrame" style="width:100%;height:100%;border:none"></iframe>
            </div>
            <div class="modal-footer" style="background:#f8fafc">
                <a id="pdfDownloadLink" class="btn btn-primary btn-sm" download>⬇ Descargar</a>
                <button class="btn btn-secondary btn-sm" onclick="document.getElementById('modalPdfViewer').classList.remove('open')">Cerrar</button>
            </div>
        </div>`;
        document.body.appendChild(ov);
    }
    document.getElementById('pdfViewerTitle').textContent = '📄 ' + titulo;
    document.getElementById('pdfViewerFrame').src = url;
    document.getElementById('pdfDownloadLink').href = url;
    ov.classList.add('open');
}

// Cerrar modal de detalle al clic en overlay
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('modalDetalle')?.addEventListener('click', e => {
        if (e.target === document.getElementById('modalDetalle')) cerrarModal('modalDetalle');
    });
    document.getElementById('modalCrear')?.addEventListener('click', e => {
        if (e.target === document.getElementById('modalCrear')) cerrarModal('modalCrear');
    });
});
</script>
@endpush
