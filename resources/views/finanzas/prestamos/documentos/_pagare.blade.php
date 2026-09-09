<h1>PAGARÉ</h1>
<p class="sello">Pagaré No. {{ $exp->pagare_numero }} &#160;·&#160; Fecha de creación: {{ $fechaCorta }} &#160;·&#160; Lugar de creación y de pago: {{ $exp->ciudad }}</p>
<p class="centrado"><strong>Valor: ${{ number_format($exp->monto, 0, ',', '.') }} M/CTE</strong></p>

@if($exp->tiene_codeudor)
<p>Yo, <strong>{{ mb_strtoupper($exp->deudor_nombre) }}</strong>, mayor de edad, identificado(a) con cédula de ciudadanía No. {{ $exp->deudor_cedula }}{{ $exp->deudor_expedida_en ? ' expedida en '.$exp->deudor_expedida_en : '' }}, obrando en mi calidad de <strong>OTORGANTE</strong> y deudor principal, y yo, <strong>{{ mb_strtoupper($exp->codeudor_nombre) }}</strong>, mayor de edad, identificado(a) con cédula de ciudadanía No. {{ $exp->codeudor_cedula }}{{ $exp->codeudor_expedida_en ? ' expedida en '.$exp->codeudor_expedida_en : '' }}, obrando en mi calidad de <strong>CODEUDOR SOLIDARIO</strong>, mediante el presente título valor hacemos constar:</p>
@else
<p>Yo, <strong>{{ mb_strtoupper($exp->deudor_nombre) }}</strong>, mayor de edad, identificado(a) con cédula de ciudadanía No. {{ $exp->deudor_cedula }}{{ $exp->deudor_expedida_en ? ' expedida en '.$exp->deudor_expedida_en : '' }}, obrando en mi calidad de <strong>OTORGANTE</strong> y deudor principal, mediante el presente título valor hago constar:</p>
@endif

<h3>PRIMERO. OBJETO.</h3>
<p>Que pagar{{ $exp->tiene_codeudor ? 'emos solidaria e incondicionalmente' : 'é incondicionalmente' }} a la orden de <strong>{{ mb_strtoupper($prestamista->nombre) }}</strong>, identificado(a) con cédula de ciudadanía No. {{ $prestamista->cedula ?: '______________________' }}, o a su cesionario o tenedor legítimo, la suma de {{ $montoLetras }}, más los intereses que adelante se pactan.</p>

<h3>SEGUNDO. FORMA DE VENCIMIENTO.</h3>
<p>Los intereses remuneratorios se pagarán por mensualidades vencidas, con corte el día {{ $exp->dia_cobro }} de cada mes, siendo el primer corte el {{ $primerCorteLargo }}. El capital, o el saldo insoluto que de él subsista, será exigible en su totalidad el día {{ $vencimientoLargo }}, sin perjuicio de los abonos que se realicen antes de esa fecha.</p>

<h3>TERCERO. INTERESES.</h3>
<p>Sobre el saldo insoluto de capital reconoc{{ $exp->tiene_codeudor ? 'eremos' : 'eré' }} intereses remuneratorios del {{ $tasaLetras }} MENSUAL, liquidados por días transcurridos, y en caso de mora, intereses moratorios a la tasa máxima legal permitida, equivalente a una y media veces el interés bancario corriente certificado por la Superintendencia Financiera de Colombia, vigente durante el período de mora. En ningún caso los intereses excederán el límite legal; de superarlo, se entenderán reducidos automáticamente al máximo permitido.</p>

<h3>CUARTO. CLÁUSULA ACELERATORIA.</h3>
<p>El incumplimiento en el pago de los intereses de un (1) cualquiera de los períodos mensuales, o del capital a su vencimiento, faculta al tenedor legítimo para declarar vencidos la totalidad de los plazos y exigir de inmediato el pago íntegro del saldo de capital, intereses y demás sumas adeudadas, mediante proceso ejecutivo.</p>

@if($exp->tiene_codeudor)
<h3>QUINTO. SOLIDARIDAD.</h3>
<p>Las obligaciones aquí contenidas son solidarias e indivisibles entre el OTORGANTE y el CODEUDOR SOLIDARIO, quienes responden cada uno por la totalidad. El CODEUDOR SOLIDARIO renuncia a los beneficios de excusión y división y acepta desde ahora las prórrogas o refinanciaciones que se concedan al deudor principal.</p>
@endif

<h3>{{ $exp->tiene_codeudor ? 'SEXTO' : 'QUINTO' }}. RENUNCIAS.</h3>
<p>Renunci{{ $exp->tiene_codeudor ? 'amos' : 'o' }} expresamente a la presentación para el pago, al aviso de rechazo, al protesto y a los requerimientos privados o judiciales para la constitución en mora, de conformidad con los artículos 787 y siguientes del Código de Comercio y el artículo 422 del Código General del Proceso.</p>

<h3>{{ $exp->tiene_codeudor ? 'SÉPTIMO' : 'SEXTO' }}. GASTOS.</h3>
<p>Serán de {{ $exp->tiene_codeudor ? 'nuestro' : 'mi' }} cargo los gastos de cobranza judicial y extrajudicial, incluidos los honorarios de abogado, que se fijan en el diez por ciento (10%) del valor total de la obligación exigible.</p>

<p>El presente pagaré presta mérito ejecutivo y se suscribe en la ciudad de {{ $exp->ciudad }}, {{ $fechaLarga }}.</p>

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
