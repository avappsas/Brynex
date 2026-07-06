@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Ficha del Préstamo')

@section('contenido')
<div class="finanzas-container" x-data="{ openAbono: false, openLiquidar: false }">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.prestamos.index') }}">Préstamos</a>
            <span>›</span>
            <span>{{ $prestamo->nombre_deudor }}</span>
        </div>
        
        <div>
            <a href="{{ route('finanzas.prestamos.edit', $prestamo->id) }}" class="btn-fin-link primary" style="padding:0.45rem 1rem;">✏️ Editar Ficha</a>
        </div>
    </div>

    {{-- Header del Préstamo --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>👤 Ficha: {{ $prestamo->nombre_deudor }}</h1>
            <p>Monitoreo completo de amortización, cobros e intereses acumulados.</p>
        </div>
    </div>

    {{-- Grid de Detalles Técnicos --}}
    <div class="prestamo-ficha-grid">
        
        {{-- Bloque de Resumen de Cifras --}}
        <div class="ficha-datos-card">
            <h3>📊 Resumen de Saldos</h3>
            <div class="fdc-grid">
                <div class="fdc-item">
                    <span class="fdc-label">Saldo Total a Cobrar</span>
                    <span class="fdc-val destacado">${{ number_format($prestamo->saldo_actual, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Capital Original</span>
                    <span class="fdc-val">${{ number_format($prestamo->monto_original, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Intereses Acumulados</span>
                    <span class="fdc-val text-warning">${{ number_format($prestamo->intereses_acumulados, 0, ',', '.') }} COP</span>
                </div>
                <div class="fdc-item">
                    <span class="fdc-label">Tasa de Interés</span>
                    <span class="fdc-val">{{ $prestamo->tasa_interes_mensual }}% Mensual</span>
                </div>
            </div>
            
            <div class="sep-light"></div>
            
            <h3>📋 Datos Generales</h3>
            <div class="fdc-general-list">
                <div class="fdcg-row">
                    <span>Cédula:</span> <strong>{{ $prestamo->cedula_deudor ?: 'No registrada' }}</strong>
                </div>
                <div class="fdcg-row">
                    <span>Celular:</span> <strong>{{ $prestamo->telefono_deudor ?: 'No registrado' }}</strong>
                </div>
                <div class="fdcg-row">
                    <span>Fecha Desembolso:</span> <strong>{{ Carbon\Carbon::parse($prestamo->fecha_desembolso)->format('d/m/Y') }}</strong>
                </div>
                <div class="fdcg-row">
                    <span>Última Liquidación:</span> <strong>{{ $prestamo->ultimo_corte ? Carbon\Carbon::parse($prestamo->ultimo_corte)->format('d/m/Y') : 'Ninguna' }}</strong>
                </div>
                <div class="fdcg-row">
                    <span>Mora Actual:</span> <strong>{{ $prestamo->dias_mora }} días</strong>
                </div>
                @if($prestamo->soporte_path)
                    <div class="fdcg-row" style="margin-top:0.75rem;">
                        <span>Archivo Soporte:</span>
                        <a href="{{ route('admin.whatsapp.chat.media', ['mensajeId' => 'soporte_' . $prestamo->id]) }}?path={{ urlencode($prestamo->soporte_path) }}" target="_blank" class="badge-info" style="text-decoration:none;">
                            📄 Descargar Soporte
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Panel de Acciones --}}
        <div class="ficha-acciones-card">
            <h3>⚡ Acciones Disponibles</h3>
            <div class="fac-list">
                
                @if($prestamo->estado !== 'pagado')
                    {{-- Registrar Pago --}}
                    <button @click="openAbono = true" class="btn-fac-action green">
                        💵 Registrar Abono / Pago
                    </button>

                    {{-- Liquidar Intereses --}}
                    <button @click="openLiquidar = true" class="btn-fac-action blue">
                        ⚙️ Liquidar Intereses Manual
                    </button>

                    {{-- Cobrar por WhatsApp --}}
                    <form action="{{ route('finanzas.prestamos.whatsapp', $prestamo->id) }}" method="POST" style="display:block;">
                        @csrf
                        <button type="submit" class="btn-fac-action success" {{ !$prestamo->telefono_deudor ? 'disabled' : '' }} style="width:100%;">
                            🟢 Cobrar por WhatsApp
                        </button>
                    </form>
                @endif

                {{-- Activar/Desactivar Alertas --}}
                <form action="{{ route('finanzas.prestamos.toggle-alertas', $prestamo->id) }}" method="POST" style="display:block;">
                    @csrf
                    <button type="submit" class="btn-fac-action ghost" style="width:100%;">
                        🔔 {{ $prestamo->alertas_activas ? 'Desactivar Recordatorios' : 'Activar Recordatorios' }}
                    </button>
                </form>

            </div>

            @if($prestamo->observaciones)
                <div class="fac-notes-bx">
                    <strong>Anotaciones:</strong>
                    <p>{{ $prestamo->observaciones }}</p>
                </div>
            @endif
        </div>

    </div>

    {{-- Tabla Historial de Movimientos --}}
    <div class="card-tabla-bx" style="margin-top:1.5rem;">
        <div style="padding:1rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:0.9rem; font-weight:700; color:#334155;">📜 Historial de Movimientos</h3>
        </div>
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th style="width: 15%">Fecha</th>
                    <th style="width: 20%">Concepto / Movimiento</th>
                    <th style="text-align:right; width: 15%;">Monto</th>
                    <th style="text-align:right; width: 15%;">Saldo Anterior</th>
                    <th style="text-align:right; width: 15%;">Saldo Posterior</th>
                    <th style="width: 20%">Observación</th>
                </tr>
            </thead>
            <tbody>
                @forelse($prestamo->movimientos as $mov)
                    <tr>
                        <td>{{ Carbon\Carbon::parse($mov->fecha)->format('d/m/Y') }}</td>
                        <td>
                            <span class="mov-tipo-tag {{ $mov->tipo }}">
                                {{ match($mov->tipo) {
                                    'desembolso' => 'Capital Inicial',
                                    'interes_mensual' => 'Liquidación Interés',
                                    'capitalizacion' => 'Capitalización',
                                    'abono_interes' => 'Abono Interés',
                                    'abono_capital' => 'Abono Capital',
                                    'pago_total' => 'Pago Total',
                                    default => $mov->tipo
                                } }}
                            </span>
                        </td>
                        <td style="text-align:right; font-weight:700; color:{{ in_array($mov->tipo, ['abono_interes', 'abono_capital', 'pago_total']) ? '#16a34a' : '#ef4444' }};">
                            ${{ number_format($mov->monto, 0, ',', '.') }}
                        </td>
                        <td style="text-align:right; color:#64748b;">${{ number_format($mov->saldo_antes, 0, ',', '.') }}</td>
                        <td style="text-align:right; font-weight:700; color:#0f172a;">${{ number_format($mov->saldo_despues, 0, ',', '.') }}</td>
                        <td style="color:#475569; font-size:0.75rem;">{{ $mov->observacion ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">
                            No hay movimientos registrados en el préstamo.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal Registrar Pago --}}
    <div x-show="openAbono" class="modal-overlay-bx" @click.self="openAbono = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #15803d, #166534);">
                <h3>💵 Registrar Abono / Pago</h3>
                <button @click="openAbono = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.prestamos.pago', $prestamo->id) }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha de Recepción</label>
                        <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto Recibido ($ COP)</label>
                        <input type="number" name="monto" placeholder="Ej: 200000" class="form-input-bx" required min="1">
                        <small style="color:#64748b; font-size:0.7rem; display:block; margin-top:0.25rem;">
                            El sistema abonará este pago automáticamente priorizando intereses acumulados y luego abono a capital.
                        </small>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones (Opcional)</label>
                        <input type="text" name="observacion" placeholder="Ej: Abono en efectivo, transferencia Bancolombia" class="form-input-bx">
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openAbono = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#16a34a;">Registrar Pago</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Liquidar Intereses Manual --}}
    <div x-show="openLiquidar" class="modal-overlay-bx" @click.self="openLiquidar = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx">
                <h3>⚙️ Liquidar Intereses Manual</h3>
                <button @click="openLiquidar = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.prestamos.liquidar', $prestamo->id) }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha de Corte / Liquidación</label>
                        <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                        <small style="color:#64748b; font-size:0.7rem; display:block; margin-top:0.25rem;">
                            Se liquidará el interés proporcional por los días transcurridos desde el último corte y se capitalizarán.
                        </small>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openLiquidar = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success">Ejecutar Liquidación</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }
.btn-fin-link { text-decoration: none; padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600; text-align: center; }
.btn-fin-link.primary { background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); color: var(--azul-btn); }

/* Ficha Grid */
.prestamo-ficha-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.25rem; margin-top: 1rem; }
@media (max-width: 768px) {
    .prestamo-ficha-grid { grid-template-columns: 1fr; }
}

.ficha-datos-card, .ficha-acciones-card { background: #fff; border-radius: 14px; border: 1px solid #cbd5e1; padding: 1.25rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.ficha-datos-card h3, .ficha-acciones-card h3 { font-size: 0.9rem; font-weight: 700; color: #334155; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; }

.fdc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.fdc-item { display: flex; flex-direction: column; }
.fdc-label { font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
.fdc-val { font-size: 1.15rem; font-weight: 700; color: #334155; margin-top: 0.15rem; }
.fdc-val.destacado { color: #b91c1c; font-size: 1.25rem; }

.fdc-general-list { display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.5rem; }
.fdcg-row { display: flex; justify-content: space-between; font-size: 0.8rem; }
.fdcg-row span { color: #64748b; }
.fdcg-row strong { color: #1e293b; }

.sep-light { height: 1px; background: #e2e8f0; margin: 1.25rem 0; }

/* Botones Acciones */
.fac-list { display: flex; flex-direction: column; gap: 0.5rem; }
.btn-fac-action { padding: 0.55rem; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; text-align: center; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.btn-fac-action.green { background: #22c55e; color: #fff; }
.btn-fac-action.green:hover { background: #16a34a; }
.btn-fac-action.blue { background: #3b82f6; color: #fff; }
.btn-fac-action.blue:hover { background: #2563eb; }
.btn-fac-action.success { background: rgba(34,197,94,0.1); color: #166534; border: 1px solid rgba(34,197,94,0.3); }
.btn-fac-action.success:hover { background: rgba(34,197,94,0.18); }
.btn-fac-action.success:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-fac-action.ghost { background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; }
.btn-fac-action.ghost:hover { background: #f1f5f9; }

.fac-notes-bx { margin-top: 1.25rem; padding: 0.75rem; background: #f8fafc; border-left: 3px solid #64748b; border-radius: 6px; }
.fac-notes-bx strong { font-size: 0.75rem; color: #475569; text-transform: uppercase; }
.fac-notes-bx p { font-size: 0.78rem; color: #334155; margin-top: 0.25rem; line-height: 1.4; }

/* Tabla */
.card-tabla-bx { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.04); overflow: hidden; }
.tabla-brynex-bx { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; }
.tabla-brynex-bx th, .tabla-brynex-bx td { border-bottom: 1px solid #e2e8f0; padding: 0.75rem 1rem; }
.tabla-brynex-bx th { background: #f8fafc; font-weight: 700; color: #475569; }

.mov-tipo-tag { display: inline-block; font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 4px; text-transform: uppercase; }
.mov-tipo-tag.desembolso { background: #f1f5f9; color: #475569; }
.mov-tipo-tag.interes_mensual { background: #fee2e2; color: #991b1b; }
.mov-tipo-tag.abono_interes { background: #d1fae5; color: #065f46; }
.mov-tipo-tag.abono_capital { background: #d1fae5; color: #065f46; border: 1px dashed #059669; }

/* Modales */
.modal-overlay-bx { position: fixed; inset: 0; z-index: 9998; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-box-bx { background: #fff; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); width: 100%; max-width: 460px; overflow: hidden; }
.modal-head-bx { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #cbd5e1; background: linear-gradient(135deg, var(--azul-oscuro), var(--azul-medio)); color: #fff; }
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
.badge-info { background: rgba(59,130,246,0.12); color: #2563eb; border: 1px solid rgba(59,130,246,0.3); border-radius: 4px; padding: 0.15rem 0.45rem; font-size: 0.72rem; font-weight: 600; }
</style>
@endpush
