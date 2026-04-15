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
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:flex-start;justify-content:center;padding:1.5rem;overflow-y:auto;}
.modal-overlay.open{display:flex;}
.modal{background:#fff;border-radius:16px;width:100%;max-width:820px;box-shadow:0 20px 60px rgba(0,0,0,.2);margin:auto;}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;}
.modal-header h3{font-size:1.05rem;font-weight:700;color:#1e293b;}
.modal-body{padding:1.25rem;}
.modal-footer{padding:.9rem 1.25rem;border-top:1px solid #e2e8f0;display:flex;gap:.6rem;justify-content:flex-end;}
.btn-close-modal{background:none;border:none;font-size:1.3rem;cursor:pointer;color:#94a3b8;padding:.2rem;}
.form-group{margin-bottom:.85rem;}
.form-group label{display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.3rem;}
.form-group input,.form-group select,.form-group textarea{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:.45rem .7rem;font-size:.83rem;font-family:inherit;}
.form-group textarea{min-height:70px;resize:vertical;}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem;}
.section-title{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;border-bottom:1px solid #e2e8f0;padding-bottom:.4rem;margin-bottom:.8rem;margin-top:.5rem;}
/* Timeline gestiones */
.timeline{display:flex;flex-direction:column;gap:.6rem;max-height:280px;overflow-y:auto;padding-right:.25rem;}
.timeline-item{display:flex;gap:.75rem;align-items:flex-start;}
.tl-dot{width:32px;height:32px;border-radius:50%;background:#eff6ff;border:2px solid #bfdbfe;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.tl-content{flex:1;background:#f8fafc;border-radius:10px;padding:.55rem .75rem;border:1px solid #e2e8f0;}
.tl-content .tl-tipo{font-size:.72rem;font-weight:700;color:#2563eb;}
.tl-content .tl-tramite{font-size:.8rem;color:#374151;margin:.2rem 0;}
.tl-content .tl-meta{font-size:.68rem;color:#94a3b8;}
/* Tabs */
.tabs{display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:1rem;}
.tab-btn{padding:.5rem 1rem;font-size:.82rem;font-weight:600;background:none;border:none;cursor:pointer;color:#64748b;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s;}
.tab-btn.active{color:#2563eb;border-bottom-color:#2563eb;}
.tab-pane{display:none;} .tab-pane.active{display:block;}
/* Prórrogas */
.proroga-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:.85rem 1rem;margin-bottom:.6rem;}
.proroga-card h5{font-size:.82rem;font-weight:700;color:#1e293b;margin-bottom:.5rem;}
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
    <div class="kpi danger"><div class="num">{{ $sinGestion10dias }}</div><div class="lbl">Sin gestión +10 días</div></div>
    <div class="kpi"><div class="num">{{ $resumen->get('recibido',0) }}</div><div class="lbl">Recibidas</div></div>
    <div class="kpi warn"><div class="num">{{ $resumen->get('en_tramite',0) }}</div><div class="lbl">En Trámite</div></div>
    <div class="kpi ok"><div class="num">{{ $resumen->get('pagado_afiliado',0) }}</div><div class="lbl">Pagadas</div></div>
</div>

{{-- Filtros --}}
<div class="filter-bar">
    <form method="GET" style="display:contents">
        <div><label>Cédula</label><input name="cedula" value="{{ request('cedula') }}" placeholder="Buscar..."></div>
        <div>
            <label>Tipo</label>
            <select name="tipo_incapacidad">
                <option value="">Todos</option>
                @foreach(\App\Models\Incapacidad::TIPOS_INCAPACIDAD as $k=>$v)
                <option value="{{ $k }}" @selected(request('tipo_incapacidad')==$k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Entidad</label>
            <select name="tipo_entidad">
                <option value="">Todas</option>
                @foreach(\App\Models\Incapacidad::TIPOS_ENTIDAD as $k=>$v)
                <option value="{{ $k }}" @selected(request('tipo_entidad')==$k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Estado</label>
            <select name="estado">
                <option value="">Todos</option>
                @foreach(\App\Models\Incapacidad::ESTADOS as $k=>$v)
                <option value="{{ $k }}" @selected(request('estado')==$k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Encargado</label>
            <select name="quien_recibe_id">
                <option value="">Todos</option>
                @foreach($trabajadores as $t)
                <option value="{{ $t->id }}" @selected(request('quien_recibe_id')==$t->id)>{{ $t->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div><label>Desde</label><input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"></div>
        <div><label>Hasta</label><input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"></div>
        <div style="display:flex;align-items:flex-end;gap:.4rem">
            <label style="display:flex;align-items:center;gap:.3rem;font-size:.78rem;cursor:pointer">
                <input type="checkbox" name="con_cerradas" value="1" @checked(request('con_cerradas'))> Ver cerradas
            </label>
        </div>
        <div style="display:flex;align-items:flex-end;gap:.4rem">
            <button type="submit" class="btn btn-primary btn-sm">🔍 Filtrar</button>
            <a href="{{ route('admin.incapacidades.index') }}" class="btn btn-secondary btn-sm">✕</a>
        </div>
    </form>
</div>

{{-- Tabla principal --}}
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Semáforo</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Entidad</th>
                    <th>Días</th>
                    <th>Prórrogas</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th>Encargado</th>
                    <th>Última Gestión</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($incapacidades as $inc)
            @php
                $color = $inc->colorSemaforo();
                $diasGestion = $inc->diasDesdeUltimaGestion();
                $alert180 = $inc->alertaDias180();
                $totalDias = $inc->totalDiasFamilia();
                $numPrr = $inc->numeroProrrogas();
            @endphp
            <tr>
                <td>
                    <span class="semaforo sem-{{ $color }}">
                        {{ $inc->iconoSemaforo() }} {{ $diasGestion }}d
                    </span>
                    @if($alert180)
                    <br><span class="alerta-180">⚠️ +180 días EPS</span>
                    @endif
                </td>
                <td>
                    <div style="font-weight:600;font-size:.82rem">{{ $inc->nombre_cliente ?? $inc->cedula_usuario }}</div>
                    <div style="font-size:.72rem;color:#64748b">{{ $inc->cedula_usuario }}</div>
                    <div style="font-size:.7rem;color:#94a3b8">{{ $inc->quien_remite }}</div>
                </td>
                <td>
                    <span style="font-size:.78rem">{{ $inc->tipoIncapacidadLabel() }}</span>
                    @if($inc->prorroga)<br><span class="badge badge-info">Prórroga doc.</span>@endif
                    @if($inc->transcripcion_requerida && !$inc->transcripcion_completada)
                    <br><span class="badge badge-warning">⚕️ Transcripción Pdte.</span>
                    @endif
                </td>
                <td>
                    <span class="badge badge-secondary">{{ strtoupper($inc->tipo_entidad) }}</span>
                    <div style="font-size:.72rem;color:#64748b;margin-top:.2rem">{{ Str::limit($inc->entidad_nombre,20) }}</div>
                </td>
                <td style="text-align:center">
                    <strong>{{ $inc->dias_incapacidad }}</strong>
                    @if($numPrr>0)
                    <div style="font-size:.7rem;color:#2563eb">Total: {{ $totalDias }}d</div>
                    @endif
                    <div style="font-size:.7rem;color:#94a3b8">
                        {{ $inc->fecha_inicio?->format('d/m/y') }}
                    </div>
                </td>
                <td style="text-align:center">
                    @if($numPrr>0)
                    <span class="badge badge-primary">{{ $numPrr }} prórroga{{ $numPrr>1?'s':'' }}</span>
                    @else
                    <span style="color:#94a3b8;font-size:.75rem">—</span>
                    @endif
                </td>
                <td><span class="badge badge-{{ match($inc->estado){
                    'recibido'=>'secondary','radicado'=>'info','en_tramite'=>'warning',
                    'autorizado'=>'success','liquidado'=>'primary',
                    'pagado_afiliado'=>'success','rechazado'=>'danger',default=>'secondary'} }}">{{ $inc->estadoLabel() }}</span></td>
                <td><span class="badge badge-{{ $inc->estadoPagoColor() }}">{{ $inc->estadoPagoLabel() }}</span>
                @if($inc->valor_esperado)
                <div style="font-size:.7rem;color:#059669;font-weight:600">${{ number_format($inc->valor_esperado,0,',','.') }}</div>
                @endif
                </td>
                <td style="font-size:.78rem">{{ $inc->quienRecibe?->nombre ?? '—' }}</td>
                <td>
                    @php $ult = $inc->gestiones()->first(); @endphp
                    @if($ult)
                    <div style="font-size:.72rem;font-weight:600;color:#2563eb">{{ \App\Models\Incapacidad::TIPOS_GESTION[$ult->tipo]??$ult->tipo }}</div>
                    <div style="font-size:.7rem;color:#64748b">{{ $ult->created_at->format('d/m/y H:i') }}</div>
                    @else
                    <span style="color:#ef4444;font-size:.75rem">Sin gestión</span>
                    @endif
                </td>
                <td>
                    <div style="display:flex;gap:.3rem;flex-wrap:wrap">
                        <button class="btn btn-info btn-sm" onclick="verDetalle({{ $inc->id }})">👁 Ver</button>
                        <button class="btn btn-warning btn-sm" onclick="abrirModalEditar({{ $inc->id }})">✏️</button>
                        <button class="btn btn-success btn-sm" onclick="abrirModalProroga({{ $inc->id }})">➕ Prórr.</button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="11" style="text-align:center;padding:2rem;color:#94a3b8">No hay incapacidades registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:.75rem 1rem;border-top:1px solid #f1f5f9">
        {{ $incapacidades->links() }}
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- MODAL CREAR/EDITAR INCAPACIDAD --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="modalCrear">
<div class="modal">
    <div class="modal-header">
        <h3 id="modalCrearTitle">➕ Nueva Incapacidad</h3>
        <button class="btn-close-modal" onclick="cerrarModal('modalCrear')">✕</button>
    </div>
    <form id="formCrear" method="POST" action="{{ route('admin.incapacidades.store') }}">
    @csrf
    <input type="hidden" name="_method" id="formMethod" value="POST">
    <input type="hidden" name="_id" id="formId">
    <input type="hidden" name="incapacidad_padre_id" id="padreId">
    <div class="modal-body">

        <div class="section-title">Datos del Afiliado</div>
        <div class="form-row">
            <div class="form-group">
                <label>Cédula *</label>
                <input type="text" name="cedula_usuario" id="cedulaInput" required placeholder="Buscar cédula..."
                       oninput="buscarCliente(this.value)">
                <div id="clienteSugerencias" style="display:none;background:#fff;border:1px solid #e2e8f0;border-radius:8px;position:absolute;z-index:100;width:280px;box-shadow:0 4px 12px rgba(0,0,0,.1)"></div>
            </div>
            <div class="form-group">
                <label>Nombre del Cliente</label>
                <input type="text" id="nombreCliente" readonly style="background:#f8fafc">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Contrato</label>
                <select name="contrato_id" id="contratoSelect">
                    <option value="">Seleccionar...</option>
                </select>
            </div>
            <div class="form-group">
                <label>Encargado *</label>
                <select name="quien_recibe_id" required>
                    <option value="">Seleccionar...</option>
                    @foreach($trabajadores as $t)
                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Quien Remite</label>
                <input type="text" name="quien_remite" id="quienRemiteInput" placeholder="Empresa o cliente (auto)">
            </div>
            <div class="form-group">
                <label>Fecha Recibido *</label>
                <input type="date" name="fecha_recibido" required value="{{ date('Y-m-d') }}">
            </div>
        </div>

        <div class="section-title">Datos de la Incapacidad</div>
        <div class="form-row">
            <div class="form-group">
                <label>Tipo de Incapacidad *</label>
                <select name="tipo_incapacidad" required>
                    <option value="">Seleccionar...</option>
                    @foreach(\App\Models\Incapacidad::TIPOS_INCAPACIDAD as $k=>$v)
                    <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Días *</label>
                <input type="number" name="dias_incapacidad" min="1" required oninput="calcularFechaFin()">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Fecha Inicio *</label>
                <input type="date" name="fecha_inicio" id="fechaInicioInput" required oninput="calcularFechaFin()">
            </div>
            <div class="form-group">
                <label>Fecha Terminación</label>
                <input type="date" name="fecha_terminacion" id="fechaFinInput">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.4rem">
                    <input type="checkbox" name="prorroga" value="1" id="chkProroga"> ¿Viene marcada como prórroga en el documento?
                </label>
                <small style="color:#64748b;font-size:.72rem">Si es prórroga, la EPS no descuenta los 2 primeros días.</small>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.4rem">
                    <input type="checkbox" name="transcripcion_requerida" value="1"> ¿Requiere transcripción IPS → Entidad?
                </label>
            </div>
        </div>
        <div class="form-group">
            <label>Diagnóstico (CIE-10 o descripción)</label>
            <input type="text" name="diagnostico">
        </div>

        <div class="section-title">Entidad Responsable</div>
        <div class="form-row">
            <div class="form-group">
                <label>Tipo Entidad *</label>
                <select name="tipo_entidad" required onchange="actualizarListaEntidades(this.value)">
                    <option value="">Seleccionar...</option>
                    @foreach(\App\Models\Incapacidad::TIPOS_ENTIDAD as $k=>$v)
                    <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Entidad Responsable</label>
                <select name="entidad_responsable_id" id="entidadSelect">
                    <option value="">Seleccionar tipo primero...</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Razón Social (NIT al radicar)</label>
                <select name="razon_social_id">
                    <option value="">No aplica</option>
                    @foreach($razonesSociales as $rs)
                    <option value="{{ $rs->id }}">{{ $rs->razon_social }} ({{ $rs->id }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Observación</label>
            <textarea name="observacion"></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalCrear')">Cancelar</button>
        <button type="submit" class="btn btn-primary">💾 Guardar</button>
    </div>
    </form>
</div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- MODAL DETALLE + GESTIONES --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="modalDetalle">
<div class="modal" style="max-width:920px">
    <div class="modal-header">
        <div>
            <h3 id="detalleTitle">Detalle de Incapacidad</h3>
            <div id="detalleSubtitle" style="font-size:.78rem;color:#64748b;margin-top:.2rem"></div>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center">
            <div id="detalleSemaforo"></div>
            <button class="btn-close-modal" onclick="cerrarModal('modalDetalle')">✕</button>
        </div>
    </div>
    <div class="modal-body" id="detalleCuerpo">
        <div style="text-align:center;padding:2rem;color:#94a3b8">Cargando...</div>
    </div>
</div>
</div>

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
function labelEstado(key){ return ESTADOS_INC[key] || key; }
function labelEstadoPago(key){ return ESTADOS_PAGO_INC[key] || key; }
function formatFecha(str){ return str ? str.substring(0,10) : '—'; }

// ── Modales ──────────────────────────────────────────────────────────────────
function cerrarModal(id){ document.getElementById(id).classList.remove('open'); }
function abrirModal(id) { document.getElementById(id).classList.add('open'); }

function abrirModalCrear(){
    document.getElementById('modalCrearTitle').textContent = '➕ Nueva Incapacidad';
    document.getElementById('formCrear').action = "{{ route('admin.incapacidades.store') }}";
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('formId').value = '';
    document.getElementById('padreId').value = '';
    document.getElementById('formCrear').reset();
    abrirModal('modalCrear');
}

function abrirModalProroga(padreId){
    abrirModalCrear();
    document.getElementById('modalCrearTitle').textContent = '➕ Registrar Prórroga';
    document.getElementById('padreId').value = padreId;
    document.getElementById('chkProroga').checked = true;
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
            f.querySelector('[name=diagnostico]').value = inc.diagnostico||'';
            f.querySelector('[name=observacion]').value = inc.observacion||'';
            document.getElementById('chkProroga').checked = inc.prorroga;
            document.getElementById('nombreCliente').value = data.cliente
                ? [data.cliente.primer_nombre,data.cliente.primer_apellido].join(' ') : inc.cedula_usuario;
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
                `· Recibida: ${inc.fecha_recibido?.substring(0,10)||'—'}`;

            const colClass = {verde:'sem-verde',amarillo:'sem-amarillo',rojo:'sem-rojo',gris:'sem-gris'}[data.semaforo]||'sem-gris';
            document.getElementById('detalleSemaforo').innerHTML =
                `<span class="semaforo ${colClass}">${data.icono} ${data.dias_gestion}d sin gestión</span>`;

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
                        <div class="tl-meta">${g.user?.nombre||'Sistema'} · ${g.created_at?.substring(0,16)||''} ${g.fecha_recordar?`· 🔔 Recordar: ${g.fecha_recordar?.substring(0,10)}`:''}${g.estado_resultado?` · Estado: ${g.estado_resultado}`:''}</div>
                    </div>
                </div>`).join('');

            // Prórrogas
            const prorrogas = (inc.prorrogas||[]).map(p=>{
                const estadoColor = {pagado_afiliado:'success',rechazado:'danger',autorizado:'success',liquidado:'primary',en_tramite:'warning'}[p.estado_pago]||'warning';
                return `
                <div class="proroga-card">
                    <h5>📄 Prórroga #${p.numero_proroga} — ${labelTipo(p.tipo_incapacidad)} — ${p.dias_incapacidad} días</h5>
                    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.5rem">
                        <span class="badge badge-secondary">${TIPOS_ENTIDAD[p.tipo_entidad]||p.tipo_entidad?.toUpperCase()}</span>
                        ${p.entidad_nombre?`<span class="badge badge-secondary">${p.entidad_nombre}</span>`:''}
                        <span class="badge badge-${estadoColor}">${labelEstadoPago(p.estado_pago)}</span>
                        ${p.valor_esperado?`<span style="font-size:.75rem;font-weight:600;color:#059669">💰 Esperado: $${Number(p.valor_esperado).toLocaleString('es-CO')}</span>`:''}
                    </div>
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:.6rem">📅 ${formatFecha(p.fecha_inicio)} → ${formatFecha(p.fecha_terminacion)}</div>
                    <div style="display:flex;gap:.4rem">
                        <button class="btn btn-info btn-sm" onclick="registrarGestion(${p.id})">📞 Gestión</button>
                        <button class="btn btn-primary btn-sm" onclick="registrarPago(${p.id})">💰 Pago</button>
                    </div>
                </div>`;
            }).join('');

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
                    <div class="form-row">
                        <div><label class="section-title">Tipo</label><p>${labelTipo(inc.tipo_incapacidad)}</p></div>
                        <div><label class="section-title">Entidad</label><p>${(TIPOS_ENTIDAD[inc.tipo_entidad]||inc.tipo_entidad?.toUpperCase())} — <strong>${inc.entidad_nombre||'N/A'}</strong></p></div>
                        <div><label class="section-title">Días</label><p><strong style="font-size:1.1rem">${inc.dias_incapacidad}</strong>${data.familia_dias>inc.dias_incapacidad?` <span style="color:#2563eb;font-size:.8rem">(Total familia: ${data.familia_dias}d)</span>`:''}</p></div>
                        <div><label class="section-title">Período</label><p>${formatFecha(inc.fecha_inicio)} → ${formatFecha(inc.fecha_terminacion)}</p></div>
                        <div><label class="section-title">Radicado</label><p>${inc.numero_radicado||'<span style="color:#94a3b8">Sin radicar</span>'} ${inc.fecha_radicado?'('+formatFecha(inc.fecha_radicado)+')':''}</p></div>
                        <div><label class="section-title">Razón Social</label><p>${inc.razon_social_nombre||'—'}</p></div>
                        <div><label class="section-title">Diagnóstico</label><p>${inc.diagnostico||'<span style="color:#94a3b8">—</span>'}</p></div>
                        <div><label class="section-title">Valor Esperado</label><p style="font-weight:700;color:#059669;font-size:1.05rem">${val}</p></div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:.6rem;margin:.75rem 0;background:#f8fafc;padding:.75rem;border-radius:10px;border:1px solid #e2e8f0">
                        <div><span style="font-size:.7rem;color:#64748b;display:block;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Estado</span><span style="font-size:.85rem;font-weight:600">${labelEstado(inc.estado)}</span></div>
                        <div><span style="font-size:.7rem;color:#64748b;display:block;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Pago</span><span style="font-size:.85rem;font-weight:600">${labelEstadoPago(inc.estado_pago)}</span></div>
                        <div><span style="font-size:.7rem;color:#64748b;display:block;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Recibido</span><span style="font-size:.85rem">${formatFecha(inc.fecha_recibido)}</span></div>
                        ${inc.prorroga?'<div><span style="font-size:.7rem;color:#2563eb;font-weight:700">✓ Doc. prórroga</span></div>':''}
                        ${inc.transcripcion_requerida&&!inc.transcripcion_completada?'<div><span style="font-size:.7rem;color:#d97706;font-weight:700">⚕️ Transcripción pdte.</span></div>':''}
                    </div>
                    ${inc.observacion?`<div class="form-group" style="margin-top:.5rem"><label class="section-title">Observación</label><p style="font-size:.82rem;color:#374151">${inc.observacion}</p></div>`:''}
                    <div style="display:flex;gap:.5rem;margin-top:1rem;flex-wrap:wrap">
                        <button class="btn btn-primary btn-sm" onclick="registrarGestion(${inc.id})">📞 Nueva Gestión</button>
                        <button class="btn btn-success btn-sm" onclick="subirDocumento(${inc.id})">📎 Subir Documento</button>
                        <button class="btn btn-warning btn-sm" onclick="abrirModalEditar(${inc.id}); cerrarModal('modalDetalle')">✏️ Editar</button>
                        <button class="btn btn-primary btn-sm" onclick="abrirModalProroga(${inc.id}); cerrarModal('modalDetalle')">➕ Prórroga</button>
                    </div>
                </div>

                <div id="tabGestiones" class="tab-pane">
                    <button class="btn btn-primary btn-sm" style="margin-bottom:.8rem" onclick="registrarGestion(${inc.id})">📞 Nueva Gestión</button>
                    <div class="timeline">${gestiones||'<div style="color:#94a3b8;font-size:.82rem">Sin gestiones aún.</div>'}</div>
                </div>

                ${data.num_prorrogas>0?`<div id="tabProrrogas" class="tab-pane">${prorrogas}</div>`:''}

                <div id="tabPago" class="tab-pane">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-bottom:1rem">
                        <div class="kpi"><div class="num" style="font-size:1.1rem">${inc.estado_pago||'—'}</div><div class="lbl">Estado Pago</div></div>
                        <div class="kpi"><div class="num" style="font-size:1.1rem">${inc.valor_pago?'$'+Number(inc.valor_pago).toLocaleString('es-CO'):'—'}</div><div class="lbl">Valor Pagado</div></div>
                        <div class="kpi"><div class="num" style="font-size:1.1rem">${val}</div><div class="lbl">Valor Esperado</div></div>
                        <div class="kpi"><div class="num" style="font-size:1.1rem">${inc.pagado_a||'—'}</div><div class="lbl">Pagado a</div></div>
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
    const optTipos = Object.entries(tipos).map(([k,v])=>`<option value="${k}">${v}</option>`).join('');
    const optEstados = Object.entries(estados).map(([k,v])=>`<option value="${k}">${v}</option>`).join('');

    const html = `<div style="padding:1rem">
        <h4 style="margin-bottom:1rem">📞 Registrar Gestión — Incapacidad #${incId}</h4>
        <div class="form-group"><label>Tipo de Gestión *</label><select id="gTipo" style="width:100%;padding:.4rem;border:1px solid #d1d5db;border-radius:6px">${optTipos}</select></div>
        <div class="form-group"><label>Trámite realizado *</label><textarea id="gTramite" style="width:100%;min-height:70px;padding:.4rem;border:1px solid #d1d5db;border-radius:6px"></textarea></div>
        <div class="form-group"><label>Respuesta / Resultado</label><textarea id="gRespuesta" style="width:100%;min-height:50px;padding:.4rem;border:1px solid #d1d5db;border-radius:6px"></textarea></div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
            <div class="form-group"><label>Estado resultado</label><select id="gEstado" style="width:100%;padding:.4rem;border:1px solid #d1d5db;border-radius:6px"><option value="">Sin cambio</option>${optEstados}</select></div>
            <div class="form-group"><label>Recordar en fecha</label><input type="date" id="gRecordar" style="width:100%;padding:.4rem;border:1px solid #d1d5db;border-radius:6px"></div>
        </div>
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;margin-bottom:1rem">
            <input type="checkbox" id="gFamilia"> Aplicar a toda la familia (padre + prórrogas)
        </label>
        <div style="display:flex;gap:.5rem;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('open')">Cancelar</button>
            <button class="btn btn-primary" onclick="enviarGestion(${incId})">💾 Guardar Gestión</button>
        </div>
    </div>`;

    let overlay = document.getElementById('modalGestion');
    if(!overlay){
        overlay = document.createElement('div');
        overlay.id = 'modalGestion';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `<div class="modal" style="max-width:560px">${html}</div>`;
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

// ── Subir documento ──────────────────────────────────────────────────────────
function subirDocumento(incId){
    const html = `<div style="padding:1rem">
        <h4 style="margin-bottom:1rem">📎 Subir Documento — Incapacidad #${incId}</h4>
        <div class="form-group"><label>Tipo de Documento *</label>
            <select id="docTipo" style="width:100%;padding:.4rem;border:1px solid #d1d5db;border-radius:6px">
                <option value="incapacidad_original">Incapacidad Original</option>
                <option value="historia_clinica">Historia Clínica</option>
                <option value="radicado_entidad">Radicado Entidad</option>
                <option value="soporte_pago">Soporte de Pago</option>
                <option value="transcripcion">Transcripción</option>
                <option value="otro">Otro</option>
            </select></div>
        <div class="form-group"><label>Archivo *</label><input type="file" id="docArchivo" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
        <div class="form-group"><label>Observación</label><input type="text" id="docObs" style="width:100%;padding:.4rem;border:1px solid #d1d5db;border-radius:6px"></div>
        <div style="display:flex;gap:.5rem;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('open')">Cancelar</button>
            <button class="btn btn-primary" onclick="enviarDocumento(${incId})">📤 Subir</button>
        </div>
    </div>`;

    let overlay = document.getElementById('modalDoc');
    if(!overlay){
        overlay = document.createElement('div');
        overlay.id = 'modalDoc';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `<div class="modal" style="max-width:480px">${html}</div>`;
        document.body.appendChild(overlay);
    } else { overlay.querySelector('.modal').innerHTML = html; }
    overlay.classList.add('open');
}

function enviarDocumento(incId){
    const fd = new FormData();
    fd.append('tipo_documento', document.getElementById('docTipo').value);
    fd.append('observacion', document.getElementById('docObs').value);
    fd.append('archivo', document.getElementById('docArchivo').files[0]);
    fd.append('_token', TOKEN);

    fetch(`/admin/incapacidades/${incId}/documento`,{method:'POST',body:fd})
        .then(r=>r.json()).then(d=>{
            if(d.ok){ document.getElementById('modalDoc').classList.remove('open'); alert('✅ Documento subido.'); }
            else alert('Error: '+(d.message||''));
        });
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
                box.innerHTML = data.map(c=>`
                    <div onclick="seleccionarCliente('${c.cedula}','${c.primer_nombre||''} ${c.primer_apellido||''}', '${c.cod_empresa||''}')"
                         style="padding:.45rem .75rem;cursor:pointer;font-size:.82rem;border-bottom:1px solid #f1f5f9;hover:background:#f8fafc">
                        <strong>${c.cedula}</strong> — ${c.primer_nombre||''} ${c.primer_apellido||''}
                    </div>`).join('');
                box.style.display='block';
            });
    }, 350);
}

function seleccionarCliente(cedula, nombre, codEmpresa){
    document.getElementById('cedulaInput').value = cedula;
    document.getElementById('nombreCliente').value = nombre;
    document.getElementById('clienteSugerencias').style.display='none';
    // Cargar contratos
    fetch(`/admin/incapacidades/api/contratos?cedula=${encodeURIComponent(cedula)}`)
        .then(r=>r.json()).then(contratos=>{
            const sel = document.getElementById('contratoSelect');
            sel.innerHTML = '<option value="">Sin contrato específico</option>';
            contratos.forEach(c=>{ sel.innerHTML+=`<option value="${c.id}">#${c.id} — ${c.estado} (${c.fecha_ingreso?.substring(0,10)||''})</option>`; });
        });
}

// ── Lista dinámica de entidades ──────────────────────────────────────────────
function actualizarListaEntidades(tipo, selId=null){
    const sel = document.getElementById('entidadSelect');
    const listas = {eps:EPS_LIST, arl:ARL_LIST, afp:AFP_LIST};
    const lista = listas[tipo]||[];
    sel.innerHTML = '<option value="">Seleccionar...</option>';
    lista.forEach(e=>{ sel.innerHTML+=`<option value="${e.id}" ${selId&&e.id==selId?'selected':''}>${e.nombre}</option>`; });
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
</script>
@endpush
