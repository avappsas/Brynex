{{-- Tarifario del asesor: qué le cobra al cliente, qué entrega y qué gana. --}}
@php
    $money = fn ($v) => '$ '.number_format((float) $v, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26px 30px 46px 30px; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1e293b; }

    .cab      { border-bottom: 2px solid #0369a1; padding-bottom: 7px; margin-bottom: 10px; }
    .cab h1   { font-size: 14px; margin: 0 0 1px 0; color: #0f172a; }
    .cab .sub { font-size: 8.5px; color: #64748b; }
    .cab .der { text-align: right; font-size: 8px; color: #64748b; }

    .resumen      { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 5px;
                    padding: 6px 9px; margin-bottom: 11px; font-size: 8.5px; color: #0c4a6e; }
    .resumen b    { color: #0369a1; }

    .plan     { font-size: 10px; font-weight: bold; color: #fff; background: #475569;
                padding: 4px 8px; margin: 11px 0 0 0; }
    .modalidad{ font-size: 8.5px; font-weight: bold; color: #0369a1; background: #f0f9ff;
                padding: 3px 8px; border-left: 3px solid #0369a1; }

    table     { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    th        { background: #f1f5f9; color: #475569; font-size: 7.5px; padding: 3.5px 5px;
                border-bottom: 1px solid #cbd5e1; text-transform: uppercase; }
    td        { padding: 3.5px 5px; border-bottom: 1px solid #f1f5f9; font-size: 8.5px; }
    .num      { text-align: right; font-family: DejaVu Sans Mono, monospace; }
    .gana     { color: #15803d; font-weight: bold; }
    .cobra    { color: #0f172a; font-weight: bold; }
    .tenue    { color: #94a3b8; }

    .pie      { position: fixed; bottom: -28px; left: 0; right: 0; font-size: 7px;
                color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; }
    .nota     { font-size: 7.5px; color: #94a3b8; margin-top: 2px; }
</style>
</head>
<body>

<div class="cab">
  <table style="border:0;">
    <tr>
      <td style="border:0;padding:0;">
        <h1>Tarifario comercial</h1>
        <div class="sub">
          <strong>{{ $asesor->nombre }}</strong>
          @if($asesor->cedula) · CC {{ number_format((int) $asesor->cedula, 0, ',', '.') }} @endif
          @if($asesor->nivel) · {{ $asesor->nivel->nombre }} @endif
        </div>
      </td>
      <td style="border:0;padding:0;" class="der">
        {{ $aliado->nombre ?? '' }}<br>
        Generado el {{ now()->format('d/m/Y') }}
      </td>
    </tr>
  </table>
</div>

<div class="resumen">
  Por cada afiliación usted <b>cobra al cliente</b> el valor de la columna «Cobra al cliente» y
  <b>gana</b> lo de la columna verde. Además, por cada cliente activo gana
  <b>{{ $money($admonAses) }} mensuales</b> de administración mientras el cliente siga vigente.
</div>

@php
  // Mismo orden que la pantalla de Configuración: primero las modalidades normales y al
  // final Tiempo Parcial en una sola sección. Sus 8 variantes se cobran igual entre sí,
  // así que repetir la misma tabla ocho veces solo llena páginas.
  $normales = array_filter($matriz, fn ($t) => ! $t['tiempo_parcial']);
  $parcial  = array_filter($matriz, fn ($t) => $t['tiempo_parcial']);

  // Una fila de tabla por opción, con las columnas ya calculadas.
  $prepararFilas = function ($o) use ($gridSs, $seguro) {
      return collect($o['filas'])
          ->filter(fn ($f) => $f['asesor'] !== null && $f['publico'] > 0)
          ->map(function ($f) use ($gridSs, $seguro) {
              $f['entrega'] = $f['retiro'] + $f['otros'] + $f['aliado'];
              $f['total_mes'] = ($gridSs[$f['clave']] ?? 0) + $f['admon'] + $seguro;

              return $f;
          });
  };

  // Firma de una opción: si dos modalidades cobran exactamente lo mismo, van juntas.
  $firma = fn ($filas) => $filas->map(fn ($f) => $f['publico'].'|'.$f['entrega'].'|'.$f['asesor'])->implode(';');
@endphp

@php $hayAlgo = false; @endphp

{{-- ══ Modalidades normales: una sección por modalidad, sus planes dentro ══ --}}
@foreach($normales as $tarjeta)
  @php
    $opciones = collect($tarjeta['opciones'])
        ->map(fn ($o) => $o + ['_filas' => $prepararFilas($o)])
        ->filter(fn ($o) => $o['_filas']->isNotEmpty());
  @endphp
  @continue($opciones->isEmpty())
  @php $hayAlgo = true; @endphp

  <div class="plan">{{ $tarjeta['nombre'] }}</div>

  @foreach($opciones as $o)
    {{-- En una tarjeta de grupo la etiqueta es la modalidad (Gestión ARL); en una normal, el plan. --}}
    <div class="modalidad">{{ $o['etiqueta'] }}</div>
    @include('pdf._tarifario_tabla', ['filas' => $o['_filas'], 'plan' => $o['plan']])
  @endforeach
@endforeach

{{-- ══ Tiempo Parcial: una sola sección, con las variantes agrupadas por precio ══ --}}
@php
  // plan → firma → ['filas' => …, 'variantes' => [nombres de modalidad]]
  $tpPorPlan = [];
  foreach ($parcial as $tarjeta) {
      foreach ($tarjeta['opciones'] as $o) {
          $filas = $prepararFilas($o);
          if ($filas->isEmpty()) {
              continue;
          }
          $k = $firma($filas);
          $tpPorPlan[$o['plan']->nombre][$k]['filas'] = $filas;
          $tpPorPlan[$o['plan']->nombre][$k]['plan'] = $o['plan'];
          $tpPorPlan[$o['plan']->nombre][$k]['variantes'][] = $tarjeta['nombre'];
      }
  }
@endphp

@if(count($tpPorPlan))
  @php $hayAlgo = true; @endphp
  <div class="plan">Tiempo Parcial</div>

  @foreach($tpPorPlan as $nombrePlan => $grupos)
    @foreach($grupos as $g)
      <div class="modalidad">{{ $nombrePlan }}</div>
      @include('pdf._tarifario_tabla', ['filas' => $g['filas'], 'plan' => $g['plan']])
      <div class="nota">
        Aplica igual a {{ count($g['variantes']) }}
        {{ count($g['variantes']) === 1 ? 'variante' : 'variantes' }}:
        {{ implode(' · ', $g['variantes']) }}
      </div>
    @endforeach
  @endforeach
@endif

@if(! $hayAlgo)
  <div style="padding:20px;text-align:center;color:#94a3b8;">
    Este asesor todavía no tiene tarifas configuradas.
  </div>
@endif

<div class="pie">
  «Total mes cliente» es un <strong>estimado</strong> calculado sobre el salario mínimo
  ({{ $money($salarioMin) }}): la seguridad social real depende del salario de cada persona.
  Precios sujetos a cambio · {{ $aliado->nombre ?? '' }} · {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
