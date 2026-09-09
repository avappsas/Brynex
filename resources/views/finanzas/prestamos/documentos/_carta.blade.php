<h1>CARTA DE INSTRUCCIONES</h1>
<h2>(Artículo 622 del Código de Comercio)</h2>

<p>Ciudad y fecha: {{ $exp->ciudad }}, {{ $fechaCorta }}</p>

<p>Señor(a)<br/>
<strong>{{ mb_strtoupper($prestamista->nombre) }}</strong><br/>
C.C. No. {{ $prestamista->cedula ?: '______________________' }}<br/>
{{ $prestamista->ciudad ?: 'Ciudad' }}</p>

<p><strong>Referencia:</strong> Instrucciones para el diligenciamiento de espacios en blanco del Pagaré No. {{ $exp->pagare_numero }}.</p>

<p>En {{ $exp->tiene_codeudor ? 'nuestra calidad de OTORGANTE y CODEUDOR SOLIDARIO' : 'mi calidad de OTORGANTE' }} del pagaré de la referencia, suscrito a su favor en la fecha indicada, y de conformidad con el artículo 622 del Código de Comercio, por medio del presente documento le impart{{ $exp->tiene_codeudor ? 'imos' : 'o' }} las siguientes instrucciones irrevocables para llenar los espacios que se hubieren dejado en blanco:</p>

<h3>PRIMERA. FACULTAD.</h3>
<p>Usted queda expresamente facultado(a), sin necesidad de aviso, requerimiento o autorización adicional, para llenar los espacios en blanco del pagaré cuando ocurra cualquiera de los eventos previstos en la cláusula aceleratoria del contrato de mutuo suscrito entre las partes, en especial el incumplimiento en el pago de los intereses de un (1) cualquiera de los períodos mensuales pactados o del capital a su vencimiento.</p>

<h3>SEGUNDA. CUANTÍA.</h3>
<p>El valor a diligenciar corresponderá al saldo insoluto de capital a la fecha en que se llene el título, más los intereses remuneratorios causados y no pagados a la tasa del {{ $tasaCorta }} mensual, más los intereses de mora liquidados a la tasa máxima legal permitida desde la fecha de exigibilidad y hasta el pago total, más los gastos de cobranza y honorarios de abogado pactados. En ningún caso la suma diligenciada podrá exceder de {{ $topeLetras }}.</p>

<h3>TERCERA. FECHAS.</h3>
<p>Como fecha de vencimiento se registrará el día en que se declare la aceleración de los plazos o aquel en que se produzca el incumplimiento que da lugar al diligenciamiento, y como fecha de creación la de suscripción del pagaré.</p>

<h3>CUARTA. LUGAR DE PAGO.</h3>
<p>El lugar de pago será la ciudad de {{ $exp->ciudad }}, domicilio contractual acordado por las partes.</p>

<h3>QUINTA. ABONOS.</h3>
<p>Reconoc{{ $exp->tiene_codeudor ? 'emos' : 'co' }} que los abonos que {{ $exp->tiene_codeudor ? 'hubiéremos' : 'hubiere' }} realizado se descontarán del saldo, conforme a la imputación de pagos prevista en el artículo 1653 del Código Civil, y que el título se diligenciará únicamente por las sumas efectivamente adeudadas.</p>

<h3>SEXTA. IRREVOCABILIDAD.</h3>
<p>Las presentes instrucciones son irrevocables mientras subsista cualquier obligación a {{ $exp->tiene_codeudor ? 'nuestro' : 'mi' }} cargo derivada del contrato de mutuo y del pagaré de la referencia, y se entienden extendidas a favor de cualquier tenedor legítimo del título.</p>

<p>Atentamente,</p>

@include('finanzas.prestamos.documentos._firma', [
    'titulo' => 'OTORGANTE (deudor principal)',
    'nombre' => $exp->deudor_nombre,
    'cedula' => $exp->deudor_cedula,
    'expedida' => $exp->deudor_expedida_en,
    'direccion' => $exp->deudor_direccion,
    'ciudad' => $exp->deudor_ciudad,
    'telefono' => $exp->deudor_telefono,
    'correo' => $exp->deudor_correo,
])

@if($exp->tiene_codeudor)
@include('finanzas.prestamos.documentos._firma', [
    'titulo' => 'CODEUDOR SOLIDARIO',
    'nombre' => $exp->codeudor_nombre,
    'cedula' => $exp->codeudor_cedula,
    'expedida' => $exp->codeudor_expedida_en,
    'direccion' => $exp->codeudor_direccion,
    'ciudad' => $exp->codeudor_ciudad,
    'telefono' => $exp->codeudor_telefono,
    'correo' => $exp->codeudor_correo,
])
@endif
