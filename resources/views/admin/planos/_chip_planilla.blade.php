{{--
    Chip del número de planilla confirmado (clic = copiar), con lo que dijo el
    operador de ese número: si cruzó (y a qué hora se pagó), si no existe allá
    —casi siempre mal digitado—, o si el usuario del portal no ve la empresa.
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
@endphp
<span class="chip-planilla {{ $claseChip }}" data-num="{{ $p->numero_planilla }}"
      onclick="copiarPlanilla(this)"
      title="Planilla: {{ $p->numero_planilla }}{{ $horaConf ? ' · confirmada '.$horaConf : '' }}. {{ trim($detalleChip) }} (clic para copiar)">{{ $iconoChip }} {{ $p->numero_planilla }}</span>
