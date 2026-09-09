@php $c = 0; @endphp
<h1>CONTRATO DE MUTUO CON INTERÉS</h1>
<p class="sello">{{ $exp->pagare_numero }}</p>

<p>Entre los suscritos a saber:</p>

<p><strong>{{ mb_strtoupper($prestamista->nombre) }}</strong>, mayor de edad, identificado(a) con cédula de ciudadanía No. {{ $prestamista->cedula ?: '______________________' }}{{ $prestamista->expedida_en ? ' expedida en '.$prestamista->expedida_en : '' }}, domiciliado(a) en {{ $prestamista->direccion ? $prestamista->direccion.' de '.$prestamista->ciudad : ($prestamista->ciudad ?: '______________________') }}, quien para efectos del presente contrato se denominará <strong>EL MUTUANTE</strong>;</p>

<p><strong>{{ mb_strtoupper($exp->deudor_nombre) }}</strong>, mayor de edad, identificado(a) con cédula de ciudadanía No. {{ $exp->deudor_cedula }}{{ $exp->deudor_expedida_en ? ' expedida en '.$exp->deudor_expedida_en : '' }}, domiciliado(a) en {{ $exp->deudor_direccion ? $exp->deudor_direccion.' de '.$exp->deudor_ciudad : ($exp->deudor_ciudad ?: '______________________') }}, quien para efectos del presente contrato se denominará <strong>EL MUTUARIO</strong>{{ $exp->tiene_codeudor ? '; y' : '.' }}</p>

@if($exp->tiene_codeudor)
<p><strong>{{ mb_strtoupper($exp->codeudor_nombre) }}</strong>, mayor de edad, identificado(a) con cédula de ciudadanía No. {{ $exp->codeudor_cedula }}{{ $exp->codeudor_expedida_en ? ' expedida en '.$exp->codeudor_expedida_en : '' }}, domiciliado(a) en {{ $exp->codeudor_direccion ? $exp->codeudor_direccion.' de '.$exp->codeudor_ciudad : ($exp->codeudor_ciudad ?: '______________________') }}, quien para efectos del presente contrato se denominará <strong>EL CODEUDOR SOLIDARIO</strong>.</p>
@endif

<p>Hemos convenido celebrar el presente contrato de mutuo con interés, que se regirá por las siguientes cláusulas y, en lo no previsto en ellas, por los artículos 2221 y siguientes del Código Civil y las normas comerciales aplicables:</p>

<h3>{{ $ordinales[$c++] }}. OBJETO Y MONTO.</h3>
<p>EL MUTUANTE entrega en calidad de mutuo (préstamo de consumo) a EL MUTUARIO, quien declara recibirla a entera satisfacción, la suma de {{ $montoLetras }}. EL MUTUARIO se obliga a restituir dicha suma junto con los intereses pactados, en la forma y plazos previstos en este contrato.</p>

<h3>{{ $ordinales[$c++] }}. ENTREGA Y PRUEBA DEL DESEMBOLSO.</h3>
<p>La suma objeto del mutuo se entrega <strong>en dinero en efectivo</strong> en la ciudad de {{ $exp->ciudad }}, en la fecha de suscripción de este documento. EL MUTUARIO declara haberla recibido real y materialmente a entera satisfacción, y la firma del presente contrato, junto con el recibo de desembolso que se suscribe en la misma fecha, presta plena prueba de la entrega. Las partes declaran que el dinero objeto del préstamo proviene de actividades lícitas.</p>

<h3>{{ $ordinales[$c++] }}. PLAZO.</h3>
<p>El plazo para la restitución total del capital es de {{ $plazoLetras }} MESES contados a partir del desembolso, venciendo el día {{ $vencimientoLargo }}. Durante la vigencia del plazo EL MUTUARIO pagará mensualmente los intereses causados, en los términos de la cláusula siguiente.</p>

<h3>{{ $ordinales[$c++] }}. INTERESES REMUNERATORIOS Y FORMA DE LIQUIDACIÓN.</h3>
<p>Sobre el <strong>saldo insoluto de capital</strong> EL MUTUARIO reconocerá y pagará a EL MUTUANTE intereses remuneratorios a la tasa del {{ $tasaLetras }} MENSUAL, equivalente al {{ $tasaEa }}% efectivo anual. Los intereses se liquidan <strong>por días transcurridos</strong>, tomando el mes de treinta (30) días, de manera que cada abono a capital reduce proporcionalmente el interés del período siguiente. El corte mensual se produce el día {{ $exp->dia_cobro }} de cada mes, siendo el primer corte el {{ $primerCorteLargo }}.</p>
<p><strong>PARÁGRAFO.</strong> En ningún caso la tasa aquí pactada, ni la de mora, podrá exceder el límite máximo legal, entendido como una y media veces el interés bancario corriente certificado por la Superintendencia Financiera de Colombia y vigente al momento de la causación. Si por cualquier circunstancia la tasa pactada llegare a superar dicho límite, se entenderá automáticamente reducida al máximo legal permitido, sin necesidad de modificación del presente contrato.</p>

