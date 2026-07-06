@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Detalle del Proyecto')

@section('contenido')
<div class="finanzas-container" x-data="{ openMovimiento: false }">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.proyectos.index') }}">Proyectos</a>
            <span>›</span>
            <span>{{ $proyecto->nombre }}</span>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>🏗️ Proyecto: {{ $proyecto->nombre }}</h1>
            <p>Monitoreo individual de ingresos y egresos para balance de rentabilidad.</p>
        </div>
    </div>

    {{-- Grid de Balances --}}
    <div class="prestamo-ficha-grid">
        
        {{-- Resumen Balances --}}
        <div class="ficha-datos-card">
            <h3>📊 Balance del Proyecto</h3>
            <div class="fdc-grid">
                @php $balance = $proyecto->ingresos_total - $proyecto->egresos_total; @endphp
                <div class="fdc-item">
                    <span class="fdc-label">Balance Neto</span>
                    <span class="fdc-val {{ $balance >= 0 ? 'pos-val' : 'neg-val' }}">${{ number_format($balance, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Ingresos Registrados</span>
                    <span class="fdc-val" style="color:#10b981;">${{ number_format($proyecto->ingresos_total, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Egresos Registrados</span>
                    <span class="fdc-val" style="color:#ef4444;">${{ number_format($proyecto->egresos_total, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Estado</span>
                    <span class="fdc-val">
                        @if($proyecto->activo)
                            <span class="badge-ok-bx" style="font-size:0.85rem;">Activo</span>
                        @else
                            <span class="badge-err-bx" style="font-size:0.85rem; background:#f1f5f9; color:#64748b; border-color:#cbd5e1;">Finalizado</span>
                        @endif
                    </span>
                </div>
            </div>
            
            @if($proyecto->descripcion)
                <div class="fac-notes-bx" style="margin-top:1.25rem;">
                    <strong>Descripción / Alcance:</strong>
                    <p>{{ $proyecto->descripcion }}</p>
                </div>
            @endif
        </div>

        {{-- Acciones --}}
        <div class="ficha-acciones-card" style="display:flex; flex-direction:column; justify-content:center;">
            <h3>⚡ Operaciones del Proyecto</h3>
            <p style="font-size:0.75rem; color:#64748b; margin-bottom:1rem;">Ingresa transacciones de entrada (pagos de clientes) o salida (servidores, APIs, integraciones) exclusivas de este negocio.</p>
            
            @if($proyecto->activo)
                <button @click="openMovimiento = true" class="btn-fac-action green" style="background:#166534; width:100%;">
                    💸 Registrar Movimiento de Caja
                </button>
            @else
                <div style="background:#f1f5f9; padding:0.75rem; border-radius:8px; border:1px solid #cbd5e1; font-size:0.8rem; color:#64748b; text-align:center;">
                    🔒 Proyecto finalizado. No se permiten nuevos movimientos de caja.
                </div>
            @endif
        </div>

    </div>

    {{-- Historial de Movimientos --}}
    <div class="card-tabla-bx" style="margin-top:1.5rem;">
        <div style="padding:1rem; border-bottom:1px solid #e2e8f0;">
            <h3 style="font-size:0.9rem; font-weight:700; color:#334155;">📜 Flujo de Caja</h3>
        </div>
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th style="width: 15%">Fecha</th>
                    <th style="width: 15%; text-align:center;">Tipo</th>
                    <th style="width: 40%">Concepto / Detalle</th>
                    <th style="text-align:right; width: 20%;">Monto</th>
                    <th style="text-align:center; width: 10%;">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($proyecto->movimientos as $mov)
                    <tr>
                        <td>{{ Carbon\Carbon::parse($mov->fecha)->format('d/m/Y') }}</td>
                        <td style="text-align:center;">
                            <span class="proy-tipo-tag {{ $mov->tipo }}">
                                {{ $mov->tipo === 'ingreso' ? '📥 Ingreso' : '📤 Egreso' }}
                            </span>
                        </td>
                        <td><strong>{{ $mov->concepto }}</strong></td>
                        <td style="text-align:right; font-weight:700; color:{{ $mov->tipo === 'ingreso' ? '#16a34a' : '#ef4444' }};">
                            ${{ number_format($mov->monto, 0, ',', '.') }} COP
                        </td>
                        <td style="text-align:center;">
                            @if($proyecto->activo)
                                <form action="{{ route('finanzas.proyectos.movimiento.destroy', [$proyecto->id, $mov->id]) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas revertir este movimiento?')" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon-bx delete" title="Eliminar/Revertir">❌</button>
                                </form>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center; padding:2rem; color:#64748b;">
                            No hay movimientos de flujo de caja en este proyecto.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal Registrar Movimiento --}}
    <div x-show="openMovimiento" class="modal-overlay-bx" @click.self="openMovimiento = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #14532d, #166534);">
                <h3>💸 Registrar Movimiento</h3>
                <button @click="openMovimiento = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.proyectos.movimiento', $proyecto->id) }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha</label>
                        <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Tipo de Movimiento</label>
                        <select name="tipo" class="form-select-bx" required>
                            <option value="ingreso">📥 Ingreso (Cobro/Venta)</option>
                            <option value="egreso">📤 Egreso (Gastos/Servidores/APIs)</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto ($ COP)</label>
                        <input type="number" name="monto" placeholder="Ej: 150000" class="form-input-bx" required min="1">
                        <small style="color:#64748b; font-size:0.7rem;">Si es ingreso, se registrará también automáticamente en la contabilidad general de entradas. Si es egreso, se creará también en gastos.</small>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Concepto / Descripción</label>
                        <input type="text" name="concepto" placeholder="Ej: Pago mensualidad cliente, Servidor AWS" class="form-input-bx" required>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openMovimiento = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#166534;">Registrar</button>
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

.fdc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.fdc-item { display: flex; flex-direction: column; }
.fdc-label { font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
.fdc-val { font-size: 1.15rem; font-weight: 700; color: #334155; margin-top: 0.15rem; }
.fdc-val.pos-val { color: #16a34a; }
.fdc-val.neg-val { color: #b91c1c; }

.fdc-general-list { display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.5rem; }
.fdcg-row { display: flex; justify-content: space-between; font-size: 0.8rem; }
.fdcg-row span { color: #64748b; }
.fdcg-row strong { color: #1e293b; }

.sep-light { height: 1px; background: #e2e8f0; margin: 1.25rem 0; }

.btn-fac-action { padding: 0.55rem; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; text-align: center; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.btn-fac-action.green { background: #22c55e; color: #fff; }
.btn-fac-action.green:hover { background: #16a34a; }

.fac-notes-bx { margin-top: 1rem; padding: 0.75rem; background: #f8fafc; border-left: 3px solid #64748b; border-radius: 6px; }
.fac-notes-bx strong { font-size: 0.75rem; color: #475569; }
.fac-notes-bx p { font-size: 0.78rem; color: #334155; margin-top: 0.25rem; line-height: 1.4; }

/* Tabla */
.card-tabla-bx { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.04); overflow: hidden; }
.tabla-brynex-bx { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; }
.tabla-brynex-bx th, .tabla-brynex-bx td { border-bottom: 1px solid #e2e8f0; padding: 0.75rem 1rem; }
.tabla-brynex-bx th { background: #f8fafc; font-weight: 700; color: #475569; }

.proy-tipo-tag { display: inline-block; font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 4px; text-transform: uppercase; }
.proy-tipo-tag.ingreso { background: #d1fae5; color: #065f46; }
.proy-tipo-tag.egreso { background: #fee2e2; color: #991b1b; }

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { border: 1px solid; border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.btn-icon-bx { background: none; border: none; font-size: 1rem; cursor: pointer; padding: 0.2rem; border-radius: 4px; transition: background 0.1s; }
.btn-icon-bx:hover { background: #f1f5f9; }

/* Modales */
.modal-overlay-bx { position: fixed; inset: 0; z-index: 9998; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-box-bx { background: #fff; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); width: 100%; max-width: 460px; overflow: hidden; }
.modal-head-bx { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #cbd5e1; color: #fff; }
.modal-head-bx h3 { color:#fff; font-size:1rem; font-weight:600; }
.modal-close-bx { background: none; border: none; font-size: 1.3rem; cursor: pointer; color: rgba(255,255,255,0.7); }
.modal-close-bx:hover { color: #fff; }
.modal-body-bx { padding: 1.25rem; }
.modal-foot-bx { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid #cbd5e1; background: #f8fafc; }
.btn-glass-bx { padding: 0.45rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer; background: #fff; color: #475569; }

.form-group-bx { display: flex; flex-direction: column; gap: 0.25rem; }
.form-label-bx { font-size: 0.78rem; font-weight: 600; color: #334155; }
.form-input-bx { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem; outline: none; }
.form-select-bx { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem; outline: none; background: #fff; cursor: pointer; }
</style>
@endpush
