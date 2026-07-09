@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Ficha del Préstamo')

@section('contenido')
<div class="finanzas-container" x-data="{ openAbono: false, openLiquidar: false, openEditarMov: false, movEditar: { id: null, fecha: '', monto: 0, observacion: '', soporte_path: '' } }">

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
            <a href="{{ route('finanzas.prestamos.edit', $prestamo->id) }}" class="btn-fin-link primary">✏️ Editar Ficha</a>
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
                        <button type="submit" class="btn-fac-action success" style="width:100%;">
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
                    <th style="text-align:center; width: 10%;">Acciones</th>
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
                        <td style="color:#475569; font-size:0.75rem;">
                            {{ $mov->observacion ?: '-' }}
                            @if($mov->soporte_path)
                                <div style="margin-top: 0.2rem;">
                                    <a href="{{ route('admin.whatsapp.chat.media', ['mensajeId' => 'soporte_mov_' . $mov->id]) }}?path={{ urlencode($mov->soporte_path) }}" target="_blank" class="badge-info" style="font-size:0.65rem; padding: 0.05rem 0.25rem;">
                                        📄 Soporte
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <button @click="movEditar = { id: {{ $mov->id }}, fecha: '{{ $mov->fecha }}', monto: {{ $mov->monto }}, observacion: '{{ addslashes($mov->observacion ?? '') }}', soporte_path: '{{ $mov->soporte_path }}' }; openEditarMov = true" 
                                    class="badge-info" 
                                    style="border:none; cursor:pointer; padding: 0.2rem 0.4rem; background: rgba(59,130,246,0.08); color: var(--azul-btn);">
                                ✏️ Editar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:2rem; color:#64748b;">
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

    {{-- Modal Editar Movimiento --}}
    <div x-show="openEditarMov" class="modal-overlay-bx" @click.self="openEditarMov = false" x-cloak>
        <div class="modal-box-bx" style="max-width: 480px;">
            <div class="modal-head-bx" style="background: linear-gradient(135deg, #1e3a8a, #1d4ed8);">
                <h3>✏️ Editar Movimiento</h3>
                <button @click="openEditarMov = false" class="modal-close-bx">&times;</button>
            </div>
            
            <form :action="'{{ route('finanzas.prestamos.movimiento.update', '') }}/' + movEditar.id" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha del Movimiento</label>
                        <input type="date" name="fecha" x-model="movEditar.fecha" class="form-input-bx" required>
                    </div>
                    
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto ($ COP)</label>
                        <input type="number" name="monto" x-model="movEditar.monto" class="form-input-bx" required min="0">
                        <small style="color:#64748b; font-size:0.7rem; display:block; margin-top:0.25rem;">
                            ⚠️ Modificar el monto recalculará automáticamente todos los saldos posteriores.
                        </small>
                    </div>
                    
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones</label>
                        <input type="text" name="observacion" x-model="movEditar.observacion" placeholder="Detalle del movimiento" class="form-input-bx">
                    </div>
                    
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Archivo Soporte</label>
                        <input type="file" name="soporte" class="form-input-bx" accept="image/*,application/pdf">
                        
                        <template x-if="movEditar.soporte_path">
                            <div style="margin-top:0.5rem; display:flex; align-items:center; justify-content:space-between; background:#f8fafc; padding:0.4rem; border-radius:6px; border:1px solid #e2e8f0;">
                                <span style="font-size:0.72rem; color:#475569;">Tiene soporte cargado</span>
                                <label style="font-size:0.72rem; color:#ef4444; display:flex; align-items:center; gap:0.25rem; cursor:pointer;">
                                    <input type="checkbox" name="eliminar_soporte" value="1"> Eliminar
                                </label>
                            </div>
                        </template>
                    </div>
                </div>
                
                <div class="modal-foot-bx" style="display:flex; justify-content:space-between; align-items:center; width:100%; box-sizing:border-box;">
                    <button type="button" 
                            @click="if(confirm('¿Estás seguro de eliminar este movimiento? Esto recalculará todos los saldos posteriores.')) { 
                                        $refs.deleteForm.action = '{{ route('finanzas.prestamos.movimiento.destroy', '') }}/' + movEditar.id; 
                                        $refs.deleteForm.submit(); 
                                    }" 
                            class="btn-glass-bx" 
                            style="color:#ef4444; border-color:#fca5a5; background:rgba(239,68,68,0.04);">
                        🗑️ Eliminar
                    </button>
                    
                    <div style="display:flex; gap:0.5rem;">
                        <button type="button" @click="openEditarMov = false" class="btn-glass-bx">Cancelar</button>
                        <button type="submit" class="btn-fin success">Guardar Cambios</button>
                    </div>
                </div>
            </form>
            
            <form x-ref="deleteForm" method="POST" style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

/* Top Bar & Breadcrumb */
.fin-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
.breadcrumb-bx { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #64748b; }
.breadcrumb-bx a { color: var(--azul-btn); text-decoration: none; font-weight: 500; }
.btn-fin-link { text-decoration: none; padding: 0.45rem 1rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600; text-align: center; display: inline-flex; align-items: center; gap: 0.35rem; }
.btn-fin-link.primary { background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.18); color: var(--azul-btn); transition: all 0.15s; }
.btn-fin-link.primary:hover { background: rgba(59,130,246,0.15); transform: translateY(-1px); }

