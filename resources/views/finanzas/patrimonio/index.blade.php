@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Patrimonio Físico')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ openCrear: false }">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <span>Patrimonio</span>
        </div>
        
        <div>
            <button @click="openCrear = true" class="btn-fin success" style="background:#006064;">
                ➕ Registrar Bien
            </button>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>🏠 Control de Patrimonio Físico</h1>
            <p>Control de vehículos, inmuebles, tecnología u otros bienes tangibles con su depreciación o valorización.</p>
        </div>
    </div>

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

    {{-- Grid de Bienes --}}
    <div class="patrimonio-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:1.25rem; margin-top:1.5rem;">
        @forelse($patrimonios as $pat)
            @php
                $catIcon = match($pat->categoria) {
                    'inmueble' => '🏢',
                    'vehiculo' => '🚗',
                    'electronico' => '💻',
                    'joya' => '💎',
                    default => '📦'
                };
            @endphp
            <div class="pat-card" style="border-top:4px solid #006064; background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:1.25rem; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                        <div>
                            <span style="font-size:1.5rem; margin-bottom:0.25rem; display:block;">{{ $catIcon }}</span>
                            <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a;">{{ $pat->nombre }}</h3>
                            <small style="color:#64748b; font-size:0.7rem; text-transform:uppercase; font-weight:600;">{{ $pat->categoria }}</small>
                        </div>
                        @if($pat->activo)
                            <span class="badge-ok-bx">Activo</span>
                        @else
                            <span class="badge-err-bx" style="background:#f1f5f9; color:#64748b; border-color:#cbd5e1;">Vendido</span>
                        @endif
                    </div>
                    
                    <div style="display:flex; flex-direction:column; gap:0.35rem; margin-top:1rem; font-size:0.78rem;">
                        <div style="display:flex; justify-content:space-between;">
                            <span style="color:#64748b;">Valor de Compra:</span>
                            <strong style="color:#334155;">${{ number_format($pat->valor_compra, 0, ',', '.') }}</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span style="color:#64748b;">Valor Actual Est.:</span>
                            <strong style="color:#0f172a; font-size:0.85rem;">${{ number_format($pat->valor_actual ?? $pat->valor_compra, 0, ',', '.') }}</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; border-top:1px dashed #e2e8f0; padding-top:0.4rem; margin-top:0.4rem;">
                            <span style="color:#64748b;">Gastos Mantenimiento:</span>
                            <strong style="color:#b91c1c;">${{ number_format($pat->valor_total_gastos, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                </div>

                <div style="border-top:1px solid #f1f5f9; padding-top:0.75rem; margin-top:1.25rem;">
                    <a href="{{ route('finanzas.patrimonio.show', $pat->id) }}" class="btn-fin-card" style="background:rgba(0,96,100,0.1); color:#006064; text-decoration:none; padding:0.4rem; text-align:center; display:block; border-radius:8px; font-size:0.75rem; font-weight:600;">
                        👁️ Ficha y Gastos
                    </a>
                </div>
            </div>
        @empty
            <div style="grid-column:1/-1; text-align:center; padding:3rem; background:#fff; border-radius:14px; border:1px solid #e2e8f0; color:#64748b;">
                No tienes bienes patrimoniales registrados.
            </div>
        @endforelse
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
                    
                    {{-- Registrar gasto check --}}
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-top:1rem;">
                        <input type="checkbox" name="registrar_gasto" value="1" id="gasto_check" style="cursor:pointer; width:16px; height:16px;">
                        <label for="gasto_check" style="font-size:0.8rem; font-weight:600; color:#475569; cursor:pointer;">
                            ¿Registrar compra en gastos del mes actual?
                        </label>
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
