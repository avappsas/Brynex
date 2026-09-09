<h1>CONTRATO DE PRENDA SIN TENENCIA SOBRE VEHÍCULO AUTOMOTOR</h1>
<p class="sello">Garantía del contrato de mutuo y del pagaré No. {{ $exp->pagare_numero }}</p>

<p>Entre <strong>{{ mb_strtoupper($prestamista->nombre) }}</strong>, identificado(a) con cédula de ciudadanía No. {{ $prestamista->cedula ?: '______________________' }}, quien se denominará <strong>EL ACREEDOR GARANTIZADO</strong>, y <strong>{{ mb_strtoupper($exp->prenda_propietario ?: $exp->deudor_nombre) }}</strong>, identificado(a) con cédula de ciudadanía No. {{ $exp->prenda_propietario_cedula ?: $exp->deudor_cedula }}, propietario(a) del bien que adelante se describe y quien se denominará <strong>EL GARANTE</strong>, se celebra el presente contrato de prenda sin tenencia, regido por la Ley 1676 de 2013 y demás normas concordantes:</p>

<h3>PRIMERA. OBLIGACIÓN GARANTIZADA.</h3>
<p>Se garantiza el pago total del capital de {{ $montoLetras }}, sus intereses remuneratorios y moratorios, gastos de cobranza y honorarios de abogado, derivados del contrato de mutuo con interés y del pagaré No. {{ $exp->pagare_numero }} suscritos en esta misma fecha entre EL ACREEDOR GARANTIZADO y {{ mb_strtoupper($exp->deudor_nombre) }}.</p>

<h3>SEGUNDA. BIEN DADO EN GARANTÍA.</h3>
<table>
    <tr><th style="width:35%;">Placa</th><td>{{ $exp->prenda_placa }}</td></tr>
    <tr><th>Clase</th><td>{{ $exp->prenda_clase }}</td></tr>
    <tr><th>Marca y línea</th><td>{{ $exp->prenda_marca }} {{ $exp->prenda_linea }}</td></tr>
    <tr><th>Modelo</th><td>{{ $exp->prenda_modelo }}</td></tr>
    <tr><th>Color</th><td>{{ $exp->prenda_color ?: '—' }}</td></tr>
    <tr><th>Motor No.</th><td>{{ $exp->prenda_motor }}</td></tr>
    <tr><th>Chasis / VIN No.</th><td>{{ $exp->prenda_chasis }}</td></tr>
    <tr><th>Licencia de tránsito No.</th><td>{{ $exp->prenda_matricula ?: '—' }}</td></tr>
    <tr><th>Avalúo declarado</th><td>{{ $exp->prenda_avaluo ? '$'.number_format($exp->prenda_avaluo, 0, ',', '.') : '—' }}</td></tr>
</table>

<h3>TERCERA. TENENCIA.</h3>
<p>EL GARANTE conserva la tenencia del bien y podrá usarlo conforme a su destinación natural, obligándose a mantenerlo en buen estado de conservación y funcionamiento, y a responder por su deterioro, pérdida o destrucción.</p>

<h3>CUARTA. DECLARACIONES DEL GARANTE.</h3>
<p>EL GARANTE declara que es propietario pleno del bien, que este se encuentra libre de embargos, gravámenes, prendas, limitaciones al dominio y pleitos pendientes, y que se halla al día en impuestos, revisión técnico-mecánica y seguro obligatorio.</p>

<h3>QUINTA. PROHIBICIONES.</h3>
<p>Mientras subsista la obligación garantizada, EL GARANTE no podrá enajenar, permutar, dar en prenda, arrendar por más de treinta (30) días, trasladar fuera del país, desmantelar ni gravar en forma alguna el bien, sin autorización escrita de EL ACREEDOR GARANTIZADO. La violación de esta cláusula da lugar a la aceleración de los plazos, sin perjuicio de las acciones penales que correspondan.</p>

<h3>SEXTA. INSCRIPCIÓN Y OPONIBILIDAD.</h3>
<p>EL GARANTE autoriza expresamente a EL ACREEDOR GARANTIZADO para inscribir la presente garantía mobiliaria en el <strong>Registro de Garantías Mobiliarias</strong> administrado por Confecámaras y para efectuar la anotación correspondiente ante el organismo de tránsito y el RUNT. Los gastos de inscripción, modificación y cancelación serán de cargo de EL GARANTE. Las partes reconocen que la garantía solo es oponible a terceros a partir de su inscripción.</p>

<h3>SÉPTIMA. SEGURO.</h3>
<p>EL GARANTE se obliga a mantener vigente el seguro obligatorio de accidentes de tránsito y a informar a EL ACREEDOR GARANTIZADO cualquier siniestro dentro de los tres (3) días siguientes a su ocurrencia.</p>

<h3>OCTAVA. EJECUCIÓN DE LA GARANTÍA.</h3>
<p>Incumplida la obligación garantizada, EL ACREEDOR GARANTIZADO podrá hacer efectiva la garantía por cualquiera de los mecanismos previstos en la Ley 1676 de 2013, incluidos el pago directo, la ejecución especial ante notario o cámara de comercio y la ejecución judicial, a su elección.</p>

<h3>NOVENA. ENTREGA DE DOCUMENTOS.</h3>
<p>EL GARANTE entrega en este acto a EL ACREEDOR GARANTIZADO copia de la licencia de tránsito del vehículo, la cual se restituirá una vez cancelada la totalidad de la obligación y levantada la garantía.</p>

<p>Para constancia se firma en la ciudad de {{ $exp->ciudad }}, {{ $fechaLarga }}.</p>

@include('finanzas.prestamos.documentos._firma', [
    'titulo' => 'EL ACREEDOR GARANTIZADO',
    'nombre' => $prestamista->nombre,
    'cedula' => $prestamista->cedula,
    'expedida' => $prestamista->expedida_en,
    'direccion' => $prestamista->direccion,
    'ciudad' => $prestamista->ciudad,
    'telefono' => $prestamista->telefono,
    'correo' => $prestamista->correo,
])

@include('finanzas.prestamos.documentos._firma', [
    'titulo' => 'EL GARANTE (propietario del vehículo)',
    'nombre' => $exp->prenda_propietario ?: $exp->deudor_nombre,
    'cedula' => $exp->prenda_propietario_cedula ?: $exp->deudor_cedula,
    'expedida' => $exp->prenda_propietario ? null : $exp->deudor_expedida_en,
    'direccion' => $exp->prenda_propietario ? null : $exp->deudor_direccion,
    'ciudad' => $exp->prenda_propietario ? null : $exp->deudor_ciudad,
    'telefono' => $exp->prenda_propietario ? null : $exp->deudor_telefono,
    'correo' => $exp->prenda_propietario ? null : $exp->deudor_correo,
])
