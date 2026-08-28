@extends('layouts.app')
@section('modulo', 'Facturación Electrónica')

@section('contenido')
<style>
/* ── Header ─────────────────────────────────────── */
.fe-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #0e4d2f 100%);
    padding: 1.4rem 1.8rem;
    border-radius: 14px;
    color: #fff;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.fe-title { font-size: 1.45rem; font-weight: 800; letter-spacing: 0.02em; }
.fe-sub   { font-size: 0.8rem; color: #94a3b8; margin-top: 0.2rem; }

/* ── Stats chips ─────────────────────────────────── */
.stat-chips { display: flex; gap: 0.7rem; flex-wrap: wrap; }
.stat-chip {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px;
    padding: 0.35rem 0.8rem;
    font-size: 0.75rem;
    color: #e2e8f0;
    display: flex; align-items: center; gap: 0.4rem;
}
.stat-chip strong { color: #fff; font-size: 0.88rem; }

/* ── Filtros ─────────────────────────────────────── */
.filtros-box {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem 1.2rem;
    margin-bottom: 1rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: flex-end;
}
.filtro-group { display: flex; flex-direction: column; gap: 0.25rem; }
.filtro-group label { font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; }
.filtro-group select,
.filtro-group input {
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.42rem 0.75rem;
    font-size: 0.85rem;
    color: #1e293b;
    outline: none;
    background: #fff;
    min-width: 130px;
    transition: border .15s;
}
.filtro-group select:focus,
.filtro-group input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.btn-filtrar {
    background: #1e3a5f; color: #fff;
    border: none; border-radius: 8px;
    padding: 0.48rem 1.1rem;
    font-size: 0.84rem; font-weight: 700;
    cursor: pointer; transition: background .15s;
    display: inline-flex; align-items: center; gap: 0.4rem;
}
.btn-filtrar:hover { background: #1d4ed8; }
.btn-limpiar {
    background: transparent; color: #64748b;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    padding: 0.45rem 0.9rem;
    font-size: 0.84rem; font-weight: 600;
    cursor: pointer; transition: all .15s;
    text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;
}
.btn-limpiar:hover { border-color: #94a3b8; color: #1e293b; }

/* ── Barra de acciones ───────────────────────────── */
.action-bar {
    background: #1e40af;
    border-radius: 10px;
    padding: 0.7rem 1.1rem;
    margin-bottom: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    transition: all .2s;
}
.action-bar.oculta { display: none; }
.action-count {
    font-size: 0.82rem; font-weight: 700; color: #bfdbfe;
    margin-right: auto;
}
.btn-accion {
    border: none; border-radius: 8px;
    padding: 0.45rem 1rem;
    font-size: 0.82rem; font-weight: 700;
    cursor: pointer; transition: all .15s;
    display: inline-flex; align-items: center; gap: 0.4rem;
}
.btn-marcar    { background: #10b981; color: #fff; }
.btn-marcar:hover    { background: #059669; }
.btn-desmarcar { background: #f59e0b; color: #fff; }
.btn-desmarcar:hover { background: #d97706; }
.btn-excel { background: #166534; color: #fff; }
.btn-excel:hover { background: #15803d; }

/* ── Tabla ───────────────────────────────────────── */
.tabla-wrap {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}
.tabla-fe {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.tabla-fe thead th {
    background: #f8fafc;
    padding: 0.65rem 0.75rem;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.tabla-fe thead th.th-num { text-align: right; }
.tabla-fe tbody td {
    padding: 0.6rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
}
.tabla-fe tbody tr:last-child td { border-bottom: none; }
.tabla-fe tbody tr:hover { background: #f8fafc; }
.tabla-fe tbody tr.ya-marcada { background: #f0fdf4; }
.tabla-fe tbody tr.ya-marcada:hover { background: #dcfce7; }
.tabla-fe tfoot td {
    padding: 0.65rem 0.75rem;
    border-top: 2px solid #e2e8f0;
    font-weight: 700;
    font-size: 0.82rem;
    background: #f8fafc;
}
.td-num { text-align: right; font-variant-numeric: tabular-nums; }
.td-mono { font-family: 'Courier New', monospace; font-size: 0.78rem; }

/* ── Badges FE ───────────────────────────────────── */
.badge-fe {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.68rem; font-weight: 700;
    padding: 0.22rem 0.6rem; border-radius: 20px;
    white-space: nowrap;
}
.badge-fe-ok      { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.badge-fe-parcial { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge-fe-pendiente { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

/* Badge tipo factura */
.badge-tipo {
    display: inline-block;
    font-size: 0.64rem; font-weight: 700;
    padding: 0.15rem 0.5rem; border-radius: 20px;
}
.badge-planilla   { background: #dbeafe; color: #1d4ed8; }
.badge-afiliacion { background: #ede9fe; color: #6d28d9; }
.badge-otro       { background: #fce7f3; color: #9d174d; }

/* Paginación */
.paginacion-wrap {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.9rem 1.2rem;
    border-top: 1px solid #f1f5f9;
    font-size: 0.8rem;
    color: #64748b;
}
.paginacion-wrap .page-links a,
.paginacion-wrap .page-links span {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 6px;
    font-size: 0.8rem; font-weight: 600;
    border: 1px solid #e2e8f0;
    color: #475569; text-decoration: none;
    margin: 0 1px;
    transition: all .15s;
}
.paginacion-wrap .page-links a:hover { background: #f1f5f9; border-color: #94a3b8; }
.paginacion-wrap .page-links span.active { background: #1e3a5f; color: #fff; border-color: #1e3a5f; }

/* Empty state */
.empty-fe { text-align: center; padding: 4rem 2rem; color: #94a3b8; }
.empty-fe .icon { font-size: 3.5rem; margin-bottom: 0.8rem; }
.empty-fe p { font-size: 0.9rem; }

/* Spinner */
.spinner {
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .6s linear infinite;
    display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

{{-- ── Header ─────────────────────────────────────────────────────────────── --}}
<div class="fe-header">
    <div>
        <div class="fe-title">📄 Facturación Electrónica</div>
        <div class="fe-sub">Seleccione facturas, descargue el Excel Dataico y marque como facturadas</div>
    </div>
    <div class="stat-chips">
        <div class="stat-chip">
            📋 Grupos <strong>{{ number_format($totales->count_grupos) }}</strong>
        </div>
        <div class="stat-chip">
            💰 Total <strong>${{ number_format($totales->sum_total) }}</strong>
        </div>
        <div class="stat-chip">
            🏦 Consignado <strong>${{ number_format($totales->sum_consignado) }}</strong>
        </div>
    </div>
</div>

{{-- ── Alertas de sesión ───────────────────────────────────────────────────── --}}
@if(session('error'))
<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:0.75rem 1rem;margin-bottom:0.8rem;color:#991b1b;font-size:0.85rem;">
    ⚠️ {{ session('error') }}
</div>
@endif

{{-- ── Filtros ─────────────────────────────────────────────────────────────── --}}
<form id="formFiltros" method="GET" action="{{ route('admin.facturacion.electronica.index') }}">
<div class="filtros-box">
    <div class="filtro-group">
        <label>Mes (Pago)</label>
        <select name="mes_pago" id="filtroMesPago">
            <option value="">— Todos —</option>
            @foreach(range(1,12) as $m)
            <option value="{{ $m }}" @selected((string)$mesPago === (string)$m)>
                {{ \Carbon\Carbon::create(null, $m)->isoFormat('MMMM') }}
            </option>
            @endforeach
        </select>
    </div>
    <div class="filtro-group">
        <label>Año (Pago)</label>
        <select name="anio_pago" id="filtroAnioPago">
            <option value="">— Todos —</option>
            @foreach(range(now()->year, 2023, -1) as $a)
            <option value="{{ $a }}" @selected((string)$anioPago === (string)$a)>{{ $a }}</option>
            @endforeach
        </select>
    </div>
    <div class="filtro-group">
        <label>Mes (Período)</label>
        <select name="mes" id="filtroMes">
            <option value="">— Todos —</option>
            @foreach(range(1,12) as $m)
            <option value="{{ $m }}" @selected((string)$mesFiltro === (string)$m)>
                {{ \Carbon\Carbon::create(null, $m)->isoFormat('MMMM') }}
            </option>
            @endforeach
        </select>
    </div>
    <div class="filtro-group">
        <label>Año (Período)</label>
        <select name="anio" id="filtroAnio">
            <option value="">— Todos —</option>
            @foreach(range(now()->year, 2023, -1) as $a)
            <option value="{{ $a }}" @selected((string)$anioFiltro === (string)$a)>{{ $a }}</option>
            @endforeach
        </select>
    </div>
    <div class="filtro-group">
        <label>Cuenta Bancaria</label>
        <select name="banco_cuenta_id" id="filtroBanco" style="min-width:180px;">
            <option value="">— Todas las cuentas —</option>
            @foreach($bancos as $b)
            <option value="{{ $b->id }}" @selected((string)$bancoCuentaId === (string)$b->id)>
                {{ $b->banco }} — {{ $b->nombre }}
            </option>
            @endforeach
        </select>
    </div>
    <div class="filtro-group">
        <label>Estado FE</label>
        <select name="estado_fe" id="filtroFe">
            <option value="todas"    @selected($estadoFe === 'todas')   >Todas</option>
            <option value="pendiente"@selected($estadoFe === 'pendiente')>⬜ Pendientes</option>
            <option value="parcial"  @selected($estadoFe === 'parcial') >⚠️ Parciales</option>
            <option value="marcada"  @selected($estadoFe === 'marcada') >✅ Facturadas</option>
        </select>
    </div>
    <div class="filtro-group">
        <label>Tipo</label>
        <select name="tipo" id="filtroTipo">
            <option value=""              @selected(!$tipoFact)                       >— Todos —</option>
            <option value="planilla"      @selected($tipoFact === 'planilla')        >Planilla</option>
            <option value="afiliacion"    @selected($tipoFact === 'afiliacion')      >Afiliación</option>
            <option value="otro_ingreso"  @selected($tipoFact === 'otro_ingreso')    >Otro Ingreso</option>
        </select>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:flex-end;">
        <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
        <a href="{{ route('admin.facturacion.electronica.index') }}" class="btn-limpiar">✕ Limpiar</a>
    </div>
</div>
</form>

{{-- ── Barra de acciones (aparece al seleccionar) ─────────────────────────── --}}
<div class="action-bar oculta" id="actionBar">
    <span class="action-count" id="actionCount">0 grupos seleccionados</span>
    <button class="btn-accion btn-marcar"    id="btnMarcar"   >✅ Marcar como FE</button>
    <button class="btn-accion btn-desmarcar" id="btnDesmarcar">✕ Desmarcar FE</button>
    <button class="btn-accion btn-excel"     id="btnExcel"    >📥 Descargar Excel Dataico</button>
</div>

{{-- ── Tabla ───────────────────────────────────────────────────────────────── --}}
<div class="tabla-wrap">
    @if($facturas->isEmpty())
        <div class="empty-fe">
            <div class="icon">📭</div>
            <p>No se encontraron facturas con los filtros aplicados.<br>
               Prueba ajustando el rango de fechas o los filtros.</p>
        </div>
    @else
    <table class="tabla-fe" id="tablaFe">
        <thead>
            <tr>
                <th style="width:36px;">
                    <input type="checkbox" id="chkTodos" title="Seleccionar todo" style="cursor:pointer;width:16px;height:16px;">
                </th>
                <th>N° Factura</th>
                <th>Período</th>
                <th>Fecha Pago</th>
                <th>Tipo</th>
                <th>Cliente / Empresa</th>
                <th class="th-num">Admon + Afil.</th>
                <th class="th-num">SS + Mora</th>
                <th class="th-num">IVA</th>
                <th class="th-num">Consignado</th>
                <th class="th-num">Efectivo</th>
                <th class="th-num">Total</th>
                <th>Estado FE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($facturas as $f)
            @php
                $feMarcada = (int)($f->fe_min ?? 0) === 1;
                $feParcial = (int)($f->fe_max ?? 0) === 1 && (int)($f->fe_min ?? 0) === 0;
                $tipoLabel = match($f->tipo ?? '') {
                    'planilla'     => ['Planilla',     'badge-planilla'],
                    'afiliacion'   => ['Afiliación',   'badge-afiliacion'],
                    'otro_ingreso' => ['Otro Ingreso', 'badge-otro'],
                    default        => [ucfirst($f->tipo ?? '—'), 'badge-otro'],
                };
                $mesLabel = \Carbon\Carbon::create(null, (int)($f->mes ?? 1))->isoFormat('MMM');
            @endphp
            <tr class="{{ $feMarcada ? 'ya-marcada' : '' }}" data-numero="{{ $f->numero_factura }}" data-id="{{ $f->id }}">
                <td>
                    <input type="checkbox" class="chk-fila" value="{{ $f->numero_factura }}"
                           style="cursor:pointer;width:16px;height:16px;">
                </td>
                <td>
                    <span style="font-weight:700;color:#1e3a5f;font-size:0.9rem;">#{{ $f->numero_factura }}</span>
                </td>
                <td>
                    <span style="font-weight:600;color:#475569;font-size:0.82rem;">
                        {{ strtoupper($mesLabel) }} {{ $f->anio ?? '—' }}
                    </span>
                </td>
                <td class="td-mono">
                    {{ $f->fecha_pago ? \Carbon\Carbon::parse($f->fecha_pago)->format('d/m/Y') : '—' }}
                </td>
                <td>
                    @php 
                        $n = (int)($f->num_clientes ?? 1);
                        $tipoName = $tipoLabel[0];
                        if ($n > 1) {
                            if ($tipoName === 'Planilla') $tipoName = 'Planillas';
                            elseif ($tipoName === 'Afiliación') $tipoName = 'Afiliaciones';
                            elseif ($tipoName === 'Otro Ingreso') $tipoName = 'Otros Ingresos';
                            else $tipoName .= 's';
                        }
                    @endphp
                    <span class="badge-tipo {{ $tipoLabel[1] }}">{{ $n }} {{ $tipoName }}</span>
                </td>
                <td style="max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    @if($f->empresa_id)
                        @php $nombreEmp = $nombresEmpresas[$f->empresa_id] ?? 'Empresa #' . $f->empresa_id; @endphp
                        <span style="font-size:0.78rem;color:#374151;" title="{{ $nombreEmp }}">🏢 {{ $nombreEmp }}</span>
                    @else
                        @php $nombreCli = $nombresClientes[$f->cedula_muestra] ?? $f->cedula_muestra ?? '—'; @endphp
                        <span style="font-size:0.78rem;color:#374151;" title="{{ $nombreCli }}">👤 {{ $nombreCli }}</span>
                    @endif
                </td>
                <td class="td-num">${{ number_format((int)($f->total_admon ?? 0)) }}</td>
                <td class="td-num">${{ number_format((int)($f->total_ss ?? 0)) }}</td>
                <td class="td-num">
                    @if((int)($f->total_iva ?? 0) > 0)
                        ${{ number_format((int)$f->total_iva) }}
                    @else
                        <span style="color:#94a3b8;">—</span>
                    @endif
                </td>
                <td class="td-num">${{ number_format((int)($f->total_consignado ?? 0)) }}</td>
                <td class="td-num">${{ number_format((int)($f->total_efectivo ?? 0)) }}</td>
                <td class="td-num" style="font-weight:700;">${{ number_format((int)($f->gran_total ?? 0)) }}</td>
                <td>
                    @php $envio = $feEstados[$f->numero_factura] ?? null; @endphp
                    @if($envio && $envio->estado === 'enviado' && $envio->dataico_numero)
                        {{-- Con número de Dataico se muestra cuál es: saber que
                             «está facturada» no sirve para buscarla en el portal. --}}
                        <span class="badge-fe badge-fe-ok"
                              title="{{ $envio->cufe ? 'CUFE '.$envio->cufe : 'Emitida ante la DIAN' }}">✅ {{ $envio->dataico_numero }}</span>
                    @elseif($envio && $envio->estado === 'error')
                        <span class="badge-fe badge-fe-parcial" style="background:#fee2e2;color:#991b1b;border-color:#fecaca"
                              title="{{ $envio->error_mensaje }}">⚠️ Error</span>
                    @elseif($envio && $envio->estado === 'omitido')
                        <span class="badge-fe badge-fe-pendiente"
                              title="{{ $envio->error_mensaje }}">🚫 Omitida</span>
                    @elseif($feMarcada)
                        <span class="badge-fe badge-fe-ok" title="Marcada como facturada, sin registro del número">✅ Facturada</span>
                    @elseif($feParcial)
                        <span class="badge-fe badge-fe-parcial">⚠️ Parcial</span>
                    @else
                        <span class="badge-fe badge-fe-pendiente">⬜ Pendiente</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="color:#64748b;font-size:0.78rem;">
                    📊 Mostrando {{ $facturas->count() }} de {{ $facturas->total() }} grupos de pago
                </td>
                <td class="td-num">${{ number_format($totales->sum_admon) }}</td>
                <td class="td-num">${{ number_format($totales->sum_ss) }}</td>
                <td class="td-num">${{ number_format($totales->sum_iva) }}</td>
                <td class="td-num">${{ number_format($totales->sum_consignado) }}</td>
                <td class="td-num">${{ number_format($totales->sum_efectivo) }}</td>
                <td class="td-num" style="color:#1e3a5f;">${{ number_format($totales->sum_total) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- Paginación --}}
    @if($facturas->hasPages())
    <div class="paginacion-wrap">
        <span>Página {{ $facturas->currentPage() }} de {{ $facturas->lastPage() }}</span>
        <div class="page-links">
        {!! $facturas->links('vendor.pagination.custom') !!}
        </div>
        <span>{{ $facturas->total() }} grupos en total</span>
    </div>
    @endif
    @endif
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Referencias ────────────────────────────────────────────────────────
    const chkTodos   = document.getElementById('chkTodos');
    const actionBar  = document.getElementById('actionBar');
    const actionCount= document.getElementById('actionCount');
    const btnMarcar  = document.getElementById('btnMarcar');
    const btnDesmarcar = document.getElementById('btnDesmarcar');
    const btnExcel   = document.getElementById('btnExcel');
    const formFiltros = document.getElementById('formFiltros');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── Obtener checkboxes seleccionados ───────────────────────────────────
    function seleccionados() {
        return [...document.querySelectorAll('.chk-fila:checked')].map(c => parseInt(c.value));
    }

    // ── Actualizar barra de acciones ───────────────────────────────────────
    function actualizarBar() {
        const sel = seleccionados();
        const n   = sel.length;
        if (n > 0) {
            actionBar.classList.remove('oculta');
            actionCount.textContent = `${n} grupo${n > 1 ? 's' : ''} seleccionado${n > 1 ? 's' : ''}`;
        } else {
            actionBar.classList.add('oculta');
        }
        if (chkTodos) {
            const total = document.querySelectorAll('.chk-fila').length;
            chkTodos.indeterminate = n > 0 && n < total;
            chkTodos.checked = n > 0 && n === total;
        }
    }

    // ── Seleccionar todo ───────────────────────────────────────────────────
    if (chkTodos) {
        chkTodos.addEventListener('change', function () {
            document.querySelectorAll('.chk-fila').forEach(c => c.checked = this.checked);
            actualizarBar();
        });
    }
    document.querySelectorAll('.chk-fila').forEach(c => {
        c.addEventListener('change', actualizarBar);
    });

    // ── Marcar / Desmarcar ─────────────────────────────────────────────────
    async function accionFe(accion, btn) {
        const nums = seleccionados();
        if (!nums.length) return;

        const iconOrig = btn.innerHTML;
        btn.innerHTML  = '<span class="spinner"></span>';
        btn.disabled   = true;

        try {
            const res = await fetch('{{ route('admin.facturacion.electronica.marcar') }}', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ numeros_factura: nums, accion }),
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();

            if (data.ok) {
                // Actualizar badges en la tabla sin recargar
                nums.forEach(num => {
                    const row = document.querySelector(`tr[data-numero="${num}"]`);
                    if (!row) return;
                    const badgeCell = row.querySelector('td:last-child');
                    if (!badgeCell) return;
                    if (accion === 'marcar') {
                        badgeCell.innerHTML = '<span class="badge-fe badge-fe-ok">✅ Facturada</span>';
                        row.classList.add('ya-marcada');
                    } else {
                        badgeCell.innerHTML = '<span class="badge-fe badge-fe-pendiente">⬜ Pendiente</span>';
                        row.classList.remove('ya-marcada');
                    }
                });
                // Deseleccionar
                document.querySelectorAll('.chk-fila').forEach(c => c.checked = false);
                if (chkTodos) chkTodos.checked = false;
                actualizarBar();
            }
        } catch (e) {
            alert('Error al procesar la solicitud: ' + e.message);
        } finally {
            btn.innerHTML = iconOrig;
            btn.disabled  = false;
        }
    }

    if (btnMarcar)    btnMarcar.addEventListener('click',    () => accionFe('marcar',    btnMarcar));
    if (btnDesmarcar) btnDesmarcar.addEventListener('click', () => accionFe('desmarcar', btnDesmarcar));

    // ── Descargar Excel ────────────────────────────────────────────────────
    if (btnExcel) {
        btnExcel.addEventListener('click', function () {
            const nums = seleccionados();

            // Construir URL con los filtros actuales + IDs seleccionados
            const params = new URLSearchParams(new FormData(formFiltros));
            nums.forEach(n => params.append('numeros_factura[]', n));

            const url = '{{ route('admin.facturacion.electronica.exportar') }}?' + params.toString();
            window.location.href = url;
        });
    }

    // ── Click en fila (abrir modal) ───────────────────────────────────
    document.querySelectorAll('.tabla-fe tbody tr').forEach(row => {
        row.addEventListener('click', function (e) {
            // Ignorar clics en el checkbox
            if (e.target.type === 'checkbox' || e.target.closest('td:first-child')) return;
            
            const id = this.dataset.id;
            if(id) {
                const iframe = document.getElementById('iframeRecibo');
                iframe.src = '{{ url("admin/facturacion/recibo") }}/' + id + '?modal=1&no_anular=1';
                document.getElementById('modalRecibo').style.display = 'flex';
            }
        });
        row.style.cursor = 'pointer';
    });

    window.cerrarRecibo = function() {
        document.getElementById('modalRecibo').style.display = 'none';
    };
})();
</script>
@endpush

{{-- Modal Recibo --}}
<div id="modalRecibo" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;width:95%;max-width:900px;height:90vh;border-radius:12px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <div style="padding:1rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
            <h3 style="margin:0;font-size:1.1rem;color:#1e293b;">📄 Recibo de Factura</h3>
            <button onclick="document.getElementById('modalRecibo').style.display='none'" style="background:none;border:none;font-size:1.8rem;line-height:1;cursor:pointer;color:#64748b;padding:0;margin:0;">&times;</button>
        </div>
        <iframe id="iframeRecibo" src="" style="width:100%;flex:1;border:none;background:#f1f5f9;"></iframe>
    </div>
</div>

@endsection
