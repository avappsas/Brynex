{{-- Al desembolsar en efectivo no hay comprobante bancario que pruebe la
     entrega: este recibo es esa prueba, y por eso lo firma el deudor. --}}
<h1>RECIBO DE DESEMBOLSO</h1>
<p class="sello">Anexo al contrato de mutuo y al pagaré No. {{ $exp->pagare_numero }}</p>

<p>{{ $exp->ciudad }}, {{ $fechaCorta }}</p>

<p>Yo, <strong>{{ mb_strtoupper($exp->deudor_nombre) }}</strong>, mayor de edad, identificado(a) con cédula de ciudadanía No. {{ $exp->deudor_cedula }}{{ $exp->deudor_expedida_en ? ' expedida en '.$exp->deudor_expedida_en : '' }}, obrando en mi calidad de <strong>MUTUARIO</strong>, declaro que en la fecha he recibido de <strong>{{ mb_strtoupper($prestamista->nombre) }}</strong>, identificado(a) con cédula de ciudadanía No. {{ $prestamista->cedula ?: '______________________' }}, real y materialmente y a entera satisfacción, en <strong>dinero en efectivo</strong>, la suma de {{ $montoLetras }}, a título de mutuo con interés.</p>

<p>Dicha suma corresponde íntegramente al capital del préstamo documentado en el contrato de mutuo y en el pagaré No. {{ $exp->pagare_numero }} suscritos en esta misma fecha, cuyas condiciones declaro conocer y aceptar. En consecuencia, no queda saldo alguno pendiente de entrega a mi favor por concepto del desembolso.</p>

<table>
    <tr><th style="width:40%;">Capital recibido</th><td>${{ number_format($exp->monto, 0, ',', '.') }}</td></tr>
    <tr><th>Tasa de interés</th><td>{{ $tasaCorta }} mensual sobre saldo insoluto</td></tr>
    <tr><th>Corte mensual</th><td>Día {{ $exp->dia_cobro }} de cada mes (primer corte: {{ $primerCorteLargo }})</td></tr>
    <tr><th>Vencimiento del capital</th><td>{{ $vencimientoLargo }}</td></tr>
</table>

@include('finanzas.prestamos.documentos._firma', [
    'titulo' => 'EL MUTUARIO (quien recibe)',
    'nombre' => $exp->deudor_nombre,
    'cedula' => $exp->deudor_cedula,
    'expedida' => $exp->deudor_expedida_en,
    'direccion' => $exp->deudor_direccion,
    'ciudad' => $exp->deudor_ciudad,
    'telefono' => $exp->deudor_telefono,
    'correo' => $exp->deudor_correo,
])