<h3>{{ $ordinales[$c++] }}. FORMA DE PAGO.</h3>
<p>EL MUTUARIO pagará en cada corte mensual, como mínimo, la totalidad de los intereses causados en el período, que a la fecha de este contrato y sobre el saldo inicial ascienden a {{ $interesMensualLetras }}. El capital podrá abonarse total o parcialmente en cualquier momento y, en todo caso, deberá estar cancelado en su totalidad a la fecha de vencimiento pactada en la cláusula {{ $ordinales[2] }}.</p>
<p>Los pagos podrán efectuarse en efectivo o por cualquier medio que las partes acuerden. <strong>Solo se tendrán por válidos los pagos acreditados con el respectivo recibo o comprobante</strong> expedido o aceptado por EL MUTUANTE. EL MUTUANTE llevará el registro de los abonos, con la liquidación de intereses y el saldo de capital, y lo pondrá a disposición de EL MUTUARIO cuando este lo solicite.</p>

<h3>{{ $ordinales[$c++] }}. IMPUTACIÓN DE PAGOS.</h3>
<p>De conformidad con el artículo 1653 del Código Civil, todo pago se imputará primero a gastos de cobranza, luego a intereses de mora, después a intereses remuneratorios causados y, por último, a capital.</p>

<h3>{{ $ordinales[$c++] }}. PAGOS ANTICIPADOS.</h3>
<p>EL MUTUARIO podrá realizar abonos extraordinarios a capital o cancelar anticipadamente la totalidad de la obligación en cualquier momento, sin sanción ni penalidad alguna. En tal evento los intereses se liquidarán únicamente hasta la fecha efectiva del pago.</p>

<h3>{{ $ordinales[$c++] }}. INTERESES DE MORA.</h3>
<p>En caso de mora en el pago de los intereses de cualquier período o del capital a su vencimiento, EL MUTUARIO reconocerá sobre las sumas en mora intereses moratorios a la tasa máxima legal permitida, equivalente a una y media veces el interés bancario corriente certificado por la Superintendencia Financiera de Colombia para la modalidad de crédito de consumo y ordinario, vigente durante el período de mora, sin perjuicio de las acciones judiciales a que haya lugar.</p>

<h3>{{ $ordinales[$c++] }}. CLÁUSULA ACELERATORIA.</h3>
<p>EL MUTUANTE podrá declarar vencidos anticipadamente todos los plazos y exigir el pago inmediato de la totalidad del saldo de capital, intereses corrientes, intereses de mora y gastos, cuando ocurra cualquiera de los siguientes eventos: (i) el incumplimiento en el pago de los intereses de un (1) cualquiera de los períodos mensuales pactados; (ii) la falsedad o inexactitud en la información suministrada por EL MUTUARIO{{ $exp->tiene_codeudor ? ' o EL CODEUDOR SOLIDARIO' : '' }}; (iii) el embargo o secuestro de sus bienes; (iv) su insolvencia, liquidación patrimonial o admisión a proceso concursal;@if($exp->tiene_prenda) (v) la enajenación, gravamen, ocultamiento o deterioro grave del bien dado en garantía; o (vi) el incumplimiento de cualquier otra obligación derivada de este contrato.@else{{ ' o (v) el incumplimiento de cualquier otra obligación derivada de este contrato.' }}@endif</p>

@if($exp->tiene_codeudor)
<h3>{{ $ordinales[$c++] }}. SOLIDARIDAD DEL CODEUDOR.</h3>
<p>EL CODEUDOR SOLIDARIO se obliga para con EL MUTUANTE en forma solidaria e indivisible con EL MUTUARIO, en los términos de los artículos 1568 y siguientes del Código Civil, por el pago total del capital, intereses corrientes, intereses de mora, gastos de cobranza y demás sumas derivadas de este contrato. En consecuencia, EL MUTUANTE podrá dirigirse indistintamente contra EL MUTUARIO, contra EL CODEUDOR SOLIDARIO, o contra ambos, por el total de la obligación, sin necesidad de agotar previamente el cobro frente a ninguno de ellos. EL CODEUDOR SOLIDARIO renuncia expresamente a los beneficios de excusión y división, y acepta desde ahora las prórrogas, novaciones, refinanciaciones o modificaciones que EL MUTUANTE conceda a EL MUTUARIO, las cuales no extinguirán su obligación.</p>
@endif

@if($exp->tiene_prenda)
<h3>{{ $ordinales[$c++] }}. GARANTÍA PRENDARIA.</h3>
<p>Como garantía del cumplimiento de las obligaciones aquí contraídas, se constituye prenda sin tenencia sobre el vehículo de placa <strong>{{ $exp->prenda_placa }}</strong>, {{ $exp->prenda_clase }} marca {{ $exp->prenda_marca }}, línea {{ $exp->prenda_linea }}, modelo {{ $exp->prenda_modelo }}, motor No. {{ $exp->prenda_motor }}, chasis No. {{ $exp->prenda_chasis }}, en los términos del contrato de prenda que se suscribe en esta misma fecha y que hace parte integral del presente contrato.</p>
@endif