/* Header Section */
.fin-header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem; }
.header-text h1 { font-size: 1.4rem; font-weight: 800; color: #0f172a; }
.header-text p { font-size: 0.85rem; color: #64748b; margin-top: 0.2rem; }

/* Ficha Grid */
.prestamo-ficha-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.25rem; margin-top: 1rem; }
@media (max-width: 768px) {
    .prestamo-ficha-grid { grid-template-columns: 1fr; }
}

.ficha-datos-card, .ficha-acciones-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 1.25rem; box-shadow: 0 4px 14px rgba(0,0,0,0.03); }
.ficha-datos-card h3, .ficha-acciones-card h3 { font-size: 0.9rem; font-weight: 700; color: #334155; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; }

/* KPIs internos de la ficha */
.fdc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
.fdc-item { display: flex; flex-direction: column; background: #f8fafc; padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid #f1f5f9; }
.fdc-label { font-size: 0.65rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }
.fdc-val { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-top: 0.2rem; }
.fdc-val.destacado { color: #b91c1c; font-size: 1.2rem; }

.fdc-general-list { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem; }
.fdcg-row { display: flex; justify-content: space-between; font-size: 0.8rem; padding: 0.35rem 0; border-bottom: 1px solid #f8fafc; }
.fdcg-row span { color: #64748b; font-weight: 500; }
.fdcg-row strong { color: #1e293b; font-weight: 600; }

.sep-light { height: 1px; background: #e2e8f0; margin: 1.25rem 0; }

/* Botones Acciones */
.fac-list { display: flex; flex-direction: column; gap: 0.6rem; }
.btn-fac-action { padding: 0.65rem 1rem; border: none; border-radius: 10px; font-size: 0.82rem; font-weight: 600; cursor: pointer; text-align: center; display: flex; align-items: center; justify-content: center; transition: all 0.15s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
.btn-fac-action.green { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
.btn-fac-action.green:hover { background: linear-gradient(135deg, #059669, #047857); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(16,185,129,0.2); }
.btn-fac-action.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; }
.btn-fac-action.blue:hover { background: linear-gradient(135deg, #2563eb, #1e3a8a); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(59,130,246,0.2); }
.btn-fac-action.success { background: rgba(34,197,94,0.08); color: #166534; border: 1px solid rgba(34,197,94,0.18); }
.btn-fac-action.success:hover { background: rgba(34,197,94,0.15); transform: translateY(-1px); }
.btn-fac-action.success:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-fac-action.ghost { background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; }
.btn-fac-action.ghost:hover { background: #f1f5f9; border-color: #94a3b8; }

.fac-notes-bx { margin-top: 1.25rem; padding: 0.85rem; background: #f8fafc; border-left: 3px solid #cbd5e1; border-radius: 8px; border: 1px solid #e2e8f0; border-left-width: 4px; }
.fac-notes-bx strong { font-size: 0.72rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; }
.fac-notes-bx p { font-size: 0.8rem; color: #334155; margin-top: 0.3rem; line-height: 1.45; }

/* Tabla */
.card-tabla-bx { background: #fff; border-radius: 14px; border: 1px solid #cbd5e1; box-shadow: 0 4px 14px rgba(0,0,0,0.03); overflow: hidden; }
.tabla-brynex-bx { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; }
.tabla-brynex-bx th, .tabla-brynex-bx td { border-bottom: 1px solid #e2e8f0; padding: 0.8rem 1rem; }
.tabla-brynex-bx th { background: #f8fafc; font-weight: 700; color: #475569; }

.mov-tipo-tag { display: inline-block; font-size: 0.62rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 6px; text-transform: uppercase; }
.mov-tipo-tag.desembolso { background: #f1f5f9; color: #475569; }
.mov-tipo-tag.interes_mensual { background: #fee2e2; color: #991b1b; }
.mov-tipo-tag.abono_interes { background: #d1fae5; color: #065f46; }
.mov-tipo-tag.abono_capital { background: #d1fae5; color: #065f46; border: 1px dashed #059669; }
.mov-tipo-tag.pago_total { background: #d1fae5; color: #065f46; font-weight: 800; border: 1px solid #059669; }

/* Modales */
.modal-overlay-bx { position: fixed; inset: 0; z-index: 9998; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-box-bx { background: #fff; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); width: 100%; max-width: 450px; overflow: hidden; border: 1px solid #cbd5e1; }
.modal-head-bx { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #cbd5e1; background: linear-gradient(135deg, #1e3a8a, #1d4ed8); color: #fff; }
.modal-head-bx h3 { color:#fff; font-size:0.95rem; font-weight:700; }
.modal-close-bx { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: rgba(255,255,255,0.75); transition: color 0.15s; }
.modal-close-bx:hover { color: #fff; }
.modal-body-bx { padding: 1.25rem; }
.modal-foot-bx { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.25rem; border-top: 1px solid #cbd5e1; background: #f8fafc; }

.btn-glass-bx { padding: 0.5rem 1.1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; background: #fff; color: #475569; transition: all 0.15s; }
.btn-glass-bx:hover { background: #f8fafc; border-color: #94a3b8; }

.btn-fin { padding: 0.5rem 1.25rem; border: none; border-radius: 9px; font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.15s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
.btn-fin.success { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
.btn-fin.success:hover { background: linear-gradient(135deg, #059669, #047857); transform: translateY(-1px); }

.form-group-bx { display: flex; flex-direction: column; gap: 0.35rem; }
.form-label-bx { font-size: 0.78rem; font-weight: 700; color: #334155; }
.form-input-bx { padding: 0.55rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem; outline: none; transition: border-color 0.15s; }
.form-input-bx:focus { border-color: var(--azul-btn); }
.form-select-bx { padding: 0.55rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem; outline: none; background: #fff; cursor: pointer; }

.badge-info { background: rgba(59,130,246,0.08); color: #2563eb; border: 1px solid rgba(59,130,246,0.22); border-radius: 6px; padding: 0.2rem 0.5rem; font-size: 0.72rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem; }
.badge-info:hover { background: rgba(59,130,246,0.15); }
</style>
@endpush
