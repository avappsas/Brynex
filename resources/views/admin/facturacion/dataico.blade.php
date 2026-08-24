@extends('layouts.app')
@section('modulo', 'Dataico')

@section('contenido')
<style>
.dt-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #0e4d2f 100%);
    padding: 1.4rem 1.8rem; border-radius: 14px; color: #fff; margin-bottom: 1.2rem;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
}
.dt-title { font-size: 1.45rem; font-weight: 800; letter-spacing: .02em; }
.dt-sub   { font-size: .8rem; color: #94a3b8; margin-top: .2rem; }
.stat-chips { display: flex; gap: .7rem; flex-wrap: wrap; }
.stat-chip {
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15);
    border-radius: 8px; padding: .35rem .8rem; font-size: .75rem; color: #e2e8f0;
    display: flex; align-items: center; gap: .4rem;
}
.stat-chip strong { color: #fff; font-size: .88rem; }

.card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 1.1rem 1.3rem; margin-bottom: 1rem;
}
.card h3 { font-size: .95rem; font-weight: 800; color: #0f172a; margin: 0 0 .9rem; }
.grid-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .8rem; }
.fld { display: flex; flex-direction: column; gap: .25rem; }
.fld label { font-size: .7rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .04em; }
.fld input, .fld select, .fld textarea {
    border: 1.5px solid #e2e8f0; border-radius: 8px; padding: .45rem .75rem;
    font-size: .85rem; color: #1e293b; outline: none; background: #fff; transition: border .15s;
}
.fld input:focus, .fld select:focus, .fld textarea:focus { border-color: #2563eb; }
.fld .hint { font-size: .68rem; color: #94a3b8; }

.btn {
    border: 0; border-radius: 8px; padding: .5rem 1rem; font-size: .82rem;
    font-weight: 700; cursor: pointer; color: #fff; background: #2563eb;
}
.btn.gris   { background: #64748b; }
.btn.verde  { background: #059669; }
.btn.ambar  { background: #d97706; }
.btn:disabled { opacity: .5; cursor: not-allowed; }

.aviso { border-radius: 8px; padding: .75rem 1rem; margin-bottom: .8rem; font-size: .85rem; }
.aviso.err  { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.aviso.ok   { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.aviso.warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

table.dt { width: 100%; border-collapse: collapse; font-size: .82rem; }
table.dt th {
    background: #f8fafc; text-align: left; padding: .55rem .7rem; font-size: .7rem;
    text-transform: uppercase; letter-spacing: .04em; color: #475569; border-bottom: 2px solid #e2e8f0;
}
table.dt td { padding: .5rem .7rem; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
table.dt tbody tr:hover { background: #f8fafc; }
.pill { border-radius: 999px; padding: .12rem .55rem; font-size: .68rem; font-weight: 700; white-space: nowrap; }
.pill.enviado   { background: #dcfce7; color: #166534; }
.pill.error     { background: #fee2e2; color: #991b1b; }
.pill.pendiente { background: #e0e7ff; color: #3730a3; }
.pill.enviando  { background: #fef3c7; color: #92400e; }
.pill.omitido   { background: #f1f5f9; color: #475569; }
.msg-err { color: #b91c1c; font-size: .72rem; max-width: 420px; display: block; }
.tabs { display: flex; gap: .4rem; margin-bottom: .8rem; flex-wrap: wrap; }
.tab {
    padding: .35rem .8rem; border-radius: 999px; font-size: .76rem; font-weight: 700;
    background: #f1f5f9; color: #475569; text-decoration: none;
}
.tab.on { background: #1e3a5f; color: #fff; }
pre.json {
    background: #0f172a; color: #e2e8f0; border-radius: 10px; padding: 1rem;
    font-size: .72rem; overflow: auto; max-height: 460px; margin: .8rem 0 0;
}
.vacio { text-align: center; padding: 3rem 1rem; color: #94a3b8; font-size: .88rem; }
</style>

@php
    $completa = $cfg && $cfg->estaCompleta();
@endphp

<div class="dt-header">
    <div>
        <div class="dt-title">🔗 Dataico — Facturación electrónica por API</div>
        <div class="dt-sub">Se emite lo que entra por la cuenta de la razón social emisora. Solo administración y afiliación.</div>
    </div>
    <div class="stat-chips">
        <div class="stat-chip">📤 Por emitir <strong>{{ number_format($porEmitir) }}</strong></div>
        <div class="stat-chip">💰 Base <strong>${{ number_format($porEmitirValor) }}</strong></div>
        <div class="stat-chip">✅ Emitidas <strong>{{ number_format($resumen['enviado'] ?? 0) }}</strong></div>
        <div class="stat-chip">⚠️ Con error <strong>{{ number_format($resumen['error'] ?? 0) }}</strong></div>
    </div>
</div>

@if(session('success'))<div class="aviso ok">✅ {{ session('success') }}</div>@endif
@if(session('error'))<div class="aviso err">⚠️ {{ session('error') }}</div>@endif

@if(!$cfg)
    <div class="aviso warn">
        Todavía no hay configuración de Dataico para este aliado. Llena el formulario de abajo y déjala
        <strong>inactiva</strong> hasta que hayas revisado el JSON con «Simular».
    </div>
@elseif(!$completa)
    <div class="aviso warn">
        La configuración está incompleta: faltan el <strong>ID de cuenta</strong> o el <strong>token</strong> de Dataico.
        Mientras tanto no se emite nada, aunque esté marcada como activa.
    </div>
@elseif(!$cfg->activo)
    <div class="aviso warn">
        Configuración completa pero <strong>desactivada</strong>. Nada se está enviando a la DIAN.
    </div>
@else
    <div class="aviso ok">
        Activa en modo <strong>{{ \App\Models\DataicoConfiguracion::MODOS[$cfg->modo] ?? $cfg->modo }}</strong>@if($cfg->modo === 'diario') a las {{ $cfg->hora_cierre }}@endif,
        emitiendo desde el {{ $cfg->fecha_inicio?->format('d/m/Y') }}.
        @if($sinCorreo > 0)
            De lo pendiente, {{ $sinCorreo }} sin correo: se emiten igual, sin envío.
        @endif
    </div>
@endif

@if($sinDocumento->isNotEmpty())
    <div class="aviso warn">
        <strong>{{ $sinDocumento->count() }} factura(s) retenidas por falta de documento del adquiriente.</strong>
        Son empresas creadas solo con el nombre del empleador, sin cédula ni NIT. No se emiten a consumidor final
        a propósito: de las 1.128 facturas que BRYGAR ya tiene ante la DIAN, ninguna usa esa figura.
        Llénales el NIT en el módulo de Empresas y entran solas en la siguiente corrida.
        <ul style="margin:.4rem 0 0 1.1rem;">
            @foreach($sinDocumento as $d)
                <li>N° <strong>{{ $d->numero_factura }}</strong> —
                    {{ $d->adquiriente['nombre_completo'] }},
                    ${{ number_format((float) $d->base_admon) }},
                    {{ $d->num_clientes }} afiliado(s)</li>
            @endforeach
        </ul>
    </div>
@endif

@if($ambiguos->isNotEmpty())
    <div class="aviso err">
        <strong>{{ $ambiguos->count() }} factura(s) no se van a emitir</strong> porque un mismo número agrupa
        filas de empresas distintas, y la factura de un lote sale a nombre de una sola empresa.
        Emitirlas cargaría a esa empresa plata que no es suya. Corrígelas en Facturación o emítelas a mano:
        <ul style="margin:.4rem 0 0 1.1rem;">
            @foreach($ambiguos as $a)
                <li>N° <strong>{{ $a->numero_factura }}</strong> — {{ $a->filas }} filas,
                    {{ $a->adquirientes }} adquirientes, ${{ number_format((float)$a->base_admon) }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ── Configuración ──────────────────────────────────────────────────────── --}}
<div class="card" x-data="{ abierto: {{ $completa ? 'false' : 'true' }}, json: null, cargando: false }">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;">⚙️ Configuración de la conexión</h3>
        <div style="display:flex;gap:.5rem;">
            <button type="button" class="btn gris" @click="abierto = !abierto" x-text="abierto ? 'Ocultar' : 'Mostrar'"></button>
            <button type="button" class="btn ambar" :disabled="cargando" @click="
                cargando = true; json = null;
                fetch('{{ route('admin.facturacion.dataico.simular') }}')
                    .then(r => r.json())
                    .then(d => json = JSON.stringify(d, null, 2))
                    .catch(e => json = 'No se pudo simular: ' + e)
                    .finally(() => cargando = false)">
                🧪 Simular una factura
            </button>
        </div>
    </div>

    <pre class="json" x-show="json" x-text="json" x-cloak></pre>

    <form method="POST" action="{{ route('admin.facturacion.dataico.configuracion') }}" x-show="abierto" x-cloak style="margin-top:1rem;">
        @csrf
        <div class="grid-form">
            <div class="fld">
                <label>Razón social emisora</label>
                <select name="razon_social_id" required>
                    <option value="">— Elegir —</option>
                    @foreach($razonesSociales as $rs)
                        <option value="{{ $rs->id }}" @selected($cfg && (int)$cfg->razon_social_id === (int)$rs->id)>
                            {{ $rs->razon_social }}{{ $rs->nit ? " — {$rs->nit}" . ($rs->dv !== null ? "-{$rs->dv}" : '') : '' }}
                        </option>
                    @endforeach
                </select>
                <span class="hint">La que tiene la resolución DIAN.</span>
            </div>

            <div class="fld">
                <label>Cuenta bancaria</label>
                <select name="banco_cuenta_id" required>
                    <option value="">— Elegir —</option>
                    @foreach($cuentas as $c)
                        <option value="{{ $c->id }}" @selected($cfg && (int)$cfg->banco_cuenta_id === (int)$c->id)>
                            {{ $c->banco }} — {{ $c->nombre }} ({{ $c->numero_cuenta }})
                        </option>
                    @endforeach
                </select>
                <span class="hint">Solo se factura lo consignado aquí. El efectivo queda por fuera.</span>
            </div>

            <div class="fld">
                <label>Emitir desde</label>
                <input type="date" name="fecha_inicio" required
                       value="{{ old('fecha_inicio', $cfg?->fecha_inicio?->format('Y-m-d')) }}">
                <span class="hint">Nada con fecha de pago anterior se envía.</span>
            </div>

            <div class="fld">
                <label>Cuándo emite</label>
                <select name="modo" required>
                    @foreach(\App\Models\DataicoConfiguracion::MODOS as $k => $v)
                        <option value="{{ $k }}" @selected($cfg && $cfg->modo === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div class="fld">
                <label>Hora de cierre</label>
                <input type="time" name="hora_cierre" value="{{ old('hora_cierre', $cfg->hora_cierre ?? '20:00') }}" required>
                <span class="hint">Solo aplica en modo «al cerrar el día».</span>
            </div>

            <div class="fld">
                <label>ID de cuenta Dataico</label>
                <input type="text" name="dataico_account_id" autocomplete="off"
                       value="{{ old('dataico_account_id', $cfg?->dataico_account_id) }}">
            </div>

            <div class="fld">
                <label>Auth-token</label>
                <input type="password" name="auth_token" autocomplete="new-password"
                       placeholder="{{ $cfg && $cfg->auth_token ? '•••••• (guardado)' : 'Pegar el token' }}">
                <span class="hint">Se guarda cifrado y nunca se muestra de vuelta. En blanco = no cambiarlo.</span>
            </div>

            <div class="fld">
                <label>Numbering range ID</label>
                <input type="text" name="numbering_range_id" autocomplete="off"
                       value="{{ old('numbering_range_id', $cfg?->numbering_range_id) }}">
                <span class="hint">Rango DIAN con el que Dataico numera.</span>
            </div>

            <div class="fld">
                <label>Prefijo</label>
                <input type="text" name="prefijo" value="{{ old('prefijo', $cfg?->prefijo) }}">
            </div>

            <div class="fld">
                <label>Resolución</label>
                <input type="text" name="resolucion" value="{{ old('resolucion', $cfg?->resolucion) }}">
            </div>

            <div class="fld">
                <label>Correo de respaldo</label>
                <input type="email" name="correo_fallback" value="{{ old('correo_fallback', $cfg?->correo_fallback) }}">
                <span class="hint">A dónde llega la factura cuando el cliente no tiene correo.</span>
            </div>

            <div class="fld">
                <label>Observación</label>
                <input type="text" name="observacion" value="{{ old('observacion', $cfg?->observacion) }}">
            </div>
        </div>

        <div style="display:flex;gap:1.2rem;align-items:center;margin-top:1rem;flex-wrap:wrap;">
            <label style="display:flex;gap:.4rem;align-items:center;font-size:.82rem;color:#334155;">
                <input type="checkbox" name="enviar_email" value="1" @checked($cfg?->enviar_email ?? true)>
                Enviar la representación gráfica por correo
            </label>
            <label style="display:flex;gap:.4rem;align-items:center;font-size:.82rem;color:#334155;">
                <input type="checkbox" name="consumidor_final" value="1" @checked($cfg?->consumidor_final ?? false)>
                Emitir a consumidor final cuando el adquiriente no tenga documento
            </label>
            <label style="display:flex;gap:.4rem;align-items:center;font-size:.82rem;color:#334155;font-weight:700;">
                <input type="checkbox" name="activo" value="1" @checked($cfg?->activo ?? false)>
                Activar la emisión automática
            </label>
            <button type="submit" class="btn verde">💾 Guardar</button>
        </div>
    </form>
</div>

{{-- ── Envíos ─────────────────────────────────────────────────────────────── --}}
<div class="card">
    <h3>📨 Envíos a Dataico</h3>

    <div class="tabs">
        @foreach(['todos' => 'Todos'] + \App\Models\DataicoEnvio::ESTADOS as $k => $v)
            <a class="tab {{ $estado === $k ? 'on' : '' }}"
               href="{{ route('admin.facturacion.dataico.index', ['estado' => $k]) }}">
                {{ $v }}@if($k !== 'todos') ({{ $resumen[$k] ?? 0 }})@endif
            </a>
        @endforeach
    </div>

    @if($envios->isEmpty())
        <div class="vacio">Todavía no hay envíos registrados con este filtro.</div>
    @else
    <form method="POST" action="{{ route('admin.facturacion.dataico.reintentar') }}">
        @csrf
        <div style="overflow-x:auto;">
        <table class="dt">
            <thead>
                <tr>
                    <th style="width:32px;"></th>
                    <th>Factura</th>
                    <th>Adquiriente</th>
                    <th>Documento</th>
                    <th style="text-align:right;">Base</th>
                    <th>Estado</th>
                    <th>N° DIAN</th>
                    <th>Enviada</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
            @foreach($envios as $e)
                <tr>
                    <td>
                        @if($e->estado === \App\Models\DataicoEnvio::ESTADO_ERROR)
                            <input type="checkbox" name="numeros_factura[]" value="{{ $e->numero_factura }}">
                        @endif
                    </td>
                    <td><strong>{{ $e->numero_factura }}</strong></td>
                    <td>{{ $e->cliente_nombre ?: '—' }}</td>
                    <td>
                        {{ $e->cliente_identificacion ?: '—' }}
                        @if($e->es_consumidor_final)
                            <span class="pill omitido">consumidor final</span>
                        @endif
                    </td>
                    <td style="text-align:right;">${{ number_format((float)$e->base_admon) }}</td>
                    <td><span class="pill {{ $e->estado }}">{{ \App\Models\DataicoEnvio::ESTADOS[$e->estado] ?? $e->estado }}</span></td>
                    <td>{{ $e->dataico_numero ?: '—' }}</td>
                    <td>{{ $e->enviado_at?->format('d/m/Y H:i') ?: '—' }}</td>
                    <td>
                        @if($e->error_mensaje)
                            <span class="msg-err">{{ $e->error_mensaje }}</span>
                            @if($e->intentos > 1)<span class="hint">({{ $e->intentos }} intentos)</span>@endif
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>

        <div style="margin-top:.9rem;">
            <button type="submit" class="btn">🔁 Reintentar seleccionadas</button>
        </div>
    </form>

    <div style="margin-top:1rem;">{{ $envios->links() }}</div>
    @endif
</div>
@endsection
