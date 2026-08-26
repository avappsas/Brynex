{{--
    Prender / apagar el cobro de la mora en la cuenta de cobro.
    Recarga el documento en la misma ventana. Al apagarla se le quita a todo el
    que aún no ha pagado (sin factura, o con factura pendiente / abono /
    préstamo). Quien ya pagó conserva la mora: ese dinero ya entró.
--}}
@php $ccMoraOn = $incluirMora ?? true; @endphp
<form class="frm-cc" method="POST" action="{{ route('admin.facturacion.cuenta_cobro.preview') }}" style="display:inline;">
    @include('admin.facturacion._cc_params', ['tipoDestino' => $tipo ?? 'simple', 'moraDestino' => $ccMoraOn ? '0' : '1'])
    <button type="submit" class="btn-ac"
            style="background:{{ $ccMoraOn ? '#b45309' : '#334155' }};color:#fff;"
            title="{{ $ccMoraOn ? 'Emitir la cuenta sin cobrar mora a quien no ha pagado' : 'Volver a cobrar la mora' }}">
        {{ $ccMoraOn ? '🚫 Quitar mora' : '⚠️ Cobrar mora' }}
    </button>
</form>
@if(!$ccMoraOn)
<span style="background:#334155;color:#fbbf24;font-size:.68rem;font-weight:800;padding:.2rem .5rem;border-radius:5px;">
    SIN MORA
</span>
@endif
