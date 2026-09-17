{{--
    Chip del número de planilla confirmado (clic = menú de opciones), con lo que
    dijo el operador de ese número: si cruzó (y a qué hora se pagó), si no existe
    allá —casi siempre mal digitado—, o si el usuario del portal no ve la empresa.
--}}
@php
    $horaConf = $p->updated_at ? sqldate($p->updated_at, 'd/m/y H:i') : '';
    [$claseChip, $iconoChip, $detalleChip] = match ($p->verif_estado ?? null) {
        'no_encontrada' => ['no-cruza',    '⚠️', 'No aparece en el operador: revisa el número. '.($p->verif_mensaje ?? '')],
        'invalida'      => ['no-cruza',    '⚠️', 'No es un número de planilla: corrígelo en la confirmación del pago.'],
        'sin_acceso'    => ['sin-acceso',  '🔒', 'No se pudo verificar: '.($p->verif_mensaje ?? '')],
        'verificando'   => ['verificando', '⏳', $p->verif_mensaje ?? 'Verificando en el operador.'],
        default         => ['',            '✅', $p->pago_operador_fecha ? 'Pagada en el operador '.sqldate($p->pago_operador_fecha, 'd/m/y H:i:s').'.' : ''],
    };

    // El gasto de ESTA planilla: en una RS independiente cada persona tiene el
    // suyo, así que la corrección se ofrece fila por fila y no en el recuadro.
    // El clic abre el menú de la planilla (copiar / corregir) en vez de copiar
    // directo: un lápiz por fila llenaba la tabla de iconos.
    $gastoChip = ($gastosPorPlanilla ?? collect())->get(trim((string) $p->numero_planilla));
    $puedeCorregirChip = $gastoChip && isset($puedeCorregir) && $puedeCorregir($gastoChip);

    // El nombre solo cuando el pago ES de esa persona: en una planilla de
    // empresa el número cubre a todo el mundo y poner un nombre engañaría.
    $nombreChip = ($esIndependiente ?? false) ? ($p->nombre_completo ?? '') : '';
@endphp
<span class="chip-planilla {{ $claseChip }}" data-num="{{ $p->numero_planilla }}"
      onclick="abrirAccionesPlanilla({{ Illuminate\Support\Js::from($p->numero_planilla) }}, {{ $puedeCorregirChip ? (int) $gastoChip->valor : 0 }}, {{ $puedeCorregirChip ? (int) $gastoChip->id : 'null' }}, {{ Illuminate\Support\Js::from($nombreChip) }}, {{ Illuminate\Support\Js::from(trim($detalleChip)) }})"
      title="Planilla: {{ $p->numero_planilla }}{{ $horaConf ? ' · confirmada '.$horaConf : '' }}. {{ trim($detalleChip) }} (clic para ver opciones)">{{ $iconoChip }} {{ $p->numero_planilla }}</span>
