{{--
    Estado de la factura electrónica de una fila del historial.

    Recibe:
      $f        la factura
      $feEstados  mapa numero_factura → fila de dataico_envios (lo carga el controlador)
      $contexto 'cliente' o 'empresa'

    La factura de un lote empresarial se emite a nombre de la EMPRESA, por la
    suma de todos sus afiliados: una sola factura electrónica para varios
    trabajadores. Por eso en el historial de una persona no se muestra su
    estado —sería el mismo icono repetido en cada afiliado, sugiriendo que cada
    uno tiene su propia factura—. Ahí solo se deja la seña de que va por la
    empresa, y el estado real se ve en el historial de ella.
--}}
@php
    // El historial de empresa pasa filas agrupadas, no modelos: se accede a
    // todo con ?? para que sirvan las dos.
    $numero  = (int) ($f->numero_factura ?? 0);
    $envio   = $numero ? ($feEstados[$numero] ?? null) : null;
    $esDeEmpresa = ! empty($f->empresa_id ?? null);
    $marcada = ! empty($f->fe_marcada ?? null);
@endphp

@if($numero === 0)
    {{-- La factura 0 es el marcador de retiro pendiente, no se factura --}}
@elseif($esDeEmpresa && $contexto === 'cliente')
    <span title="Va en la factura de la empresa; su estado se ve en el historial de ella"
          style="font-size:.7rem;color:#94a3b8">🏢</span>
@elseif($envio && $envio->estado === 'enviado')
    <span title="Factura electrónica {{ $envio->dataico_numero ?: 'emitida' }}{{ $envio->cufe ? ' · CUFE '.\Illuminate\Support\Str::limit($envio->cufe, 24) : '' }}"
          style="font-size:.7rem;color:#059669;font-weight:700">🧾 {{ $envio->dataico_numero ?: 'OK' }}</span>
@elseif($envio && $envio->estado === 'error')
    <span title="{{ $envio->error_mensaje ?: 'La emisión falló' }}"
          style="font-size:.7rem;color:#dc2626;font-weight:700">🧾 error</span>
@elseif($envio && $envio->estado === 'omitido')
    <span title="{{ $envio->error_mensaje ?: 'Excluida de la facturación electrónica' }}"
          style="font-size:.7rem;color:#64748b">🧾 omitida</span>
@elseif($f->fe_marcada)
    <span title="Marcada como facturada, sin registro del número en Dataico"
          style="font-size:.7rem;color:#059669">🧾 ✓</span>
@else
    <span title="Sin factura electrónica" style="font-size:.7rem;color:#cbd5e1">🧾</span>
@endif
