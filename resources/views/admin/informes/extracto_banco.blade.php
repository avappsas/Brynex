@extends('layouts.app')
@section('modulo', 'Extracto del banco')

@php
    $fmt = fn ($v) => '$' . number_format((float) ($v ?? 0), 0, ',', '.');
    $reglas = [
        'referencia'    => ['Comprobante', '#16a34a'],
        'fecha_valor'   => ['Fecha y valor', '#2563eb'],
        'valor_cercano' => ['Valor, otro día', '#d97706'],
        'partido'       => ['Pago partido', '#7c3aed'],
        'agrupado'      => ['Varias facturas', '#7c3aed'],
        'manual'        => ['A mano', '#475569'],
    ];
@endphp

@section('contenido')
<style>
    .eb-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
        border-radius: 14px; color: #fff; padding: .85rem 1.4rem; margin-bottom: 1rem;
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: .8rem; box-shadow: 0 4px 20px rgba(15,23,42,.2);
    }
    .eb-header h1 { font-size: 1.05rem; font-weight: 700; margin: 0; }
    .eb-header p { font-size: .72rem; opacity: .75; margin: .15rem 0 0; }
    .eb-filtros { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
    .eb-filtros select, .eb-filtros input {
        background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.25);
        color: #fff; border-radius: 8px; padding: .3rem .55rem; font-size: .74rem;
    }
    .eb-filtros option { color: #0f172a; }
    .eb-btn {
        background: #2563eb; color: #fff; border: none; border-radius: 8px;
        padding: .35rem .85rem; font-size: .76rem; font-weight: 600; cursor: pointer;
        transition: background .15s;
    }
    .eb-btn:hover { background: #3b82f6; }
    .eb-btn--ghost {
        background: rgba(59,130,246,.15); border: 1px solid rgba(59,130,246,.35); color: #93c5fd;
    }
    .eb-btn--ghost:hover { background: rgba(59,130,246,.3); }

    .eb-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .8rem; margin-bottom: 1rem; }
    .eb-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: .85rem 1rem; box-shadow: 0 2px 8px rgba(0,0,0,.05); }
    .eb-kpi__label { font-size: .7rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .02em; }
    .eb-kpi__valor { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-top: .15rem; }
    .eb-kpi__pie { font-size: .72rem; color: #94a3b8; }
    .eb-kpi--alerta { border-left: 4px solid #f59e0b; }
    .eb-kpi--falta { border-left: 4px solid #ef4444; }
    .eb-kpi--ok { border-left: 4px solid #10b981; }

    .eb-tabs { display: flex; gap: .35rem; margin-bottom: .75rem; flex-wrap: wrap; }
    .eb-tab {
        padding: .35rem .9rem; border-radius: 20px; border: 1px solid #cbd5e1;
        background: #fff; color: #475569; font-size: .75rem; font-weight: 600; cursor: pointer;
    }
    .eb-tab.activa { background: #2563eb; border-color: #2563eb; color: #fff; }

    .eb-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .eb-tabla { width: 100%; border-collapse: collapse; font-size: .78rem; }
    .eb-tabla th {
        background: #f8fafc; color: #475569; font-size: .68rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .03em; text-align: left;
        padding: .55rem .75rem; border-bottom: 1px solid #e2e8f0; white-space: nowrap;
    }
    .eb-tabla td { padding: .5rem .75rem; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
    .eb-tabla tr:hover td { background: #f8fafc; }
    .eb-num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; white-space: nowrap; }
    .eb-vacia { text-align: center; padding: 2.2rem 1rem; color: #94a3b8; }
    .eb-vacia i { font-size: 1.6rem; display: block; margin-bottom: .5rem; opacity: .55; }
    .eb-mini { font-size: .7rem; color: #94a3b8; }

    .eb-regla { border-radius: 999px; padding: .12rem .55rem; font-size: .68rem; font-weight: 700; color: #fff; white-space: nowrap; }
    .eb-accion {
        border: 1px solid #cbd5e1; background: #fff; color: #475569; border-radius: 7px;
        padding: .22rem .6rem; font-size: .71rem; font-weight: 600; cursor: pointer;
    }
    .eb-accion:hover { border-color: #2563eb; color: #2563eb; }
    .eb-accion--rojo:hover { border-color: #ef4444; color: #ef4444; }

    .eb-modal-bg { position: fixed; inset: 0; z-index: 9998; background: rgba(0,0,0,.55); display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .eb-modal { background: #fff; border-radius: 14px; width: 100%; max-width: 640px; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
    .eb-modal__head { display: flex; justify-content: space-between; align-items: center; padding: .9rem 1.2rem; border-bottom: 1px solid #e5e7eb; }
    .eb-modal__head h3 { font-size: .95rem; font-weight: 600; color: #1e293b; margin: 0; }
    .eb-modal__body { padding: 1rem 1.2rem; overflow-y: auto; }
    .eb-modal__close { background: none; border: none; font-size: 1.4rem; color: #94a3b8; cursor: pointer; line-height: 1; }
    .eb-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: .45rem .7rem; font-size: .8rem; }
    .eb-cand { display: flex; justify-content: space-between; align-items: center; gap: .8rem; padding: .55rem .2rem; border-bottom: 1px solid #f1f5f9; }
    .eb-nota { background: #f8fafc; border-left: 3px solid #cbd5e1; padding: .6rem .8rem; font-size: .74rem; color: #64748b; border-radius: 0 8px 8px 0; margin-top: .8rem; }
</style>

<div x-data="extractoBanco()">

    @if (session('success'))
        <div x-data="{ v: true }" x-show="v" x-init="setTimeout(() => v = false, 5000)"
             style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#065f46;border-radius:10px;padding:.7rem 1rem;margin-bottom:.9rem;font-size:.8rem">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#991b1b;border-radius:10px;padding:.7rem 1rem;margin-bottom:.9rem;font-size:.8rem">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    <div class="eb-header">
        <div>
            <h1>Extracto del banco</h1>
            <p>Lo que dice el banco contra lo que dice el libro de BryNex</p>
            <p style="font-size:.68rem;opacity:.6;margin-top:.15rem">
                El extracto se descarga de la Sucursal Virtual en Excel y se carga aquí
            </p>
        </div>

        <form method="GET" action="{{ route('admin.informes.extracto_banco') }}" class="eb-filtros">
            <select name="cuenta" onchange="this.form.submit()">
                <option value="">Todas las cuentas</option>
                @foreach ($cuentas as $c)
                    <option value="{{ $c->id }}" @selected($cuentaId == $c->id)>{{ $c->etiqueta }}</option>
                @endforeach
            </select>
            <input type="date" name="desde" value="{{ $desde }}" onchange="this.form.submit()">
            <input type="date" name="hasta" value="{{ $hasta }}" onchange="this.form.submit()">
        </form>

        <div class="eb-filtros">
            <form method="POST" action="{{ route('admin.informes.extracto_banco.cargar') }}"
                  enctype="multipart/form-data" x-ref="formCargar" style="display:flex;align-items:center;gap:.35rem">
                @csrf
                <select name="cuenta" class="eb-btn eb-btn--ghost" style="padding:.3rem .5rem" required>
                    <option value="">¿de qué cuenta?</option>
                    @foreach ($cuentas as $c)
                        <option value="{{ $c->id }}" @selected($cuentaId == $c->id)>{{ $c->banco }} {{ $c->numero_cuenta }}</option>
                    @endforeach
                </select>
                <input type="file" name="archivo" accept=".xlsx,.xls" x-ref="archivo" style="display:none"
                       @change="$refs.formCargar.submit()">
                <button type="button" class="eb-btn" @click="$refs.archivo.click()">
                    <i class="fas fa-file-excel"></i> Cargar extracto
                </button>
            </form>
            <form method="POST" action="{{ route('admin.informes.extracto_banco.conciliar') }}">
                @csrf
                <input type="hidden" name="cuenta" value="{{ $cuentaId }}">
                <input type="hidden" name="desde" value="{{ $desde }}">
                <input type="hidden" name="hasta" value="{{ $hasta }}">
                <button class="eb-btn"><i class="fas fa-wand-magic-sparkles"></i> Cruzar</button>
            </form>
        </div>
    </div>

    <div class="eb-kpis">
        <div class="eb-kpi eb-kpi--alerta">
            <div class="eb-kpi__label">Entró y nadie registró</div>
            <div class="eb-kpi__valor">{{ $resumen['sin_identificar'] }}</div>
            <div class="eb-kpi__pie">{{ $fmt($resumen['valor_sin_identificar']) }} sin identificar</div>
        </div>
        <div class="eb-kpi eb-kpi--falta">
            <div class="eb-kpi__label">Registrado y sin respaldo</div>
            <div class="eb-kpi__valor">{{ $resumen['sin_respaldo'] }}</div>
            <div class="eb-kpi__pie">{{ $fmt($resumen['valor_sin_respaldo']) }} que el banco no reporta</div>
        </div>
        <div class="eb-kpi eb-kpi--alerta">
            <div class="eb-kpi__label">Salió y nadie registró</div>
            <div class="eb-kpi__valor">{{ $resumen['salidas_sueltas'] }}</div>
            <div class="eb-kpi__pie">{{ $fmt($resumen['valor_salidas_sueltas']) }} sin un gasto que lo explique</div>
        </div>
        <div class="eb-kpi eb-kpi--ok">
            <div class="eb-kpi__label">Cruzado</div>
            <div class="eb-kpi__valor">{{ $resumen['cruzados'] }}</div>
            <div class="eb-kpi__pie">vínculos en el rango</div>
        </div>
    </div>

    <div class="eb-tabs">
        <button class="eb-tab" :class="tab === 'entradas' && 'activa'" @click="tab = 'entradas'">
            Entradas sin identificar ({{ $resumen['sin_identificar'] }})
        </button>
        <button class="eb-tab" :class="tab === 'faltantes' && 'activa'" @click="tab = 'faltantes'">
            Sin respaldo en el banco ({{ $resumen['sin_respaldo'] + $resumen['gastos_sin_respaldo'] }})
        </button>
        <button class="eb-tab" :class="tab === 'salidas' && 'activa'" @click="tab = 'salidas'">
            Salidas sin identificar ({{ $resumen['salidas_sueltas'] }})
        </button>
        <button class="eb-tab" :class="tab === 'banco' && 'activa'" @click="tab = 'banco'">
            Cobros del banco ({{ $resumen['cobros_banco'] }})
        </button>
        <button class="eb-tab" :class="tab === 'cruzados' && 'activa'" @click="tab = 'cruzados'">
            Cruzadas ({{ $resumen['cruzados'] }})
        </button>
    </div>

    {{-- ── Entradas del banco que nadie registró ───────────────────── --}}
    <div class="eb-card" x-show="tab === 'entradas'" x-cloak>
        <table class="eb-tabla">
            <thead>
                <tr>
                    <th>Fecha</th><th>Descripción</th><th>Quién pagó</th>
                    <th>Comprobante</th><th class="eb-num">Valor</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sinIdentificar as $m)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
                        <td>
                            {{ $m->descripcion ?: '—' }}
                            <div class="eb-mini">{{ $m->banco }} {{ $m->numero_cuenta }}{{ $m->canal ? ' · ' . $m->canal : '' }}</div>
                        </td>
                        <td>
                            {{ $m->contraparte_nombre ?: '—' }}
                            @if ($m->contraparte_documento)
                                <div class="eb-mini">{{ $m->contraparte_documento }}</div>
                            @endif
                        </td>
                        <td>{{ $m->referencia ?: '—' }}</td>
                        <td class="eb-num">{{ $fmt($m->valor) }}</td>
                        <td style="white-space:nowrap">
                            <button class="eb-accion" @click="abrirVinculo({{ $m->id }}, '{{ $fmt($m->valor) }}', '{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}')">
                                <i class="fas fa-link"></i> Vincular
                            </button>
                            <button class="eb-accion" @click="abrirEntrada({{ $m->id }}, '{{ $fmt($m->valor) }}', @js($m->descripcion))">
                                <i class="fas fa-plus"></i> Registrar
                            </button>
                            <form method="POST" action="{{ route('admin.informes.extracto_banco.personal', $m->id) }}" style="display:inline"
                                  onsubmit="return confirm('¿Marcar como movimiento personal? Sale del cuadre del negocio.')">
                                @csrf
                                <button class="eb-accion"><i class="fas fa-user"></i> Es personal</button>
                            </form>
                            <form method="POST" action="{{ route('admin.informes.extracto_banco.ignorar', $m->id) }}" style="display:inline"
                                  onsubmit="return confirm('¿Marcar este movimiento como ajeno al libro?')">
                                @csrf
                                <button class="eb-accion eb-accion--rojo"><i class="fas fa-ban"></i> No es del libro</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="eb-vacia">
                        <i class="fas fa-inbox"></i>
                        Todo lo que entró al banco está identificado.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Consignaciones que el banco no reporta ──────────────────── --}}
    <div class="eb-card" x-show="tab === 'faltantes'" x-cloak>
        <table class="eb-tabla">
            <thead>
                <tr>
                    <th>Fecha</th><th>Titular</th><th>Factura</th>
                    <th>Comprobante</th><th class="eb-num">Valor</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sinRespaldo as $c)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($c->fecha)->format('d/m/Y') }}</td>
                        <td>
                            {{ $c->titular ?: '—' }}
                            <div class="eb-mini">consignación {{ $c->id }} · {{ $c->tipo }}</div>
                        </td>
                        <td>{{ $c->numero_factura ?: '—' }}</td>
                        <td>{{ $c->referencia ?: '—' }}</td>
                        <td class="eb-num">{{ $fmt($c->valor) }}</td>
                        <td style="white-space:nowrap">
                            <form method="POST" action="{{ route('admin.informes.extracto_banco.no_aparece', $c->id) }}"
                                  onsubmit="return confirm('¿Marcar esta consignación como no encontrada en el extracto?')">
                                @csrf
                                <button class="eb-accion eb-accion--rojo"><i class="fas fa-triangle-exclamation"></i> No aparece</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="eb-vacia">
                        <i class="fas fa-circle-check"></i>
                        Todas las consignaciones del rango tienen respaldo en el banco.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="eb-nota" style="margin:0;border-radius:0">
            Que una consignación no esté en el extracto puede ser que no entró la plata, o que
            el rango consultado no la alcanza. Por eso el cruce automático nunca marca
            «no aparece» solo: esa decisión queda con el nombre de quien la toma.
        </div>

        {{-- El mismo problema, del lado de las salidas --}}
        <div style="padding:.9rem 1rem .3rem;border-top:1px solid #e2e8f0">
            <b style="font-size:.8rem;color:#1e293b">Gastos que el banco no reporta ({{ $resumen['gastos_sin_respaldo'] }})</b>
            <div class="eb-mini">{{ $fmt($resumen['valor_gastos_sin_respaldo']) }} registrados como salida sin un movimiento que los respalde</div>
        </div>
        <table class="eb-tabla">
            <thead>
                <tr><th>Fecha</th><th>Concepto</th><th>Pagado a</th><th>Planilla</th><th class="eb-num">Valor</th></tr>
            </thead>
            <tbody>
                @forelse ($gastosSinRespaldo as $g)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($g->fecha)->format('d/m/Y') }}</td>
                        <td>
                            {{ \App\Models\Gasto::TIPOS[$g->tipo] ?? $g->tipo }}
                            <div class="eb-mini">{{ \Str::limit($g->descripcion ?: '—', 46) }}</div>
                        </td>
                        <td class="eb-mini">{{ $g->pagado_a ?: '—' }}</td>
                        <td class="eb-mini">{{ $g->numero_planilla ?: '—' }}</td>
                        <td class="eb-num" style="color:#b91c1c">-{{ $fmt($g->valor) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="eb-vacia">
                        <i class="fas fa-circle-check"></i>
                        Todos los gastos del rango tienen su movimiento en el extracto.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Salidas del banco que ningún gasto explica ──────────────── --}}
    <div class="eb-card" x-show="tab === 'salidas'" x-cloak>
        <table class="eb-tabla">
            <thead>
                <tr>
                    <th>Fecha</th><th>Descripción</th><th>Cuenta</th>
                    <th class="eb-num">Valor</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($salidasSueltas as $s)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($s->fecha)->format('d/m/Y') }}</td>
                        <td>
                            {{ $s->descripcion ?: '—' }}
                            @if ($s->canal)
                                <div class="eb-mini">{{ $s->canal }}</div>
                            @endif
                        </td>
                        <td class="eb-mini">{{ $s->banco }}</td>
                        <td class="eb-num" style="color:#b91c1c">-{{ $fmt($s->valor) }}</td>
                        <td style="white-space:nowrap">
                            <button class="eb-accion" @click="abrirGasto({{ $s->id }}, '{{ $fmt($s->valor) }}', @js($s->descripcion))">
                                <i class="fas fa-plus"></i> Registrar gasto
                            </button>
                            <form method="POST" action="{{ route('admin.informes.extracto_banco.personal', $s->id) }}" style="display:inline"
                                  onsubmit="return confirm('¿Marcar como movimiento personal? Sale del cuadre del negocio.')">
                                @csrf
                                <button class="eb-accion"><i class="fas fa-user"></i> Es personal</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="eb-vacia">
                        <i class="fas fa-circle-check"></i>
                        Toda la plata que salió tiene su gasto registrado.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="eb-nota" style="margin:0;border-radius:0">
            Cada una de estas salidas debería tener un gasto que la explique: un pago de
            planilla, un proveedor o un traslado a otra cuenta propia. Registrarlas es lo
            que hace que el saldo del libro vuelva a coincidir con el del banco.
        </div>
    </div>

    {{-- ── Lo que cobró o abonó el banco ───────────────────────────── --}}
    <div class="eb-card" x-show="tab === 'banco'" x-cloak>
        <table class="eb-tabla">
            <thead>
                <tr><th>Fecha</th><th>Concepto</th><th>Cuenta</th><th class="eb-num">Valor</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($cobrosBanco as $b)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($b->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $b->descripcion ?: '—' }}</td>
                        <td class="eb-mini">
                            {{ $b->banco }}
                            @if(($b->clasificacion ?? '') === 'personal')
                                <div style="color:#7c3aed;font-weight:700">👤 personal</div>
                            @endif
                        </td>
                        <td class="eb-num" style="color:{{ $b->tipo === 'debito' ? '#b91c1c' : '#15803d' }}">
                            {{ $b->tipo === 'debito' ? '-' : '+' }}{{ $fmt($b->valor) }}
                        </td>
                        <td style="white-space:nowrap">
                            @if ($b->tipo === 'debito')
                                <button class="eb-accion" @click="abrirGasto({{ $b->id }}, '{{ $fmt($b->valor) }}', @js($b->descripcion))">
                                    <i class="fas fa-plus"></i> Registrar gasto
                                </button>
                            @else
                                <button class="eb-accion" @click="abrirEntrada({{ $b->id }}, '{{ $fmt($b->valor) }}', @js($b->descripcion))">
                                    <i class="fas fa-plus"></i> Registrar entrada
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="eb-vacia">
                        <i class="fas fa-building-columns"></i>
                        El banco no cobró nada en este rango.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="eb-nota" style="margin:0;border-radius:0">
            El 4x1000, la cuota de manejo y los intereses son plata que se movió de verdad.
            Mientras no queden registrados en BryNex, el saldo del libro no va a coincidir
            con el del banco: el botón crea el gasto o la entrada y deja el movimiento cuadrado.
        </div>
    </div>

    {{-- ── Lo que ya cuadró ────────────────────────────────────────── --}}
    <div class="eb-card" x-show="tab === 'cruzados'" x-cloak>
        <table class="eb-tabla">
            <thead>
                <tr>
                    <th>Fecha</th><th>Movimiento</th><th>Consignación</th>
                    <th>Regla</th><th class="eb-num">Aplicado</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cruzados as $x)
                    @php $r = $reglas[$x->regla] ?? [$x->regla, '#64748b']; @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($x->fecha)->format('d/m/Y') }}</td>
                        <td>
                            {{ $x->descripcion ?: 'movimiento ' . $x->movimiento_id }}
                            <div class="eb-mini">#{{ $x->movimiento_id }} · {{ $fmt($x->valor) }}</div>
                        </td>
                        <td>
                            {{ $x->numero_factura ? 'factura ' . $x->numero_factura : 'consignación ' . $x->consignacion_id }}
                            <div class="eb-mini">{{ $x->referencia ?: 'sin comprobante' }}</div>
                        </td>
                        <td>
                            <span class="eb-regla" style="background:{{ $r[1] }}">{{ $r[0] }}</span>
                            @if ($x->dias_diferencia > 0)
                                <div class="eb-mini">{{ $x->dias_diferencia }} día(s) de diferencia</div>
                            @endif
                        </td>
                        <td class="eb-num">{{ $fmt($x->valor_aplicado) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.informes.extracto_banco.desvincular', $x->pivote_id) }}"
                                  onsubmit="return confirm('¿Deshacer este cruce? La consignación vuelve a quedar pendiente.')">
                                @csrf
                                <button class="eb-accion eb-accion--rojo"><i class="fas fa-link-slash"></i> Deshacer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="eb-vacia">
                        <i class="fas fa-arrows-left-right"></i>
                        Todavía no hay cruces en este rango. Traiga el extracto y presione «Cruzar».
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Modal: registrar el gasto que falta ─────────────────────── --}}
    <div class="eb-modal-bg" x-show="modalGasto" x-cloak @click.self="modalGasto = false">
        <form class="eb-modal" method="POST" :action="urlGasto">
            @csrf
            <div class="eb-modal__head">
                <h3>Registrar el gasto de <span x-text="movValor"></span></h3>
                <button type="button" class="eb-modal__close" @click="modalGasto = false">&times;</button>
            </div>
            <div class="eb-modal__body">
                <p class="eb-mini" style="margin:0 0 .8rem">
                    En el extracto: <b x-text="movDesc"></b>
                </p>

                <label class="eb-mini" style="display:block;margin-bottom:.2rem">Tipo de gasto</label>
                <select name="tipo" class="eb-input" x-model="tipoGasto" required>
                    @foreach ($tiposGasto as $clave => $etiqueta)
                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>

                <label class="eb-mini" style="display:block;margin:.7rem 0 .2rem">Descripción</label>
                <input type="text" name="descripcion" class="eb-input" x-model="descGasto" maxlength="255" required>

                <label class="eb-mini" style="display:block;margin:.7rem 0 .2rem">Pagado a (opcional)</label>
                <input type="text" name="pagado_a" class="eb-input" maxlength="255">

                <div class="eb-nota">
                    Se crea un gasto nuevo con la fecha y el valor del extracto, pagado desde
                    esta cuenta. No se modifica ningún gasto existente, y el saldo del libro
                    baja en ese valor — que es justo lo que falta para que cuadre con el banco.
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:.6rem;padding:.9rem 1.2rem;border-top:1px solid #e5e7eb">
                <button type="button" class="eb-accion" @click="modalGasto = false">Cancelar</button>
                <button class="eb-btn">Crear gasto</button>
            </div>
        </form>
    </div>

    {{-- ── Modal: registrar la entrada que falta ───────────────────── --}}
    <div class="eb-modal-bg" x-show="modalEntrada" x-cloak @click.self="modalEntrada = false">
        <form class="eb-modal" method="POST" :action="urlEntrada">
            @csrf
            <div class="eb-modal__head">
                <h3>Registrar la entrada de <span x-text="movValor"></span></h3>
                <button type="button" class="eb-modal__close" @click="modalEntrada = false">&times;</button>
            </div>
            <div class="eb-modal__body">
                <p class="eb-mini" style="margin:0 0 .8rem">
                    En el extracto: <b x-text="movDesc"></b>
                </p>

                <label class="eb-mini" style="display:block;margin-bottom:.2rem">Qué fue</label>
                <select name="tipo" class="eb-input" required>
                    <option value="banco_recibido">Transferencia recibida de otra cuenta</option>
                    <option value="traslado_efectivo">Traslado de efectivo a la cuenta</option>
                    <option value="cliente">Pago de cliente (sin factura asociada)</option>
                </select>

                <label class="eb-mini" style="display:block;margin:.7rem 0 .2rem">Observación</label>
                <input type="text" name="observacion" class="eb-input" x-model="descGasto" maxlength="500" required>

                <div class="eb-nota">
                    Queda confirmada de una: viene del extracto, o sea que el banco ya la
                    reportó. Si en realidad es el pago de una factura, es mejor cerrar esto y
                    usar «Vincular» para amarrarla a su consignación.
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:.6rem;padding:.9rem 1.2rem;border-top:1px solid #e5e7eb">
                <button type="button" class="eb-accion" @click="modalEntrada = false">Cancelar</button>
                <button class="eb-btn">Crear entrada</button>
            </div>
        </form>
    </div>

    {{-- ── Modal: vincular a mano ──────────────────────────────────── --}}
    <div class="eb-modal-bg" x-show="modal" x-cloak @click.self="modal = false">
        <div class="eb-modal">
            <div class="eb-modal__head">
                <h3>Vincular la entrada del <span x-text="movFecha"></span> por <span x-text="movValor"></span></h3>
                <button class="eb-modal__close" @click="modal = false">&times;</button>
            </div>
            <div class="eb-modal__body">
                <input type="text" class="eb-input" x-model="busqueda" @input.debounce.400ms="buscar()"
                       placeholder="Cédula, empresa, número de factura o comprobante…">

                <p class="eb-mini" style="margin:.5rem 0 0">
                    Sin escribir nada, se muestran las consignaciones de la misma cuenta con el mismo
                    valor y fecha cercana.
                </p>

                <template x-if="cargando">
                    <p class="eb-mini" style="margin-top:.8rem"><i class="fas fa-spinner fa-spin"></i> Buscando…</p>
                </template>

                <template x-if="!cargando && candidatos.length === 0">
                    <p class="eb-mini" style="margin-top:.8rem">Sin consignaciones libres que coincidan.</p>
                </template>

                <template x-for="c in candidatos" :key="c.id">
                    <div class="eb-cand">
                        <div>
                            <div style="font-weight:600" x-text="c.titular || ('consignación ' + c.id)"></div>
                            <div class="eb-mini">
                                <span x-text="c.fecha"></span>
                                · <span x-text="c.numero_factura ? ('factura ' + c.numero_factura) : 'sin factura'"></span>
                                · <span x-text="c.referencia || 'sin comprobante'"></span>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.6rem">
                            <span class="eb-num" x-text="formato(c.valor)"></span>
                            <form method="POST" :action="urlVincular" @submit="modal = false">
                                @csrf
                                <input type="hidden" name="consignacion_id" :value="c.id">
                                <button class="eb-btn" style="padding:.25rem .7rem">Vincular</button>
                            </form>
                        </div>
                    </div>
                </template>

                <div class="eb-nota">
                    Se aplica solo lo que quepa: si la consignación ya estaba cubierta en parte,
                    entra el saldo. Así se puede armar a mano un pago que llegó partido.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function extractoBanco() {
        return {
            tab: 'entradas',
            modal: false,
            modalGasto: false,
            modalEntrada: false,
            movDesc: '',
            tipoGasto: 'otros',
            descGasto: '',
            movId: null,
            movValor: '',
            movFecha: '',
            busqueda: '',
            candidatos: [],
            cargando: false,

            get urlVincular() {
                return this.base + '/vincular';
            },
            get urlGasto() {
                return this.base + '/registrar-gasto';
            },
            get urlEntrada() {
                return this.base + '/registrar-entrada';
            },
            get base() {
                return '{{ url('admin/informes/extracto-banco/movimiento') }}/' + this.movId;
            },

            // El tipo se sugiere leyendo la descripción del banco: casi siempre
            // acierta y ahorra el clic, pero queda editable.
            sugerirTipo(desc) {
                const d = (desc || '').toUpperCase();
                if (/4X1000|GMF|IVA|CUOTA DE MANEJO|C MANEJO|SERVICIO|COMISION/.test(d)) return 'servicios';
                if (/ENLACE|SIMPLE OI|COMPENSAR|ASOPAGOS|PLANILLA|SOI/.test(d)) return 'pago_planilla';
                if (/TRANSFERENCIA/.test(d)) return 'banco_banco';
                return 'otros';
            },

            abrirGasto(id, valor, desc) {
                this.movId = id;
                this.movValor = valor;
                this.movDesc = desc || 'sin descripción';
                this.descGasto = desc || 'Movimiento del extracto';
                this.tipoGasto = this.sugerirTipo(desc);
                this.modalGasto = true;
            },

            abrirEntrada(id, valor, desc) {
                this.movId = id;
                this.movValor = valor;
                this.movDesc = desc || 'sin descripción';
                this.descGasto = desc || 'Entrada del extracto';
                this.modalEntrada = true;
            },

            abrirVinculo(id, valor, fecha) {
                this.movId = id;
                this.movValor = valor;
                this.movFecha = fecha;
                this.busqueda = '';
                this.candidatos = [];
                this.modal = true;
                this.buscar();
            },

            buscar() {
                this.cargando = true;
                const url = '{{ route('admin.informes.extracto_banco.consignaciones') }}'
                    + '?movimiento_id=' + this.movId
                    + '&q=' + encodeURIComponent(this.busqueda);

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(d => { this.candidatos = d.consignaciones || []; })
                    .catch(() => { this.candidatos = []; })
                    .finally(() => { this.cargando = false; });
            },

            formato(v) {
                return '$' + new Intl.NumberFormat('es-CO').format(Math.round(v));
            },
        };
    }
</script>
@endsection