<h3>{{ $ordinales[$c++] }}. TÍTULO VALOR.</h3>
<p>Como respaldo de las obligaciones aquí contraídas, EL MUTUARIO{{ $exp->tiene_codeudor ? ' y EL CODEUDOR SOLIDARIO' : '' }} suscribe{{ $exp->tiene_codeudor ? 'n' : '' }} en esta misma fecha el pagaré No. {{ $exp->pagare_numero }} a favor de EL MUTUANTE, junto con su respectiva carta de instrucciones. La suscripción del pagaré no constituye novación de las obligaciones contenidas en este contrato, conforme al artículo 882 del Código de Comercio, y las partes declaran que ambos documentos son complementarios y no dan lugar a doble cobro.</p>

<h3>{{ $ordinales[$c++] }}. GASTOS DE COBRANZA.</h3>
<p>Serán de cargo de EL MUTUARIO{{ $exp->tiene_codeudor ? ' y de EL CODEUDOR SOLIDARIO' : '' }} todos los gastos de cobranza prejudicial y judicial en que deba incurrir EL MUTUANTE, incluidos honorarios de abogado, los cuales se fijan en un diez por ciento (10%) del valor total de la obligación exigible, sin perjuicio de la liquidación de costas y agencias en derecho que efectúe el juez.</p>

<h3>{{ $ordinales[$c++] }}. AUTORIZACIÓN DE TRATAMIENTO DE DATOS.</h3>
<p>EL MUTUARIO{{ $exp->tiene_codeudor ? ' y EL CODEUDOR SOLIDARIO' : '' }} autoriza{{ $exp->tiene_codeudor ? 'n' : '' }} de manera expresa, previa e informada a EL MUTUANTE, en los términos de la Ley 1266 de 2008 y la Ley 1581 de 2012, para consultar, recolectar, almacenar, procesar y reportar ante los operadores de información financiera y centrales de riesgo el comportamiento de pago derivado de la presente obligación, así como para el envío de comunicaciones de cobro por los canales de contacto registrados.</p>

<h3>{{ $ordinales[$c++] }}. NOTIFICACIONES Y DOMICILIO CONTRACTUAL.</h3>
<p>Las partes señalan como direcciones para notificaciones las indicadas al pie de sus firmas y se obligan a informar por escrito cualquier cambio dentro de los cinco (5) días hábiles siguientes; en su defecto, se tendrán por válidas las notificaciones enviadas a las direcciones aquí registradas. Para todos los efectos legales las partes fijan como domicilio contractual la ciudad de {{ $exp->ciudad }}.</p>

<h3>{{ $ordinales[$c++] }}. MÉRITO EJECUTIVO.</h3>
<p>Las partes declaran que el presente contrato, junto con el pagaré y el recibo de desembolso, presta mérito ejecutivo y contiene una obligación clara, expresa y exigible en los términos del artículo 422 del Código General del Proceso, sin necesidad de requerimiento privado o judicial, requerimientos a los cuales EL MUTUARIO{{ $exp->tiene_codeudor ? ' y EL CODEUDOR SOLIDARIO renuncian' : ' renuncia' }} expresamente.</p>

<h3>{{ $ordinales[$c++] }}. LEY APLICABLE.</h3>
<p>El presente contrato se rige por la ley colombiana. Las controversias que surjan entre las partes serán sometidas al conocimiento de los jueces civiles competentes del domicilio contractual.</p>

<p>Para constancia se firma en la ciudad de {{ $exp->ciudad }}, {{ $fechaLarga }}, en {{ $exp->tiene_codeudor ? 'tres (3)' : 'dos (2)' }} ejemplares del mismo tenor y valor, uno para cada parte.</p>

@include('finanzas.prestamos.documentos._firma', [
    'titulo' => 'EL MUTUANTE (acreedor)',
    'nombre' => $prestamista->nombre,
    'cedula' => $prestamista->cedula,
    'expedida' => $prestamista->expedida_en,
    'direccion' => $prestamista->direccion,
    'ciudad' => $prestamista->ciudad,
    'telefono' => $prestamista->telefono,
    'correo' => $prestamista->correo,
])

@include('finanzas.prestamos.documentos._firma', [
    'titulo' => 'EL MUTUARIO (deudor)',
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
    'titulo' => 'EL CODEUDOR SOLIDARIO',
    'nombre' => $exp->codeudor_nombre,
    'cedula' => $exp->codeudor_cedula,
    'expedida' => $exp->codeudor_expedida_en,
    'direccion' => $exp->codeudor_direccion,
    'ciudad' => $exp->codeudor_ciudad,
    'telefono' => $exp->codeudor_telefono,
    'correo' => $exp->codeudor_correo,
])
@endif
