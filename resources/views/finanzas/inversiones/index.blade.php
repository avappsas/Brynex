@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Inversiones Cripto / USDT')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="inversionesControl({{ json_encode($precioUsdtData) }}, {{ json_encode($inversiones) }})">

    @component('finanzas.partials._header_banner', [
        'titulo' => '🪙 Portafolio de Inversiones',
        'subtitulo' => 'Control de adquisiciones cripto (Binance USDT), acciones o fondos, con recálculo dinámico basado en la API en tiempo real.',
        'breadcrumb' => [
            'Finanzas Personales' => route('finanzas.dashboard'),
            'Inversiones' => null
        ]
    ])
        @slot('opciones')
            <button @click="openCrear = true" class="btn-fin success" style="background:#0284c7;">
                ➕ Nueva Inversión
            </button>
        @endslot
    @endcomponent

    {{-- Cripto Live Bar --}}
    <div class="cripto-live-header-bar">
        <div class="clh-left">
            <span class="clh-icon">🪙</span>
            <div>
                <strong>Cotización USDT en Vivo (CoinGecko):</strong>
                <span class="clh-val">${{ number_format($precioUsdtData['precio_cop'], 0, ',', '.') }} COP</span>
                <span class="clh-val-usd">(${{ number_format($precioUsdtData['precio_usd'], 2) }} USD)</span>
            </div>
        </div>
        <button @click="refreshPrecio()" class="btn-refresh-cripto" :disabled="refresando">
            <span x-show="!refresando">🔄 Refrescar Precio</span>
            <span x-show="refresando" x-cloak>⏳ Cargando...</span>
        </button>
    </div>

    {{-- Grid de KPIs del Portafolio --}}
    <div class="fin-kpis-grid" style="margin-top:1rem;">
        <div class="kpi-card" style="border-left: 4px solid #0284c7">
            <div class="kpi-icon">💰</div>
            <div class="kpi-content">
                <span class="kpi-label">Total Invertido (COP)</span>
                <span class="kpi-val">${{ number_format($valorTotalInvertido, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="kpi-card" style="border-left: 4px solid #8b5cf6">
            <div class="kpi-icon">📈</div>
            <div class="kpi-content">
                <span class="kpi-label">Valor Estimado Actual</span>
                <span class="kpi-val">${{ number_format($valorTotalActual, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="kpi-card" style="border-left: 4px solid {{ $balanceNeta >= 0 ? '#10b981' : '#ef4444' }}">
            <div class="kpi-icon">⚖️</div>
            <div class="kpi-content">
                <span class="kpi-label">Rentabilidad Netas</span>
                <span class="kpi-val" style="color:{{ $balanceNeta >= 0 ? '#10b981' : '#ef4444' }}">
                    ${{ number_format($balanceNeta, 0, ',', '.') }} COP
                </span>
            </div>
        </div>
    </div>

    {{-- Listado de Inversiones --}}
    <div class="card-tabla-bx" style="margin-top:1.5rem;">
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th>Nombre Activo / Inversión</th>
                    <th style="text-align:center;">Tipo</th>
                    <th style="text-align:right;">Cantidad (Tokens)</th>
                    <th style="text-align:right;">Precio Compra Prom.</th>
                    <th style="text-align:right;">Monto Invertido</th>
                    <th style="text-align:right;">Valor Actual Estimado</th>
                    <th style="text-align:right;">Ganancia / Pérdida</th>
                    <th style="text-align:center; width:10%;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inversiones as $inv)
                    @php
                        $rentabilidad = ($inv->valor_actual_cop ?? $inv->monto_invertido_cop) - $inv->monto_invertido_cop;
                    @endphp
                    <tr>
                        <td><strong>{{ $inv->nombre }}</strong></td>
                        <td style="text-align:center;"><span class="tipo-tag-bx {{ $inv->tipo }}">{{ $inv->tipo }}</span></td>
                        <td style="text-align:right; font-family:monospace;">{{ $inv->cantidad_tokens ? number_format($inv->cantidad_tokens, 4, ',', '.') : '-' }}</td>
                        <td style="text-align:right;">{{ $inv->precio_compra_promedio ? '$' . number_format($inv->precio_compra_promedio, 0, ',', '.') : '-' }}</td>
                        <td style="text-align:right; font-weight:600;">${{ number_format($inv->monto_invertido_cop, 0, ',', '.') }}</td>
                        <td style="text-align:right; font-weight:700; color:#8b5cf6;">
                            ${{ number_format($inv->valor_actual_cop ?? $inv->monto_invertido_cop, 0, ',', '.') }}
                        </td>
                        <td style="text-align:right; font-weight:700; color:{{ $rentabilidad >= 0 ? '#16a34a' : '#b91c1c' }};">
                            ${{ number_format($rentabilidad, 0, ',', '.') }} COP
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex; justify-content:center; gap:0.4rem;">
                                <button @click="selectedInversion = {{ json_encode($inv) }}; openEditar = true" class="btn-icon-bx edit" title="Editar">✏️</button>
                                <form action="{{ route('finanzas.inversiones.destroy', $inv->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta inversión del historial?')" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon-bx delete" title="Eliminar">❌</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center; padding:2rem; color:#64748b;">
                            No tienes inversiones registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal Crear --}}
    <div x-show="openCrear" class="modal-overlay-bx" @click.self="openCrear = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #0369a1, #075985);">
                <h3>🪙 Nueva Inversión / Adquisición</h3>
                <button @click="openCrear = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.inversiones.store') }}" method="POST">
                @csrf
                <div class="modal-body-bx" x-data="{ tipo: 'cripto' }">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre del Activo</label>
                        <input type="text" name="nombre" placeholder="Ej: Binance USDT, Acciones Ecopetrol" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Tipo de Activo</label>
                        <select name="tipo" x-model="tipo" class="form-select-bx" required>
                            <option value="cripto">Criptomonedas / Dólar Digital</option>
                            <option value="trading">Trading / Portafolios</option>
                            <option value="otro">Otros Activos / Fondos</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Fecha de Compra</label>
                        <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                    </div>
                    
                    {{-- Monto Invertido --}}
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto Invertido ($ COP)</label>
                        <input type="number" step="any" name="monto_invertido_cop" placeholder="Ej: 2000000" class="form-input-bx" required min="1">
                    </div>

                    {{-- Cuenta de origen --}}
                    @if(isset($cuentas) && $cuentas->isNotEmpty())
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">¿De qué cuenta salió el dinero?</label>
                        <select name="cuenta_id" class="form-select-bx" required>
                            @foreach($cuentas as $cta)
                                <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    {{-- Cripto/Trading Campos Adicionales --}}
                    <div x-show="tipo === 'cripto' || tipo === 'trading'" x-cloak style="margin-top:1rem; border-left:2px dashed #0284c7; padding-left:1rem;">
                        <div style="display:flex; gap:0.5rem;">
                            <div class="form-group-bx" style="flex:1;">
                                <label class="form-label-bx" style="color:#0284c7;">Cantidad de Tokens</label>
                                <input type="number" step="0.00000001" name="cantidad_tokens" placeholder="Ej: 480.5" class="form-input-bx">
                            </div>
                            <div class="form-group-bx" style="flex:1;">
                                <label class="form-label-bx" style="color:#0284c7;">Precio Token (COP)</label>
                                <input type="number" step="0.01" name="precio_token_cop" placeholder="Ej: 4150" class="form-input-bx">
                            </div>
                        </div>
                    </div>

                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones</label>
                        <textarea name="observaciones" placeholder="Detalles de la compra..." class="form-input-bx" style="height:70px; resize:none;"></textarea>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openCrear = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#0284c7;">Guardar Inversión</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar --}}
    <div x-show="openEditar" class="modal-overlay-bx" @click.self="openEditar = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #0369a1, #075985);">
                <h3>✏️ Editar Inversión</h3>
                <button @click="openEditar = false" class="modal-close-bx">&times;</button>
            </div>
            <form :action="'{{ route('finanzas.inversiones.index') }}/' + selectedInversion.id" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre</label>
                        <input type="text" name="nombre" x-model="selectedInversion.nombre" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Tipo</label>
                        <select name="tipo" x-model="selectedInversion.tipo" class="form-select-bx" required>
                            <option value="cripto">Criptomonedas / Dólar Digital</option>
                            <option value="trading">Trading / Portafolios</option>
                            <option value="otro">Otros Activos / Fondos</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto Invertido ($ COP)</label>
                        <input type="number" step="any" name="monto_invertido_cop" x-model="selectedInversion.monto_invertido_cop" class="form-input-bx" required min="0">
                    </div>

                    {{-- Cripto/Trading Campos Adicionales --}}
                    <div x-show="selectedInversion.tipo === 'cripto' || selectedInversion.tipo === 'trading'" x-cloak style="margin-top:1rem; border-left:2px dashed #0284c7; padding-left:1rem;">
                        <div style="display:flex; gap:0.5rem;">
                            <div class="form-group-bx" style="flex:1;">
                                <label class="form-label-bx" style="color:#0284c7;">Cantidad de Tokens</label>
                                <input type="number" step="0.00000001" name="cantidad_tokens" x-model="selectedInversion.cantidad_tokens" placeholder="Ej: 480.5" class="form-input-bx">
                            </div>
                            <div class="form-group-bx" style="flex:1;">
                                <label class="form-label-bx" style="color:#0284c7;">Precio Compra Promedio (COP)</label>
                                <input type="number" step="0.01" name="precio_compra_promedio" x-model="selectedInversion.precio_compra_promedio" placeholder="Ej: 4150" class="form-input-bx">
                            </div>
                        </div>
                    </div>

                    <div class="form-group-bx" style="margin-top:1rem;" x-show="!( (selectedInversion.tipo === 'cripto' || selectedInversion.tipo === 'trading') && selectedInversion.cantidad_tokens > 0 )">
                        <label class="form-label-bx">Valor Actual ($ COP)</label>
                        <input type="number" step="any" name="valor_actual_cop" x-model="selectedInversion.valor_actual_cop" class="form-input-bx" required min="0">
                        <small style="color:#64748b; font-size:0.7rem;">Puedes forzar el valor estimado actual aquí.</small>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Estado</label>
                        <select name="activo" x-model="selectedInversion.activo" class="form-select-bx" required>
                            <option value="1">Activo</option>
                            <option value="0">Cerrado / Retirado</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones</label>
                        <textarea name="observaciones" x-model="selectedInversion.observaciones" class="form-input-bx" style="height:70px; resize:none;"></textarea>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openEditar = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#0284c7;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

/* Live Cripto Bar */
.cripto-live-header-bar { display: flex; justify-content: space-between; align-items: center; background: #0f172a; border: 1px solid #1e293b; color: #fff; padding: 0.75rem 1.25rem; border-radius: 12px; font-size: 0.8rem; box-shadow: 0 4px 14px rgba(0,0,0,0.15); margin-top: 1rem; flex-wrap: wrap; gap: 0.75rem; }
.clh-left { display: flex; align-items: center; gap: 0.75rem; }
.clh-icon { font-size: 1.5rem; }
.clh-val { color: #34d399; font-size: 1rem; font-weight: 700; margin-left: 5px; }
.clh-val-usd { color: #94a3b8; font-size: 0.75rem; margin-left: 5px; }
.btn-refresh-cripto { background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.4); color: #93c5fd; padding: 0.35rem 0.85rem; border-radius: 8px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: background 0.15s; }
.btn-refresh-cripto:hover { background: rgba(59, 130, 246, 0.35); }

/* Tabla */

.tipo-tag-bx { display: inline-block; font-size: 0.62rem; font-weight: 700; text-transform: uppercase; padding: 0.05rem 0.3rem; border-radius: 4px; }
.tipo-tag-bx.cripto { background: #e0f2fe; color: #0369a1; }
.tipo-tag-bx.trading { background: #f3e8ff; color: #6b21a8; }
.tipo-tag-bx.otro { background: #f1f5f9; color: #475569; }


/* KPIs */

/* Modales */

</style>
@endpush

@push('scripts')
<script>
function inversionesControl(precioInicial, inversionesInicial) {
    return {
        precio: precioInicial,
        inversiones: inversionesInicial,
        openCrear: false,
        openEditar: false,
        selectedInversion: {},
        refresando: false,
        
        async refreshPrecio() {
            this.refresando = true;
            try {
                const response = await fetch("{{ route('finanzas.inversiones.precio-usdt') }}");
                if (response.ok) {
                    const data = await response.json();
                    this.precio = data;
                    // Recargar para aplicar y actualizar base de datos en caliente
                    window.location.reload();
                }
            } catch (error) {
                console.error(error);
                alert("Error de red al actualizar precio.");
            } finally {
                this.refresando = false;
            }
        }
    }
}
</script>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
