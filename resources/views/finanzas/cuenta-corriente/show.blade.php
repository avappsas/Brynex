@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Cuenta Corriente')

@section('contenido')
@include('finanzas.partials._responsive_fin')

@php
    $hoy = now()->toDateString();
    $itemsVacio = [['descripcion' => '', 'cantidad' => 1, 'valor_unitario' => 0, 'costo_unitario' => 0]];
@endphp

<div class="finanzas-container" x-data="ccCliente()">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.cuenta-corriente.index') }}">Cuenta Corriente</a>
            <span>›</span>
            <span>{{ $cliente->nombre }}</span>
        </div>

        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <button @click="openTrabajo = true" class="btn-fin success" style="background:#7e22ce;">➕ Registrar Trabajo</button>
        </div>
    </div>

    @if(session('error'))
        <div class="cc-alert error">⚠️ {{ session('error') }}</div>
    @endif

    {{-- Resumen del cliente --}}
    <div class="cc-resumen">
        <div class="cc-resumen-main">
            <h1>🏢 {{ $cliente->nombre }}</h1>
            <p>
                {{ $totales['trabajos_pendientes'] }} de {{ $totales['trabajos_totales'] }} trabajo(s) pendiente(s)
                @if($cliente->telefono) · 📱 {{ $cliente->telefono }} @endif
                · Tasa por defecto {{ rtrim(rtrim(number_format($cliente->tasa_interes_mensual, 3, ',', '.'), '0'), ',') }}% mensual
            </p>
            @if($cliente->notas)
                <p class="cc-notas">{{ $cliente->notas }}</p>
            @endif
        </div>

        <div class="cc-resumen-cifras">
            <div class="cc-cifra">
                <span>Total por cobrar</span>
                <strong style="color:{{ $totales['saldo'] > 0 ? '#b91c1c' : '#16a34a' }};">
                    ${{ number_format($totales['saldo'], 0, ',', '.') }}
                </strong>
            </div>
            <div class="cc-cifra">
                <span>Valor de trabajos</span>
                <strong>${{ number_format($totales['capital'], 0, ',', '.') }}</strong>
                <small style="display:block; font-size:0.62rem; color:#94a3b8; text-transform:none;">sin intereses</small>
            </div>
            <div class="cc-cifra">
                <span>Intereses causados</span>
                <strong style="color:{{ $totales['intereses'] >= 1000 ? '#b91c1c' : '#64748b' }};">
                    ${{ number_format($totales['intereses'], 0, ',', '.') }}
                </strong>
            </div>
            @if($totales['costos'] > 0)
            <div class="cc-cifra">
                <span>Utilidad acumulada</span>
                <strong style="color:{{ $totales['utilidad'] >= 0 ? '#16a34a' : '#b91c1c' }};">
                    ${{ number_format($totales['utilidad'], 0, ',', '.') }}
                </strong>
                <small style="display:block; font-size:0.62rem; color:#94a3b8; text-transform:none;">
                    ${{ number_format($totales['facturado'], 0, ',', '.') }} cobrado − ${{ number_format($totales['costos'], 0, ',', '.') }} en costos
                </small>
            </div>
            @endif
        </div>
    </div>

    {{-- Acciones del cliente --}}
    <div class="cc-acciones">
        <button @click="openAbono = true" class="btn-fin success" @if($totales['trabajos_pendientes'] === 0) disabled @endif>
            💰 Abono general
        </button>

        <form action="{{ route('finanzas.cuenta-corriente.whatsapp', $cliente->id) }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="btn-fin" @if(!$cliente->telefono || $totales['trabajos_pendientes'] === 0) disabled @endif>
                {{ $totales['vencidos'] > 0 ? '🔴 Cobrar por WhatsApp' : '🟢 Recordar por WhatsApp' }}
            </button>
        </form>

        <form action="{{ route('finanzas.cuenta-corriente.liquidar', $cliente->id) }}" method="POST" style="display:inline;"
              onsubmit="return confirm('Se causarán los intereses de todos los trabajos que ya cumplieron un mes o más. ¿Continuar?');">
            @csrf
            <button type="submit" class="btn-fin" @if($totales['trabajos_pendientes'] === 0) disabled @endif>⚙️ Liquidar meses cumplidos</button>
        </form>

        <button @click="openCliente = true" class="btn-fin">✏️ Editar cliente</button>
    </div>

    {{-- Filtro --}}
    @if($conteos['cerrados'] > 0)
    <div class="cc-filtros">
        @php
            $opciones = [
                'pendientes' => ['Por cobrar', $conteos['pendientes']],
                'cerrados' => ['Saldados', $conteos['cerrados']],
                'todos' => ['Todos', $conteos['todos']],
            ];
        @endphp
        @foreach($opciones as $clave => [$etiqueta, $cuenta])
            <a href="{{ route('finanzas.cuenta-corriente.show', ['cliente' => $cliente->id, 'ver' => $clave]) }}"
               class="cc-filtro {{ $ver === $clave ? 'activo' : '' }}">
                {{ $etiqueta }} <span>{{ $cuenta }}</span>
            </a>
        @endforeach
    </div>
    @endif

    {{-- Trabajos --}}
    <div class="cc-trabajos">
        @forelse($trabajos as $t)
            @php
                $pagado = $t->estado === 'pagado';
                $vencido = $t->esta_vencido;
                $abonado = round($t->total_items - $t->monto_original, 2);
                $itemsJs = $t->items->map(fn ($i) => [
                    'descripcion' => $i->descripcion,
                    'cantidad' => (float) $i->cantidad,
                    'valor_unitario' => (float) $i->valor_unitario,
                    'costo_unitario' => (float) $i->costo_unitario,
                ])->values();
            @endphp

            <div class="cc-trabajo {{ $pagado ? 'pagado' : ($vencido ? 'vencido' : '') }}" x-data="{ abierto: {{ $pagado ? 'false' : 'true' }} }">

                <div class="cc-trabajo-head" @click="abierto = !abierto">
                    <div class="cc-trabajo-titulo">
                        <span class="cc-flecha" :class="abierto && 'abierta'">▸</span>
                        <div>
                            <h3>{{ $t->descripcion ?: 'Trabajo sin descripción' }}</h3>
                            <small>
                                {{ \Carbon\Carbon::parse($t->fecha_desembolso)->format('d/m/Y') }} ·
                                {{ $t->items->count() }} ítem(s)
                                @if($t->sin_interes)
                                    · <span class="cc-tag gris">sin interés</span>
                                @elseif(!$pagado)
                                    · corte {{ $t->fecha_corte->format('d/m/Y') }}
                                    ({{ $t->dias_para_corte >= 0 ? 'faltan ' . $t->dias_para_corte . 'd' : 'vencido' }})
                                @endif
                            </small>
                        </div>
                    </div>

                    <div class="cc-trabajo-cifras">
                        <div class="cc-trabajo-valor">
                            <span>Valor</span>
                            <strong>${{ number_format($t->total_items, 0, ',', '.') }}</strong>
                        </div>
                        @if($t->costo_items > 0)
                        <div class="cc-trabajo-valor">
                            <span>Utilidad</span>
                            <strong style="color:{{ $t->utilidad >= 0 ? '#16a34a' : '#b91c1c' }};">
                                ${{ number_format($t->utilidad, 0, ',', '.') }}
                            </strong>
                        </div>
                        @endif
                        <div class="cc-trabajo-valor">
                            <span>Saldo</span>
                            <strong style="color:{{ $t->saldo_actual > 0 ? '#b91c1c' : '#16a34a' }};">
                                ${{ number_format($t->saldo_actual, 0, ',', '.') }}
                            </strong>
                        </div>
                        @if($pagado)
                            <span class="cc-tag verde">Pagado</span>
                        @elseif($vencido)
                            <span class="cc-tag rojo">Vencido {{ $t->dias_vencidos }}d</span>
                        @else
                            <span class="cc-tag azul">Pendiente</span>
                        @endif
                    </div>
                </div>

                <div x-show="abierto" x-cloak class="cc-trabajo-body">

                    {{-- Desglose --}}
                    <div class="cc-bloque">
                        <h4>Desglose</h4>
                        <table class="cc-tabla">
                            <thead>
                                <tr>
                                    <th>Concepto</th>
                                    <th style="text-align:center; width:70px;">Cant.</th>
                                    <th style="text-align:right; width:105px;">Costo unit.</th>
                                    <th style="text-align:right; width:105px;">Cobro unit.</th>
                                    <th style="text-align:right; width:110px;">Subtotal</th>
                                    <th style="text-align:right; width:105px;">Utilidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($t->items as $item)
                                    <tr>
                                        <td>{{ $item->descripcion }}</td>
                                        <td style="text-align:center;">{{ rtrim(rtrim(number_format($item->cantidad, 2, ',', '.'), '0'), ',') }}</td>
                                        <td style="text-align:right; color:{{ $item->costo_unitario > 0 ? '#b45309' : '#cbd5e1' }};">
                                            {{ $item->costo_unitario > 0 ? '$' . number_format($item->costo_unitario, 0, ',', '.') : '—' }}
                                        </td>
                                        <td style="text-align:right;">${{ number_format($item->valor_unitario, 0, ',', '.') }}</td>
                                        <td style="text-align:right; font-weight:600;">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                        <td style="text-align:right; font-weight:600; color:{{ $item->utilidad >= 0 ? '#16a34a' : '#b91c1c' }};">
                                            ${{ number_format($item->utilidad, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" style="color:#94a3b8; font-style:italic;">
                                            Este trabajo se registró antes del desglose; su valor total es ${{ number_format($t->monto_original, 0, ',', '.') }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($t->items->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <td colspan="4" style="text-align:right; font-weight:700;">Total del trabajo</td>
                                    <td style="text-align:right; font-weight:800; color:#7e22ce;">${{ number_format($t->total_items, 0, ',', '.') }}</td>
                                    <td style="text-align:right; font-weight:800; color:{{ $t->utilidad >= 0 ? '#16a34a' : '#b91c1c' }};">
                                        ${{ number_format($t->utilidad, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @if($t->costo_items > 0)
                                <tr>
                                    <td colspan="4" style="text-align:right; color:#b45309;">
                                        Costos, ya descontados de
                                        {{ optional($t->gastoCosto)->cuenta_id ? optional(\App\Models\Finanzas\Cuenta::find($t->gastoCosto->cuenta_id))->nombre : 'tu cuenta' }}
                                    </td>
                                    <td style="text-align:right; color:#b45309; font-weight:700;">−${{ number_format($t->costo_items, 0, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                                @endif
                                @if($abonado > 0)
                                <tr>
                                    <td colspan="4" style="text-align:right; color:#16a34a;">Abonado al trabajo</td>
                                    <td style="text-align:right; color:#16a34a; font-weight:700;">−${{ number_format($abonado, 0, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                                @endif
                                @if($t->intereses_acumulados > 0)
                                <tr>
                                    <td colspan="4" style="text-align:right; color:#b91c1c;">Intereses causados sin pagar</td>
                                    <td style="text-align:right; color:#b91c1c; font-weight:700;">+${{ number_format($t->intereses_acumulados, 0, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                                @endif
                            </tfoot>
                            @endif
                        </table>
                    </div>

                    {{-- Movimientos --}}
                    @if($t->movimientos->isNotEmpty())
                    <div class="cc-bloque">
                        <h4>Historial</h4>
                        <table class="cc-tabla">
                            <thead>
                                <tr>
                                    <th style="width:95px;">Fecha</th>
                                    <th style="width:150px;">Tipo</th>
                                    <th>Detalle</th>
                                    <th style="text-align:right; width:110px;">Monto</th>
                                    <th style="text-align:right; width:110px;">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($t->movimientos as $mov)
                                    @php
                                        $etiquetas = [
                                            'desembolso' => ['Trabajo registrado', '#7e22ce'],
                                            'interes_mensual' => ['Interés del mes', '#b91c1c'],
                                            'interes_proporcional' => ['Interés proporcional', '#b91c1c'],
                                            'capitalizacion' => ['Capitalización', '#b91c1c'],
                                            'abono_interes' => ['Pago a intereses', '#16a34a'],
                                            'abono_capital' => ['Pago al trabajo', '#16a34a'],
                                            'pago_total' => ['Pago total', '#16a34a'],
                                        ];
                                        [$etiqueta, $color] = $etiquetas[$mov->tipo] ?? [$mov->tipo, '#64748b'];
                                        $suma = in_array($mov->tipo, ['desembolso', 'interes_mensual', 'interes_proporcional', 'capitalizacion']);
                                    @endphp
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($mov->fecha)->format('d/m/Y') }}</td>
                                        <td style="color:{{ $color }}; font-weight:600;">{{ $etiqueta }}</td>
                                        <td style="color:#64748b; font-size:0.72rem;">
                                            {{ $mov->observacion }}
                                            @if($mov->soporte_path)
                                                <a href="{{ route('finanzas.prestamos.movimiento.descargar-soporte', $mov->id) }}" target="_blank" style="color:#3b82f6;">📎</a>
                                            @endif
                                        </td>
                                        <td style="text-align:right; color:{{ $suma ? '#b91c1c' : '#16a34a' }}; font-weight:600;">
                                            {{ $suma ? '+' : '−' }}${{ number_format($mov->monto, 0, ',', '.') }}
                                        </td>
                                        <td style="text-align:right;">${{ number_format($mov->saldo_despues, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    {{-- Acciones del trabajo --}}
                    <div class="cc-trabajo-acciones">
                        @unless($pagado)
                            <button class="btn-fin success"
                                    @click="abrirPago({{ $t->id }}, @js($t->descripcion), {{ (float) $t->saldo_actual }})">
                                💵 Registrar pago
                            </button>
                        @endunless

                        <button class="btn-fin"
                                @click="abrirEditar({{ $t->id }}, @js($t->descripcion), @js(\Carbon\Carbon::parse($t->fecha_desembolso)->toDateString()), {{ (float) $t->tasa_interes_mensual }}, {{ $t->sin_interes ? 'true' : 'false' }}, @js($t->observaciones), @js($itemsJs->isEmpty() ? $itemsVacio : $itemsJs))">
                            ✏️ Editar
                        </button>

                        <form action="{{ route('finanzas.cuenta-corriente.trabajos.destroy', $t->id) }}" method="POST" style="display:inline;"
                              onsubmit="return confirm('Se eliminará el trabajo «{{ $t->descripcion }}» con su desglose y todo su historial de pagos. Esta acción no se puede deshacer. ¿Continuar?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-fin danger">🗑️ Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="cc-vacio">
                @if($conteos['todos'] === 0)
                    Este cliente todavía no tiene trabajos registrados.<br>
                    <small>Usa «Registrar Trabajo» para cargar el primero con su desglose.</small>
                @elseif($ver === 'pendientes')
                    🎉 {{ $cliente->nombre }} está a paz y salvo: no hay nada por cobrar.<br>
                    <small>
                        Tiene {{ $conteos['cerrados'] }} trabajo(s) saldado(s).
                        <a href="{{ route('finanzas.cuenta-corriente.show', ['cliente' => $cliente->id, 'ver' => 'cerrados']) }}"
                           style="color:#7e22ce; font-weight:600;">Verlos</a>
                    </small>
                @else
                    No hay trabajos saldados todavía.
                @endif
            </div>
        @endforelse
    </div>

    @include('finanzas.cuenta-corriente._modales')

</div>
@endsection

@push('styles')
<style>
[x-cloak] { display: none !important; }
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

.cc-alert { border-radius: 10px; padding: 0.7rem 0.9rem; font-size: 0.8rem; font-weight: 600; margin-bottom: 1rem; }
.cc-alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.cc-resumen { display: flex; justify-content: space-between; align-items: flex-start; gap: 1.5rem; flex-wrap: wrap; background: #fff; border: 1px solid #cbd5e1; border-radius: 14px; padding: 1.1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.cc-resumen-main h1 { font-size: 1.25rem; font-weight: 800; color: #0f172a; }
.cc-resumen-main p { font-size: 0.76rem; color: #64748b; margin-top: 0.25rem; }
.cc-notas { font-style: italic; color: #94a3b8 !important; }
.cc-resumen-cifras { display: flex; gap: 1.5rem; flex-wrap: wrap; }
.cc-cifra { text-align: right; }
.cc-cifra span { display: block; font-size: 0.68rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
.cc-cifra strong { font-size: 1.1rem; font-weight: 800; color: #1e293b; }

.cc-acciones { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem; }
.cc-acciones button[disabled] { opacity: 0.45; cursor: not-allowed; }

.cc-filtros { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 1.25rem; }
.cc-filtro { text-decoration: none; background: #fff; border: 1px solid #cbd5e1; border-radius: 999px; padding: 0.3rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #475569; display: inline-flex; align-items: center; gap: 0.35rem; transition: all .12s ease; }
.cc-filtro:hover { border-color: #a855f7; color: #7e22ce; }
.cc-filtro span { background: #f1f5f9; border-radius: 999px; padding: 0 0.35rem; font-size: 0.68rem; color: #64748b; }
.cc-filtro.activo { background: #7e22ce; border-color: #7e22ce; color: #fff; }
.cc-filtro.activo span { background: rgba(255,255,255,0.25); color: #fff; }

.cc-trabajos { display: flex; flex-direction: column; gap: 0.85rem; margin-top: 1rem; }
.cc-trabajo { background: #fff; border: 1px solid #cbd5e1; border-left: 4px solid #7e22ce; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.cc-trabajo.pagado { border-left-color: #16a34a; opacity: 0.82; }
.cc-trabajo.vencido { border-left-color: #b91c1c; }

.cc-trabajo-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.85rem 1rem; cursor: pointer; flex-wrap: wrap; }
.cc-trabajo-head:hover { background: #faf5ff; }
.cc-trabajo-titulo { display: flex; align-items: center; gap: 0.6rem; min-width: 0; }
.cc-trabajo-titulo h3 { font-size: 0.88rem; font-weight: 700; color: #1e293b; }
.cc-trabajo-titulo small { font-size: 0.7rem; color: #64748b; display: block; margin-top: 0.1rem; }
.cc-flecha { color: #7e22ce; font-size: 0.9rem; transition: transform .15s ease; display: inline-block; }
.cc-flecha.abierta { transform: rotate(90deg); }

.cc-trabajo-cifras { display: flex; align-items: center; gap: 1.1rem; }
.cc-trabajo-valor { text-align: right; }
.cc-trabajo-valor span { display: block; font-size: 0.64rem; color: #94a3b8; text-transform: uppercase; }
.cc-trabajo-valor strong { font-size: 0.9rem; font-weight: 700; }

.cc-tag { border-radius: 999px; padding: 0.15rem 0.55rem; font-size: 0.66rem; font-weight: 700; white-space: nowrap; }
.cc-tag.verde { background: rgba(34,197,94,0.12); color: #166534; }
.cc-tag.rojo { background: rgba(239,68,68,0.1); color: #b91c1c; }
.cc-tag.azul { background: rgba(59,130,246,0.1); color: #1d4ed8; }
.cc-tag.gris { background: #f1f5f9; color: #475569; }

.cc-trabajo-body { padding: 0 1rem 1rem; border-top: 1px solid #f1f5f9; }
.cc-bloque { margin-top: 0.9rem; }
.cc-bloque h4 { font-size: 0.7rem; font-weight: 800; color: #7e22ce; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem; }

.cc-tabla { width: 100%; border-collapse: collapse; font-size: 0.76rem; }
.cc-tabla th { text-align: left; font-size: 0.66rem; font-weight: 700; color: #64748b; text-transform: uppercase; padding: 0.35rem 0.5rem; border-bottom: 1px solid #e2e8f0; }
.cc-tabla td { padding: 0.4rem 0.5rem; border-bottom: 1px solid #f8fafc; color: #334155; }
.cc-tabla tfoot td { border-top: 1px solid #e2e8f0; border-bottom: none; padding-top: 0.5rem; }

.cc-trabajo-acciones { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; }

.cc-vacio { text-align: center; padding: 3rem 1rem; background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; }
.cc-vacio small { font-size: 0.75rem; color: #94a3b8; }

.cc-hint { display: block; margin-top: 0.25rem; font-size: 0.68rem; color: #94a3b8; }
.cc-pago-resumen { display: flex; justify-content: space-between; align-items: center; gap: 1rem; background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 10px; padding: 0.6rem 0.8rem; font-size: 0.78rem; color: #581c87; flex-wrap: wrap; }
.cc-pago-resumen strong { font-weight: 800; }
.cc-check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: #334155; cursor: pointer; }
.cc-check input { width: 16px; height: 16px; cursor: pointer; }

/* Filas de ítems en los formularios */
.cc-item-fila { display: flex; gap: 0.5rem; align-items: flex-start; margin-bottom: 0.45rem; }
.cc-item-fila input { padding: 0.45rem 0.6rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.78rem; width: 100%; }
.cc-item-fila .cc-col-desc { flex: 1 1 auto; min-width: 0; }
.cc-item-fila .cc-col-num { flex: 0 0 78px; }
.cc-item-fila .cc-col-val { flex: 0 0 120px; }
.cc-item-fila .cc-col-sub { flex: 0 0 110px; text-align: right; font-size: 0.78rem; font-weight: 700; color: #334155; padding-top: 0.5rem; }
.cc-item-quitar { flex: 0 0 30px; background: #fee2e2; color: #b91c1c; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; height: 33px; }
.cc-item-total { display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; padding-top: 0.6rem; border-top: 2px solid #f1f5f9; font-weight: 800; color: #7e22ce; font-size: 0.9rem; }
.cc-item-utilidad { display: flex; justify-content: space-between; align-items: center; margin-top: 0.3rem; font-weight: 700; font-size: 0.78rem; color: #64748b; }
.cc-item-cabecera { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.3rem; font-size: 0.62rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.03em; }
.cc-item-cabecera .cc-col-val, .cc-item-cabecera .cc-col-num { text-align: center; }
.cc-item-cabecera .cc-col-sub { text-align: right; padding-top: 0; }
.cc-input-costo { background: #fffbeb !important; border-color: #fcd34d !important; }

@media (max-width: 720px) {
    .cc-resumen-cifras { width: 100%; justify-content: space-between; gap: 0.75rem; }
    .cc-cifra { text-align: left; }
    .cc-trabajo-head { align-items: flex-start; }
    .cc-trabajo-cifras { width: 100%; justify-content: space-between; }
    .cc-item-fila { flex-wrap: wrap; }
    .cc-item-fila .cc-col-desc { flex: 1 1 100%; }
    .cc-item-fila .cc-col-sub { flex: 1 1 auto; text-align: left; }
}
</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
