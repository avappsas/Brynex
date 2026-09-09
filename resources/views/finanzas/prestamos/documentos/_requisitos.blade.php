{{-- Hoja de control: qué se le pide al deudor y en qué orden debe quedar el
     PDF único que después se sube al expediente. --}}
<h1>DOCUMENTOS REQUERIDOS</h1>
<p class="sello">Expediente {{ $exp->pagare_numero }} · {{ mb_strtoupper($exp->deudor_nombre) }} · C.C. {{ $exp->deudor_cedula }}</p>

<p>Antes del desembolso deben recogerse los documentos de esta lista. Al final, todo se escanea en <strong>un solo archivo PDF</strong>, en el orden aquí indicado, y se sube al expediente del préstamo.</p>

<h3>1. Documentos que firma el deudor{{ $exp->tiene_codeudor ? ' y el codeudor' : '' }}</h3>
<table>
    <tr><th style="width:8%;">✔</th><th>Documento</th><th style="width:32%;">Observación</th></tr>
    <tr><td></td><td>Contrato de mutuo con interés</td><td>Firma y huella de todas las partes en cada página final</td></tr>
    <tr><td></td><td>Pagaré No. {{ $exp->pagare_numero }}</td><td>Firma y huella; no se llena el valor a mano</td></tr>
    <tr><td></td><td>Carta de instrucciones</td><td>Firma y huella</td></tr>
    @if($exp->tiene_prenda)
    <tr><td></td><td>Contrato de prenda sin tenencia</td><td>Firma del propietario del vehículo</td></tr>
    @endif
    <tr><td></td><td>Recibo de desembolso</td><td>Se firma en el momento de entregar el dinero</td></tr>
</table>

<h3>2. Documentos del deudor</h3>
<table>
    <tr><th style="width:8%;">✔</th><th>Documento</th><th style="width:32%;">Observación</th></tr>
    <tr><td></td><td>Copia de la cédula de ciudadanía, ampliada al 150%</td><td>Ambas caras, legible</td></tr>
    <tr><td></td><td>Soporte de ingresos</td><td>Certificado laboral, contrato, RUT o declaración de la actividad</td></tr>
    <tr><td></td><td>Extractos o movimientos bancarios de los últimos 3 meses</td><td>Si maneja cuenta</td></tr>
    <tr><td></td><td>Recibo de servicio público del domicilio</td><td>No mayor a 60 días</td></tr>
    <tr><td></td><td>Dos referencias personales o familiares</td><td>Nombre, parentesco y teléfono</td></tr>
</table>

@if($exp->tiene_codeudor)
<h3>3. Documentos del codeudor solidario</h3>
<table>
    <tr><th style="width:8%;">✔</th><th>Documento</th><th style="width:32%;">Observación</th></tr>
    <tr><td></td><td>Copia de la cédula de ciudadanía, ampliada al 150%</td><td>Ambas caras, legible</td></tr>
    <tr><td></td><td>Soporte de ingresos</td><td>Certificado laboral, contrato, RUT o declaración de la actividad</td></tr>
    <tr><td></td><td>Recibo de servicio público del domicilio</td><td>No mayor a 60 días</td></tr>
</table>
@endif

@if($exp->tiene_prenda)
<h3>{{ $exp->tiene_codeudor ? '4' : '3' }}. Documentos del vehículo en garantía (placa {{ $exp->prenda_placa }})</h3>
<table>
    <tr><th style="width:8%;">✔</th><th>Documento</th><th style="width:32%;">Observación</th></tr>
    <tr><td></td><td>Licencia de tránsito (tarjeta de propiedad)</td><td>A nombre del garante</td></tr>
    <tr><td></td><td>SOAT vigente</td><td></td></tr>
    <tr><td></td><td>Revisión técnico-mecánica vigente</td><td>Si el modelo la exige</td></tr>
    <tr><td></td><td>Consulta de RUNT y de comparendos</td><td>Verificar que no tenga prendas ni embargos</td></tr>
    <tr><td></td><td>Fotografías del vehículo</td><td>Frente, lateral y placa</td></tr>
    <tr><td></td><td>Inscripción en el Registro de Garantías Mobiliarias</td><td>Sin esto la prenda no es oponible a terceros</td></tr>
</table>
@endif

<h3>Cómo armar el PDF que se sube al sistema</h3>
<p>Un único archivo PDF, escaneado en este orden: (1) contrato de mutuo firmado, (2) pagaré firmado, (3) carta de instrucciones firmada,@if($exp->tiene_prenda) (4) contrato de prenda firmado,@endif ({{ $exp->tiene_prenda ? '5' : '4' }}) recibo de desembolso firmado, y a continuación las cédulas y demás soportes de esta lista. Cada hoja firmada debe llevar firma y huella; las copias deben ser legibles.</p>
