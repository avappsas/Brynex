{{-- Bloque de firma con los datos de contacto al pie, como lo exige la
     cláusula de notificaciones: la dirección aquí registrada es la válida. --}}
<div class="firma">
    <p class="firma-linea">____________________________________________</p>
    <p class="firma-linea"><strong>{{ mb_strtoupper($nombre) }}</strong></p>
    <p class="dato">{{ $titulo }}</p>
    <p class="dato">C.C. No. {{ $cedula ?: '______________________' }}{{ $expedida ? ' de '.$expedida : '' }}</p>
    <p class="dato">Dirección: {{ $direccion ?: '______________________' }} &#160; Ciudad: {{ $ciudad ?: '______________' }}</p>
    <p class="dato">Teléfono: {{ $telefono ?: '______________________' }} &#160; Correo: {{ $correo ?: '______________________' }}</p>
    <p class="dato">Huella índice derecho: ______</p>
</div>
