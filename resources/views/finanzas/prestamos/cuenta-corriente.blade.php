@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Cuenta Corriente de Servicios')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.prestamos.index') }}">Préstamos</a>
            <span>›</span>
            <span>Cuenta Corriente</span>
        </div>
        
        <div>
            <a href="{{ route('finanzas.prestamos.create', ['es_cuenta_corriente' => 1]) }}" class="btn-fin success" style="background:#7e22ce; text-decoration:none; display:inline-block; line-height:22px; text-align:center;">
                ➕ Registrar Trabajo / Servicio
            </a>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>💼 Cuenta Corriente de Servicios</h1>
            <p>Control de deudas de trabajos realizados acumulados de un cliente recurrente. Total por Cobrar: <strong style="color:#7e22ce; font-size:1.25rem;">${{ number_format($saldoTotalPendiente, 0, ',', '.') }} COP</strong></p>
        </div>
    </div>

    {{-- Listado por Grupos --}}
    <div class="cuenta-corriente-container" style="display:flex; flex-direction:column; gap:1.5rem; margin-top:1rem;">
        @forelse($grupos as $grupoNombre => $items)
            @php
                $saldoGrupo = $items->whereIn('estado', ['activo', 'mora'])->sum('saldo_actual');
            @endphp
            <div class="cc-grupo-card">
                <div class="cc-grupo-header">
                    <h2>📁 {{ $grupoNombre ?: 'Sin Clasificar' }}</h2>
                    <span class="cc-grupo-total">Por cobrar: <strong>${{ number_format($saldoGrupo, 0, ',', '.') }} COP</strong></span>
                </div>
                
                <div class="card-tabla-bx" style="margin-top:0.5rem; border:none; box-shadow:none;">
                    <table class="tabla-brynex-bx">
                        <thead>
                            <tr>
                                <th style="width: 25%">Descripción Trabajo</th>
                                <th style="width: 15%">Fecha Registro</th>
                                <th style="width: 15%; text-align:right;">Monto Original</th>
                                <th style="width: 10%; text-align:center;">Interés</th>
                                <th style="width: 15%; text-align:right;">Saldo Pendiente</th>
                                <th style="width: 10%; text-align:center;">Estado</th>
                                <th style="width: 10%; text-align:center;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->nombre_deudor }}</strong>
                                        <small style="color:#64748b; display:block; font-size:0.7rem;">{{ $item->descripcion ?: '-' }}</small>
                                    </td>
                                    <td>{{ Carbon\Carbon::parse($item->fecha_desembolso)->format('d/m/Y') }}</td>
                                    <td style="text-align:right;">${{ number_format($item->monto_original, 0, ',', '.') }}</td>
                                    <td style="text-align:center; color:#7e22ce; font-weight:600;">{{ $item->tasa_interes_mensual }}%</td>
                                    <td style="text-align:right; font-weight:700; color:{{ $item->saldo_actual > 0 ? '#b91c1c' : '#16a34a' }};">
                                        ${{ number_format($item->saldo_actual, 0, ',', '.') }}
                                    </td>
                                    <td style="text-align:center;">
                                        @if($item->estado === 'pagado')
                                            <span class="badge-ok-bx">Pagado</span>
                                        @elseif($item->dias_mora > 0)
                                            <span class="badge-err-bx" style="font-size:0.65rem;">Mora: {{ $item->dias_mora }}d</span>
                                        @else
                                            <span class="badge-ok-bx" style="background:rgba(34,197,94,0.1); color:#166534;">Pendiente</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="{{ route('finanzas.prestamos.show', $item->id) }}" class="btn-fin-small primary" style="background:#7e22ce; display:inline-block; text-decoration:none;">
                                            Ver Ficha
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div style="text-align:center; padding:3rem; background:#fff; border-radius:14px; border:1px solid #e2e8f0; color:#64748b;">
                No hay servicios cargados en Cuenta Corriente.
            </div>
        @endforelse
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

/* Grupo Card */
.cc-grupo-card { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; padding: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.cc-grupo-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; margin-bottom: 0.5rem; }
.cc-grupo-header h2 { font-size: 0.95rem; font-weight: 800; color: #1e293b; }
.cc-grupo-total { font-size: 0.8rem; color: #475569; }
.cc-grupo-total strong { color: #7e22ce; font-size: 0.9rem; }

/* Tabla */

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { background: rgba(239,68,68,0.1); color: #b91c1c; border: 1px solid rgba(239,68,68,0.35); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.btn-fin-small { padding: 0.25rem 0.5rem; border: none; border-radius: 6px; font-size: 0.72rem; font-weight: 600; cursor: pointer; }
.btn-fin-small.primary { background: #3b82f6; color: #fff; }
</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
