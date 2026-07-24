@php
    $precioPromo = $cfg?->promocion_costo_afiliacion;
    $vencePromo  = $cfg?->promocion_vencimiento?->format('Y-m-d');
    $vigente     = $cfg?->promocionVigente() ?? false;
@endphp
<input type="hidden" class="promo-input-precio" data-key="{{ $key }}" name="configs[{{ $key }}][promocion_costo_afiliacion]" value="{{ $precioPromo !== null ? (int) $precioPromo : '' }}">
<input type="hidden" class="promo-input-vence" data-key="{{ $key }}" name="configs[{{ $key }}][promocion_vencimiento]" value="{{ $vencePromo }}">

<button type="button"
    class="btn-promocion"
    data-key="{{ $key }}"
    data-precio="{{ $precioPromo !== null ? (int) $precioPromo : '' }}"
    data-vence="{{ $vencePromo }}"
    onclick="abrirModalPromocion(this)"
    style="font-size:0.7rem;font-weight:700;padding:0.3rem 0.6rem;border-radius:999px;cursor:pointer;white-space:nowrap;
        {{ $vigente
            ? 'background:#fef9c3;color:#92400e;border:1px solid #fde047;'
            : 'background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1;' }}">
    {{ $vigente ? '🏷️ Hasta ' . \Carbon\Carbon::parse($vencePromo)->format('d/m/Y') : '+ Configurar' }}
</button>
