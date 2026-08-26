{{--
    Campos ocultos comunes de la cuenta de cobro. Los usan los botones que
    recargan el documento (cambiar de vista, prender/apagar la mora) para no
    perder por el camino los contratos elegidos ni las opciones de cobro.

    Variables esperadas:
      $tipoDestino  → 'simple' | 'detallada'
      $moraDestino  → '1' cobra la mora, '0' la deja por fuera
--}}
@csrf
<input type="hidden" name="tipo" value="{{ $tipoDestino }}">
<input type="hidden" name="mes" value="{{ $mes }}">
<input type="hidden" name="anio" value="{{ $anio }}">
<input type="hidden" name="empresa_id" value="{{ $empresa?->id ?? '' }}">
<input type="hidden" name="admon_retiro_completa" value="{{ ($admonRetiroCompleta ?? true) ? '1' : '0' }}">
<input type="hidden" name="incluir_mora" value="{{ $moraDestino }}">
@foreach(request()->input('contratos', []) as $cid)
<input type="hidden" name="contratos[]" value="{{ $cid }}">
@endforeach
@foreach(request()->input('cobros_adicionales_ids', []) as $caid)
<input type="hidden" name="cobros_adicionales_ids[]" value="{{ $caid }}">
@endforeach
