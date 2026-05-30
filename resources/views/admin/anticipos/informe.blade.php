@extends('layouts.app')

@section('title', 'Informe de Anticipos')

@section('contenido')
<style>
/* ══════════════════════════════════════
   Informe de Anticipos — BryNex
══════════════════════════════════════ */
.ant-page { max-width: 1200px; margin: 0 auto; padding: 1.5rem 1rem; }

/* Header */
.ant-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: .75rem; margin-bottom: 1.5rem;
}
.ant-title {
    font-size: 1.45rem; font-weight: 900; color: #0f172a;
    display: flex; align-items: center; gap: .55rem;
}
.ant-title span { font-size: 1.6rem; }

/* Filtros */
.ant-filters {
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 14px;
    padding: .9rem 1.2rem; display: flex; gap: .75rem; flex-wrap: wrap;
    align-items: flex-end; margin-bottom: 1.5rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.ant-flt-grp { display: flex; flex-direction: column; gap: .22rem; }
.ant-flt-lbl {
    font-size: .6rem; font-weight: 800; color: #64748b;
    text-transform: uppercase; letter-spacing: .05em;
}
.ant-flt-inp, .ant-flt-sel {
    padding: .38rem .65rem; border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: .82rem; outline: none; background: #fff; font-family: inherit;
    transition: border-color .15s;
}
.ant-flt-inp:focus, .ant-flt-sel:focus { border-color: #d97706; }
.ant-flt-btn {
    padding: .42rem 1.2rem; background: linear-gradient(135deg,#78350f,#d97706);
    color: #fff; border: none; border-radius: 8px; cursor: pointer;
    font-size: .82rem; font-weight: 800; transition: all .18s; white-space: nowrap;
}
.ant-flt-btn:hover { opacity: .9; transform: translateY(-1px); }

/* Tarjetas totales */
.ant-totales {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
    gap: .85rem; margin-bottom: 1.5rem;
}
.ant-card {
    border-radius: 13px; padding: .85rem 1.1rem;
    display: flex; flex-direction: column; gap: .3rem;
}
.ant-card-label { font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; opacity: .8; }
.ant-card-val   { font-size: 1.45rem; font-weight: 900; font-family: monospace; }
.ant-card-recibido  { background: linear-gradient(135deg,#dbeafe,#eff6ff); color: #1e40af; }
.ant-card-aplicado  { background: linear-gradient(135deg,#dcfce7,#f0fdf4); color: #15803d; }
.ant-card-disponible{ background: linear-gradient(135deg,#fef3c7,#fffbeb); color: #92400e; }
.ant-card-devuelto  { background: linear-gradient(135deg,#fee2e2,#fff1f2); color: #991b1b; }

/* Tabla */
.ant-table-wrap {
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 14px;
    overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.05);
}
.ant-table-wrap table {
    width: 100%; border-collapse: collapse; font-size: .8rem;
}
.ant-table-wrap thead th {
    background: #f8fafc; border-bottom: 2px solid #e2e8f0;
    padding: .55rem .85rem; font-weight: 800; color: #64748b;
    font-size: .65rem; text-transform: uppercase; letter-spacing: .06em;
    text-align: left; white-space: nowrap;
}
.ant-table-wrap tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .12s; }
.ant-table-wrap tbody tr:last-child { border-bottom: none; }
.ant-table-wrap tbody tr:hover { background: #fafafa; }
.ant-table-wrap td { padding: .5rem .85rem; color: #1e293b; vertical-align: middle; }
.ant-mono { font-family: 'JetBrains Mono', monospace; font-weight: 700; }

/* Badges */
.ant-badge {
    display: inline-flex; align-items: center; gap: .25rem;
    padding: .18rem .55rem; border-radius: 20px; font-size: .65rem; font-weight: 800;
}
.badge-disponible { background: #dcfce7; color: #15803d; }
.badge-parcial    { background: #fef3c7; color: #92400e; }
.badge-aplicado   { background: #f1f5f9; color: #64748b; }
.badge-devuelto   { background: #fee2e2; color: #991b1b; }

/* Forma pago */
.ant-forma {
    display: inline-flex; align-items: center; gap: .22rem;
    font-size: .7rem; font-weight: 700; color: #475569;
    background: #f8fafc; border: 1px solid #e2e8f0;
    padding: .1rem .4rem; border-radius: 5px;
}

/* Factura link */
.ant-factura-link {
    font-size: .7rem; color: #1d4ed8; font-weight: 700;
    text-decoration: none; background: #eff6ff;
    border: 1px solid #bfdbfe; border-radius: 5px; padding: .1rem .4rem;
    transition: background .15s;
}
.ant-factura-link:hover { background: #dbeafe; }

/* Empty */
.ant-empty {
    padding: 3rem; text-align: center; color: #94a3b8;
    font-size: .9rem; font-weight: 600;
}
.ant-empty-icon { font-size: 2.5rem; display: block; margin-bottom: .75rem; }

/* Responsive */
@media (max-width: 640px) {
    .ant-table-wrap { overflow-x: auto; }
}
</style>

<div class="ant-page">

    {{-- HEADER --}}
    <div class="ant-header">
        <h1 class="ant-title">
            <span>💰</span> Informe de Anticipos
        </h1>
        <a href="{{ url()->previous() }}"
           style="padding:.42rem 1.1rem;background:#fff;color:#475569;border:1.5px solid #e2e8f0;border-radius:8px;text-decoration:none;font-size:.8rem;font-weight:700;">
            ← Volver
        </a>
    </div>

    {{-- FILTROS --}}
    <form method="GET" action="{{ route('admin.anticipos.informe') }}" class="ant-filters">
        <div class="ant-flt-grp">
            <span class="ant-flt-lbl">Desde</span>
            <input type="date" name="desde" class="ant-flt-inp" value="{{ $desde }}">
        </div>
        <div class="ant-flt-grp">
            <span class="ant-flt-lbl">Hasta</span>
            <input type="date" name="hasta" class="ant-flt-inp" value="{{ $hasta }}">
        </div>
        <div class="ant-flt-grp">
            <span class="ant-flt-lbl">Estado</span>
            <select name="estado" class="ant-flt-sel">
                <option value="">— Todos —</option>
                <option value="disponible" {{ $estado === 'disponible' ? 'selected' : '' }}>✅ Disponible</option>
                <option value="parcial"    {{ $estado === 'parcial'    ? 'selected' : '' }}>🟡 Parcial</option>
                <option value="aplicado"   {{ $estado === 'aplicado'   ? 'selected' : '' }}>📋 Aplicado</option>
                <option value="devuelto"   {{ $estado === 'devuelto'   ? 'selected' : '' }}>↩️ Devuelto</option>
            </select>
        </div>
        <button type="submit" class="ant-flt-btn">🔍 Filtrar</button>
    </form>

    {{-- TOTALES --}}
    <div class="ant-totales">
        <div class="ant-card ant-card-recibido">
            <span class="ant-card-label">💵 Total recibido</span>
            <span class="ant-card-val">${{ number_format($totales['recibido'], 0, ',', '.') }}</span>
            <span style="font-size:.68rem;opacity:.75;">{{ $anticipos->count() }} anticipo(s)</span>
        </div>
        <div class="ant-card ant-card-aplicado">
            <span class="ant-card-label">✅ Total aplicado</span>
            <span class="ant-card-val">${{ number_format($totales['aplicado'], 0, ',', '.') }}</span>
            <span style="font-size:.68rem;opacity:.75;">Ya vinculado a facturas</span>
        </div>
        <div class="ant-card ant-card-disponible">
            <span class="ant-card-label">⏳ Disponible</span>
            <span class="ant-card-val">${{ number_format($totales['disponible'], 0, ',', '.') }}</span>
            <span style="font-size:.68rem;opacity:.75;">Saldo sin usar</span>
        </div>
        <div class="ant-card ant-card-devuelto">
            <span class="ant-card-label">↩️ Devuelto</span>
            <span class="ant-card-val">${{ number_format($totales['devuelto'], 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- TABLA --}}
    <div class="ant-table-wrap">
        @if($anticipos->isEmpty())
            <div class="ant-empty">
                <span class="ant-empty-icon">💰</span>
                No hay anticipos registrados en el período seleccionado.
            </div>
        @else
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha pago</th>
                    <th>Cliente / Empresa</th>
                    <th>Forma</th>
                    <th>Referencia</th>
                    <th style="text-align:right">Valor</th>
                    <th style="text-align:right">Aplicado</th>
                    <th style="text-align:right">Disponible</th>
                    <th>Estado</th>
                    <th>Factura</th>
                    <th>Registrado por</th>
                </tr>
            </thead>
            <tbody>
            @foreach($anticipos as $ant)
            <tr>
                {{-- ID --}}
                <td style="font-size:.68rem;color:#94a3b8;font-weight:700;">#{{ $ant->id }}</td>

                {{-- Fecha --}}
                <td class="ant-mono" style="font-size:.75rem;">
                    {{ $ant->fecha_pago->format('d/m/Y') }}
                </td>

                {{-- Cliente o empresa --}}
                <td>
                    @if($ant->empresa)
                        <div style="font-weight:700;font-size:.78rem;">🏢 {{ $ant->empresa->empresa }}</div>
                        <div style="font-size:.65rem;color:#94a3b8;">Empresa</div>
                    @elseif($ant->contrato?->cliente)
                        <div style="font-weight:700;font-size:.78rem;">
                            {{ $ant->contrato->cliente->nombre ?? ($ant->cedula ?? '—') }}
                        </div>
                        <div style="font-size:.65rem;color:#94a3b8;">{{ $ant->cedula }}</div>
                    @else
                        <span style="color:#94a3b8;font-size:.75rem;">{{ $ant->cedula ?? '—' }}</span>
                    @endif
                </td>

                {{-- Forma de pago --}}
                <td>
                    <span class="ant-forma">
                        {{ match($ant->forma_pago) {
                            'efectivo'      => '💵',
                            'nequi'         => '📱',
                            'consignacion'  => '🏦',
                            'transferencia' => '↔️',
                            default         => '💳',
                        } }}
                        {{ \App\Models\Anticipo::FORMAS_PAGO[$ant->forma_pago] ?? ucfirst($ant->forma_pago) }}
                    </span>
                </td>

                {{-- Referencia --}}
                <td style="font-size:.72rem;color:#64748b;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    {{ $ant->referencia ?? '—' }}
                </td>

                {{-- Valor total --}}
                <td class="ant-mono" style="text-align:right;color:#1e40af;">
                    ${{ number_format($ant->valor, 0, ',', '.') }}
                </td>

                {{-- Aplicado --}}
                <td class="ant-mono" style="text-align:right;color:#15803d;">
                    @if($ant->valor_aplicado > 0)
                        ${{ number_format($ant->valor_aplicado, 0, ',', '.') }}
                    @else
                        <span style="color:#94a3b8;">—</span>
                    @endif
                </td>

                {{-- Disponible --}}
                <td class="ant-mono" style="text-align:right;color:#d97706;font-weight:900;">
                    @if($ant->valor_disponible > 0)
                        ${{ number_format($ant->valor_disponible, 0, ',', '.') }}
                    @else
                        <span style="color:#94a3b8;">$0</span>
                    @endif
                </td>

                {{-- Estado --}}
                <td>
                    <span class="ant-badge badge-{{ $ant->estado }}">
                        {{ $ant->etiqueta_estado }}
                    </span>
                </td>

                {{-- Factura vinculada --}}
                <td>
                    @if($ant->factura)
                        <a href="{{ route('admin.facturacion.recibo', $ant->factura->id) }}"
                           target="_blank" class="ant-factura-link">
                            #{{ $ant->factura->numero_factura }}
                            <span style="font-weight:500;font-size:.62rem;opacity:.75;">
                                {{ $ant->factura->mes }}/{{ $ant->factura->anio }}
                            </span>
                        </a>
                    @else
                        <span style="color:#94a3b8;font-size:.7rem;">Sin factura</span>
                    @endif
                </td>

                {{-- Usuario --}}
                <td style="font-size:.7rem;color:#64748b;">
                    {{ $ant->usuario?->nombre ?? 'Sistema' }}
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Nota informativa --}}
    <div style="margin-top:1.2rem;padding:.65rem 1rem;background:#fef3c7;border:1.5px solid #fde68a;border-radius:10px;font-size:.75rem;color:#78350f;font-weight:600;line-height:1.7;">
        📌 <strong>Cómo funciona:</strong>
        Los anticipos se registran en el mes que se reciben y aparecen como ingreso en el cuadre diario de ese día.
        Cuando se factura, el valor aplicado queda como <em>Anticipo aplicado</em> en la factura —
        sin sumarlo de nuevo como ingreso nuevo — evitando doble conteo en el cuadre diario del mes de facturación.
    </div>

</div>
@endsection
