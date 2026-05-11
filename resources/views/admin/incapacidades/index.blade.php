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
function cerrarModal(id){ document.getElementById(id).classList.remove('open'); }
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
            document.getElementById('modalCrearTitle').textContent = '✏️ Editar Incapacidad';
            document.getElementById('formCrear').action = `/admin/incapacidades/${id}`;
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('formId').value = id;
            document.getElementById('formCrear').reset();
            // Poblar campos
            const f = document.getElementById('formCrear');
            f.querySelector('[name=cedula_usuario]').value = inc.cedula_usuario;
            f.querySelector('[name=dias_incapacidad]').value = inc.dias_incapacidad;
            f.querySelector('[name=fecha_inicio]').value = inc.fecha_inicio?.substring(0,10)||'';
            f.querySelector('[name=fecha_terminacion]').value = inc.fecha_terminacion?.substring(0,10)||'';
            f.querySelector('[name=fecha_recibido]').value = inc.fecha_recibido?.substring(0,10)||'';
            f.querySelector('[name=tipo_incapacidad]').value = inc.tipo_incapacidad;
            f.querySelector('[name=tipo_entidad]').value = inc.tipo_entidad;
            f.querySelector('[name=diagnostico]') && (f.querySelector('[name=diagnostico]').value = inc.diagnostico||'');
            f.querySelector('[name=observacion]') && (f.querySelector('[name=observacion]').value = inc.observacion||'');
            // Razon social hidden
            const rsH = document.getElementById('razonSocialHidden');
            if(rsH) rsH.value = inc.razon_social_id || '';
            // Nombre cliente
            document.getElementById('nombreCliente').value = data.cliente
                ? [data.cliente.primer_nombre, data.cliente.primer_apellido].filter(Boolean).join(' ')
                : inc.cedula_usuario;
            actualizarListaEntidades(inc.tipo_entidad, inc.entidad_responsable_id);
            abrirModal('modalCrear');
        });
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
                    <button class="tab-btn" onclick="switchTab(this,'tabGestiones')">📞 Gestiones (${(inc.gestiones||[]).length})</button>
                    ${data.num_prorrogas>0?`<button class="tab-btn" onclick="switchTab(this,'tabProrrogas')">📄 Prórrogas (${data.num_prorrogas})</button>`:''}
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
                        <button class="btn btn-success btn-sm" onclick="subirDocumento(${inc.id})">📎 Subir Documento</button>
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
function registrarGestion(incId){
    const tipos = @json(\App\Models\Incapacidad::TIPOS_GESTION);
    const estados = @json(\App\Models\Incapacidad::ESTADOS);
    const optTipos = Object.entries(tipos).map(([k,v])=>`<option value="${k}">${v.label||v}</option>`).join('');
    const optEstados = Object.entries(estados).map(([k,v])=>`<option value="${k}">${v.label||v}</option>`).join('');

    const html = `
    <div class="modal-header" style="background:linear-gradient(135deg,#1e40af,#0891b2);border-radius:16px 16px 0 0">
        <div><h3 style="color:#fff;font-size:1rem;font-weight:700;margin:0">📞 Registrar Gestión</h3>
        <div style="font-size:.75rem;color:rgba(255,255,255,.75);margin-top:.15rem">Incapacidad #${incId}</div></div>
        <button class="btn-close-modal" onclick="document.getElementById('modalGestion').classList.remove('open')">×</button>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Tipo de Gestión *</label><select id="gTipo" class="form-control">${optTipos}</select></div>
        <div class="form-group"><label>Trámite realizado *</label><textarea id="gTramite" class="form-control" style="min-height:70px"></textarea></div>
        <div class="form-group"><label>Respuesta / Resultado</label><textarea id="gRespuesta" class="form-control" style="min-height:50px"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
            <div class="form-group"><label>Estado resultado</label><select id="gEstado" class="form-control"><option value="">Sin cambio</option>${optEstados}</select></div>
            <div class="form-group"><label>Recordar en fecha</label><input type="date" id="gRecordar" class="form-control"></div>
        </div>
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:#374151;margin-bottom:.5rem;cursor:pointer">
            <input type="checkbox" id="gFamilia"> Aplicar a toda la familia (padre + prórrogas)
        </label>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="document.getElementById('modalGestion').classList.remove('open')">Cancelar</button>
        <button class="btn btn-primary" onclick="enviarGestion(${incId})">💾 Guardar Gestión</button>
    </div>`;

    let overlay = document.getElementById('modalGestion');
    if(!overlay){
        overlay = document.createElement('div');
        overlay.id = 'modalGestion';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `<div class="modal" style="max-width:540px">${html}</div>`;
        document.body.appendChild(overlay);
    } else { overlay.querySelector('.modal').innerHTML = html; }
    overlay.classList.add('open');
}

function enviarGestion(incId){
    const body = {
        tipo: document.getElementById('gTipo').value,
        tramite: document.getElementById('gTramite').value,
        respuesta: document.getElementById('gRespuesta').value,
        estado_resultado: document.getElementById('gEstado').value,
        fecha_recordar: document.getElementById('gRecordar').value,
        aplica_a_familia: document.getElementById('gFamilia').checked ? 1 : 0,
        _token: TOKEN
    };
    fetch(`/admin/incapacidades/${incId}/gestion`, {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN},
        body: JSON.stringify(body)
    }).then(r=>r.json()).then(d=>{
        if(d.ok){ document.getElementById('modalGestion').classList.remove('open'); verDetalle(incId); }
        else alert('Error: '+(d.message||'Error desconocido'));
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
                <button class="btn-close-modal" onclick="document.getElementById('modalDoc').classList.remove('open')">✕</button>
            </div>
            <div class="modal-body">
                <div id="docListaExistente" style="margin-bottom:1rem"></div>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem">
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:.05em;margin-bottom:.7rem">➕ Agregar Documento</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
                        <div class="form-group">
                            <label>Tipo de Documento *</label>
                            <select id="docTipo" class="form-control">
                                <option value="incapacidad_original">Incapacidad Original</option>
                                <option value="historia_clinica">Historia Clínica</option>
                                <option value="radicado_entidad">Radicado Entidad</option>
                                <option value="soporte_pago">Soporte de Pago</option>
                                <option value="transcripcion">Transcripción</option>
                                <option value="otro">Otro</option>
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
                <button class="btn btn-secondary" onclick="document.getElementById('modalDoc').classList.remove('open')">Cerrar</button>
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
                msg.innerHTML='<span style="color:#059669">✅ Documento subido correctamente.</span>';
                document.getElementById('docArchivo').value='';
                document.getElementById('docObs').value='';
                cargarDocumentosExistentes(incId);
            } else { msg.innerHTML=`<span style="color:#ef4444">Error: ${d.message||''}</span>`; }
        }).catch(e=>{ btn.disabled=false; btn.textContent='📤 Subir Documento'; msg.innerHTML=`<span style="color:#ef4444">Error: ${e.message}</span>`; });
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
            if(!isEdit && data.incapacidad_id){ setTimeout(()=>{ subirDocumento(data.incapacidad_id); }, 250); }
            else { location.reload(); }
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
</script>
@endpush
