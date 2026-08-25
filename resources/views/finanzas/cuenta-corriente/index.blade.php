@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Cuenta Corriente')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ openCliente: false }">

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
            <button @click="openCliente = true" class="btn-fin success" style="background:#7e22ce;">
                ➕ Nuevo Cliente
            </button>
        </div>
    </div>

    @if(session('error'))
        <div class="cc-alert error">⚠️ {{ session('error') }}</div>
    @endif

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>💼 Cuenta Corriente de Servicios</h1>
            <p>
                Clientes recurrentes a los que se les hacen trabajos a crédito.
                Total por cobrar:
                <strong style="color:#7e22ce; font-size:1.25rem;">${{ number_format($saldoTotalPendiente, 0, ',', '.') }} COP</strong>
            </p>
        </div>
    </div>

    {{-- Clientes --}}
    <div class="cc-grid">
        @forelse($clientes as $cliente)
            @php $r = $resumen->get($cliente->id); @endphp
            <a href="{{ route('finanzas.cuenta-corriente.show', $cliente->id) }}" class="cc-cliente-card {{ $cliente->activo ? '' : 'inactivo' }}">
                <div class="cc-cliente-top">
                    <div>
                        <h2>{{ $cliente->nombre }}</h2>
                        <small>
                            {{ $r->total_trabajos ?? 0 }} trabajo(s) ·
                            {{ $r->pendientes ?? 0 }} pendiente(s)
                            @unless($cliente->activo) · <em>inactivo</em> @endunless
                        </small>
                    </div>
                    <span class="cc-tasa">{{ rtrim(rtrim(number_format($cliente->tasa_interes_mensual, 3, ',', '.'), '0'), ',') }}%</span>
                </div>

                <div class="cc-cliente-saldo">
                    <span>Saldo pendiente</span>
                    <strong style="color:{{ ($r->saldo ?? 0) > 0 ? '#b91c1c' : '#16a34a' }};">
                        ${{ number_format($r->saldo ?? 0, 0, ',', '.') }}
                    </strong>
                </div>

                @if(($r->intereses ?? 0) >= 1000)
                    <div class="cc-cliente-foot vencido">
                        Incluye ${{ number_format($r->intereses, 0, ',', '.') }} de intereses causados
                    </div>
                @elseif(($r->pendientes ?? 0) > 0)
                    <div class="cc-cliente-foot">Sin intereses causados todavía</div>
                @else
                    <div class="cc-cliente-foot ok">Al día ✓</div>
                @endif
            </a>
        @empty
            <div class="cc-vacio">
                Todavía no hay clientes en cuenta corriente.<br>
                <small>Crea uno (ej. «Oficina Arroyave») y empieza a registrarle trabajos.</small>
            </div>
        @endforelse
    </div>

    {{-- Modal: nuevo cliente --}}
    <div x-show="openCliente" class="modal-overlay-bx" @click.self="openCliente = false" x-cloak>
        <div class="modal-box-bx">
            <form action="{{ route('finanzas.cuenta-corriente.clientes.store') }}" method="POST">
                @csrf
                <div class="modal-head-bx">
                    <h3>➕ Nuevo Cliente de Cuenta Corriente</h3>
                    <button type="button" class="modal-close-bx" @click="openCliente = false">✕</button>
                </div>

                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre del cliente</label>
                        <input type="text" name="nombre" class="form-input-bx" placeholder="Ej: Oficina Arroyave" required maxlength="100">
                    </div>

                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Cédula / NIT (opcional)</label>
                            <input type="text" name="cedula" class="form-input-bx" maxlength="20">
                        </div>
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Celular (WhatsApp)</label>
                            <input type="text" name="telefono" class="form-input-bx" placeholder="3001234567" maxlength="20">
                        </div>
                    </div>

                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Tasa mensual por defecto (%)</label>
                            <input type="number" step="0.001" min="0" max="100" name="tasa_interes_mensual" class="form-input-bx" value="1.65" required>
                            <small class="cc-hint">Se aplica solo si un trabajo cumple un mes sin pagarse.</small>
                        </div>
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Días límite de pago</label>
                            <input type="number" min="1" name="dias_mora_alerta" class="form-input-bx" value="30" required>
                        </div>
                    </div>

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Notas (opcional)</label>
                        <textarea name="notas" class="form-input-bx" rows="2"></textarea>
                    </div>

                    <label class="cc-check" style="margin-top:1rem;">
                        <input type="checkbox" name="alertas_activas" value="1" checked>
                        <span>Enviar recordatorios por WhatsApp</span>
                    </label>
                </div>

                <div class="modal-foot-bx">
                    <button type="button" class="btn-fin" @click="openCliente = false">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#7e22ce;">Crear cliente</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
[x-cloak] { display: none !important; }
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

.cc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; margin-top: 1rem; }

.cc-cliente-card { display: block; background: #fff; border: 1px solid #cbd5e1; border-radius: 14px; padding: 1rem; text-decoration: none; color: inherit; box-shadow: 0 4px 12px rgba(0,0,0,0.04); transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease; }
.cc-cliente-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(126,34,206,0.12); border-color: #a855f7; }
.cc-cliente-card.inactivo { opacity: 0.6; }
.cc-cliente-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; }
.cc-cliente-top h2 { font-size: 0.95rem; font-weight: 800; color: #1e293b; }
.cc-cliente-top small { font-size: 0.7rem; color: #64748b; display: block; margin-top: 0.15rem; }
.cc-tasa { background: rgba(126,34,206,0.1); color: #7e22ce; border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 700; white-space: nowrap; }
.cc-cliente-saldo { display: flex; justify-content: space-between; align-items: baseline; margin-top: 0.85rem; padding-top: 0.6rem; border-top: 1px solid #f1f5f9; }
.cc-cliente-saldo span { font-size: 0.72rem; color: #64748b; }
.cc-cliente-saldo strong { font-size: 1.05rem; font-weight: 800; }
.cc-cliente-foot { margin-top: 0.5rem; font-size: 0.68rem; color: #64748b; }
.cc-cliente-foot.vencido { color: #b91c1c; font-weight: 600; }
.cc-cliente-foot.ok { color: #16a34a; font-weight: 600; }

.cc-vacio { grid-column: 1 / -1; text-align: center; padding: 3rem 1rem; background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; }
.cc-vacio small { font-size: 0.75rem; color: #94a3b8; }

.cc-alert { border-radius: 10px; padding: 0.7rem 0.9rem; font-size: 0.8rem; font-weight: 600; margin-bottom: 1rem; }
.cc-alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.cc-hint { display: block; margin-top: 0.25rem; font-size: 0.68rem; color: #94a3b8; }
.cc-check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: #334155; cursor: pointer; }
.cc-check input { width: 16px; height: 16px; cursor: pointer; }
</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
