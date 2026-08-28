@extends('layouts.app')
@section('modulo', 'Razón Social · ' . $ficha->razon_social)
@section('contenido')

@php
    $meses = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
    $colorSemaforo = ['verde'=>'#047857','amarillo'=>'#b45309','rojo'=>'#b91c1c','gris'=>'#94a3b8'];
    $iconoSemaforo = ['verde'=>'🟢','amarillo'=>'🟡','rojo'=>'🔴','gris'=>'⚪'];
@endphp

<div style="max-width:1500px;margin:0 auto;"
     x-data="{
        pestana: '{{ request('t', 'checklist') }}',
        // Los modales arrancan con un objeto vacío, no con null: `x-show` no
        // desmonta el nodo, así que el x-model de adentro se evalúa igual y
        // contra null tira 'Cannot read properties of null'.
        verEditar: false,
        editar: { id: null, nombre: '', periodo: '', estado: 'pendiente', valor_pagado: '', fecha_pago: '', observacion: '' },
        verClave: false,
        clave: { credencial_id: '', tipo: 'DIAN', entidad: '', usuario: '', link_acceso: '', observacion: '' },
        revelada: {},
        async revelar(id) {
            const r = await fetch('{{ url('brynex/razones-sociales/claves') }}/' + id + '/revelar');
            const d = await r.json();
            if (!r.ok) { alert(d.error || 'No se pudo revelar la clave.'); return; }
            this.revelada[id] = d.contrasena;
        }
     }">

    {{-- ── Cabecera ────────────────────────────────────────────────── --}}
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.2rem;margin-bottom:1rem;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
            <div>
                <a href="{{ route('brynex.razones.index') }}" style="color:#64748b;font-size:0.78rem;text-decoration:none;">← Todas las razones sociales</a>
                <h1 style="font-size:1.4rem;font-weight:800;color:#0d2550;margin:0.3rem 0 0 0;">
                    {{ $ficha->razon_social }}
                    @if($ficha->propiedad === 'brynex')
                        <span style="background:#ede9fe;color:#6d28d9;font-size:0.7rem;font-weight:700;padding:0.15rem 0.5rem;border-radius:20px;vertical-align:middle;">PROPIA DE BRYNEX</span>
                    @endif
                </h1>
                <div style="color:#64748b;font-size:0.83rem;margin-top:0.25rem;">
                    NIT {{ $ficha->nitFormateado() }} ·
                    {{ $ficha->regimen === 'RST' ? 'Régimen Simple' : 'Régimen Ordinario' }} ·
                    {{ \App\Models\BrynexRazonSocial::PERIODICIDAD_IVA[$ficha->periodicidad_iva] ?? 'IVA sin definir' }}
                    @if($ficha->contador)
                        · Contador: {{ $ficha->contador->nombre }}
                    @endif
                </div>

                {{-- Las cifras van en una sola línea de chips y no en un bloque
                     aparte: eran tres números enormes que empujaban la tabla
                     media pantalla hacia abajo. El detalle de cada uno está en
                     su pestaña. --}}
                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-top:0.6rem;">
                    <span class="rs-chip" title="{{ $afiliados['por_aliado']->map(fn ($f) => $f->aliado.': '.$f->total)->implode(' · ') ?: 'Sin afiliados vigentes' }}">
                        👥 <b>{{ number_format($afiliados['total'], 0, ',', '.') }}</b> afiliados vigentes
                    </span>

                    <span class="rs-chip {{ $resumenChecklist['rojo'] ? 'alerta' : '' }}">
                        ✅ <b>{{ $resumenChecklist['verde'] }}/{{ $resumenChecklist['total'] }}</b> al día en {{ $anio }}
                        @if($resumenChecklist['rojo'])
                            · <b>{{ $resumenChecklist['rojo'] }}</b> vencida(s)
                        @endif
                        @if($resumenChecklist['amarillo'])
                            · {{ $resumenChecklist['amarillo'] }} por vencer
                        @endif
                    </span>

                    <span class="rs-chip" title="{{ $vinculos->pluck('aliado')->filter()->implode(' · ') }}">
                        🏢 <b>{{ $vinculos->count() }}</b> {{ $vinculos->count() === 1 ? 'aliado la usa' : 'aliados la usan' }}
                    </span>
                </div>
            </div>

            <div style="display:flex;gap:0.5rem;align-items:center;">
                @if($ficha->firma_electronica_vence)
                    @php $diasFirma = now()->startOfDay()->diffInDays($ficha->firma_electronica_vence, false); @endphp
                    <div style="background:{{ $diasFirma < 0 ? '#fef2f2' : ($diasFirma <= 60 ? '#fffbeb' : '#f0fdf4') }};border:1px solid {{ $diasFirma < 0 ? '#fecaca' : ($diasFirma <= 60 ? '#fde68a' : '#bbf7d0') }};padding:0.4rem 0.7rem;border-radius:8px;font-size:0.75rem;">
                        <div style="font-weight:700;color:#334155;">✍️ Firma electrónica</div>
                        <div style="color:{{ $diasFirma < 0 ? '#b91c1c' : '#475569' }};">
                            {{ $diasFirma < 0 ? 'Vencida hace ' . abs($diasFirma) . ' días' : 'Vence en ' . $diasFirma . ' días' }}
                        </div>
                    </div>
                @endif
                {{-- El año manda sobre el checklist y sobre los movimientos,
                     así que vive en la cabecera y no dentro de una pestaña.
                     `t` conserva la pestaña abierta al recargar. --}}
                <form method="GET" style="display:flex;align-items:center;gap:0.4rem;background:#f8fafc;border:1px solid #e2e8f0;padding:0.3rem 0.55rem;border-radius:9px;">
                    <input type="hidden" name="t" :value="pestana">
                    <span style="font-size:0.7rem;text-transform:uppercase;color:#64748b;font-weight:700;letter-spacing:0.03em;">Año</span>
                    <select name="anio" onchange="this.form.submit()"
                            style="border:none;background:transparent;font-size:0.95rem;font-weight:800;color:#0d2550;cursor:pointer;outline:none;">
                        @foreach($anios as $a)
                            <option value="{{ $a }}" @selected($anio == $a)>{{ $a }}</option>
                        @endforeach
                        @if(! $anios->contains($anio))
                            <option value="{{ $anio }}" selected>{{ $anio }}</option>
                        @endif
                    </select>
                </form>

                @can('brynex_razones.gestionar')
                    <button type="button" @click="pestana = 'datos'" style="background:#1e3a8a;color:#fff;border:none;padding:0.5rem 0.9rem;border-radius:8px;font-size:0.82rem;font-weight:600;cursor:pointer;">✏️ Editar ficha</button>
                @endcan
            </div>
        </div>

    </div>

    @include('brynex.razones_sociales._alertas')

    {{-- ── Pestañas ────────────────────────────────────────────────── --}}
    @php
        $pestanas = [
            'checklist' => ['✅', 'Checklist', $resumenChecklist['rojo'] ?: null, 'rojo'],
            'afiliados' => ['👥', 'Afiliados', $afiliados['total'] ?: null,       'neutro'],
            'dinero'    => ['💰', 'Movimientos', null,                            'neutro'],
            'claves'    => ['🔑', 'Claves',    $credenciales->count() ?: null,    'neutro'],
            'datos'     => ['⚙️', 'Datos',     null,                              'neutro'],
        ];
    @endphp

    {{-- Las pestañas y lo del checklist comparten fila: eran dos renglones
         completos para tres datos y un botón. --}}
    <div style="display:flex;justify-content:space-between;align-items:center;gap:0.8rem;flex-wrap:wrap;margin-bottom:1.2rem;">
    <div class="rs-tabs">
        @foreach($pestanas as $k => [$icono, $texto, $contador, $tono])
            <button type="button" class="rs-tab" @click="pestana = '{{ $k }}'"
                    :class="pestana === '{{ $k }}' ? 'activa' : ''">
                <span class="rs-tab-ico">{{ $icono }}</span>{{ $texto }}
                @if($contador)
                    <span class="rs-pill rs-pill-{{ $tono }}">{{ number_format($contador, 0, ',', '.') }}</span>
                @endif
            </button>
        @endforeach
    </div>

        {{-- Solo tiene sentido junto al checklist, así que se esconde con él.
             Ni lo vencido ni lo pagado se repiten aquí: lo vencido ya lo dice
             el contador rojo de la pestaña y lo pagado está en la columna de
             valores, fila por fila. --}}
        <div x-show="pestana === 'checklist'" x-cloak
             style="display:flex;gap:0.45rem;align-items:center;flex-wrap:wrap;">
            @can('brynex_razones.gestionar')
                <form method="POST" action="{{ route('brynex.razones.regenerar', $ficha->id) }}">
                    @csrf
                    <button type="submit" class="rs-chip" style="cursor:pointer;background:#fff;"
                            title="Crea los renglones que falten según el régimen y la fecha de constitución. No toca los que ya existen.">
                        🔄 Generar faltantes
                    </button>
                </form>
            @endcan
        </div>
    </div>

    {{-- ══ CHECKLIST ═══════════════════════════════════════════════ --}}
    <div x-show="pestana === 'checklist'" x-cloak>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.84rem;">
                    <thead>
                        <tr style="background:#f8fafc;color:#475569;font-size:0.7rem;text-transform:uppercase;text-align:left;">
                            <th style="padding:0.65rem 0.9rem;font-weight:700;"></th>
                            <th style="padding:0.65rem;font-weight:700;">Obligación</th>
                            <th style="padding:0.65rem;font-weight:700;">Período</th>
                            <th style="padding:0.65rem;font-weight:700;">Vence</th>
                            <th style="padding:0.65rem;font-weight:700;">Estado</th>
                            <th style="padding:0.65rem;font-weight:700;text-align:right;">Valor pagado</th>
                            <th style="padding:0.65rem;font-weight:700;">Soportes</th>
                            <th style="padding:0.65rem 0.9rem;font-weight:700;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($obligaciones as $o)
                        @php
                            $sem = $o->semaforo();
                            $cat = $catalogo[$o->obligacion_codigo] ?? null;
                            $dias = $o->diasParaVencer();
                        @endphp
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:0.6rem 0.9rem;font-size:1rem;" title="{{ $sem }}">{{ $iconoSemaforo[$sem] }}</td>
                            <td style="padding:0.6rem;font-weight:600;color:#0f172a;">
                                {{ $cat?->nombre ?? $o->obligacion_codigo }}
                                @if($cat?->formulario)
                                    <span style="color:#94a3b8;font-weight:400;font-size:0.75rem;">· form. {{ $cat->formulario }}</span>
                                @endif
                            </td>
                            <td style="padding:0.6rem;color:#475569;white-space:nowrap;">
                                {{ $o->periodo_etiqueta }}
                                {{-- `$mesesPeriodo` y no `$meses`: arriba ya hay un
                                     `$meses` con los nombres de los 12 meses que usa la
                                     pestaña de Movimientos. --}}
                                @php $mesesPeriodo = $cat?->mesesDelPeriodo($o->periodo); @endphp
                                @if($mesesPeriodo)
                                    <div style="font-size:0.7rem;color:#94a3b8;">{{ $mesesPeriodo }}</div>
                                @endif
                                {{-- El año gravable solo se dice cuando NO coincide con la
                                     pestaña: es el caso de la anual, que se presenta al año
                                     siguiente y si no se aclara parece un error. --}}
                                {{-- Con cast: sqlsrv devuelve `anio` como string y
                                     "2026" !== 2026 sería siempre verdadero. --}}
                                @if((int) $o->anio !== $anio)
                                    <div style="font-size:0.7rem;color:#b45309;font-weight:600;">año gravable {{ $o->anio }}</div>
                                @endif
                            </td>
                            <td style="padding:0.6rem;color:{{ $colorSemaforo[$sem] }};font-weight:600;white-space:nowrap;">
                                @if($o->fecha_vencimiento)
                                    {{ $o->fecha_vencimiento->format('d/m/Y') }}
                                    @if($dias !== null && ! in_array($o->estado, ['pagada','no_aplica']))
                                        <div style="font-size:0.68rem;font-weight:400;">
                                            {{ $dias < 0 ? 'hace ' . abs($dias) . ' días' : 'en ' . $dias . ' días' }}
                                        </div>
                                    @endif
                                @else
                                    <span style="color:#cbd5e1;font-weight:400;" title="Año sin calendario cargado">sin fecha</span>
                                @endif
                            </td>
                            <td style="padding:0.6rem;">
                                <span style="background:{{ $colorSemaforo[$sem] }}18;color:{{ $colorSemaforo[$sem] }};font-weight:700;font-size:0.72rem;padding:0.15rem 0.5rem;border-radius:20px;">
                                    {{ \App\Models\BrynexObligacion::ESTADOS[$o->estado] }}
                                </span>
                            </td>
                            <td style="padding:0.6rem;text-align:right;font-variant-numeric:tabular-nums;color:#0f172a;">
                                {{ $o->valor_pagado ? '$' . number_format($o->valor_pagado, 0, ',', '.') : '—' }}
                            </td>
                            <td style="padding:0.6rem;">
                                @forelse($o->documentos as $doc)
                                    <div style="display:flex;align-items:center;gap:0.3rem;">
                                        <a href="{{ route('brynex.razones.documentos.descargar', $doc->id) }}"
                                           style="color:#1e3a8a;font-size:0.75rem;text-decoration:none;" title="{{ $doc->tamanoLegible() }}">
                                            📎 {{ Str::limit($doc->nombre_original, 22) }}
                                        </a>
                                        @can('brynex_razones.gestionar')
                                            <form method="POST" action="{{ route('brynex.razones.documentos.destroy', $doc->id) }}"
                                                  onsubmit="return confirm('¿Eliminar este soporte?')" style="display:inline;">
                                                @csrf @method('DELETE')
                                                <button type="submit" style="background:none;border:none;color:#cbd5e1;cursor:pointer;font-size:0.7rem;padding:0;">✕</button>
                                            </form>
                                        @endcan
                                    </div>
                                @empty
                                    <span style="color:#cbd5e1;font-size:0.75rem;">—</span>
                                @endforelse
                            </td>
                            <td style="padding:0.6rem 0.9rem;text-align:right;white-space:nowrap;">
                                @can('brynex_razones.gestionar')
                                    <button type="button"
                                            @click="verEditar = true; editar = {{ Js::from([
                                                'id' => $o->id,
                                                'nombre' => $cat?->nombre ?? $o->obligacion_codigo,
                                                'periodo' => $o->periodo_etiqueta,
                                                'estado' => $o->estado,
                                                'valor_pagado' => $o->valor_pagado ? (float) $o->valor_pagado : '',
                                                'fecha_pago' => $o->fecha_pago?->toDateString(),
                                                'observacion' => $o->observacion,
                                            ]) }}"
                                            style="background:#1e3a8a;color:#fff;border:none;padding:0.28rem 0.65rem;border-radius:6px;font-size:0.74rem;font-weight:600;cursor:pointer;">
                                        Chulear
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="padding:2rem;text-align:center;color:#94a3b8;">
                            No hay obligaciones generadas para {{ $anio }}. Usa «Generar las que falten».
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p style="color:#94a3b8;font-size:0.75rem;margin-top:0.6rem;">
            ⚪ «Sin fecha» son los años anteriores al calendario DIAN cargado: el renglón existe para poder subir el
            soporte y ponerse al día, pero no entra al semáforo ni dispara alertas.
        </p>
    </div>

    {{-- ══ AFILIADOS ═══════════════════════════════════════════════ --}}
    <div x-show="pestana === 'afiliados'" x-cloak>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.2rem;">
            <h3 style="font-size:1rem;font-weight:800;color:#0d2550;margin:0 0 0.2rem 0;">Afiliados vigentes por aliado</h3>
            <p style="color:#64748b;font-size:0.8rem;margin:0 0 1rem 0;">
                Este es el número que no se ve desde el panel de ningún aliado: ante la ley todos están en la misma empresa.
            </p>

            <table style="width:100%;border-collapse:collapse;font-size:0.87rem;">
                <thead>
                    <tr style="background:#f8fafc;color:#475569;font-size:0.72rem;text-transform:uppercase;text-align:left;">
                        <th style="padding:0.6rem 0.8rem;font-weight:700;">Aliado</th>
                        <th style="padding:0.6rem;font-weight:700;text-align:right;">Afiliados vigentes</th>
                        <th style="padding:0.6rem;font-weight:700;text-align:right;">Participación</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($afiliados['por_aliado'] as $fila)
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:0.6rem 0.8rem;font-weight:600;color:#0f172a;">{{ $fila->aliado }}</td>
                        <td style="padding:0.6rem;text-align:right;font-weight:700;font-variant-numeric:tabular-nums;">{{ number_format($fila->total, 0, ',', '.') }}</td>
                        <td style="padding:0.6rem;text-align:right;color:#64748b;">
                            {{ $afiliados['total'] > 0 ? round($fila->total * 100 / $afiliados['total'], 1) : 0 }}%
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="padding:1.5rem;text-align:center;color:#94a3b8;">Sin afiliados vigentes.</td></tr>
                @endforelse
                </tbody>
                @if($afiliados['total'] > 0)
                    <tfoot>
                        <tr style="border-top:2px solid #e2e8f0;background:#f8fafc;">
                            <td style="padding:0.6rem 0.8rem;font-weight:800;color:#0d2550;">Total</td>
                            <td style="padding:0.6rem;text-align:right;font-weight:800;color:#0d2550;">{{ number_format($afiliados['total'], 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            <h4 style="font-size:0.85rem;font-weight:800;color:#334155;margin:1.5rem 0 0.5rem 0;">Filas de razón social enlazadas</h4>
            <p style="color:#94a3b8;font-size:0.75rem;margin:0 0 0.6rem 0;">
                El mismo NIT registrado por cada aliado. Se resincroniza solo al abrir la ficha.
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
                @foreach($vinculos as $v)
                    <span style="background:#f1f5f9;color:#334155;font-size:0.75rem;padding:0.25rem 0.6rem;border-radius:20px;">
                        {{ $v->aliado ?? 'Aliado ' . $v->aliado_id }} · id {{ $v->razon_social_id }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ══ DINERO ══════════════════════════════════════════════════ --}}
    <div x-show="pestana === 'dinero'" x-cloak>
        <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:0.75rem 0.9rem;border-radius:10px;font-size:0.8rem;margin-bottom:1rem;">
            ⚠️ <strong>Ojo con lo que este número no es.</strong> La mayor parte de lo que entra a estas cuentas es plata de
            los afiliados para pagar su seguridad social, no ingreso de la empresa. Sirve para conciliar contra el extracto
            y para saber cuánto se movió la cuenta, no como base gravable.
            <br>La base gravable es la columna <strong>Admón + afiliación</strong>: lo que la razón social cobra por su
            servicio y lo único que se le sube a Dataico.
            <br><strong>Facturado</strong> es la parte de esa base que ya tiene factura electrónica emitida en Dataico.
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.2rem;margin-bottom:1rem;">
            <h3 style="font-size:1rem;font-weight:800;color:#0d2550;margin:0 0 0.8rem 0;">Movimientos {{ $anio }}, mes a mes</h3>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.83rem;">
                    <thead>
                        <tr style="background:#f8fafc;color:#475569;font-size:0.7rem;text-transform:uppercase;text-align:right;">
                            <th style="padding:0.55rem 0.7rem;font-weight:700;text-align:left;">Mes</th>
                            <th style="padding:0.55rem;font-weight:700;">Entradas</th>
                            <th style="padding:0.55rem;font-weight:700;">#</th>
                            <th style="padding:0.55rem;font-weight:700;color:#0d2550;" title="Administración + afiliación de las facturas pagadas por esta cuenta. Es la base que se le sube a Dataico.">Admón + afiliación</th>
                            <th style="padding:0.55rem;font-weight:700;">#</th>
                            <th style="padding:0.55rem;font-weight:700;color:#5b21b6;" title="De esa base, lo que ya tiene factura electrónica emitida en Dataico.">Facturado</th>
                            <th style="padding:0.55rem;font-weight:700;">#</th>
                            <th style="padding:0.55rem;font-weight:700;">Salidas</th>
                            <th style="padding:0.55rem;font-weight:700;">#</th>
                            <th style="padding:0.55rem;font-weight:700;">Neto</th>
                            <th style="padding:0.55rem 0.7rem;font-weight:700;">Acumulado</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($movimientos['meses'] as $m => $d)
                        <tr style="border-top:1px solid #f1f5f9;text-align:right;{{ $d['entradas'] == 0 && $d['salidas'] == 0 ? 'opacity:0.45;' : '' }}">
                            <td style="padding:0.5rem 0.7rem;text-align:left;font-weight:600;color:#334155;">
{{ $meses[$m] }}</td>
                            <td style="padding:0.5rem;color:#047857;font-variant-numeric:tabular-nums;">{{ $d['entradas'] ? '$' . number_format($d['entradas'], 0, ',', '.') : '—' }}</td>
                            <td style="padding:0.5rem;color:#94a3b8;font-size:0.75rem;">{{ $d['n_entradas'] ?: '' }}</td>
                            <td style="padding:0.5rem;color:#0d2550;font-weight:700;font-variant-numeric:tabular-nums;">{{ $d['base'] ? '$' . number_format($d['base'], 0, ',', '.') : '—' }}</td>
                            <td style="padding:0.5rem;color:#94a3b8;font-size:0.75rem;">{{ $d['n_base'] ?: '' }}</td>
                            @php $falta = $d['base'] - $d['facturado']; @endphp
                            <td style="padding:0.5rem;color:#5b21b6;font-variant-numeric:tabular-nums;"
                                title="{{ $falta > 0 ? 'Faltan por emitir $' . number_format($falta, 0, ',', '.') : '' }}">
                                {{ $d['facturado'] ? '$' . number_format($d['facturado'], 0, ',', '.') : '—' }}
                                @if($d['base'] > 0)
                                    <span style="color:{{ $falta > 0 ? '#b45309' : '#047857' }};font-size:0.7rem;">
                                        {{ round($d['facturado'] / $d['base'] * 100) }}%
                                    </span>
                                @endif
                            </td>
                            <td style="padding:0.5rem;color:#94a3b8;font-size:0.75rem;">{{ $d['n_facturado'] ?: '' }}</td>
                            <td style="padding:0.5rem;color:#b91c1c;font-variant-numeric:tabular-nums;">{{ $d['salidas'] ? '$' . number_format($d['salidas'], 0, ',', '.') : '—' }}</td>
                            <td style="padding:0.5rem;color:#94a3b8;font-size:0.75rem;">{{ $d['n_salidas'] ?: '' }}</td>
                            @if($d['salidas_incompletas'] ?? false)
                                <td colspan="2" style="padding:0.5rem 0.7rem;color:#a16207;font-size:0.72rem;text-align:center;"
                                    title="Este mes no tiene un solo gasto atado a la cuenta: los gastos migrados llegaron sin decir de dónde salieron, así que el neto sería falso.">
                                    sin gastos atados
                                </td>
                            @else
                                <td style="padding:0.5rem;font-weight:600;color:{{ $d['neto'] >= 0 ? '#0f172a' : '#b91c1c' }};font-variant-numeric:tabular-nums;">
                                    {{ $d['neto'] ? '$' . number_format($d['neto'], 0, ',', '.') : '—' }}
                                </td>
                                <td style="padding:0.5rem 0.7rem;color:#475569;font-variant-numeric:tabular-nums;">${{ number_format($d['acumulado'], 0, ',', '.') }}</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid #e2e8f0;background:#f8fafc;text-align:right;font-weight:800;color:#0d2550;">
                            <td style="padding:0.6rem 0.7rem;text-align:left;">Total</td>
                            <td style="padding:0.6rem;color:#047857;">${{ number_format($movimientos['total_entradas'], 0, ',', '.') }}</td>
                            <td></td>
                            <td style="padding:0.6rem;color:#0d2550;">${{ number_format($movimientos['total_base'] ?? 0, 0, ',', '.') }}</td>
                            <td></td>
                            <td style="padding:0.6rem;color:#5b21b6;">${{ number_format($movimientos['total_facturado'] ?? 0, 0, ',', '.') }}</td>
                            <td></td>
                            <td style="padding:0.6rem;color:#b91c1c;">${{ number_format($movimientos['total_salidas'], 0, ',', '.') }}</td>
                            <td></td>
                            <td style="padding:0.6rem;" colspan="2">
                                @if($movimientos['neto_parcial'] ?? false)
                                    <span style="color:#a16207;font-size:0.72rem;font-weight:600;">Neto no comparable: hay meses sin gastos atados a la cuenta</span>
                                @else
                                    Neto ${{ number_format($movimientos['neto'], 0, ',', '.') }}
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.2rem;">
                <h3 style="font-size:0.95rem;font-weight:800;color:#0d2550;margin:0 0 0.7rem 0;">Quién consignó en {{ $anio }}</h3>
                <table style="width:100%;border-collapse:collapse;font-size:0.83rem;">
                    @forelse($movimientos['por_aliado'] as $fila)
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:0.5rem 0;font-weight:600;color:#334155;">{{ $fila->aliado }}</td>
                            <td style="padding:0.5rem 0;text-align:right;color:#94a3b8;font-size:0.75rem;">{{ number_format($fila->cuantas, 0, ',', '.') }} mov.</td>
                            <td style="padding:0.5rem 0;text-align:right;font-weight:700;color:#047857;font-variant-numeric:tabular-nums;">${{ number_format($fila->total, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td style="padding:1rem 0;color:#94a3b8;">Sin consignaciones registradas.</td></tr>
                    @endforelse
                </table>
            </div>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.2rem;">
                <h3 style="font-size:0.95rem;font-weight:800;color:#0d2550;margin:0 0 0.2rem 0;">Cuentas bancarias</h3>
                <p style="color:#94a3b8;font-size:0.73rem;margin:0 0 0.7rem 0;">Enlazadas por NIT contra las cuentas de los aliados.</p>
                @forelse($movimientos['cuentas'] as $c)
                    <div style="border-top:1px solid #f1f5f9;padding:0.5rem 0;font-size:0.82rem;">
                        <div style="font-weight:600;color:#334155;">{{ $c->banco }} · {{ $c->numero_cuenta }}</div>
                        <div style="color:#94a3b8;font-size:0.73rem;">{{ $c->nombre }} · NIT {{ $c->nit ?: '—' }}</div>
                    </div>
                @empty
                    <p style="color:#94a3b8;font-size:0.8rem;margin:0;">
                        Ninguna cuenta bancaria de los aliados tiene este NIT registrado. Los movimientos van a salir en cero
                        hasta que alguien corrija el NIT de la cuenta en la configuración del aliado.
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ CLAVES ══════════════════════════════════════════════════ --}}
    <div x-show="pestana === 'claves'" x-cloak>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:0.75rem 0.9rem;border-radius:10px;font-size:0.8rem;margin-bottom:1rem;">
            🔗 Estas claves son <strong>del NIT, no del aliado</strong>. Si un aliado la cambia, los demás que usan esta razón
            social ven el cambio al instante. Cada vez que alguien revela una contraseña queda registrado en la bitácora.
        </div>

        @can('brynex_razones.claves')
            <div style="margin-bottom:0.8rem;">
                <button type="button" @click="verClave = true; clave = { credencial_id: '', tipo: 'DIAN', entidad: '', usuario: '', link_acceso: '', observacion: '' }"
                        style="background:#047857;color:#fff;border:none;padding:0.45rem 0.9rem;border-radius:8px;font-size:0.82rem;font-weight:600;cursor:pointer;">
                    + Agregar clave
                </button>
            </div>
        @endcan

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                <thead>
                    <tr style="background:#f8fafc;color:#475569;font-size:0.7rem;text-transform:uppercase;text-align:left;">
                        <th style="padding:0.65rem 0.9rem;font-weight:700;">Portal</th>
                        <th style="padding:0.65rem;font-weight:700;">Usuario</th>
                        <th style="padding:0.65rem;font-weight:700;">Contraseña</th>
                        <th style="padding:0.65rem;font-weight:700;">Observación</th>
                        <th style="padding:0.65rem 0.9rem;font-weight:700;"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($credenciales as $c)
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:0.6rem 0.9rem;">
                            <div style="font-weight:600;color:#0f172a;">{{ $c->tipoEtiqueta() }}</div>
                            <div style="color:#64748b;font-size:0.78rem;">
                                {{ $c->entidad }}
                                @if($c->link_acceso)
                                    · <a href="{{ $c->link_acceso }}" target="_blank" rel="noopener noreferrer" style="color:#1e3a8a;">abrir ↗</a>
                                @endif
                            </div>
                        </td>
                        <td style="padding:0.6rem;color:#334155;font-family:ui-monospace,monospace;font-size:0.8rem;">{{ $c->usuario ?: '—' }}</td>
                        <td style="padding:0.6rem;">
                            <template x-if="revelada[{{ $c->id }}]">
                                <span style="font-family:ui-monospace,monospace;font-size:0.82rem;color:#0f172a;background:#fef3c7;padding:0.15rem 0.4rem;border-radius:4px;" x-text="revelada[{{ $c->id }}]"></span>
                            </template>
                            <template x-if="! revelada[{{ $c->id }}]">
                                <button type="button" @click="revelar({{ $c->id }})"
                                        style="background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;padding:0.2rem 0.6rem;border-radius:6px;font-size:0.75rem;cursor:pointer;">
                                    👁 Ver{{ $c->tipo === 'BANCO' ? ' (banco)' : '' }}
                                </button>
                            </template>
                        </td>
                        <td style="padding:0.6rem;color:#64748b;font-size:0.78rem;">{{ Str::limit($c->observacion, 50) ?: '—' }}</td>
                        <td style="padding:0.6rem 0.9rem;text-align:right;white-space:nowrap;">
                            @can('brynex_razones.claves')
                                <button type="button"
                                        @click="verClave = true; clave = {{ Js::from([
                                            'credencial_id' => $c->id,
                                            'tipo' => $c->tipo,
                                            'entidad' => $c->entidad,
                                            'usuario' => $c->usuario,
                                            'link_acceso' => $c->link_acceso,
                                            'observacion' => $c->observacion,
                                        ]) }}"
                                        style="background:none;border:none;color:#1e3a8a;cursor:pointer;font-size:0.78rem;font-weight:600;">Editar</button>
                                <form method="POST" action="{{ route('brynex.razones.claves.destroy', $c->id) }}"
                                      onsubmit="return confirm('¿Eliminar esta clave?')" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:none;border:none;color:#cbd5e1;cursor:pointer;font-size:0.78rem;">✕</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:2rem;text-align:center;color:#94a3b8;">
                        Todavía no hay claves registradas para esta razón social.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══ DATOS ═══════════════════════════════════════════════════ --}}
    <div x-show="pestana === 'datos'" x-cloak>
        <form method="POST" action="{{ route('brynex.razones.update', $ficha->id) }}"
              style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.3rem;max-width:900px;">
            @csrf @method('PUT')

            <h3 style="font-size:1rem;font-weight:800;color:#0d2550;margin:0 0 1rem 0;">Datos de la razón social</h3>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:0.9rem;">
                <label style="font-size:0.78rem;font-weight:700;color:#334155;grid-column:span 2;">
                    Razón social *
                    <input type="text" name="razon_social" required value="{{ old('razon_social', $ficha->razon_social) }}"
                           style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                </label>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                    Dígito de verificación
                    <input type="number" name="dv" min="0" max="9" value="{{ old('dv', $ficha->dv) }}"
                           style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                </label>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                    ¿De quién es? *
                    <select name="propiedad" required style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                        @foreach(\App\Models\BrynexRazonSocial::PROPIEDAD as $k => $v)
                            <option value="{{ $k }}" @selected(old('propiedad', $ficha->propiedad) === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </label>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                    Régimen tributario *
                    <select name="regimen" required style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                        @foreach(\App\Models\BrynexRazonSocial::REGIMENES as $k => $v)
                            <option value="{{ $k }}" @selected(old('regimen', $ficha->regimen) === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </label>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                    IVA *
                    <select name="periodicidad_iva" required style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                        @foreach(\App\Models\BrynexRazonSocial::PERIODICIDAD_IVA as $k => $v)
                            <option value="{{ $k }}" @selected(old('periodicidad_iva', $ficha->periodicidad_iva) === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </label>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                    Fecha de constitución *
                    <input type="date" name="fecha_constitucion" required max="{{ now()->toDateString() }}"
                           value="{{ old('fecha_constitucion', $ficha->fecha_constitucion?->toDateString()) }}"
                           style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                </label>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                    Vence la firma electrónica
                    <input type="date" name="firma_electronica_vence"
                           value="{{ old('firma_electronica_vence', $ficha->firma_electronica_vence?->toDateString()) }}"
                           style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                </label>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                    Municipio (ICA)
                    <input type="text" name="municipio_ica" value="{{ old('municipio_ica', $ficha->municipio_ica) }}"
                           style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                </label>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                    Periodicidad del ICA
                    <select name="periodicidad_ica" style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                        <option value="">— No se controla —</option>
                        <option value="bimestral" @selected(old('periodicidad_ica', $ficha->periodicidad_ica) === 'bimestral')>Bimestral</option>
                        <option value="anual"     @selected(old('periodicidad_ica', $ficha->periodicidad_ica) === 'anual')>Anual</option>
                    </select>
                </label>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                    Contador responsable
                    <select name="contador_id" style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                        <option value="">— Sin asignar —</option>
                        @foreach($contadores as $u)
                            <option value="{{ $u->id }}" @selected((string) old('contador_id', $ficha->contador_id) === (string) $u->id)>{{ $u->nombre }}</option>
                        @endforeach
                    </select>
                </label>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                    Estado de la ficha *
                    <select name="estado" required style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                        <option value="activa"   @selected(old('estado', $ficha->estado) === 'activa')>Activa</option>
                        <option value="inactiva" @selected(old('estado', $ficha->estado) === 'inactiva')>Inactiva</option>
                    </select>
                </label>
            </div>

            <fieldset style="border:1px solid #e2e8f0;border-radius:10px;padding:0.9rem;margin-top:1rem;">
                <legend style="font-size:0.78rem;font-weight:700;color:#334155;padding:0 0.4rem;">Responsabilidades del RUT</legend>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:0.35rem;">
                    @foreach(\App\Models\BrynexRazonSocial::RESPONSABILIDADES_RUT as $codigo => $texto)
                        <label style="font-size:0.78rem;color:#475569;display:flex;align-items:center;gap:0.4rem;">
                            <input type="checkbox" name="responsabilidades_rut[]" value="{{ $codigo }}"
                                   @checked(in_array($codigo, old('responsabilidades_rut', $ficha->responsabilidades_rut ?? []), true))>
                            {{ $texto }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <label style="font-size:0.78rem;font-weight:700;color:#334155;display:block;margin-top:1rem;">
                Notas
                <textarea name="notas" rows="3" style="width:100%;margin-top:0.25rem;padding:0.45rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;font-family:inherit;">{{ old('notas', $ficha->notas) }}</textarea>
            </label>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.3rem;">
                @can('brynex_razones.gestionar')
                    <button type="submit" style="background:#1e3a8a;color:#fff;border:none;padding:0.55rem 1.3rem;border-radius:8px;font-size:0.87rem;font-weight:700;cursor:pointer;">Guardar cambios</button>
                @endcan
            </div>
        </form>

        @can('brynex_razones.gestionar')
            @if($ficha->en_seguimiento)
                <form method="POST" action="{{ route('brynex.razones.dejar', $ficha->id) }}"
                      onsubmit="return confirm('¿Sacar esta razón social del seguimiento? El checklist y los soportes se conservan.')"
                      style="margin-top:1rem;max-width:900px;">
                    @csrf
                    <button type="submit" style="background:none;border:1px solid #fecaca;color:#b91c1c;padding:0.45rem 0.9rem;border-radius:8px;font-size:0.8rem;font-weight:600;cursor:pointer;">
                        Dejar de hacer seguimiento
                    </button>
                </form>
            @endif
        @endcan
    </div>

    {{-- ── Modal: chulear obligación ───────────────────────────────── --}}
    <div x-show="verEditar" x-cloak
         style="position:fixed;inset:0;background:rgba(15,23,42,0.55);display:flex;align-items:center;justify-content:center;z-index:60;padding:1rem;"
         @click.self="verEditar = false">
        <div style="background:#fff;border-radius:16px;max-width:520px;width:100%;padding:1.5rem;">
            <h2 style="font-size:1.05rem;font-weight:800;color:#0d2550;margin:0;" x-text="editar.nombre"></h2>
            <p style="color:#64748b;font-size:0.8rem;margin:0.15rem 0 1.1rem 0;" x-text="editar.periodo"></p>

            <form :action="'{{ url('brynex/razones-sociales/obligaciones') }}/' + editar.id" method="POST">
                @csrf @method('PUT')

                <label style="font-size:0.78rem;font-weight:700;color:#334155;display:block;">
                    Estado
                    <select name="estado" x-model="editar.estado" required
                            style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                        @foreach(\App\Models\BrynexObligacion::ESTADOS as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </label>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-top:0.9rem;">
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Valor pagado
                        <input type="number" name="valor_pagado" min="0" step="1" :value="editar.valor_pagado"
                               style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Fecha de pago
                        <input type="date" name="fecha_pago" :value="editar.fecha_pago"
                               style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>
                </div>

                <label style="font-size:0.78rem;font-weight:700;color:#334155;display:block;margin-top:0.9rem;">
                    Observación
                    <textarea name="observacion" rows="2" x-model="editar.observacion"
                              style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;font-family:inherit;"></textarea>
                </label>

                <div style="display:flex;justify-content:flex-end;gap:0.6rem;margin-top:1.2rem;">
                    <button type="button" @click="verEditar = false" style="background:#f1f5f9;color:#334155;border:none;padding:0.5rem 1rem;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">Cancelar</button>
                    <button type="submit" style="background:#1e3a8a;color:#fff;border:none;padding:0.5rem 1.2rem;border-radius:8px;font-size:0.85rem;font-weight:700;cursor:pointer;">Guardar</button>
                </div>
            </form>

            <hr style="border:none;border-top:1px solid #f1f5f9;margin:1.2rem 0;">

            <form :action="'{{ url('brynex/razones-sociales/obligaciones') }}/' + editar.id + '/documento'"
                  method="POST" enctype="multipart/form-data">
                @csrf
                <label style="font-size:0.78rem;font-weight:700;color:#334155;display:block;">
                    Subir soporte (la declaración, el recibo)
                    <input type="file" name="archivo" required accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx,.zip"
                           style="width:100%;margin-top:0.3rem;font-size:0.8rem;font-weight:400;">
                </label>
                <button type="submit" style="background:#047857;color:#fff;border:none;padding:0.45rem 1rem;border-radius:8px;font-size:0.82rem;font-weight:600;cursor:pointer;margin-top:0.7rem;">
                    📎 Subir soporte
                </button>
                <p style="color:#94a3b8;font-size:0.72rem;margin:0.5rem 0 0 0;">
                    Máximo 15 MB. Si la obligación estaba pendiente, al subir el soporte pasa a «Presentada».
                </p>
            </form>
        </div>
    </div>

    {{-- ── Modal: clave ────────────────────────────────────────────── --}}
    <div x-show="verClave" x-cloak
         style="position:fixed;inset:0;background:rgba(15,23,42,0.55);display:flex;align-items:center;justify-content:center;z-index:60;padding:1rem;"
         @click.self="verClave = false">
        <div style="background:#fff;border-radius:16px;max-width:520px;width:100%;padding:1.5rem;">
            <h2 style="font-size:1.05rem;font-weight:800;color:#0d2550;margin:0 0 1rem 0;">Clave de portal</h2>

            <form method="POST" action="{{ route('brynex.razones.claves.guardar', $ficha->id) }}">
                @csrf
                <input type="hidden" name="credencial_id" :value="clave.credencial_id">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Tipo *
                        <select name="tipo" x-model="clave.tipo" required style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                            @foreach(\App\Models\RazonSocialCredencial::TIPOS as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Portal / entidad *
                        <input type="text" name="entidad" required :value="clave.entidad" placeholder="DIAN MUISCA, Bancolombia…"
                               style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Usuario
                        <input type="text" name="usuario" :value="clave.usuario" autocomplete="off"
                               style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;">
                        Contraseña
                        <input type="password" name="contrasena" autocomplete="new-password" placeholder="Dejar vacío = no cambiar"
                               style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;grid-column:span 2;">
                        Link de acceso
                        <input type="url" name="link_acceso" :value="clave.link_acceso" placeholder="https://…"
                               style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>
                    <label style="font-size:0.78rem;font-weight:700;color:#334155;grid-column:span 2;">
                        Observación
                        <input type="text" name="observacion" :value="clave.observacion"
                               style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-weight:400;">
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:0.6rem;margin-top:1.2rem;">
                    <button type="button" @click="verClave = false" style="background:#f1f5f9;color:#334155;border:none;padding:0.5rem 1rem;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">Cancelar</button>
                    <button type="submit" style="background:#047857;color:#fff;border:none;padding:0.5rem 1.2rem;border-radius:8px;font-size:0.85rem;font-weight:700;cursor:pointer;">Guardar clave</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.rs-tabs {
    display: inline-flex; flex-wrap: wrap; gap: .2rem;
    background: #f1f5f9; border: 1px solid #e2e8f0;
    padding: .25rem; border-radius: 12px;
}
.rs-tab {
    display: inline-flex; align-items: center; gap: .4rem;
    border: 0; background: transparent; cursor: pointer;
    padding: .5rem .9rem; border-radius: 9px;
    font-size: .84rem; font-weight: 700; color: #64748b;
    transition: background .12s, color .12s;
}
.rs-tab:hover { background: #e2e8f0; color: #334155; }
.rs-tab.activa {
    background: #fff; color: #1e3a8a;
    box-shadow: 0 1px 3px rgba(15, 23, 42, .1);
}
.rs-tab.activa:hover { background: #fff; }
.rs-tab-ico { font-size: .95rem; line-height: 1; }
.rs-pill {
    font-size: .68rem; font-weight: 800; line-height: 1;
    padding: .2rem .4rem; border-radius: 20px;
    background: #e2e8f0; color: #475569;
}
.rs-tab.activa .rs-pill { background: #dbeafe; color: #1d4ed8; }
.rs-pill-rojo { background: #fee2e2; color: #b91c1c; }

.rs-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px;
    padding: .25rem .7rem; font-size: .78rem; color: #475569;
    white-space: nowrap;
}
.rs-chip b { color: #0f172a; font-weight: 800; }
.rs-chip.alerta { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.rs-chip.alerta b { color: #b91c1c; }
.rs-tab.activa .rs-pill-rojo { background: #fee2e2; color: #b91c1c; }

.rs-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px;
    padding: .25rem .7rem; font-size: .78rem; color: #475569;
    white-space: nowrap;
}
.rs-chip b { color: #0f172a; font-weight: 800; }
.rs-chip.alerta { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.rs-chip.alerta b { color: #b91c1c; }

@media (max-width: 640px) {
    .rs-tabs { display: flex; width: 100%; }
    .rs-tab { flex: 1; justify-content: center; padding: .5rem .4rem; }
}
</style>

@endsection
