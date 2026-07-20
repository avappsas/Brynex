@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Detalle del Proyecto')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ openMovimiento: false, mostrarBalanceHistorico: false, openEditar: false, itemEditar: {}, openConfirmarEliminar: false }">

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

    {{-- Header Banner con Gradiente --}}
    <div style="background: linear-gradient(135deg, #0a1628 0%, #0d2550 60%, #1e40af 100%); border-radius: 14px; padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.15); margin-bottom: 1.5rem; color: #fff;">
        <div>
            <h1 style="font-size: 1.4rem; font-weight: 700; color: #fff; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                🏗️ Proyecto: {{ $proyecto->nombre }}
            </h1>
            <p style="font-size: 0.8rem; color: rgba(255,255,255,0.75); margin: 0.25rem 0 0 0; font-weight: 400;">
                Monitoreo individual de ingresos y egresos para balance de rentabilidad.
            </p>
        </div>
        
        {{-- Selector de Año --}}
        <div style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 0.6rem 1.25rem; min-width: 180px;">
            <form method="GET" action="{{ route('finanzas.proyectos.show', $proyecto->id) }}" id="filterForm">
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <label style="font-size: 0.62rem; color: rgba(255,255,255,0.7); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Año de Consulta</label>
                    <select name="anio" onchange="document.getElementById('filterForm').submit()" style="padding: 0.35rem; font-size: 0.8rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(10, 22, 40, 0.85); color: #fff; width: 100%; cursor: pointer;">
                        <option value="todos" @selected($anio === 'todos')>Todos los Años</option>
                        @foreach($aniosDisponibles as $a)
                            <option value="{{ $a }}" @selected($anio !== 'todos' && (int)$anio === (int)$a)>Año {{ $a }}</option>
                        @endforeach
                        @if(!in_array(date('Y'), $aniosDisponibles))
                            <option value="{{ date('Y') }}" @selected($anio !== 'todos' && (int)$anio === (int)date('Y'))>Año {{ date('Y') }}</option>
                        @endif
                    </select>
                </div>
            </form>
        </div>
    </div>

    {{-- Grid de Balances --}}
    <div class="prestamo-ficha-grid" :style="mostrarBalanceHistorico ? '' : 'grid-template-columns: 1fr;'">
        
        {{-- Resumen Balances --}}
        <div class="ficha-datos-card" x-show="mostrarBalanceHistorico" x-transition x-cloak>
            <h3>📊 Balance Histórico Total</h3>
            <div class="fdc-grid">
                @php $balanceHistorico = $proyecto->balance; @endphp
                <div class="fdc-item">
                    <span class="fdc-label">Balance Neto Histórico</span>
                    <span class="fdc-val {{ $balanceHistorico >= 0 ? 'pos-val' : 'neg-val' }}">${{ number_format($balanceHistorico, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Ingresos Totales</span>
                    <span class="fdc-val" style="color:#10b981;">${{ number_format($proyecto->entradas_total, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Egresos Totales</span>
                    <span class="fdc-val" style="color:#ef4444;">${{ number_format($proyecto->salidas_total, 0, ',', '.') }} COP</span>
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
        <div class="ficha-acciones-card" style="display:flex; flex-direction:column; justify-content:center; gap: 0.75rem;">
            <h3>⚡ Operaciones del Proyecto</h3>
            <p style="font-size:0.75rem; color:#64748b; margin-bottom:0.25rem;">Ingresa transacciones de entrada (pagos de clientes) o salida (servidores, APIs, integraciones) exclusivas de este negocio.</p>
            
            @if($proyecto->activo)
                <button @click="openMovimiento = true" class="btn-fac-action green" style="background:#166534; width:100%;">
                    💸 Registrar Movimiento de Caja
                </button>
            @else
                <div style="background:#f1f5f9; padding:0.75rem; border-radius:8px; border:1px solid #cbd5e1; font-size:0.8rem; color:#64748b; text-align:center;">
                    🔒 Proyecto finalizado. No se permiten nuevos movimientos de caja.
                </div>
            @endif

            <button @click="mostrarBalanceHistorico = !mostrarBalanceHistorico" class="btn-fac-action" style="background:#1e40af; color:#fff; width:100%; transition: background 0.2s;" :style="mostrarBalanceHistorico ? 'background:#475569;' : ''">
                <span x-show="!mostrarBalanceHistorico">📊 Ver Balances y Resúmenes</span>
                <span x-show="mostrarBalanceHistorico" x-cloak>🙈 Ocultar Balances y Resúmenes</span>
            </button>
        </div>

    </div>

    {{-- KPIs del Período --}}
    <div x-show="mostrarBalanceHistorico" x-transition x-cloak style="margin-top: 1.5rem;">
        <div class="ficha-datos-card" style="padding: 1.25rem;">
            <h3 style="font-size: 0.85rem; font-weight: 700; color: #334155; margin-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.25rem;">
                📊 Resultado @if($anio === 'todos') Histórico @else del Año {{ $anio }} @endif
            </h3>
            <div class="fdc-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="fdc-item">
                    <span class="fdc-label">Entradas @if($anio === 'todos') Históricas @else del Período ({{ $anio }}) @endif</span>
                    <span class="fdc-val" style="color:#10b981; font-size:1.1rem;">${{ number_format($totalEntradas, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Salidas @if($anio === 'todos') Históricas @else del Período ({{ $anio }}) @endif</span>
                    <span class="fdc-val" style="color:#ef4444; font-size:1.1rem;">${{ number_format($totalSalidas, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Saldo Neto del Período</span>
                    <span class="fdc-val {{ $balancePeriodo >= 0 ? 'pos-val' : 'neg-val' }}">${{ number_format($balancePeriodo, 0, ',', '.') }} COP</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Historial de Movimientos --}}
    <div class="card-tabla-bx" style="margin-top:1.5rem;">
        <div style="padding:1rem; border-bottom:1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size:0.9rem; font-weight:700; color:#334155; margin: 0;">📜 Flujo de Caja (@if($anio === 'todos') Histórico @else Año {{ $anio }} @endif)</h3>
        </div>
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th style="width: 12%">Fecha</th>
                    <th style="width: 38%">Concepto / Observación</th>
                    <th style="text-align:right; width: 16%;">Entrada</th>
                    <th style="text-align:right; width: 16%;">Salida</th>
                    <th style="text-align:right; width: 18%;">Saldo Acumulado</th>
                    @if($proyecto->activo)
                        <th style="text-align:center; width: 10%;">Acción</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($movimientos as $mov)
                    <tr>
                        <td>{{ Carbon\Carbon::parse($mov->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $mov->observacion }}</td>
                        <td style="text-align:right; font-weight:700; color:#10b981;">
                            @if($mov->tipo === 'ingreso' || $mov->tipo === 'entrada')
                                ${{ number_format($mov->monto, 0, ',', '.') }} COP
                            @else
                                <span style="color: #cbd5e1; font-weight: 400;">-</span>
                            @endif
                        </td>
                        <td style="text-align:right; font-weight:700; color:#ef4444;">
                            @if($mov->tipo === 'egreso' || $mov->tipo === 'salida')
                                ${{ number_format($mov->monto, 0, ',', '.') }} COP
                            @else
                                <span style="color: #cbd5e1; font-weight: 400;">-</span>
                            @endif
                        </td>
                        <td style="text-align:right; font-weight:700; color:{{ $mov->saldo_acumulado >= 0 ? '#16a34a' : '#ef4444' }}; background: rgba(248, 250, 252, 0.5);">
                            ${{ number_format($mov->saldo_acumulado, 0, ',', '.') }} COP
                        </td>
                        @if($proyecto->activo)
                            <td style="text-align:center;">
                                <button @click="itemEditar = {{ json_encode([
                                    'id' => $mov->id,
                                    'tipo' => $mov->tipo,
                                    'monto' => $mov->monto,
                                    'fecha' => $mov->fecha,
                                    'observacion' => $mov->observacion,
                                    'cuenta_id' => $mov->cuenta_id
                                ]) }}; openEditar = true" class="btn-icon-bx edit" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 6px; padding: 0.2rem 0.5rem; cursor: pointer; font-size: 0.78rem; font-weight:600; display:inline-flex; align-items:center; gap:0.25rem;" title="Editar">
                                    ✏️ Editar
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $proyecto->activo ? 6 : 5 }}" style="text-align:center; padding:2rem; color:#64748b;">
                            No hay movimientos de flujo de caja en este proyecto @if($anio === 'todos') registrados @else para el año {{ $anio }} @endif.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($movimientos->isNotEmpty())
                <tfoot>
                    <tr style="background: #f8fafc; font-weight: bold; border-top: 2px solid #cbd5e1;">
                        <td colspan="2" style="text-align: left; padding: 0.75rem 1rem;">SALDO TOTAL DEL PERÍODO (@if($anio === 'todos') Histórico @else {{ $anio }} @endif):</td>
                        <td style="text-align: right; color: #16a34a; padding: 0.75rem 0.5rem;">
                            ${{ number_format($totalEntradas, 0, ',', '.') }} COP
                        </td>
                        <td style="text-align: right; color: #ef4444; padding: 0.75rem 0.5rem;">
                            ${{ number_format($totalSalidas, 0, ',', '.') }} COP
                        </td>
                        <td style="text-align: right; color: {{ $balancePeriodo >= 0 ? '#16a34a' : '#ef4444' }}; padding: 0.75rem 0.5rem; font-size: 1rem;">
                            ${{ number_format($balancePeriodo, 0, ',', '.') }} COP
                        </td>
                        @if($proyecto->activo)
                            <td></td>
                        @endif
                    </tr>
                </tfoot>
            @endif
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
                            <option value="entrada">📥 Entrada (Cobro/Venta)</option>
                            <option value="salida">📤 Salida (Gastos/Servidores/APIs)</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto ($ COP)</label>
                        <input type="number" name="monto" placeholder="Ej: 150000" class="form-input-bx" required min="1">
                        <small style="color:#64748b; font-size:0.7rem;">El neto del mes del proyecto (entradas - salidas) se suma automáticamente a la fuente PROYECTOS de las entradas globales.</small>
                    </div>
                    @if(isset($cuentas) && $cuentas->isNotEmpty())
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Cuenta / Bolsillo del dinero</label>
                        <select name="cuenta_id" class="form-select-bx" required>
                            @foreach($cuentas as $cta)
                                <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Concepto / Descripción</label>
                        <input type="text" name="observacion" placeholder="Ej: Pago mensualidad cliente, Servidor AWS" class="form-input-bx" required>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openMovimiento = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#166534;">Registrar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar Movimiento --}}
    <div x-show="openEditar" class="modal-overlay-bx" @click.self="openEditar = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #1e40af, #2563eb);">
                <h3>✏️ Editar Movimiento</h3>
                <button @click="openEditar = false" class="modal-close-bx">&times;</button>
            </div>
            <form :action="'{{ route('finanzas.proyectos.movimiento.update', 'ID_TEMPORAL') }}'.replace('ID_TEMPORAL', itemEditar.id)" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha</label>
                        <input type="date" name="fecha" x-model="itemEditar.fecha" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Tipo de Movimiento</label>
                        <select name="tipo" x-model="itemEditar.tipo" class="form-select-bx" required>
                            <option value="ingreso">📥 Entrada (Ingreso/Cobro/Venta)</option>
                            <option value="egreso">📤 Salida (Egreso/Gastos/Servidores)</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto ($ COP)</label>
                        <input type="number" name="monto" x-model="itemEditar.monto" placeholder="Ej: 150000" class="form-input-bx" required min="1">
                    </div>
                    @if(isset($cuentas) && $cuentas->isNotEmpty())
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Cuenta / Bolsillo del dinero</label>
                        <select name="cuenta_id" x-model="itemEditar.cuenta_id" class="form-select-bx" required>
                            @foreach($cuentas as $cta)
                                <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Concepto / Descripción</label>
                        <input type="text" name="observacion" x-model="itemEditar.observacion" placeholder="Ej: Pago mensualidad cliente..." class="form-input-bx" required>
                    </div>
                </div>
                <div class="modal-foot-bx" style="display:flex; justify-content:space-between; align-items:center;">
                    <button type="button" @click="openConfirmarEliminar = true" class="btn-fac-action" style="background:#dc2626; color:#fff; padding:0.45rem 1.25rem; border-radius:8px; display:inline-flex; align-items:center; gap:0.25rem;">
                        🗑️ Eliminar
                    </button>
                    <div>
                        <button type="button" @click="openEditar = false" class="btn-glass-bx" style="margin-right:0.5rem;">Cancelar</button>
                        <button type="submit" class="btn-fin success" style="background:#1e40af;">Guardar Cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Confirmación de Eliminación --}}
    <div x-show="openConfirmarEliminar" class="modal-overlay-bx" style="z-index: 10000; background: rgba(0, 0, 0, 0.65);" @click.self="openConfirmarEliminar = false" x-cloak>
        <div class="modal-box-bx" style="max-width: 400px; border: 2px solid #ef4444;">
            <div class="modal-head-bx" style="background:#ef4444; color:#fff; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="font-weight:700;">⚠️ Confirmar Eliminación</h3>
                <button @click="openConfirmarEliminar = false" class="modal-close-bx" style="color:#fff;">&times;</button>
            </div>
            <div class="modal-body-bx" style="text-align:center; padding: 1.5rem 1rem;">
                <p style="font-size: 0.9rem; color: #1e293b; font-weight:500;">
                    ¿Seguro que deseas eliminar este movimiento de caja?
                </p>
                <p style="font-size: 0.8rem; color: #dc2626; font-weight:700; margin-top:0.5rem; text-transform:uppercase;">
                    Esta acción es irreversible.
                </p>
            </div>
            <div class="modal-foot-bx" style="display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" @click="openConfirmarEliminar = false" class="btn-glass-bx">No, Cancelar</button>
                <form :action="'{{ route('finanzas.proyectos.movimiento.destroy', 'ID_TEMPORAL') }}'.replace('ID_TEMPORAL', itemEditar.id)" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-fin danger" style="background:#dc2626; color:#fff; border:none; padding:0.5rem 1rem; border-radius:8px; font-weight:600; cursor:pointer;">
                        Sí, Eliminar
                    </button>
                </form>
            </div>
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

.proy-tipo-tag { display: inline-block; font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 4px; text-transform: uppercase; }
.proy-tipo-tag.ingreso { background: #d1fae5; color: #065f46; }
.proy-tipo-tag.egreso { background: #fee2e2; color: #991b1b; }

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { border: 1px solid; border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }

/* Modales */

</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
