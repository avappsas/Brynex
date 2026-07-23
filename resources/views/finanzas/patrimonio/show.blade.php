@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Detalle de Bien Patrimonial')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ openGasto: false }">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.patrimonio.index') }}">Patrimonio</a>
            <span>›</span>
            <span>{{ $patrimonio->nombre }}</span>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>🏠 Bien: {{ $patrimonio->nombre }}</h1>
            <p>Ficha técnica de adquisición y registro de gastos operacionales asociados.</p>
        </div>
    </div>

    {{-- Grid de Datos y Formularios --}}
    <div class="prestamo-ficha-grid">
        
        {{-- Detalle Técnico --}}
        <div class="ficha-datos-card">
            <h3>📋 Ficha Técnica</h3>
            <div class="fdc-general-list">
                <div class="fdcg-row">
                    <span>Categoría:</span> <strong>{{ ucfirst($patrimonio->categoria) }}</strong>
                </div>
                <div class="fdcg-row">
                    <span>Fecha Adquisición:</span> <strong>{{ Carbon\Carbon::parse($patrimonio->fecha_adquisicion)->format('d/m/Y') }}</strong>
                </div>
                <div class="fdcg-row" style="border-top:1px dashed #e2e8f0; padding-top:0.4rem; margin-top:0.4rem;">
                    <span>Valor de Adquisición:</span> <strong>${{ number_format($patrimonio->valor_compra, 0, ',', '.') }} COP</strong>
                </div>
                <div class="fdcg-row">
                    <span>Valor Comercial Est.:</span> <strong style="color:#006064;">${{ number_format($patrimonio->valor_actual ?? $patrimonio->valor_compra, 0, ',', '.') }} COP</strong>
                </div>
                <div class="fdcg-row" style="border-top:1px dashed #e2e8f0; padding-top:0.4rem; margin-top:0.4rem;">
                    <span>Total Gastos de Mantenimiento:</span> <strong style="color:#b91c1c;">${{ number_format($patrimonio->valor_total_gastos, 0, ',', '.') }} COP</strong>
                </div>
                <div class="fdcg-row">
                    <span>Estado:</span> 
                    <strong>
                        @if($patrimonio->activo)
                            <span class="badge-ok-bx">Activo</span>
                        @else
                            <span class="badge-err-bx" style="background:#f1f5f9; color:#64748b; border-color:#cbd5e1;">Vendido</span>
                        @endif
                    </strong>
                </div>
            </div>

            @if($patrimonio->observaciones)
                <div class="fac-notes-bx" style="margin-top:1rem;">
                    <strong>Detalles / Notas:</strong>
                    <p>{{ $patrimonio->observaciones }}</p>
                </div>
            @endif
        </div>

        {{-- Formulario Actualizar Valor e Ingresar Gasto --}}
        <div class="ficha-acciones-card">
            <h3>⚡ Registrar Gasto Asociado</h3>
            <p style="font-size:0.75rem; color:#64748b; margin-bottom:0.75rem;">SOAT, seguro todo riesgo, impuesto predial, mantenimientos, etc.</p>
            
            <button @click="openGasto = true" class="btn-fac-action blue" style="background:#006064; width:100%;">
                🔧 Registrar Gasto Bien
            </button>

            <div class="sep-light"></div>

            <h3>⚙️ Actualizar Valor Comercial</h3>
            <form action="{{ route('finanzas.patrimonio.update', $patrimonio->id) }}" method="POST">
                @csrf
                @method('PUT')
                {{-- Campos ocultos para mantener consistencia --}}
                <input type="hidden" name="nombre" value="{{ $patrimonio->nombre }}">
                <input type="hidden" name="categoria" value="{{ $patrimonio->categoria }}">
                <input type="hidden" name="valor_compra" value="{{ $patrimonio->valor_compra }}">
                <input type="hidden" name="fecha_adquisicion" value="{{ $patrimonio->fecha_adquisicion }}">
                <input type="hidden" name="activo" value="{{ $patrimonio->activo ? '1' : '0' }}">
                <input type="hidden" name="observaciones" value="{{ $patrimonio->observaciones }}">

                <div class="form-group-bx">
                    <label class="form-label-bx">Nuevo Valor Comercial ($ COP)</label>
                    <input type="number" name="valor_actual" value="{{ $patrimonio->valor_actual ?? $patrimonio->valor_compra }}" class="form-input-bx" required min="0">
                </div>
                <button type="submit" class="btn-fin success" style="background:#00bcd4; font-size:0.75rem; margin-top:0.5rem; width:100%;">
                    💾 Actualizar Valor Comercial
                </button>
            </form>
        </div>

    </div>

    {{-- Tabla de Gastos de Mantenimiento --}}
    <div class="card-tabla-bx" style="margin-top:1.5rem;">
        <div style="padding:1rem; border-bottom:1px solid #e2e8f0;">
            <h3 style="font-size:0.9rem; font-weight:700; color:#334155;">🔧 Historial de Gastos del Bien</h3>
        </div>
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th style="width: 15%">Fecha</th>
                    <th style="width: 35%">Concepto / Gasto</th>
                    <th style="text-align:right; width: 20%;">Monto</th>
                    <th style="width: 30%">Observación</th>
                </tr>
            </thead>
            <tbody>
                @forelse($patrimonio->gastos as $g)
                    <tr>
                        <td>{{ Carbon\Carbon::parse($g->fecha)->format('d/m/Y') }}</td>
                        <td><strong>🔧 {{ $g->concepto }}</strong></td>
                        <td style="text-align:right; font-weight:700; color:#ef4444;">
                            ${{ number_format($g->monto, 0, ',', '.') }} COP
                        </td>
                        <td style="color:#475569; font-size:0.75rem;">{{ $g->observacion ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center; padding:2rem; color:#64748b;">
                            No hay gastos de mantenimiento registrados para este bien.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal Agregar Gasto --}}
    <div x-show="openGasto" class="modal-overlay-bx" @click.self="openGasto = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #004d40, #006064);">
                <h3>🔧 Registrar Gasto Asociado</h3>
                <button @click="openGasto = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.patrimonio.gasto', $patrimonio->id) }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha</label>
                        <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Concepto / Gasto</label>
                        <input type="text" name="concepto" placeholder="Ej: SOAT, Impuesto predial, Llantas" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto ($ COP)</label>
                        <input type="number" name="monto" placeholder="Ej: 650000" class="form-input-bx" required min="1" autocomplete="off">
                        <small style="color:#64748b; font-size:0.7rem;">Este gasto se creará automáticamente también en la tabla general de gastos.</small>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observación (Opcional)</label>
                        <input type="text" name="observacion" placeholder="Detalles extra..." class="form-input-bx">
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openGasto = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#006064;">Registrar Gasto</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

