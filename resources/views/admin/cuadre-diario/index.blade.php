@extends('layouts.app')
@section('modulo', 'Cuadre Diario')

@php
use Carbon\Carbon;
$fmt = fn($v) => '$'.number_format($v ?? 0, 0, ',', '.');
$tiposGasto = \App\Models\Gasto::TIPOS;
$tiposAdmin = \App\Models\Gasto::TIPOS_ADMIN;
$esAdmin = auth()->user()->hasRole(['admin','superadmin']);
$esSuperAdmin = auth()->user()->hasRole('superadmin');
@endphp

@section('contenido')
<style>
.cd-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1rem}
.cd-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.8rem;margin-bottom:1rem}
.cd-card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.2rem}
.cd-card-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:.4rem}
.cd-card-val{font-size:1.5rem;font-weight:800;color:#0f172a}
.cd-card.efectivo .cd-card-val{color:#15803d}
.cd-card.gastos .cd-card-val{color:#dc2626}
.cd-card.saldo .cd-card-val{color:#1d4ed8}
.tbl-cd{width:100%;border-collapse:collapse;font-size:.8rem}
.tbl-cd th{background:#0f172a;color:#94a3b8;font-size:.65rem;text-transform:uppercase;padding:.4rem .6rem;position:sticky;top:0}
.tbl-cd td{padding:.38rem .6rem;border-bottom:1px solid #f1f5f9}
.tbl-cd tr:hover td{background:#f8fafc}
.num{text-align:right;font-family:monospace}
.badge-tipo{padding:.12rem .4rem;border-radius:20px;font-size:.66rem;font-weight:700}
.btn-sm{padding:.25rem .65rem;border-radius:6px;font-size:.75rem;font-weight:600;border:none;cursor:pointer}
.btn-open{background:#2563eb;color:#fff}
.btn-gasto{background:#065f46;color:#fff}
.btn-cerrar{background:#dc2626;color:#fff}
.dia-row td{background:#f8fafc;font-weight:600}
</style>

{{-- Header --}}
<div class="cd-header">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
        <div>
            <div style="font-size:1.15rem;font-weight:800">💰 Cuadre Diario</div>
            <div style="font-size:.8rem;color:#94a3b8;margin-top:.2rem">
                {{ auth()->user()->nombre }} — {{ now()->format('d/m/Y') }}
            </div>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
            @if($esAdmin)
            <a href="{{ route('admin.cuadre-diario.consolidado') }}"
               style="padding:.35rem .85rem;font-size:.78rem;font-weight:600;border-radius:7px;background:rgba(255,255,255,.12);color:#cbd5e1;text-decoration:none">
                📊 Consolidado
            </a>
            @endif
            @if($esAdmin)
            <a href="{{ route('admin.cuadre-diario.facturas-dia') }}"
               style="padding:.35rem .85rem;font-size:.78rem;font-weight:600;border-radius:7px;background:rgba(255,255,255,.12);color:#cbd5e1;text-decoration:none">
                🧾 Facturas del día
            </a>
            @endif
            @if($esAdmin)
            <a href="{{ route('admin.cuadre-diario.bancos') }}"
               style="padding:.35rem .85rem;font-size:.78rem;font-weight:600;border-radius:7px;background:rgba(255,255,255,.12);color:#cbd5e1;text-decoration:none">
                🏦 Bancos
            </a>
            @endif
            @if($esAdmin)
            <a href="{{ route('admin.anticipos.informe') }}"
               style="padding:.35rem .85rem;font-size:.78rem;font-weight:600;border-radius:7px;background:rgba(253,230,138,.25);color:#fde68a;text-decoration:none">
                💰 Anticipos
            </a>
            @endif
            @if($esAdmin)
            <a href="{{ route('admin.caja-menor.index') }}"
               style="padding:.35rem .85rem;font-size:.78rem;font-weight:600;border-radius:7px;background:#f59e0b;color:#fff;text-decoration:none">
                💵 Caja Menor
            </a>
            @endif
        </div>
    </div>
</div>

@if(session('error'))
<div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:.6rem 1rem;border-radius:8px;margin-bottom:.8rem;font-size:.83rem">
    ❌ {{ session('error') }}
</div>
@endif

@if(!$cuadre)
{{-- Sin cuadre abierto --}}
<div style="background:#fff;border-radius:14px;border:2px dashed #cbd5e1;padding:3rem;text-align:center">
    <div style="font-size:2.5rem;margin-bottom:.8rem">📋</div>
    <div style="font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:.4rem">No tienes un cuadre abierto</div>
    <div style="color:#64748b;font-size:.85rem;margin-bottom:1.5rem">
        Caja menor disponible: <strong>{{ $fmt($cajaMenor) }}</strong>
    </div>
    <form method="POST" action="{{ route('admin.cuadre-diario.abrir') }}">
        @csrf
        <button type="submit" class="btn-sm btn-open" style="font-size:.9rem;padding:.5rem 1.5rem">
            ▶ Abrir Cuadre
        </button>
    </form>
</div>

@else
{{-- Cuadre abierto --}}

{{-- Tarjetas resumen --}}
@php
$totalBancos = $bancos->sum(fn($bc) => \App\Models\Consignacion::saldoBanco(session('aliado_id_activo'), $bc->id));
@endphp
<div class="cd-cards" style="grid-template-columns:repeat(5,1fr)">
    <div class="cd-card efectivo">
        <div class="cd-card-title">💵 Efectivo cobrado</div>
        <div class="cd-card-val">{{ $fmt($datosPeriodo['efectivo_total']) }}</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.3rem">
            {{ $facturasPeriodo->count() }} facturas
        </div>
    </div>
    {{-- Cobros de cartera: abonos a préstamos cuya plata entró en este período --}}
    @if(($datosPeriodo['cobros_cartera'] ?? 0) > 0)
    <div class="cd-card" style="border-color:#d1fae5">
        <div class="cd-card-title" style="color:#065f46">📋 Cobros cartera</div>
        <div class="cd-card-val" style="color:#065f46">{{ $fmt($datosPeriodo['cobros_cartera']) }}</div>
        <div style="font-size:.72rem;color:#6b7280;margin-top:.3rem">Préstamos recuperados</div>
    </div>
    @endif
    {{-- Anticipos efectivo/Nequi recibidos en este período --}}
    @if(($datosPeriodo['anticipos_efectivo'] ?? 0) > 0)
    <div class="cd-card" style="border-color:#fde68a">
        <div class="cd-card-title" style="color:#78350f">💰 Anticipos recibidos</div>
        <div class="cd-card-val" style="color:#78350f">{{ $fmt($datosPeriodo['anticipos_efectivo']) }}</div>
        <div style="font-size:.72rem;color:#6b7280;margin-top:.3rem">Efectivo/Nequi (no facturado aún)</div>
    </div>
    @endif
    {{-- Total prestado: informativo, no es ingreso real --}}
    @if(($datosPeriodo['total_prestado'] ?? 0) > 0)
    <div class="cd-card" style="border-color:#fde68a">
        <div class="cd-card-title" style="color:#92400e">⚠️ Total prestado</div>
        <div class="cd-card-val" style="color:#b45309">{{ $fmt($datosPeriodo['total_prestado']) }}</div>
        <div style="font-size:.72rem;color:#6b7280;margin-top:.3rem">Cartera por cobrar</div>
    </div>
    @endif
    {{-- Bancos: una sola tarjeta clickeable --}}
    <div class="cd-card" onclick="document.getElementById('modal-bancos').style.display='flex'"
         style="cursor:pointer;border-color:#bfdbfe;transition:box-shadow .15s"
         onmouseover="this.style.boxShadow='0 4px 16px rgba(37,99,235,.15)'"
         onmouseout="this.style.boxShadow=''">
        <div class="cd-card-title" style="color:#1d4ed8">🏦 Bancos
            <span style="font-size:.6rem;background:#dbeafe;color:#1d4ed8;padding:.1rem .35rem;border-radius:20px;margin-left:.3rem;font-weight:600">
                {{ $bancos->count() }} cuentas
            </span>
        </div>
        <div class="cd-card-val" style="color:#1d4ed8">{{ $fmt($totalBancos) }}</div>
        <div style="font-size:.7rem;color:#64748b;margin-top:.3rem">Clic para ver detalle</div>
    </div>
    <div class="cd-card gastos">
        <div class="cd-card-title">📤 Gastos efectivo</div>
        <div class="cd-card-val">-{{ $fmt($datosPeriodo['gastos_efectivo']) }}</div>
    </div>
    <div class="cd-card saldo">
        <div class="cd-card-title">✅ Saldo esperado</div>
        <div class="cd-card-val">{{ $fmt($datosPeriodo['saldo_final']) }}</div>
    </div>
</div>

{{-- Modal: Detalle de cuentas bancarias --}}
<div id="modal-bancos"
     onclick="if(event.target.id==='modal-bancos')this.style.display='none'"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:14px;width:min(480px,96vw);box-shadow:0 20px 50px rgba(0,0,0,.25);overflow:hidden">
        <div style="background:#1e3a5f;padding:.85rem 1.1rem;display:flex;justify-content:space-between;align-items:center">
            <span style="color:#fff;font-weight:700;font-size:.95rem">🏦 Saldos por cuenta bancaria</span>
            <button onclick="document.getElementById('modal-bancos').style.display='none'"
                    style="background:rgba(255,255,255,.18);color:#fff;border:none;border-radius:5px;width:28px;height:28px;cursor:pointer;font-weight:800;font-size:1rem">×</button>
        </div>
        <div style="padding:1.1rem;display:flex;flex-direction:column;gap:.55rem">
            @forelse($bancos as $bc)
            @php $saldo = \App\Models\Consignacion::saldoBanco(session('aliado_id_activo'), $bc->id); @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;
                        background:#f8fafc;border-radius:9px;padding:.65rem .9rem;
                        border:1px solid #e2e8f0">
                <div>
                    <div style="font-weight:700;font-size:.85rem;color:#0f172a">
                        {{ $bc->banco }} {{ $bc->nombre }}
                    </div>
                    @if($bc->numero_cuenta)
                    <div style="font-size:.72rem;color:#94a3b8;margin-top:.1rem">{{ $bc->numero_cuenta }}</div>
                    @endif
                </div>
                <div style="font-size:1.05rem;font-weight:800;color:{{ $saldo > 0 ? '#1d4ed8' : ($saldo < 0 ? '#dc2626' : '#64748b') }}">
                    {{ $fmt($saldo) }}
                </div>
            </div>
            @empty
            <div style="text-align:center;color:#94a3b8;font-size:.83rem;padding:1rem">Sin cuentas registradas</div>
            @endforelse
            <div style="border-top:2px solid #e2e8f0;margin-top:.3rem;padding-top:.7rem;
                        display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:.78rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.04em">Total bancos</span>
                <span style="font-size:1.15rem;font-weight:800;color:#1d4ed8">{{ $fmt($totalBancos) }}</span>
            </div>
            <a href="{{ route('admin.cuadre-diario.bancos') }}"
               style="text-align:center;font-size:.78rem;color:#2563eb;text-decoration:none;font-weight:600;padding:.4rem;border-radius:7px;border:1px solid #bfdbfe;margin-top:.2rem">
                Ver movimientos completos →
            </a>
        </div>
    </div>
</div>

{{-- Tabla de caja por día --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:.8rem">
    <div style="padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between">
        <div style="font-size:.85rem;font-weight:700">
            📅 Período: {{ $cuadre->fecha_inicio->format('d/m/Y') }} —
            {{ $cuadre->fecha_fin ? $cuadre->fecha_fin->format('d/m/Y') : 'Abierto' }}
        </div>
        <div style="font-size:.75rem;color:#64748b">
            Caja Menor Inicial: <strong>{{ $fmt($cajaMenor) }}</strong>
        </div>
    </div>
    <div style="overflow-x:auto">
    <table class="tbl-cd">
        <thead><tr>
            <th>Día</th>
            <th class="num">+ Ingresos efectivo</th>
            <th class="num" style="color:#6ee7b7">+ Cobros cartera</th>
            <th class="num" style="color:#fde68a">💰 Anticipos</th>
            <th class="num">- Gastos efectivo</th>
            <th class="num">Saldo acumulado</th>
        </tr></thead>
        <tbody>
        @foreach($datosPeriodo['por_dia'] as $dia)
        <tr class="{{ ($dia['ingresos'] > 0 || ($dia['cartera'] ?? 0) > 0 || ($dia['anticipos'] ?? 0) > 0 || $dia['gastos'] > 0) ? '' : 'opacity-40' }}">
            <td>{{ sqldate($dia['fecha'])->locale('es')->isoFormat('ddd DD MMM') }}</td>
            <td class="num" style="color:#15803d">{{ $dia['ingresos'] ? '+'.$fmt($dia['ingresos']) : '—' }}</td>
            <td class="num" style="color:#065f46;font-size:.75rem">
                {{ ($dia['cartera'] ?? 0) > 0 ? '+'.$fmt($dia['cartera']) : '' }}
            </td>
            <td class="num" style="color:#d97706;font-size:.75rem">
                {{ ($dia['anticipos'] ?? 0) > 0 ? '+'.$fmt($dia['anticipos']) : '' }}
            </td>
            <td class="num" style="color:#dc2626">{{ $dia['gastos'] ? '-'.$fmt($dia['gastos']) : '—' }}</td>
            <td class="num" style="font-weight:700;color:{{ $dia['saldo'] >= 0 ? '#1d4ed8' : '#dc2626' }}">
                {{ $fmt($dia['saldo']) }}
            </td>
        </tr>
        @endforeach
        <tr style="background:#0f172a">
            <td style="color:#94a3b8;font-weight:600;font-size:.75rem">SALDO FINAL ESPERADO</td>
            <td class="num" style="color:#4ade80;font-weight:800;font-size:.9rem">{{ $fmt($datosPeriodo['efectivo_total']) }}</td>
            <td class="num" style="color:#6ee7b7;font-weight:700;font-size:.85rem">{{ ($datosPeriodo['cobros_cartera'] ?? 0) > 0 ? '+'.$fmt($datosPeriodo['cobros_cartera']) : '—' }}</td>
            <td class="num" style="color:#fde68a;font-weight:700;font-size:.85rem">{{ ($datosPeriodo['anticipos_efectivo'] ?? 0) > 0 ? '+'.$fmt($datosPeriodo['anticipos_efectivo']) : '—' }}</td>
            <td class="num" style="color:#f87171;font-weight:800;font-size:.9rem">-{{ $fmt($datosPeriodo['gastos_efectivo']) }}</td>
            <td class="num" style="color:#fbbf24;font-weight:800;font-size:.95rem">{{ $fmt($datosPeriodo['saldo_final']) }}</td>
        </tr>
        </tbody>
    </table>
    </div>
</div>

{{-- Gastos registrados --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:.8rem">
    <div style="padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.4rem">
        <div style="font-size:.85rem;font-weight:700">📋 Gastos del período</div>
        <button onclick="abrirModalGasto()" class="btn-sm btn-gasto">
            + Registrar Gasto
        </button>
    </div>
    @if($gastos->isEmpty())
    <div style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:.83rem">Sin gastos registrados</div>
    @else
    <div style="overflow-x:auto">
    <table class="tbl-cd">
        <thead><tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Descripción</th>
            <th>Forma Pago</th>
            <th>Banco</th>
            <th class="num">Valor</th>
            <th style="text-align:center">Acción</th>
        </tr></thead>
        <tbody>
        @foreach($gastos as $g)
        <tr>
            <td style="font-size:.75rem">{{ $g->fecha->format('d/m/Y') }}</td>
            <td>
                <span class="badge-tipo"
                    style="background:{{ in_array($g->tipo, ['nomina','transferencia_banco','banco_banco']) ? '#fef3c7' : '#dbeafe' }};color:{{ in_array($g->tipo, ['nomina','transferencia_banco','banco_banco']) ? '#92400e' : '#1d4ed8' }}">
                    {{ $g->tipoLabel() }}
                </span>
            </td>
            <td style="max-width:250px;font-size:.77rem">
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
                {{ $g->bancoOrigen?->banco ?? '—' }}
                @if($g->bancoDestino) → {{ $g->bancoDestino->banco }} @endif
            </td>
            <td class="num" style="color:#dc2626;font-weight:700">-{{ $fmt($g->valor) }}</td>
            <td style="text-align:center">
                <form method="POST" action="{{ route('admin.cuadre-diario.gasto.destroy', $g->id) }}"
                    onsubmit="return confirm('¿Eliminar este gasto?')" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" style="background:#fee2e2;color:#991b1b;border:none;border-radius:5px;padding:.2rem .5rem;cursor:pointer;font-size:.72rem">
                        🗑️
                    </button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>

{{-- Cerrar cuadre --}}
@if($esSuperAdmin)
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
    <div style="font-size:.83rem;color:#64748b">
        🔒 Cerrar el cuadre registrará que recibiste <strong>{{ $fmt($datosPeriodo['saldo_final']) }}</strong> en efectivo.
    </div>
    <button onclick="document.getElementById('modal-cerrar').style.display='flex'" class="btn-sm btn-cerrar">
        🔒 Cerrar Cuadre
    </button>
</div>
@endif

@endif

{{-- Cuadres anteriores --}}
@if(isset($cuadresAnteriores) && $cuadresAnteriores->isNotEmpty())
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-top:.8rem">
    <div style="padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;font-size:.85rem;font-weight:700">
        🗂️ Cuadres anteriores (últimos 15 días)
    </div>
    <table class="tbl-cd">
        <thead><tr>
            <th>Período</th>
            <th class="num">Saldo Apertura</th>
            <th class="num">Saldo Cierre</th>
            <th>Cerrado por</th>
            <th style="text-align:center">Ver</th>
        </tr></thead>
        <tbody>
        @foreach($cuadresAnteriores as $ca)
        <tr>
            <td style="font-size:.77rem">
                {{ $ca->fecha_inicio->format('d/m/Y') }}
                @if($ca->fecha_fin) — {{ $ca->fecha_fin->format('d/m/Y') }} @endif
            </td>
            <td class="num">{{ $fmt($ca->saldo_apertura) }}</td>
            <td class="num" style="font-weight:700;color:#1d4ed8">{{ $fmt($ca->saldo_cierre) }}</td>
            <td style="font-size:.72rem;color:#64748b">{{ $ca->cerradoPor?->nombre ?? '—' }}</td>
            <td style="text-align:center">
                <a href="{{ route('admin.cuadre-diario.ver', $ca->id) }}"
                   style="padding:.2rem .5rem;background:#0f172a;color:#fff;border-radius:5px;font-size:.72rem;text-decoration:none">
                    👁️ Ver
                </a>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if($cuadre)
@include('admin.partials.modal_gasto', [
    'formAction'  => route('admin.cuadre-diario.gasto.store', $cuadre->id),
    'bancos'      => $bancos,
    'esAdmin'     => $esAdmin,
    'modalId'     => 'modal-gasto',
    'imagenPaste' => true,
])
@endif

{{-- Modal: Cerrar Cuadre --}}
@if($cuadre && $esSuperAdmin)
<div id="modal-cerrar"
     onclick="if(event.target.id==='modal-cerrar')this.style.display='none'"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:14px;width:min(420px,96vw);box-shadow:0 20px 50px rgba(0,0,0,.3)">
        <div style="background:#991b1b;padding:.8rem 1.1rem;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:center">
            <span style="color:#fff;font-weight:700">🔒 Cerrar Cuadre</span>
            <button onclick="document.getElementById('modal-cerrar').style.display='none'"
                    style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:5px;width:26px;height:26px;cursor:pointer;font-weight:700">×</button>
        </div>
        <div style="padding:1.2rem">
            <div style="background:#fef3c7;border-radius:8px;padding:.8rem;font-size:.83rem;color:#92400e;margin-bottom:1rem">
                ⚠️ Al cerrar, quedará registrado que <strong>{{ auth()->user()->nombre }}</strong>
                recibió <strong>{{ $fmt($datosPeriodo['saldo_final']) }}</strong> en efectivo.
            </div>
            <form method="POST" action="{{ route('admin.cuadre-diario.cerrar', $cuadre->id) }}"
                  style="display:flex;flex-direction:column;gap:.8rem">
                @csrf
                <div>
                    <label style="font-size:.76rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem">Observación</label>
                    <textarea name="observacion" rows="3" placeholder="Ej: Efectivo entregado a caja principal"
                              style="width:100%;padding:.4rem .6rem;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.82rem;resize:vertical"></textarea>
                </div>
                <button type="submit"
                        style="background:#dc2626;color:#fff;border:none;border-radius:8px;padding:.6rem;font-size:.88rem;font-weight:700;cursor:pointer">
                    🔒 Confirmar Cierre
                </button>
            </form>
        </div>
    </div>
</div>
@endif

<script>
// Los aliases actualizarBancos/actualizarFormPago ya están definidos en el partial modal_gasto
function abrirModalGasto() {
    const form = document.getElementById('modal-gasto-form');
    if (form) {
        form.reset();
        // Re-ocultar paneles que el JS de tipo/forma_pago podría haber mostrado
        const hide = ['modal-gasto-banco-origen','modal-gasto-banco-destino','modal-gasto-blq-usuario'];
        hide.forEach(id => { const el = document.getElementById(id); if(el) el.style.display='none'; });
        // Limpiar zona de imagen si existe
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
