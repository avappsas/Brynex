@extends('layouts.app')
@section('modulo', 'Cuadre de Caja')

@php
$fmt = fn($v) => '$'.number_format($v ?? 0, 0, ',', '.');
$esSuperAdmin = auth()->user()->hasRole('superadmin');

$carbonFecha = \Carbon\Carbon::parse($fecha);
$esHoy       = $carbonFecha->isToday();

// El día cuadrado queda congelado: no se registran ni se borran movimientos.
$diaBloqueado    = (bool) $cuadreDia;
$totalConsignado = $consignaciones->flatten()->sum('valor');
$totalFacturas   = array_sum($canales['conteo']);
@endphp

@section('contenido')
<style>
.cd-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1rem}
.cd-link{padding:.35rem .85rem;font-size:.78rem;font-weight:600;border-radius:7px;background:rgba(255,255,255,.12);color:#cbd5e1;text-decoration:none;white-space:nowrap}
.cd-input{border-radius:7px;padding:.32rem .6rem;font-size:.8rem;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);color:#fff;outline:none}
.cd-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:.8rem;margin-bottom:1rem}
.cd-card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.2rem}
.cd-card-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:.4rem}
.cd-card-val{font-size:1.5rem;font-weight:800;color:#0f172a}
.cd-card.facturado{border-color:#ddd6fe}
.cd-card.facturado .cd-card-val{color:#4c1d95}
.cd-card.efectivo{border-color:#bbf7d0}
.cd-card.efectivo .cd-card-val{color:#15803d}
.cd-card.gastos{border-color:#fecaca}
.cd-card.gastos .cd-card-val{color:#dc2626}
.cd-card.saldo{border-color:#bfdbfe;background:#f8fbff}
.cd-card.saldo .cd-card-val{color:#1d4ed8}
.cd-card-click{cursor:pointer;transition:box-shadow .15s,transform .15s}
.cd-card-click:hover{box-shadow:0 6px 18px rgba(3,105,161,.16);transform:translateY(-2px)}
.cd-panel{background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:.8rem}
.cd-panel-head{padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.4rem}
.cd-panel-title{font-size:.85rem;font-weight:700;color:#0f172a}
.tbl-cd{width:100%;border-collapse:collapse;font-size:.8rem}
.tbl-cd th{background:#0f172a;color:#94a3b8;font-size:.65rem;text-transform:uppercase;padding:.4rem .6rem;text-align:left}
.tbl-cd td{padding:.38rem .6rem;border-bottom:1px solid #f1f5f9}
.tbl-cd tr:hover td{background:#f8fafc}
.tbl-cd tfoot td{background:#0f172a;color:#e2e8f0;font-weight:700}
.num{text-align:right;font-family:monospace}
.badge-tipo{padding:.12rem .45rem;border-radius:20px;font-size:.66rem;font-weight:700;color:#fff}
.btn-sm{padding:.28rem .7rem;border-radius:6px;font-size:.75rem;font-weight:600;border:none;cursor:pointer}
.btn-gasto{background:#065f46;color:#fff}
.btn-cerrar{background:#dc2626;color:#fff}
.vacio{padding:1.5rem;text-align:center;color:#94a3b8;font-size:.83rem}

/* ── Canales del día ── */
.cn-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.4rem;margin:1.2rem 0 .7rem}
.cn-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:.8rem;align-items:stretch}
@media (max-width:1100px){.cn-grid{grid-template-columns:1fr}}
.cn-card{background:#fff;border-radius:13px;border:1px solid #e2e8f0;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 1px 5px rgba(0,0,0,.05)}
.cn-card-head{padding:.6rem .9rem;color:#fff}
.cn-label{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.6)}
.cn-title{font-size:.95rem;font-weight:800;margin-top:.1rem}
.cn-body{padding:.5rem .6rem;flex:1}
.cn-section{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;padding:.15rem .3rem .35rem}
/* 4 columnas: etiqueta + efectivo + consignado + prestado */
.cn-row{display:grid;grid-template-columns:minmax(0,1.5fr) 1fr 1fr 1fr;gap:.3rem;align-items:center;
        padding:.26rem .3rem;border-bottom:1px solid #f8fafc}
.cn-row-head{border-bottom:1px solid #e2e8f0;padding-bottom:.3rem}
.cn-row-head div{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;text-align:right}
.cn-lbl{font-size:.73rem;color:#334155;display:flex;align-items:center;gap:.3rem;min-width:0;
        overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cn-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.cn-v{font-size:.73rem;font-family:monospace;font-weight:700;text-align:right;white-space:nowrap}
/* Prestado: no es plata recibida, se separa visualmente */
.cn-pr{border-left:1px dashed #e2e8f0;padding-left:.3rem}
/* Sin préstamos en el día la columna sobra: se cae a 3 columnas */
.cn-grid.sin-prestado .cn-pr{display:none}
.cn-grid.sin-prestado .cn-row{grid-template-columns:minmax(0,1.5fr) 1fr 1fr}
.cn-nota{opacity:.6;font-style:italic;border-top:1px dashed #e2e8f0;border-bottom:0;margin-top:.2rem}
.cn-nota .cn-v{color:#f59e0b}
.cn-card-foot{padding:.5rem .9rem;color:#fff}
.cn-foot-l{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,.7)}
.cn-foot-v{font-size:.82rem;font-weight:900;font-family:monospace;text-align:right;color:#fff;white-space:nowrap}
.cn-foot-tot{font-size:.68rem;color:rgba(255,255,255,.55);text-align:right;margin-top:.2rem}

/* ── Impresión: hoja carta vertical ── */
@media print {
    @page { size: letter portrait; margin: 12mm; }

    /* Los fondos de color son la identidad de cada canal: deben salir */
    *{ -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important }

    /* Fuera el chrome de la app y todo lo accionable */
    .header, .mobile-drawer, .no-print, .no-print *,
    #modal-gasto, #modal-cerrar, #modal-consig { display:none !important }
    .contenido{ padding:0 !important; margin:0 !important }
    body{ background:#fff !important }

    /* La hoja carta mide ~730px útiles: el breakpoint de 1100px la volvería
       de una columna, así que aquí se fuerza la retícula. */
    .cn-grid{ grid-template-columns:repeat(3,1fr) !important; gap:.4rem !important;
              break-inside:avoid; page-break-inside:avoid }
    .cd-cards{ grid-template-columns:repeat(auto-fit,minmax(125px,1fr)) !important; gap:.4rem !important }
    .cd-card{ padding:.6rem .7rem !important }
    .cd-card-val{ font-size:1.05rem !important }
    .cd-panel, .cn-card{ break-inside:avoid; page-break-inside:avoid;
                         box-shadow:none !important }
    .cd-header{ padding:.6rem .9rem !important; margin-bottom:.6rem !important }
    .tbl-cd th, .tbl-cd td{ padding:.2rem .4rem !important; font-size:.68rem !important }

    /* En carta cada canal mide ~240px: la etiqueta cede ancho y todo encoge,
       incluido el pie — si no, los dos valores del total se tocan. */
    .cn-card-head{ padding:.45rem .6rem !important }
    .cn-title{ font-size:.78rem !important }
    .cn-body, .cn-card-foot{ padding:.4rem .5rem !important }
    .cn-lbl, .cn-v{ font-size:.62rem !important }
    /* En papel no hay hover ni tooltip: una etiqueta cortada se pierde para
       siempre, así que aquí se deja envolver en vez de truncar. */
    .cn-lbl{ white-space:normal !important; overflow:visible !important;
             text-overflow:clip !important; line-height:1.15; align-items:flex-start }
    .cn-row{ grid-template-columns:minmax(0,1.2fr) 1fr 1fr 1fr !important; gap:.5rem !important;
             padding:.2rem .15rem !important }
    .cn-grid.sin-prestado .cn-row{ grid-template-columns:minmax(0,1.2fr) 1fr 1fr !important }
    .cn-row-head div{ font-size:.55rem !important }
    .cn-foot-l{ font-size:.54rem !important; line-height:1.15 }
    .cn-foot-v{ font-size:.64rem !important }
    .cn-foot-tot{ font-size:.58rem !important }
}
</style>

{{-- ═══════════ Header: fecha + usuario + accesos ═══════════ --}}
<div class="cd-header">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.7rem">
        <div>
            <div style="font-size:1.15rem;font-weight:800">💰 Caja del día</div>
            <div style="font-size:.8rem;color:#94a3b8;margin-top:.2rem">
                {{ ($verTodos ?? false) ? 'Todos los usuarios' : $usuarioVista->nombre }} ·
                {{ $carbonFecha->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }}
                @if($esHoy)<span style="color:#4ade80;font-weight:700"> · hoy</span>@endif
            </div>
        </div>

        <div class="no-print" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
            {{-- Filtros y accesos en una sola barra --}}
            <form method="GET" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;margin:0">
                <input type="date" name="fecha" value="{{ $fecha }}" max="{{ today()->toDateString() }}"
                       onchange="this.form.submit()" class="cd-input" title="Día que estás viendo">
                @if($esAdmin)
                <select name="usuario_id" onchange="this.form.submit()" class="cd-input" title="Usuario">
                    {{-- Solo admin/superadmin ve este selector, asi que la caja total
                         queda restringida a ellos sin necesidad de otra guarda. --}}
                    <option value="todos" @selected($verTodos ?? false) style="background:#0f172a">
                        👥 Todos (caja total)
                    </option>
                    @foreach($usuarios as $u)
                    <option value="{{ $u->id }}" @selected((int) $u->id === $usuarioId) style="background:#0f172a">
                        {{ $u->nombre }}@if($u->id === auth()->id()) (yo)@endif
                    </option>
                    @endforeach
                </select>
                @endif
            </form>

            @if($esAdmin)
            <a href="{{ route('admin.cuadre-diario.facturas-dia', ['fecha' => $fecha]) }}" class="cd-link">🧾 Facturas del día</a>
            <a href="{{ route('admin.anticipos.informe') }}" class="cd-link" style="background:rgba(253,230,138,.25);color:#fde68a">💰 Anticipos</a>
            @endif
            @if(!$diaBloqueado && $esPropio)
            <button type="button" onclick="abrirModalGasto()" class="cd-link"
                    style="background:#059669;color:#fff;border:none;cursor:pointer;font-family:inherit">
                + Registrar gasto
            </button>
            @endif
            <button type="button" onclick="window.print()" class="cd-link"
                    style="border:none;cursor:pointer;font-family:inherit;padding:.35rem .6rem;font-size:.9rem"
                    title="Imprimir en hoja carta vertical" aria-label="Imprimir">🖨️</button>
        </div>
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;color:#166534;padding:.6rem 1rem;border-radius:8px;margin-bottom:.8rem;font-size:.83rem">
    ✅ {{ session('success') }}
</div>
@endif
@if(session('error'))
<div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:.6rem 1rem;border-radius:8px;margin-bottom:.8rem;font-size:.83rem">
    ❌ {{ session('error') }}
</div>
@endif

{{-- Aviso de día ya cuadrado --}}
@if($cuadreDia)
<div style="background:#ecfdf5;border:1px solid #6ee7b7;border-radius:10px;padding:.7rem 1rem;margin-bottom:.8rem;
            display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
    <div style="font-size:.83rem;color:#065f46">
        🔒 Día cuadrado por <strong>{{ $cuadreDia->cerradoPor?->nombre ?? '—' }}</strong>
        con <strong>{{ $fmt($cuadreDia->saldo_cierre) }}</strong> en efectivo.
        @if($cuadreDia->observacion)<span style="color:#047857"> · {{ $cuadreDia->observacion }}</span>@endif
    </div>
    @if($esSuperAdmin)
    <form method="POST" action="{{ route('admin.cuadre-diario.reabrir-dia', $cuadreDia->id) }}"
          onsubmit="return confirm('¿Reabrir este día? El registro de cuadre se elimina.')">
        @csrf @method('DELETE')
        <button type="submit" class="btn-sm" style="background:#fff;color:#065f46;border:1px solid #6ee7b7">🔓 Reabrir</button>
    </form>
    @endif
</div>
@endif

{{-- ═══════════ 1. Resumen de la caja del día ═══════════ --}}
<div class="cd-cards">
    {{-- Lo facturado va primero: es el bruto del que sale todo lo demás. --}}
    <div class="cd-card facturado">
        <div class="cd-card-title">🧾 Total facturado</div>
        <div class="cd-card-val">{{ $fmt($resumen['total_facturado']) }}</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.3rem">
            Sin retiros ni facturas en cero
        </div>
    </div>

    <div class="cd-card efectivo">
        <div class="cd-card-title">💵 Recibido en efectivo</div>
        <div class="cd-card-val">{{ $fmt($resumen['recibido_efectivo']) }}</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.3rem">
            Facturas + cartera + anticipos
        </div>
    </div>

    <div class="cd-card gastos">
        <div class="cd-card-title">📤 Gastos en efectivo</div>
        <div class="cd-card-val">-{{ $fmt($resumen['gastos_efectivo']) }}</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.3rem">
            {{ $gastos->count() }} {{ \Illuminate\Support\Str::plural('registro', $gastos->count()) }}
        </div>
    </div>

    <div class="cd-card saldo">
        <div class="cd-card-title">✅ Efectivo en caja</div>
        <div class="cd-card-val">{{ $fmt($resumen['saldo_esperado']) }}</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.3rem"
             title="Una factura de empresa cubre varios empleados y cuenta una sola vez">
            {{ $resumen['num_facturas'] }} {{ \Illuminate\Support\Str::plural('factura', $resumen['num_facturas']) }}
            · base caja menor {{ $fmt($resumen['base_caja']) }}
        </div>
    </div>

    {{-- El cliente consigna; el usuario solo registra el soporte. Clic → detalle. --}}
    <div class="cd-card cd-card-click" style="border-color:#bae6fd"
         onclick="document.getElementById('modal-consig').style.display='flex'"
         title="Ver el detalle por cuenta">
        <div class="cd-card-title" style="color:#0369a1">🏦 Consignado en cuentas</div>
        <div class="cd-card-val" style="color:#0369a1">{{ $fmt($resumen['consignado']) }}</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.3rem">
            {{ $consignaciones->count() }} {{ \Illuminate\Support\Str::plural('cuenta', $consignaciones->count()) }}<span
                class="no-print" style="color:#2563eb;font-weight:600"> · ver detalle →</span>
        </div>
    </div>

    @if($resumen['cobros_cartera'] > 0)
    <div class="cd-card" style="border-color:#d1fae5">
        <div class="cd-card-title" style="color:#065f46">📋 Cobros de cartera</div>
        <div class="cd-card-val" style="color:#065f46">{{ $fmt($resumen['cobros_cartera']) }}</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.3rem">Préstamos recuperados</div>
    </div>
    @endif

    @if($resumen['anticipos_efectivo'] > 0)
    <div class="cd-card" style="border-color:#fde68a">
        <div class="cd-card-title" style="color:#78350f">💰 Anticipos recibidos</div>
        <div class="cd-card-val" style="color:#78350f">{{ $fmt($resumen['anticipos_efectivo']) }}</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.3rem">Efectivo/Nequi, aún sin facturar</div>
    </div>
    @endif

    @if($resumen['total_prestado'] > 0)
    <div class="cd-card" style="border-color:#e9d5ff">
        <div class="cd-card-title" style="color:#6b21a8">⚠️ Prestado hoy</div>
        <div class="cd-card-val" style="color:#7c3aed">{{ $fmt($resumen['total_prestado']) }}</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.3rem">Cartera por cobrar, no es ingreso</div>
    </div>
    @endif
</div>

{{-- ═══════════ 2. Los 3 canales del día ═══════════ --}}
@php
// Cada canal reparte sus componentes según cómo se pagó la factura.
$val = fn($v) => abs($v) >= 1 ? $fmt(round($v)) : null;
@endphp

<div class="cn-head">
    <div>
        <div class="cd-panel-title">📊 Resumen del día por canal</div>
        <div style="font-size:.72rem;color:#94a3b8;margin-top:.15rem">
            Cada renglón repartido según cómo se pagó la factura ·
            {{ $canales['conteo']['planilla'] }} planillas ·
            {{ $canales['conteo']['afiliacion'] }} afiliaciones ·
            {{ $canales['conteo']['prestamo'] }} préstamos ·
            {{ $canales['conteo']['retiro'] }} retiros
        </div>
    </div>
</div>

@if($totalFacturas === 0)
<div class="cd-panel"><div class="vacio">Sin facturas ese día</div></div>
@else
<div class="cn-grid {{ $canales['hay_prestado'] ? '' : 'sin-prestado' }}">
    @foreach($canales['canales'] as $cn)
    <div class="cn-card">
        <div class="cn-card-head" style="background:linear-gradient(135deg,{{ $cn['gradiente'] }})">
            <div class="cn-label">Canal {{ $cn['n'] }}</div>
            <div class="cn-title">{{ $cn['titulo'] }}</div>
        </div>

        <div class="cn-body">
            <div class="cn-section">{{ $cn['subtitulo'] }}</div>

            <div class="cn-row cn-row-head">
                <div></div>
                <div>💵 Efectivo</div>
                <div>🏦 Consignado</div>
                <div class="cn-pr">📋 Prestado</div>
            </div>

            @forelse($cn['filas'] as $f)
            <div class="cn-row">
                <div class="cn-lbl"><span class="cn-dot" style="background:{{ $f['color'] }}"></span>{{ $f['etiqueta'] }}</div>
                <div class="cn-v" style="color:{{ $val($f['efectivo'])   ? '#15803d' : '#cbd5e1' }}">{{ $val($f['efectivo'])   ?? '—' }}</div>
                <div class="cn-v" style="color:{{ $val($f['consignado']) ? '#0369a1' : '#cbd5e1' }}">{{ $val($f['consignado']) ?? '—' }}</div>
                <div class="cn-v cn-pr" style="color:{{ $val($f['prestado']) ? '#7c3aed' : '#cbd5e1' }}">{{ $val($f['prestado']) ?? '—' }}</div>
            </div>
            @empty
            <div style="padding:.9rem;text-align:center;color:#cbd5e1;font-size:.78rem">Sin movimiento en este canal</div>
            @endforelse

            @if($cn['n'] === 1 && ($val($canales['nota']['efectivo']) || $val($canales['nota']['consignado']) || $val($canales['nota']['prestado'])))
            <div class="cn-row cn-nota" title="Comisión ganada por asesores — sale de la administración, aún sin pagar">
                <div class="cn-lbl">↳ {{ $canales['nota']['etiqueta'] }}</div>
                <div class="cn-v">{{ $val($canales['nota']['efectivo'])   ?? '—' }}</div>
                <div class="cn-v">{{ $val($canales['nota']['consignado']) ?? '—' }}</div>
                <div class="cn-v cn-pr">{{ $val($canales['nota']['prestado']) ?? '—' }}</div>
            </div>
            @endif
        </div>

        <div class="cn-card-foot" style="background:linear-gradient(135deg,{{ $cn['gradiente'] }})">
            <div class="cn-row" style="border:0;padding:0">
                <div class="cn-foot-l">{{ $cn['n'] === 2 ? 'Total bruto' : 'Total canal' }}</div>
                <div class="cn-foot-v">{{ $val($cn['total']['efectivo'])   ?? '—' }}</div>
                <div class="cn-foot-v">{{ $val($cn['total']['consignado']) ?? '—' }}</div>
                <div class="cn-foot-v cn-pr">{{ $val($cn['total']['prestado']) ?? '—' }}</div>
            </div>
            <div class="cn-foot-tot">
                Total {{ $fmt(round($cn['total']['efectivo'] + $cn['total']['consignado'] + $cn['total']['prestado'])) }}
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- ═══════════ 3. Gastos del día ═══════════ --}}
<div class="cd-panel">
    <div class="cd-panel-head">
        <div class="cd-panel-title">📋 {{ ($verTodos ?? false) ? 'Gastos del día — todos los usuarios' : 'Gastos que reportaste' }}</div>
        <div style="font-size:.75rem;color:#64748b">
            Total <strong style="color:#dc2626">-{{ $fmt($gastos->sum('valor')) }}</strong>
        </div>
    </div>
    @if($gastos->isEmpty())
    <div class="vacio">Sin gastos ese día</div>
    @else
    <div style="overflow-x:auto">
    <table class="tbl-cd">
        <thead><tr>
            <th>Tipo</th>
            <th>Descripción</th>
            <th>Forma de pago</th>
            <th>Banco</th>
            <th class="num">Valor</th>
            <th class="no-print" style="text-align:center">Acción</th>
        </tr></thead>
        <tbody>
        @foreach($gastos as $g)
        <tr>
            <td>
                <span class="badge-tipo"
                      style="background:{{ in_array($g->tipo, ['nomina','transferencia_banco','banco_banco']) ? '#b45309' : '#1d4ed8' }}">
                    {{ $g->tipoLabel() }}
                </span>
            </td>
            <td style="max-width:280px;font-size:.77rem">
                {{ $g->descripcion }}
                @if($g->pagado_a)<div style="color:#64748b;font-size:.7rem">→ {{ $g->pagado_a }}</div>@endif
            </td>
            <td style="font-size:.75rem">
                {{ match($g->forma_pago) {
                    'efectivo' => '💵 Efectivo',
                    'transferencia_bancaria' => '🏦 Banco',
                    'banco_banco' => '🔄 Banco→Banco',
                    default => $g->forma_pago
                } }}
            </td>
            <td style="font-size:.72rem;color:#64748b">
                {{ $g->bancoOrigen?->banco ?? '—' }}@if($g->bancoDestino) → {{ $g->bancoDestino->banco }} @endif
            </td>
            <td class="num" style="color:#dc2626;font-weight:700">-{{ $fmt($g->valor) }}</td>
            <td class="no-print" style="text-align:center">
                @if(!$diaBloqueado)
                <form method="POST" action="{{ route('admin.cuadre-diario.gasto.destroy', $g->id) }}"
                      onsubmit="return confirm('¿Eliminar este gasto?')" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" style="background:#fee2e2;color:#991b1b;border:none;border-radius:5px;padding:.2rem .5rem;cursor:pointer;font-size:.72rem">🗑️</button>
                </form>
                @else
                <span style="color:#cbd5e1;font-size:.72rem">🔒</span>
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>
        <tfoot><tr>
            <td colspan="4" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em">Total gastos</td>
            <td class="num" style="color:#f87171">-{{ $fmt($gastos->sum('valor')) }}</td>
            <td></td>
        </tr></tfoot>
    </table>
    </div>
    @endif
</div>

{{-- ═══════════ 4. Cerrar el día ═══════════ --}}
{{-- El cierre es por persona: con "Todos" solo se mira el total, no se cuadra. --}}
@if($esSuperAdmin && !$cuadreDia && !($verTodos ?? false))
<div class="no-print" style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem;display:flex;
            align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:.8rem">
    <div style="font-size:.83rem;color:#64748b">
        🔒 Al cuadrar quedará registrado que <strong>{{ $usuarioVista->nombre }}</strong> entregó
        <strong>{{ $fmt($resumen['saldo_esperado']) }}</strong> del {{ $carbonFecha->format('d/m/Y') }}.
    </div>
    <button onclick="document.getElementById('modal-cerrar').style.display='flex'" class="btn-sm btn-cerrar">
        🔒 Cuadrar este día
    </button>
</div>
@endif

{{-- ═══════════ 5. Consolidado (superadmin) ═══════════ --}}
@if($esSuperAdmin)
<div class="no-print" style="text-align:center;margin:1.2rem 0 .5rem">
    <a href="{{ route('admin.cuadre-diario.consolidado', ['fecha' => $fecha]) }}"
       style="display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1.1rem;border-radius:8px;
              background:#0f172a;color:#cbd5e1;text-decoration:none;font-size:.8rem;font-weight:600">
        📊 Ver el consolidado del día — todos los usuarios
    </a>
</div>
@endif

{{-- ═══════════ Modales ═══════════ --}}

{{-- Detalle de consignaciones del día, por cuenta --}}
<div id="modal-consig"
     onclick="if(event.target.id==='modal-consig')this.style.display='none'"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:14px;width:min(720px,96vw);max-height:88vh;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,.25);overflow:hidden">
        <div style="background:#0369a1;padding:.85rem 1.1rem;display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="color:#fff;font-weight:700;font-size:.95rem">🏦 Consignado en cuentas</div>
                <div style="color:rgba(255,255,255,.7);font-size:.72rem;margin-top:.1rem">
                    Soportes que registraste el {{ $carbonFecha->format('d/m/Y') }} · Total {{ $fmt($totalConsignado) }}
                </div>
            </div>
            <button onclick="document.getElementById('modal-consig').style.display='none'"
                    style="background:rgba(255,255,255,.18);color:#fff;border:none;border-radius:5px;width:28px;height:28px;cursor:pointer;font-weight:800;font-size:1rem">×</button>
        </div>

        <div style="overflow-y:auto">
            @if($consignaciones->isEmpty())
            <div class="vacio">Sin consignaciones registradas ese día</div>
            @else
            @foreach($consignaciones as $bancoId => $movs)
            @php $bc = $movs->first()->bancoCuenta; @endphp
            <div style="border-bottom:1px solid #f1f5f9">
                <div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;padding:.5rem 1rem">
                    <div style="font-size:.8rem;font-weight:700;color:#0f172a">
                        {{ $bc?->banco ?? 'Cuenta ' . $bancoId }} {{ $bc?->nombre }}
                        @if($bc?->numero_cuenta)
                        <span style="font-weight:500;color:#94a3b8;font-size:.72rem"> · {{ $bc->numero_cuenta }}</span>
                        @endif
                    </div>
                    <div style="font-size:.9rem;font-weight:800;color:#0369a1">{{ $fmt($movs->sum('valor')) }}</div>
                </div>
                <table class="tbl-cd">
                    <tbody>
                    @foreach($movs as $cs)
                    <tr>
                        <td style="width:110px;font-size:.75rem;color:#64748b">
                            {{ $cs->factura?->numero_factura ? 'Fact. '.$cs->factura->numero_factura : ucfirst($cs->tipo ?? 'ingreso') }}
                        </td>
                        <td style="font-size:.77rem">{{ $cs->observacion ?: ($cs->referencia ?: '—') }}</td>
                        <td style="width:130px">
                            @if($cs->no_aparece)
                            <span class="badge-tipo" style="background:#dc2626">❌ No aparece</span>
                            @elseif($cs->confirmado)
                            <span class="badge-tipo" style="background:#15803d">✅ Confirmada</span>
                            @else
                            <span class="badge-tipo" style="background:#f59e0b">🕐 Pendiente</span>
                            @endif
                        </td>
                        <td class="num" style="width:130px;font-weight:700;color:#0369a1">{{ $fmt($cs->valor) }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endforeach
            @endif
        </div>
    </div>
</div>

@if(!$diaBloqueado && $esPropio)
@include('admin.partials.modal_gasto', [
    'formAction'   => route('admin.cuadre-diario.gasto.store'),
    'bancos'       => $bancosFacturacion,
    'esAdmin'      => $esAdmin,
    'usuarios'     => $usuarios,
    'modalId'      => 'modal-gasto',
    'imagenPaste'  => true,
    'fechaDefault' => $fecha,
])
@endif

@if($esSuperAdmin && !$cuadreDia && !($verTodos ?? false))
<div id="modal-cerrar"
     onclick="if(event.target.id==='modal-cerrar')this.style.display='none'"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:14px;width:min(420px,96vw);box-shadow:0 20px 50px rgba(0,0,0,.3)">
        <div style="background:#991b1b;padding:.8rem 1.1rem;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:center">
            <span style="color:#fff;font-weight:700">🔒 Cuadrar el día</span>
            <button onclick="document.getElementById('modal-cerrar').style.display='none'"
                    style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:5px;width:26px;height:26px;cursor:pointer;font-weight:700">×</button>
        </div>
        <div style="padding:1.2rem">
            <div style="background:#fef3c7;border-radius:8px;padding:.8rem;font-size:.83rem;color:#92400e;margin-bottom:1rem">
                ⚠️ Quedará registrado que <strong>{{ $usuarioVista->nombre }}</strong> entregó
                <strong>{{ $fmt($resumen['saldo_esperado']) }}</strong> en efectivo del
                <strong>{{ $carbonFecha->format('d/m/Y') }}</strong>. Ese día no admitirá más gastos.
            </div>
            <form method="POST" action="{{ route('admin.cuadre-diario.cerrar-dia') }}"
                  style="display:flex;flex-direction:column;gap:.8rem">
                @csrf
                <input type="hidden" name="fecha" value="{{ $fecha }}">
                <input type="hidden" name="usuario_id" value="{{ $usuarioId }}">
                <div>
                    <label style="font-size:.76rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem">Observación</label>
                    <textarea name="observacion" rows="3" placeholder="Ej: Efectivo entregado a caja principal"
                              style="width:100%;padding:.4rem .6rem;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.82rem;resize:vertical"></textarea>
                </div>
                <button type="submit"
                        style="background:#dc2626;color:#fff;border:none;border-radius:8px;padding:.6rem;font-size:.88rem;font-weight:700;cursor:pointer">
                    🔒 Confirmar cuadre del día
                </button>
            </form>
        </div>
    </div>
</div>
@endif

<script>
// Los helpers gasto_* ya están definidos en el partial modal_gasto.
function abrirModalGasto() {
    const form = document.getElementById('modal-gasto-form');
    if (form) {
        form.reset();
        // Re-ocultar paneles que el JS de tipo/forma_pago pudo haber mostrado
        ['modal-gasto-banco-origen','modal-gasto-banco-destino','modal-gasto-blq-usuario']
            .forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });

        const zone = document.getElementById('modal-gasto-paste-zone');
        if (zone) {
            zone.style.borderColor = '#cbd5e1';
            zone.innerHTML = '<div style="font-size:1.3rem">📎</div><p style="font-size:.75rem;color:#64748b;margin:0">Pega imagen (Ctrl+V) o arrastra aquí</p><p style="font-size:.68rem;color:#94a3b8;margin:0">Clic para seleccionar archivo</p>';
        }
        const b64 = document.getElementById('modal-gasto-base64');
        if (b64) b64.value = '';
    }
    document.getElementById('modal-gasto').style.display = 'flex';
}
</script>

@endsection
