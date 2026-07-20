@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Cuentas y Bolsillos')

@section('contenido')
<div class="finanzas-container" x-data="{ openCrear: false, openEditar: false, openTransferir: false, selectedCuenta: {} }">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <span>Cuentas</span>
        </div>

        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <button @click="openTransferir = true" class="btn-fin" style="background:#0284c7; color:#fff;">
                🔁 Transferir
            </button>
            <button @click="openCrear = true" class="btn-fin success" style="background:linear-gradient(135deg, #4f46e5, #4338ca);">
                ➕ Nueva Cuenta
            </button>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>💳 Cuentas y Bolsillos</h1>
            <p>Controla dónde está tu dinero: banco, efectivo, billeteras. Saldo total:
                <strong style="color:{{ $saldoTotal >= 0 ? '#10b981' : '#ef4444' }}; font-size:1.15rem;">${{ number_format($saldoTotal, 0, ',', '.') }} COP</strong>
            </p>
        </div>
    </div>

    {{-- Grid de Cuentas --}}
    <div class="cuentas-grid">
        @forelse($cuentas as $cuenta)
            <div class="cuenta-card" style="border-top: 4px solid {{ $cuenta->color ?: '#64748b' }};">
                <div class="cuenta-card-head">
                    <span class="cuenta-icono">{{ $cuenta->icono ?: '💳' }}</span>
                    <div>
                        <strong>{{ $cuenta->nombre }}</strong>
                        <span class="cuenta-tipo-tag">{{ ucfirst($cuenta->tipo) }}</span>
                    </div>
                    <button @click="selectedCuenta = {{ json_encode($cuenta->only(['id','nombre','tipo','icono','saldo_inicial','orden','activo'])) }}; openEditar = true" class="btn-icon-bx edit" title="Editar" style="margin-left:auto;">✏️</button>
                </div>
                <div class="cuenta-saldo" style="color:{{ $cuenta->saldo_actual >= 0 ? '#0f172a' : '#ef4444' }};">
                    ${{ number_format($cuenta->saldo_actual, 0, ',', '.') }}
                    <small>COP</small>
                </div>
            </div>
        @empty
            <div style="grid-column:1/-1; text-align:center; padding:2rem; color:#64748b; background:#fff; border-radius:12px; border:1px dashed #cbd5e1;">
                No tienes cuentas activas. Crea la primera con "➕ Nueva Cuenta".
            </div>
        @endforelse
    </div>

    {{-- Historial de Transferencias --}}
    <div class="fin-header-section" style="margin-top:1.5rem;">
        <div class="header-text">
            <h1 style="font-size:1.05rem;">🔁 Últimas Transferencias</h1>
        </div>
    </div>

    {{-- Desktop: tabla --}}
    <div class="card-tabla-bx solo-desktop" style="margin-top:0.75rem;">
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th style="text-align:right;">Monto</th>
                    <th>Observación</th>
                    <th style="text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transferencias as $tr)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($tr->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $tr->origen->icono ?? '' }} {{ $tr->origen->nombre ?? '—' }}</td>
                        <td>{{ $tr->destino->icono ?? '' }} {{ $tr->destino->nombre ?? '—' }}</td>
                        <td style="text-align:right; font-weight:700;">${{ number_format($tr->monto, 0, ',', '.') }}</td>
                        <td style="color:#64748b;">{{ $tr->observacion ?: '—' }}</td>
                        <td style="text-align:center;">
                            <form action="{{ route('finanzas.cuentas.transferencia.destroy', $tr->id) }}" method="POST" onsubmit="return confirm('¿Eliminar esta transferencia? Los saldos se recalcularán.')" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon-bx delete" title="Eliminar">❌</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center; padding:1.5rem; color:#64748b;">Sin transferencias registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Móvil: cards --}}
    <div class="solo-movil" style="margin-top:0.75rem; display:flex; flex-direction:column; gap:0.5rem;">
        @forelse($transferencias as $tr)
            <div class="transf-card-movil">
                <div class="tcm-linea">
                    <span>{{ $tr->origen->icono ?? '' }} {{ $tr->origen->nombre ?? '—' }} → {{ $tr->destino->icono ?? '' }} {{ $tr->destino->nombre ?? '—' }}</span>
                    <strong>${{ number_format($tr->monto, 0, ',', '.') }}</strong>
                </div>
                <div class="tcm-linea sub">
                    <span>{{ \Carbon\Carbon::parse($tr->fecha)->format('d/m/Y') }}{{ $tr->observacion ? ' · ' . $tr->observacion : '' }}</span>
                    <form action="{{ route('finanzas.cuentas.transferencia.destroy', $tr->id) }}" method="POST" onsubmit="return confirm('¿Eliminar esta transferencia?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-icon-bx delete">❌</button>
                    </form>
                </div>
            </div>
        @empty
            <div style="text-align:center; padding:1.5rem; color:#64748b; background:#fff; border-radius:12px;">Sin transferencias registradas.</div>
        @endforelse
    </div>

    {{-- Cuentas inactivas --}}
    @if($inactivas->isNotEmpty())
        <div style="margin-top:1.5rem; font-size:0.78rem; color:#64748b;">
            <strong>Cuentas desactivadas:</strong>
            @foreach($inactivas as $inactiva)
                <span style="background:#f1f5f9; border-radius:6px; padding:0.15rem 0.5rem; margin-left:0.3rem; display:inline-block;">
                    {{ $inactiva->icono }} {{ $inactiva->nombre }}
                    <a href="#" @click.prevent="selectedCuenta = {{ json_encode($inactiva->only(['id','nombre','tipo','icono','saldo_inicial','orden','activo'])) }}; openEditar = true" style="margin-left:0.2rem;">reactivar</a>
                </span>
            @endforeach
        </div>
    @endif

    {{-- Modal Crear Cuenta --}}
    <div x-show="openCrear" class="modal-overlay-bx" @click.self="openCrear = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #4f46e5, #4338ca);">
                <h3>➕ Nueva Cuenta / Bolsillo</h3>
                <button @click="openCrear = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.cuentas.store') }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre</label>
                        <input type="text" name="nombre" placeholder="Ej: Bancolombia, Daviplata, Caja fuerte" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Tipo</label>
                        <select name="tipo" class="form-select-bx" required>
                            <option value="banco">🏦 Banco</option>
                            <option value="efectivo">💵 Efectivo</option>
                            <option value="billetera">📱 Billetera digital</option>
                            <option value="otro">💳 Otro</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Ícono (emoji)</label>
                            <input type="text" name="icono" placeholder="🏦" maxlength="10" class="form-input-bx">
                        </div>
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Orden</label>
                            <input type="number" name="orden" value="10" class="form-input-bx">
                        </div>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Saldo inicial ($ COP)</label>
                        <input type="number" name="saldo_inicial" value="0" step="0.01" class="form-input-bx">
                        <small style="color:#64748b; font-size:0.7rem;">Lo que hay HOY en esta cuenta antes de empezar a registrar movimientos en ella.</small>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openCrear = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#4f46e5;">Guardar Cuenta</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar Cuenta --}}
    <div x-show="openEditar" class="modal-overlay-bx" @click.self="openEditar = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #4f46e5, #4338ca);">
                <h3>✏️ Editar Cuenta</h3>
                <button @click="openEditar = false" class="modal-close-bx">&times;</button>
            </div>
            <form :action="'{{ route('finanzas.cuentas.index') }}/' + selectedCuenta.id" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre</label>
                        <input type="text" name="nombre" x-model="selectedCuenta.nombre" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Tipo</label>
                        <select name="tipo" x-model="selectedCuenta.tipo" class="form-select-bx" required>
                            <option value="banco">🏦 Banco</option>
                            <option value="efectivo">💵 Efectivo</option>
                            <option value="billetera">📱 Billetera digital</option>
                            <option value="otro">💳 Otro</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Ícono (emoji)</label>
                            <input type="text" name="icono" x-model="selectedCuenta.icono" maxlength="10" class="form-input-bx">
                        </div>
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Orden</label>
                            <input type="number" name="orden" x-model="selectedCuenta.orden" class="form-input-bx">
                        </div>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Saldo inicial ($ COP)</label>
                        <input type="number" name="saldo_inicial" x-model="selectedCuenta.saldo_inicial" step="0.01" class="form-input-bx">
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Estado</label>
                        <select name="activo" x-model="selectedCuenta.activo" class="form-select-bx" required>
                            <option value="1">Activa</option>
                            <option value="0">Desactivada</option>
                        </select>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openEditar = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#4f46e5;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Transferir --}}
    <div x-show="openTransferir" class="modal-overlay-bx" @click.self="openTransferir = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #0369a1, #075985);">
                <h3>🔁 Transferir entre Cuentas</h3>
                <button @click="openTransferir = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.cuentas.transferir') }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Desde</label>
                        <select name="cuenta_origen_id" class="form-select-bx" required>
                            @foreach($cuentas as $cuenta)
                                <option value="{{ $cuenta->id }}">{{ $cuenta->icono }} {{ $cuenta->nombre }} (${{ number_format($cuenta->saldo_actual, 0, ',', '.') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Hacia</label>
                        <select name="cuenta_destino_id" class="form-select-bx" required>
                            @foreach($cuentas as $cuenta)
                                <option value="{{ $cuenta->id }}" @selected($loop->index === 1)>{{ $cuenta->icono }} {{ $cuenta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Monto ($ COP)</label>
                            <input type="number" name="monto" placeholder="Ej: 500000" min="1" class="form-input-bx" required>
                        </div>
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Fecha</label>
                            <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                        </div>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observación</label>
                        <input type="text" name="observacion" placeholder="Ej: Retiro cajero" maxlength="255" class="form-input-bx">
                    </div>
                    <p style="font-size:0.7rem; color:#64748b; margin-top:0.75rem;">La transferencia no cuenta como gasto ni entrada: solo mueve el dinero de bolsillo.</p>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openTransferir = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#0284c7;">Transferir</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

.cuentas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1rem; margin-top: 1rem; }
.cuenta-card { background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.cuenta-card-head { display: flex; align-items: center; gap: 0.6rem; }
.cuenta-card-head strong { display: block; font-size: 0.9rem; color: #0f172a; }
.cuenta-icono { font-size: 1.6rem; }
.cuenta-tipo-tag { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; color: #64748b; }
.cuenta-saldo { font-size: 1.4rem; font-weight: 800; margin-top: 0.75rem; }
.cuenta-saldo small { font-size: 0.7rem; color: #94a3b8; font-weight: 600; }

.card-tabla-bx { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.04); overflow: hidden; }
.tabla-brynex-bx { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; }
.tabla-brynex-bx th, .tabla-brynex-bx td { border-bottom: 1px solid #e2e8f0; padding: 0.75rem 1rem; }
.tabla-brynex-bx th { background: #f8fafc; font-weight: 700; color: #475569; }

.btn-icon-bx { background: none; border: none; font-size: 1rem; cursor: pointer; padding: 0.2rem; border-radius: 4px; transition: background 0.1s; }
.btn-icon-bx:hover { background: #f1f5f9; }

.transf-card-movil { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem 0.9rem; }
.tcm-linea { display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; }
.tcm-linea.sub { font-size: 0.7rem; color: #94a3b8; margin-top: 0.25rem; }

.modal-overlay-bx { position: fixed; inset: 0; z-index: 9998; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-box-bx { background: #fff; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); width: 100%; max-width: 460px; overflow: hidden; max-height: 92vh; overflow-y: auto; }
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

/* Responsive: tabla solo en desktop, cards solo en móvil */
.solo-movil { display: none; }
@media (max-width: 768px) {
    .solo-desktop { display: none; }
    .solo-movil { display: flex; }
    .fin-top-bar { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
    .cuentas-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.6rem; }
    .cuenta-saldo { font-size: 1.15rem; }
}
</style>
@endpush