/* Ficha Grid */
.prestamo-ficha-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 1.25rem; margin-top: 1rem; }
@media (max-width: 768px) {
    .prestamo-ficha-grid { grid-template-columns: 1fr; }
}

.ficha-datos-card, .ficha-acciones-card { background: #fff; border-radius: 14px; border: 1px solid #cbd5e1; padding: 1.25rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.ficha-datos-card h3, .ficha-acciones-card h3 { font-size: 0.9rem; font-weight: 700; color: #334155; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; }

.fdc-general-list { display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.5rem; }
.fdcg-row { display: flex; justify-content: space-between; font-size: 0.8rem; }
.fdcg-row span { color: #64748b; }
.fdcg-row strong { color: #1e293b; }

.sep-light { height: 1px; background: #e2e8f0; margin: 1.25rem 0; }

.btn-fac-action { padding: 0.55rem; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; text-align: center; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.btn-fac-action.blue { background: #3b82f6; color: #fff; }
.btn-fac-action.blue:hover { background: #2563eb; }

.fac-notes-bx { margin-top: 1rem; padding: 0.75rem; background: #f8fafc; border-left: 3px solid #64748b; border-radius: 6px; }
.fac-notes-bx strong { font-size: 0.75rem; color: #475569; }
.fac-notes-bx p { font-size: 0.78rem; color: #334155; margin-top: 0.25rem; line-height: 1.4; }

/* Tabla */

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { border: 1px solid; border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }

/* Modales */

.badge-info { background: rgba(59,130,246,0.12); color: #2563eb; border: 1px solid rgba(59,130,246,0.3); border-radius: 4px; padding: 0.15rem 0.45rem; font-size: 0.72rem; font-weight: 600; }
</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
