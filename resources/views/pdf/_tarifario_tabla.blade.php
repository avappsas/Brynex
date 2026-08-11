{{-- Tabla de una opción del tarifario: una fila por nivel de riesgo.
     Espera $filas (ya filtradas y con entrega/total_mes calculados) y $plan. --}}
<table>
  <thead>
    <tr>
      <th style="text-align:left;">Riesgo ARL</th>
      <th class="num">Cobra al cliente</th>
      <th class="num">Entrega a {{ \Illuminate\Support\Str::limit($aliado->nombre ?? 'la empresa', 14) }}</th>
      <th class="num">Usted gana</th>
      <th class="num">Admon mes</th>
      <th class="num">Total mes cliente</th>
    </tr>
  </thead>
  <tbody>
    @foreach($filas as $nivelArl => $f)
    <tr>
      <td>{{ $plan->incluye_arl ? 'Nivel '.$nivelArl : 'Único' }}</td>
      <td class="num cobra">{{ $money($f['publico']) }}</td>
      <td class="num tenue">{{ $money($f['entrega']) }}</td>
      <td class="num gana">{{ $money($f['asesor']) }}</td>
      <td class="num gana">{{ $money($admonAses) }}</td>
      <td class="num tenue">{{ $money($f['total_mes']) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
<div class="nota">
  Desglose de lo que entrega: retiro {{ $money($filas->first()['retiro']) }}
  @if($filas->first()['otros'] > 0) · otros {{ $money($filas->first()['otros']) }} @endif
  · {{ $aliado->nombre ?? 'empresa' }} {{ $money($filas->first()['aliado']) }}
  @if($filas->count() > 1) <em>(varía por nivel de riesgo)</em> @endif
</div>
