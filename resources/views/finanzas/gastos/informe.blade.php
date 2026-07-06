@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Informe Anual de Gastos')

@section('contenido')
<div class="finanzas-container">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.gastos.index') }}">Egresos / Gastos</a>
            <span>›</span>
            <span>Informe Anual</span>
        </div>
        
        <form method="GET" action="{{ route('finanzas.gastos.informe') }}" class="period-selector-bx">
            <select name="anio" class="select-fin" onchange="this.form.submit()">
                @foreach(range(2020, now()->year + 1) as $a)
                    <option value="{{ $a }}" @selected($anio == $a)>{{ $a }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>📊 Distribución Anual de Gastos ({{ $anio }})</h1>
            <p>Visualiza el consolidado de gastos clasificado por categorías y meses para identificar patrones de consumo.</p>
        </div>
    </div>

    {{-- Cuadrícula del Informe --}}
    <div class="card-tabla" style="overflow-x:auto;">
        <table class="tabla-informe">
            <thead>
                <tr>
                    <th class="categoria-col">Categoría</th>
                    @foreach(range(1,12) as $m)
                        <th class="mes-col">{{ ucfirst(\Carbon\Carbon::create()->month($m)->locale('es')->shortMonthName) }}</th>
                    @endforeach
                    <th class="total-col">Total Anual</th>
                </tr>
            </thead>
            <tbody>
                @php $granTotal = 0; @endphp
                @forelse($categorias as $categoria)
                    @php $totalCategoria = 0; @endphp
                    <tr>
                        <td class="categoria-name-cell">
                            <span style="display:flex; align-items:center; gap:0.4rem;">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:{{ $categoria->color }}"></span>
                                <strong>{{ $categoria->icono }} {{ $categoria->nombre }}</strong>
                            </span>
                        </td>
                        @foreach(range(1,12) as $mesNum)
                            @php
                                $monto = isset($gastosPeriodo[$categoria->id][$mesNum]) ? $gastosPeriodo[$categoria->id][$mesNum]->first()->total : 0;
                                $totalCategoria += $monto;
                            @endphp
                            <td class="monto-cell {{ $monto > 0 ? 'gasto-activo' : '' }}">
                                {{ $monto > 0 ? '$' . number_format($monto, 0, ',', '.') : '-' }}
                            </td>
                        @endforeach
                        @php $granTotal += $totalCategoria; @endphp
                        <td class="categoria-total-cell">
                            <strong>${{ number_format($totalCategoria, 0, ',', '.') }}</strong>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" style="text-align:center; padding:2rem; color:#64748b;">
                            No tienes categorías registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td><strong>Total Mensual</strong></td>
                    @foreach(range(1,12) as $mesNum)
                        @php
                            $totalMes = 0;
                            foreach($categorias as $cat) {
                                $totalMes += isset($gastosPeriodo[$cat->id][$mesNum]) ? $gastosPeriodo[$cat->id][$mesNum]->first()->total : 0;
                            }
                        @endphp
                        <td><strong>${{ number_format($totalMes, 0, ',', '.') }}</strong></td>
                    @endforeach
                    <td class="gran-total-cell"><strong>${{ number_format($granTotal, 0, ',', '.') }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

/* Tabla Informe */
.card-tabla { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.04); margin-top: 1rem; }
.tabla-informe { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; table-layout: fixed; }
.tabla-informe th, .tabla-informe td { border: 1px solid #e2e8f0; padding: 0.65rem 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tabla-informe th { background: #f8fafc; font-weight: 700; color: #475569; text-align: center; }
.tabla-informe th.categoria-col { width: 200px; text-align: left; }
.tabla-informe th.mes-col { width: 90px; }
.tabla-informe th.total-col { width: 130px; background: #f1f5f9; color: #1e293b; }

.categoria-name-cell { display: flex; align-items: center; justify-content: flex-start; height: 100%; }
.monto-cell { text-align: right; color: #64748b; }
.monto-cell.gasto-activo { color: #b91c1c; font-weight: 500; }

.categoria-total-cell { text-align: right; background: #f8fafc; color: #1e293b; }
.total-row { background: #f1f5f9; color: #0f172a; text-align: right; font-size: 0.82rem; }
.total-row td { border-top: 2px solid #cbd5e1; }
.total-row td:first-child { text-align: left; }
.gran-total-cell { background: #e2e8f0; color: #b91c1c; font-weight: 800; }
</style>
@endpush
