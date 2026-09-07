@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Patrimonio Físico')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ openCrear: false }">

    @component('finanzas.partials._header_banner', [
        'titulo' => '🏠 Control de Patrimonio Físico',
        'subtitulo' => 'Control de vehículos, inmuebles, tecnología u otros bienes tangibles con su depreciación o valorización.',
        'breadcrumb' => [
            'Finanzas Personales' => route('finanzas.dashboard'),
            'Patrimonio' => null
        ]
    ])
    @endcomponent

    {{-- Grid de KPIs del Patrimonio --}}
    <div class="fin-kpis-grid">
        <div class="kpi-card" style="border-left: 4px solid #006064">
            <div class="kpi-icon">🏠</div>
            <div class="kpi-content">
                <span class="kpi-label">Valor de Adquisición (Total)</span>
                <span class="kpi-val">${{ number_format($valorTotalPatrimonio, 0, ',', '.') }} COP</span>
            </div>
        </div>
        <div class="kpi-card" style="border-left: 4px solid #00acc1">
            <div class="kpi-icon">📈</div>
            <div class="kpi-content">
                <span class="kpi-label">Valor Comercial Estimado</span>
                <span class="kpi-val">${{ number_format($valorTotalActual, 0, ',', '.') }} COP</span>
            </div>
        </div>
    </div>

    {{-- Tabla de bienes --}}
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 2px 8px rgba(0,0,0,0.04); margin-top:1.5rem; overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.8rem; white-space:nowrap;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0; color:#64748b; text-align:left;">
                        <th style="padding:0.7rem 0.9rem; font-weight:700;">Bien</th>
                        <th style="padding:0.7rem 0.9rem; font-weight:700;">Adquirido</th>
                        <th style="padding:0.7rem 0.9rem; font-weight:700; text-align:right;">Costó</th>
                        <th style="padding:0.7rem 0.9rem; font-weight:700; text-align:right;">Vale hoy</th>
                        <th style="padding:0.7rem 0.9rem; font-weight:700; text-align:right;">Diferencia</th>
                        <th style="padding:0.7rem 0.9rem; font-weight:700; text-align:right;">Mantenimiento</th>
                        <th style="padding:0.7rem 0.9rem; font-weight:700; text-align:center;">Estado</th>
                        <th style="padding:0.7rem 0.9rem;"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($patrimonios as $pat)
                    @php
                        $catIcon = match($pat->categoria) {
                            'inmueble' => '🏢',
                            'vehiculo' => '🚗',
                            'electronico' => '💻',
                            'joya' => '💎',
                            default => '📦'
                        };
                        $tasa = \App\Models\Finanzas\Patrimonio::DEVALUACION_ANUAL[$pat->categoria] ?? 0;
                    @endphp
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:0.7rem 0.9rem;">
                            <strong style="color:#0f172a;">{{ $catIcon }} {{ $pat->nombre }}</strong>
                            <small style="display:block; color:#94a3b8; font-size:0.68rem; text-transform:uppercase; font-weight:600;">
                                {{ $pat->categoria }}@if($tasa > 0) · −{{ number_format($tasa * 100, 0) }}%/año @endif
                            </small>
                        </td>
                        <td style="padding:0.7rem 0.9rem; color:#64748b;">{{ \Carbon\Carbon::parse($pat->fecha_adquisicion)->format('d/m/Y') }}</td>
                        <td style="padding:0.7rem 0.9rem; text-align:right; color:#475569;">${{ number_format($pat->valor_compra, 0, ',', '.') }}</td>
                        <td style="padding:0.7rem 0.9rem; text-align:right; font-weight:700; color:#0f172a;">${{ number_format($pat->valor_estimado, 0, ',', '.') }}</td>
                        <td style="padding:0.7rem 0.9rem; text-align:right; font-weight:600; color:{{ $pat->diferencia_valor < 0 ? '#ef4444' : ($pat->diferencia_valor > 0 ? '#10b981' : '#94a3b8') }};">
                            @if($pat->diferencia_valor == 0) — @else
                                {{ $pat->diferencia_valor < 0 ? '▼' : '▲' }} ${{ number_format(abs($pat->diferencia_valor), 0, ',', '.') }}
                            @endif
                        </td>
                        <td style="padding:0.7rem 0.9rem; text-align:right; color:{{ ($pat->gastos_sum_monto ?? 0) > 0 ? '#b91c1c' : '#cbd5e1' }};">
                            ${{ number_format($pat->gastos_sum_monto ?? 0, 0, ',', '.') }}
                        </td>
                        <td style="padding:0.7rem 0.9rem; text-align:center;">
                            @if($pat->activo)
                                <span class="badge-ok-bx">Activo</span>
                            @else
                                <span class="badge-err-bx" style="background:#f1f5f9; color:#64748b; border-color:#cbd5e1;">Vendido</span>
                            @endif
                        </td>
                        <td style="padding:0.7rem 0.9rem; text-align:right;">
                            <a href="{{ route('finanzas.patrimonio.show', $pat->id) }}" class="btn-fin-small primary" style="background:rgba(0,96,100,0.1); color:#006064; text-decoration:none; padding:0.3rem 0.6rem; border-radius:6px; font-size:0.72rem; font-weight:600;">
                                👁️ Ficha
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center; padding:2.5rem; color:#64748b;">
                            No tienes bienes patrimoniales registrados.
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if($patrimonios->count() > 0)
                <tfoot>
                    <tr style="border-top:2px solid #cbd5e1; background:#f8fafc; font-weight:700;">
                        @php
                            $activos = $patrimonios->where('activo', true);
                            $difTotal = $activos->sum->diferencia_valor;
                        @endphp
                        <td colspan="2" style="padding:0.7rem 0.9rem; color:#475569;">TOTAL ({{ $activos->count() }} bienes activos)</td>
                        <td style="padding:0.7rem 0.9rem; text-align:right; color:#475569;">${{ number_format($activos->sum('valor_compra'), 0, ',', '.') }}</td>
                        <td style="padding:0.7rem 0.9rem; text-align:right; color:#0f172a;">${{ number_format($valorTotalActual, 0, ',', '.') }}</td>
                        <td style="padding:0.7rem 0.9rem; text-align:right; color:{{ $difTotal < 0 ? '#ef4444' : '#10b981' }};">
                            {{ $difTotal < 0 ? '▼' : '▲' }} ${{ number_format(abs($difTotal), 0, ',', '.') }}
                        </td>
                        <td style="padding:0.7rem 0.9rem; text-align:right; color:#b91c1c;">${{ number_format($patrimonios->sum('gastos_sum_monto'), 0, ',', '.') }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Registrar, al final de la tabla --}}
    <div style="margin-top:1rem;">
        <button @click="openCrear = true" class="btn-fin"
                style="background:linear-gradient(135deg,#00838f,#006064); color:#fff; width:100%; border:none; padding:0.75rem; border-radius:12px; font-weight:700; cursor:pointer; box-shadow:0 4px 12px rgba(0,96,100,0.25); display:flex; align-items:center; justify-content:center; gap:0.5rem;">
            ➕ Registrar Bien
        </button>
    </div>

    {{-- Modal Crear --}}
    <div x-show="openCrear" class="modal-overlay-bx" @click.self="openCrear = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #004d40, #006064);">
                <h3>🏠 Registrar Nuevo Bien Patrimonial</h3>
                <button @click="openCrear = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.patrimonio.store') }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre del Bien / Activo</label>
                        <input type="text" name="nombre" placeholder="Ej: Carro Mazda, Apartamento 502" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Categoría del Patrimonio</label>
                        <select name="categoria" class="form-select-bx" required>
                            <option value="inmueble">🏢 Inmueble (Apartamento/Casa/Lote)</option>
                            <option value="vehiculo">🚗 Vehículo / Moto</option>
                            <option value="electronico">💻 Tecnología (Portátil/Celular)</option>
                            <option value="joya">💎 Joyas / Metales Preciosos</option>
                            <option value="otro">📦 Otro Bien Físico</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Valor de Compra ($ COP)</label>
                            <input type="number" name="valor_compra" placeholder="Ej: 45000000" class="form-input-bx" required min="1">
                        </div>
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Fecha Adquisición</label>
                            <input type="date" name="fecha_adquisicion" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                        </div>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem; max-width:50%;">
                        <label class="form-label-bx">Valor Comercial Actual</label>
                        <input type="number" name="valor_actual" placeholder="Ej: 43000000" class="form-input-bx" min="0">
                    </div>
                    
                    {{-- De dónde sale la plata --}}
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">¿De dónde salió la plata?</label>
                        <select name="cuenta_id" class="form-select-bx">
                            <option value="">No registrar salida — ya tenía este bien</option>
                            @foreach($cuentas as $cuenta)
                                <option value="{{ $cuenta->id }}">{{ $cuenta->icono ?? '💳' }} {{ $cuenta->nombre }}</option>
                            @endforeach
                        </select>
                        <small style="display:block; font-size:0.7rem; color:#64748b; margin-top:0.25rem;">
                            La compra baja el saldo de esa cuenta, pero no cuenta como gasto del mes:
                            el bien queda y se puede vender.
                        </small>
                    </div>

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones</label>
                        <textarea name="observaciones" placeholder="Detalles o descripción del bien..." class="form-input-bx" style="height:70px; resize:none;"></textarea>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openCrear = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#006064;">Registrar Bien</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

/* KPIs */

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { border: 1px solid; border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }

/* Modales */

</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
