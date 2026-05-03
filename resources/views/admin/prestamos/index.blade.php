@extends('layouts.app')
@section('modulo', 'Préstamos')

@php
$fmt = fn($v) => '$'.number_format($v ?? 0, 0, ',', '.');
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
@endphp

@section('contenido')
<style>
/* ── Layout ── */
.prest-wrap { display:flex; flex-direction:column; gap:.8rem; }

/* ── Header ── */
.prest-header {
    background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#1e40af 100%);
    border-radius:14px; padding:1rem 1.4rem; color:#fff;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.7rem;
}
.prest-title { font-size:1.3rem; font-weight:800; }
.prest-sub   { font-size:.77rem; color:#94a3b8; margin-top:.15rem; }

/* ── Cards ── */
.cards-row { display:grid; grid-template-columns:repeat(4,1fr); gap:.7rem; }
@media(max-width:768px){ .cards-row{grid-template-columns:1fr 1fr;} }
.card-item { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:.85rem 1rem; }
.ci-label { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; }
.ci-val   { font-size:1.45rem; font-weight:800; color:#0f172a; font-family:monospace; margin-top:.2rem; }
.card-danger { border-top:3px solid #dc2626; }
.card-warn   { border-top:3px solid #d97706; }
.card-info   { border-top:3px solid #2563eb; }
.card-red-val  .ci-val { color:#dc2626; }
.card-oran-val .ci-val { color:#d97706; }
.card-blue-val .ci-val { color:#2563eb; }

/* ── Tabs ── */
.tabs-bar { display:flex; gap:.45rem; align-items:center; }
.tab-link {
    padding:.38rem 1rem; border-radius:8px; font-size:.82rem; font-weight:700;
    text-decoration:none; border:1.5px solid #e2e8f0; background:#fff; color:#64748b;
    transition:.15s;
}
.tab-link.active { background:#1e40af; border-color:#1e40af; color:#fff; }

/* ── Filtros ── */
.filtros {
    background:#fff; border:1px solid #e2e8f0; border-radius:12px;
    padding:.7rem 1rem; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;
}
.filtros input { padding:.38rem .7rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.82rem; outline:none; }
.filtros input:focus { border-color:#3b82f6; }
.btn-filtrar { padding:.38rem .95rem; background:#1e40af; color:#fff; border:none; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; }
.btn-limpiar { padding:.38rem .8rem; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; text-decoration:none; }

/* ── Tabla ── */
.tbl-wrap { overflow-x:auto; border-radius:12px; border:1px solid #e2e8f0; background:#fff; }
.tbl-prest { width:100%; border-collapse:collapse; font-size:.8rem; white-space:nowrap; }
.tbl-prest thead th { background:#0f172a; color:#fff; padding:.5rem .7rem; font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; font-weight:600; }
.tbl-prest tbody tr { border-bottom:1px solid #f1f5f9; transition:background .12s; }
.tbl-prest tbody tr:hover { background:#f8fafc; }
.tbl-prest td { padding:.5rem .7rem; vertical-align:middle; color:#1e293b; }

/* ── Semáforo ── */
.sem { display:inline-block; width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.sem-gris     { background:#94a3b8; }
.sem-verde    { background:#16a34a; }
.sem-amarillo { background:#d97706; }
.sem-rojo     { background:#dc2626; box-shadow:0 0 5px #dc262688; }

/* ── Montos ── */
.monto-deuda { font-weight:700; color:#dc2626; font-family:monospace; }
.monto-abono { font-weight:600; color:#16a34a; font-family:monospace; }

/* ── Botones acción ── */
.btn-sm { padding:.25rem .65rem; border-radius:6px; font-size:.73rem; font-weight:700; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:.2rem; }
.btn-ver   { background:#dbeafe; color:#1d4ed8; }
.btn-abono { background:#dcfce7; color:#15803d; }
.btn-gestion { background:#f3e8ff; color:#7c3aed; }

/* ── Empty ── */
.empty-state { text-align:center; padding:3rem; color:#94a3b8; }

/* ── Modales ── */
.modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.modal-bg.open { display:flex; }
.modal-box { background:#fff; border-radius:16px; padding:1.5rem; width:min(440px,96vw); max-height:92vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.modal-title { font-size:.97rem; font-weight:800; color:#0f172a; margin-bottom:1rem; padding-bottom:.55rem; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
.modal-close { background:none; border:none; font-size:1.2rem; cursor:pointer; color:#94a3b8; }
.modal-close:hover { color:#ef4444; }
.form-grp { display:flex; flex-direction:column; gap:.2rem; margin-bottom:.75rem; }
.form-grp label { font-size:.7rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.04em; }
.form-grp input, .form-grp select, .form-grp textarea {
    padding:.45rem .65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.85rem; outline:none;
}
.form-grp input:focus, .form-grp select:focus, .form-grp textarea:focus { border-color:#3b82f6; }
.form-grp textarea { resize:vertical; min-height:70px; }
.btn-save { background:linear-gradient(135deg,#1e40af,#2563eb); color:#fff; border:none; border-radius:10px; padding:.55rem 1.4rem; font-size:.88rem; font-weight:700; cursor:pointer; width:100%; }
</style>

<div class="prest-wrap">

{{-- ══ HEADER ══ --}}
<div class="prest-header">
    <div>
        <div class="prest-title">📋 Préstamos / Cartera</div>
        <div class="prest-sub">Gestión de servicios prestados pendientes de cobro</div>
    </div>
</div>

{{-- ══ CARDS RESUMEN ══ --}}
<div class="cards-row">
    <div class="card-item card-info card-blue-val">
        <div class="ci-label">📋 Préstamos activos</div>
        <div class="ci-val">{{ $totalPrestamos }}</div>
    </div>
    <div class="card-item card-danger card-red-val">
        <div class="ci-label">💸 Total deuda</div>
        <div class="ci-val">{{ $fmt($totalDeudaInd + $totalDeudaEmp) }}</div>
    </div>
    <div class="card-item card-warn card-oran-val">
        <div class="ci-label">🏢 Deuda empresas</div>
        <div class="ci-val">{{ $fmt($totalDeudaEmp) }}</div>
    </div>
    <div class="card-item {{ $sinGestion > 0 ? 'card-danger card-red-val' : 'card-info card-blue-val' }}">
        <div class="ci-label">🔴 Sin gestión reciente</div>
        <div class="ci-val">{{ $sinGestion }}</div>
    </div>
</div>

{{-- ══ TABS + FILTRO ══ --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
    <div class="tabs-bar">
        <a href="?tab=individuales{{ $buscar ? '&buscar='.urlencode($buscar) : '' }}"
           class="tab-link {{ $tab === 'individuales' ? 'active' : '' }}">
            👤 Individuales ({{ $individuales->count() }})
        </a>
        <a href="?tab=empresas{{ $buscar ? '&buscar='.urlencode($buscar) : '' }}"
           class="tab-link {{ $tab === 'empresas' ? 'active' : '' }}">
            🏢 Empresas ({{ $empresasAgrupadas->count() }})
        </a>
    </div>
</div>

<form method="GET" class="filtros">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="text" name="buscar" value="{{ $buscar }}" placeholder="🔍 Nombre, cédula o empresa..." style="min-width:220px;">
    <button type="submit" class="btn-filtrar">Buscar</button>
    @if($buscar)
    <a href="?tab={{ $tab }}" class="btn-limpiar">✕ Limpiar</a>
    @endif
</form>

{{-- ══ TAB INDIVIDUALES ══ --}}
@if($tab === 'individuales')
<div class="tbl-wrap">
    @if($individuales->isEmpty())
    <div class="empty-state">
        <div style="font-size:2.5rem;">✅</div>
        <div style="font-size:1rem;font-weight:700;margin-top:.5rem;color:#0f172a;">Sin préstamos individuales pendientes</div>
    </div>
    @else
    <table class="tbl-prest">
        <thead>
            <tr>
                <th title="Semáforo de gestión"></th>
                <th>Cliente</th>
                <th>Cédula</th>
                <th>Asesor</th>
                <th>Período</th>
                <th class="text-right">Valor original</th>
                <th class="text-right">Abonado</th>
                <th class="text-right">Saldo deuda</th>
                <th>Última gestión</th>
                <th style="text-align:center">Acciones</th>
            </tr>
        </thead>
        <tbody>
        @foreach($individuales as $f)
        @php
        $nombre = trim(
            ($f->contrato?->cliente?->primer_nombre ?? '') . ' ' .
            ($f->contrato?->cliente?->primer_apellido ?? '')
        );
        $sem = $f->semaforo;
        $semTip = match($sem) {
            'verde'    => 'Gestionado recientemente',
            'amarillo' => 'Hace 3–7 días sin gestión',
            'rojo'     => 'Más de 7 días sin gestión',
            default    => 'Sin gestiones registradas',
        };
        @endphp
        <tr>
            <td style="text-align:center;">
                <span class="sem sem-{{ $sem }}" title="{{ $semTip }}"></span>
            </td>
            <td>
                <div style="font-weight:700;color:#1e3a5f;">{{ $nombre ?: '—' }}</div>
            </td>
            <td style="font-family:monospace;color:#64748b;font-size:.78rem;">
                {{ number_format($f->cedula, 0, '', '.') }}
            </td>
            <td style="font-size:.75rem;color:#64748b;">{{ $f->contrato?->asesor?->nombre ?? '—' }}</td>
            <td>
                <span style="background:#dbeafe;color:#1d4ed8;padding:.15rem .5rem;border-radius:20px;font-size:.7rem;font-weight:700;">
                    {{ $meses[$f->mes] }} {{ $f->anio }}
                </span>
            </td>
            <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $fmt($f->total) }}</td>
            <td style="text-align:right;" class="monto-abono">{{ $fmt($f->total_abonado) }}</td>
            <td style="text-align:right;" class="monto-deuda">{{ $fmt($f->saldo_pendiente_prestamo) }}</td>
            <td style="font-size:.73rem;">
                @if($f->ultima_gestion)
                    <div style="font-weight:600;color:#334155;">
                        {{ \App\Models\BitacoraCobro::RESULTADOS[$f->ultima_gestion->resultado] ?? $f->ultima_gestion->resultado }}
                    </div>
                    <div style="color:#94a3b8;font-size:.68rem;">
                        {{ $f->ultima_gestion->fecha_llamada->format('d/m/Y') }}
                    </div>
                @else
                    <span style="color:#cbd5e1;">Sin gestiones</span>
                @endif
            </td>
            <td style="text-align:center;">
                <div style="display:flex;gap:.3rem;justify-content:center;flex-wrap:wrap;">
                    <a href="{{ route('admin.prestamos.show', $f->id) }}" class="btn-sm btn-ver">👁 Ver</a>
                    <button onclick="abrirAbonar({{ $f->id }}, '{{ addslashes($nombre) }}', {{ $f->saldo_pendiente_prestamo }})"
                            class="btn-sm btn-abono">💰 Abonar</button>
                    <button onclick="abrirGestion({{ $f->id }})"
                            class="btn-sm btn-gestion">📞 Gestión</button>
                </div>
            </td>
        </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr style="background:#0f172a;color:#fff;font-weight:700;">
            <td colspan="5" style="padding:.5rem .7rem;font-size:.72rem;">TOTALES ({{ $individuales->count() }} préstamos)</td>
            <td style="text-align:right;padding:.5rem .7rem;font-family:monospace;">{{ $fmt($individuales->sum('total')) }}</td>
            <td style="text-align:right;padding:.5rem .7rem;font-family:monospace;color:#86efac;">{{ $fmt($individuales->sum('total_abonado')) }}</td>
            <td style="text-align:right;padding:.5rem .7rem;font-family:monospace;color:#fca5a5;">{{ $fmt($totalDeudaInd) }}</td>
            <td colspan="2"></td>
        </tr>
        </tfoot>
    </table>
    @endif
</div>
@endif

{{-- ══ TAB EMPRESAS ══ --}}
@if($tab === 'empresas')
<div class="tbl-wrap">
    @if($empresasAgrupadas->isEmpty())
    <div class="empty-state">
        <div style="font-size:2.5rem;">✅</div>
        <div style="font-size:1rem;font-weight:700;margin-top:.5rem;color:#0f172a;">Sin préstamos de empresas pendientes</div>
    </div>
    @else
    <table class="tbl-prest">
        <thead>
            <tr>
                <th></th>
                <th>Empresa</th>
                <th style="text-align:center;">Préstamos</th>
                <th style="text-align:right;">Total original</th>
                <th style="text-align:right;">Abonado</th>
                <th style="text-align:right;">Saldo deuda</th>
                <th>Última gestión</th>
                <th style="text-align:center;">Ver facturas</th>
            </tr>
        </thead>
        <tbody>
        @foreach($empresasAgrupadas as $grupo)
        <tr>
            <td style="text-align:center;">
                <span class="sem sem-{{ $grupo->semaforo }}"></span>
            </td>
            <td style="font-weight:700;color:#1e3a5f;">
                {{ $grupo->empresa?->empresa ?? 'Empresa #'.$grupo->empresa?->id }}
            </td>
            <td style="text-align:center;">
                <span style="background:#e0f2fe;color:#0369a1;padding:.15rem .5rem;border-radius:20px;font-size:.72rem;font-weight:700;">
                    {{ $grupo->cant_facturas }}
                </span>
            </td>
            <td style="text-align:right;font-family:monospace;">{{ $fmt($grupo->total_original) }}</td>
            <td style="text-align:right;" class="monto-abono">{{ $fmt($grupo->total_abonado) }}</td>
            <td style="text-align:right;" class="monto-deuda">{{ $fmt($grupo->total_deuda) }}</td>
            <td style="font-size:.73rem;">
                @if($grupo->ultima_gestion)
                    <div style="font-weight:600;color:#334155;">
                        {{ \App\Models\BitacoraCobro::RESULTADOS[$grupo->ultima_gestion->resultado] ?? $grupo->ultima_gestion->resultado }}
                    </div>
                    <div style="color:#94a3b8;font-size:.68rem;">{{ $grupo->ultima_gestion->fecha_llamada->format('d/m/Y') }}</div>
                @else
                    <span style="color:#cbd5e1;">Sin gestiones</span>
                @endif
            </td>
            <td style="text-align:center;">
                <div style="display:flex;gap:.25rem;justify-content:center;flex-wrap:wrap;">
                @foreach($grupo->lotes as $lote)
                    <a href="{{ route('admin.prestamos.show', $lote->factura_id) }}"
                       class="btn-sm btn-ver"
                       style="font-size:.68rem;"
                       title="Factura #{{ $lote->numero_factura }} &mdash; {{ $lote->facturas->count() }} cliente(s)">
                        #{{ $lote->numero_factura }}&nbsp;{{ $meses[$lote->mes] }}/{{ $lote->anio }}
                    </a>
                @endforeach
                </div>
            </td>
        </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr style="background:#0f172a;color:#fff;font-weight:700;">
            <td colspan="3" style="padding:.5rem .7rem;font-size:.72rem;">TOTALES ({{ $empresasAgrupadas->count() }} empresas)</td>
            <td style="text-align:right;padding:.5rem .7rem;font-family:monospace;">{{ $fmt($empresasAgrupadas->sum('total_original')) }}</td>
            <td style="text-align:right;padding:.5rem .7rem;font-family:monospace;color:#86efac;">{{ $fmt($empresasAgrupadas->sum('total_abonado')) }}</td>
            <td style="text-align:right;padding:.5rem .7rem;font-family:monospace;color:#fca5a5;">{{ $fmt($totalDeudaEmp) }}</td>
            <td colspan="2"></td>
        </tr>
        </tfoot>
    </table>
    @endif
</div>
@endif

</div>{{-- /prest-wrap --}}

{{-- ══ MODAL ABONAR ══ --}}
<div class="modal-bg" id="modalAbonar">
<div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-title">
        <span>💰 Registrar Abono</span>
        <button class="modal-close" onclick="cerrarModal('modalAbonar')">✕</button>
    </div>
    <div id="ab-info" style="background:#f0fdf4;border:1px solid #86efac;border-radius:9px;padding:.55rem .85rem;margin-bottom:.85rem;font-size:.82rem;color:#15803d;font-weight:600;"></div>
    <form id="formAbonar">
        <input type="hidden" id="ab-id">
        <div class="form-grp">
            <label>Valor del abono *</label>
            <input type="number" id="ab-valor" min="1" required>
        </div>
        <div class="form-grp">
            <label>Forma de pago *</label>
            <select id="ab-forma" onchange="toggleAbonoForma()">
                <option value="efectivo">💵 Efectivo</option>
                <option value="consignacion">🏦 Consignación</option>
                <option value="mixto">🔀 Mixto</option>
            </select>
        </div>
        <div id="ab-ef-row" class="form-grp">
            <label>Valor efectivo</label>
            <input type="number" id="ab-ef" min="0">
        </div>
        <div id="ab-cs-row" class="form-grp" style="display:none;">
            <label>Valor consignado</label>
            <input type="number" id="ab-cs" min="0">
        </div>
        <div class="form-grp">
            <label>Observación</label>
            <textarea id="ab-obs" rows="2"></textarea>
        </div>
        <button type="submit" class="btn-save">💰 Registrar Abono</button>
    </form>
</div>
</div>

{{-- ══ MODAL GESTIÓN ══ --}}
<div class="modal-bg" id="modalGestion">
<div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-title">
        <span>📞 Registrar Gestión de Cobro</span>
        <button class="modal-close" onclick="cerrarModal('modalGestion')">✕</button>
    </div>
    <form id="formGestion">
        <input type="hidden" id="g-id">
        <div class="form-grp">
            <label>Resultado *</label>
            <select id="g-resultado">
                <option value="no_contesta">📵 No contesta</option>
                <option value="promesa_pago">🤝 Promesa de pago</option>
                <option value="pagado">✅ Ya pagó / Pagará hoy</option>
                <option value="numero_errado">❌ Número errado</option>
                <option value="otro">📝 Otro</option>
            </select>
        </div>
        <div class="form-grp">
            <label>Observación — ¿Qué dijo el cliente? *</label>
            <textarea id="g-obs" rows="3" placeholder="Ej: Dijo que consigna el viernes..."></textarea>
        </div>
        <button type="submit" class="btn-save">📞 Guardar Gestión</button>
    </form>
</div>
</div>

@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
const fmt  = v => '$' + parseInt(v||0).toLocaleString('es-CO');

// ── Modales ───────────────────────────────────────────────────
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-bg').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ── Modal Abonar ──────────────────────────────────────────────
function abrirAbonar(id, nombre, saldo) {
    document.getElementById('ab-id').value = id;
    document.getElementById('ab-info').textContent = '👤 ' + nombre + ' — Saldo pendiente: ' + fmt(saldo);
    document.getElementById('ab-valor').value = saldo;
    document.getElementById('ab-ef').value = saldo;
    document.getElementById('ab-cs').value = '';
    document.getElementById('ab-obs').value = '';
    toggleAbonoForma();
    document.getElementById('modalAbonar').classList.add('open');
}
function toggleAbonoForma() {
    const f = document.getElementById('ab-forma').value;
    document.getElementById('ab-ef-row').style.display = (f==='efectivo'||f==='mixto') ? '' : 'none';
    document.getElementById('ab-cs-row').style.display = (f==='consignacion'||f==='mixto') ? '' : 'none';
}
document.getElementById('formAbonar').addEventListener('submit', async e => {
    e.preventDefault();
    const id = document.getElementById('ab-id').value;
    const r  = await fetch(`/admin/prestamos/${id}/abonar`, {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({
            valor:            document.getElementById('ab-valor').value,
            forma_pago:       document.getElementById('ab-forma').value,
            valor_efectivo:   document.getElementById('ab-ef').value || 0,
            valor_consignado: document.getElementById('ab-cs').value || 0,
            observacion:      document.getElementById('ab-obs').value,
        })
    });
    const res = await r.json();
    alert(res.mensaje || (res.ok ? 'Abono registrado' : 'Error'));
    if (res.ok) location.reload();
});

// ── Modal Gestión ─────────────────────────────────────────────
function abrirGestion(id) {
    document.getElementById('g-id').value = id;
    document.getElementById('g-obs').value = '';
    document.getElementById('modalGestion').classList.add('open');
}
document.getElementById('formGestion').addEventListener('submit', async e => {
    e.preventDefault();
    const id = document.getElementById('g-id').value;
    const r  = await fetch(`/admin/prestamos/${id}/gestion`, {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({
            resultado:   document.getElementById('g-resultado').value,
            observacion: document.getElementById('g-obs').value,
        })
    });
    const res = await r.json();
    if (res.ok) { alert('✅ Gestión registrada.'); location.reload(); }
    else alert('Error al registrar gestión');
});
</script>
@endpush
