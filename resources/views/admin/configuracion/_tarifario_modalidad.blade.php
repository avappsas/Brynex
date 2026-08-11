{{-- Una tarjeta del tarifario: cabecera plegable → selector de opción → tabla de riesgos.
     Espera $t (tarjeta con sus opciones) y $r (resumen de celdas llenas/total).

     Una opción es un par (modalidad, plan). En una tarjeta normal las opciones son los planes
     de esa modalidad; en una de grupo (Solo ARL) son las modalidades del grupo. El estado
     `abierto` y `opcion` los aporta el x-data de la tarjeta contenedora. --}}
@php
  $card    = $t['clave'];
  $primera = $t['opciones'][0]['modalidad_id'].'_'.$t['opciones'][0]['plan']->id;
@endphp

<div style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:0.6rem;overflow:hidden;">

  {{-- Cabecera --}}
  <div @click="abierto = (abierto === '{{ $card }}' ? null : '{{ $card }}')"
       style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;padding:0.65rem 0.9rem;cursor:pointer;background:#f8fafc;"
       onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
    <div style="display:flex;align-items:center;gap:0.6rem;min-width:0;">
      <i class="fas fa-chevron-right" style="color:#94a3b8;font-size:0.7rem;transition:transform .15s;"
         :style="abierto === '{{ $card }}' ? 'transform:rotate(90deg)' : ''"></i>
      <span style="font-weight:700;color:#0f172a;font-size:0.85rem;">{{ $t['nombre'] }}</span>
      <span style="font-size:0.66rem;color:#94a3b8;">{{ count($t['opciones']) }} {{ count($t['opciones']) === 1 ? 'opción' : 'opciones' }}</span>
    </div>
    <div style="white-space:nowrap;">
      @if($r['llenas'] < $r['total'])
      <span style="background:#fef9c3;color:#92400e;font-size:0.62rem;font-weight:700;padding:0.12rem 0.45rem;border-radius:999px;">
        {{ $r['total'] - $r['llenas'] }} sin tarifar
      </span>
      @else
      <span style="background:#dcfce7;color:#166534;font-size:0.62rem;font-weight:700;padding:0.12rem 0.45rem;border-radius:999px;">completo</span>
      @endif
    </div>
  </div>

  <div x-show="abierto === '{{ $card }}'" x-cloak style="padding:0.85rem 0.9rem;border-top:1px solid #e2e8f0;">

    {{-- Selector de opción --}}
    <div style="display:flex;flex-wrap:wrap;gap:0.45rem;margin-bottom:0.85rem;">
      @foreach($t['opciones'] as $o)
      @php
        $ok      = $o['modalidad_id'].'_'.$o['plan']->id;
        $llenasO = 0;
        foreach ($o['niveles_arl'] as $n) {
            $c = $o['niveles'][$n];
            if ($c['costo_afiliacion'] !== null || $c['retiro'] !== null || $c['otros'] !== null || $c['administracion'] !== null) $llenasO++;
        }
        $totalO = count($o['niveles_arl']);
      @endphp
      <button type="button"
          @click="opcion['{{ $card }}'] = '{{ $ok }}'"
          :class="(opcion['{{ $card }}'] ?? '{{ $primera }}') === '{{ $ok }}' ? 'plan-on' : 'plan-off'"
          class="btn-plan">
        <span class="btn-plan-nombre">{{ $o['etiqueta'] }}</span>
        <span class="btn-plan-meta">
          @if($o['plan']->incluye_arl) 5 riesgos · @endif
          @if($llenasO >= $totalO)
            <span style="color:#16a34a;font-weight:700;">✓ {{ $llenasO }}/{{ $totalO }}</span>
          @else
            <span style="color:#b45309;font-weight:700;">{{ $llenasO }}/{{ $totalO }}</span>
          @endif
        </span>
      </button>
      @endforeach
    </div>

    @foreach($t['opciones'] as $o)
    @php
      $plan  = $o['plan'];
      $modId = $o['modalidad_id'];
      $resp  = $o['respaldos'];
      $ok    = $modId.'_'.$plan->id;
    @endphp
    <div x-show="(opcion['{{ $card }}'] ?? '{{ $primera }}') === '{{ $ok }}'" x-cloak>

      <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.45rem;flex-wrap:wrap;">
        <span style="font-size:0.7rem;color:#64748b;">
          {{ $o['etiqueta'] !== $plan->nombre ? $plan->nombre.' · ' : '' }}
          Afiliación general: <strong style="color:#475569;">${{ number_format($resp['costo_afiliacion'], 0, ',', '.') }}</strong>
          <span style="color:#cbd5e1;">·</span> se usa donde dejes la casilla vacía
        </span>
        <div style="display:flex;gap:0.4rem;">
          @if($plan->incluye_arl)
          <button type="button" onclick="replicarRiesgos({{ $plan->id }}, {{ $modId }})"
              style="font-size:0.68rem;padding:0.25rem 0.55rem;border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:#475569;cursor:pointer;"
              title="Copia los valores del riesgo 1 a los riesgos 2 al 5">
            ⧉ Replicar riesgo 1
          </button>
          <button type="button" onclick="ajustarPorArl({{ $plan->id }}, {{ $modId }})"
              style="font-size:0.68rem;padding:0.25rem 0.55rem;border:1px solid #bae6fd;border-radius:6px;background:#f0f9ff;color:#0369a1;cursor:pointer;"
              title="Sube la afiliación de cada riesgo según lo que sube la prima ARL, redondeado a miles">
            ↗ Ajustar por ARL
          </button>
          @endif
          <button type="button" onclick="copiarDePlan({{ $plan->id }}, {{ $modId }})"
              style="font-size:0.68rem;padding:0.25rem 0.55rem;border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:#475569;cursor:pointer;"
              title="Copia esta misma modalidad desde otro plan">
            ⇄ Copiar de otro plan
          </button>
        </div>
      </div>

      <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:0.78rem;">
        <thead>
          <tr style="background:#f0f9ff;">
            <th style="padding:0.4rem 0.5rem;text-align:left;color:#0369a1;font-size:0.65rem;font-weight:700;">RIESGO</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#0369a1;font-size:0.65rem;font-weight:700;">AFILIACIÓN</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#0369a1;font-size:0.65rem;font-weight:700;" title="Parte de la afiliación que va al retiro">RETIRO</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#0369a1;font-size:0.65rem;font-weight:700;" title="Bolsa del aliado dentro de la afiliación">OTROS</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#0369a1;font-size:0.65rem;font-weight:700;">ADMON MES</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#64748b;font-size:0.65rem;font-weight:700;">SEG. SOCIAL</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#166534;font-size:0.65rem;font-weight:700;">TOTAL MES</th>
          </tr>
        </thead>
        <tbody>
          @foreach($o['niveles_arl'] as $n)
          @php
            $c     = $o['niveles'][$n];
            $clave = "{$plan->id}_{$modId}_{$n}";
            $ss    = $gridSs[$clave] ?? 0;
          @endphp
          <tr style="border-bottom:1px solid #f1f5f9;" data-celda="{{ $clave }}">
            <td style="padding:0.3rem 0.5rem;font-weight:700;color:#0f172a;white-space:nowrap;">
              {{ $plan->incluye_arl ? "Nivel $n" : 'Único' }}
            </td>
            @foreach([
              'costo_afiliacion' => $resp['costo_afiliacion'],
              'retiro'           => round($resp['costo_afiliacion'] * $resp['retiro_pct'] / 100),
              'otros'            => 0,
              'administracion'   => $resp['administracion'],
            ] as $campo => $respaldo)
            <td style="padding:0.25rem 0.4rem;text-align:right;">
              <input type="text"
                  name="tarifario[{{ $plan->id }}][{{ $modId }}][{{ $n }}][{{ $campo }}]"
                  value="{{ $c[$campo] !== null ? number_format($c[$campo], 0, ',', '.') : '' }}"
                  placeholder="{{ number_format($respaldo, 0, ',', '.') }}"
                  class="input-miles tarifa-celda"
                  data-card="{{ $card }}" data-plan="{{ $plan->id }}" data-mod="{{ $modId }}"
                  data-nivel="{{ $n }}" data-campo="{{ $campo }}"
                  @if($campo === 'administracion') oninput="recalcTotal(this)" @endif
                  style="width:92px;padding:0.28rem 0.4rem;border:1px solid #e2e8f0;border-radius:5px;font-size:0.76rem;font-family:monospace;text-align:right;background:{{ $c[$campo] !== null ? '#fff' : '#fafafa' }};"
                  onfocus="this.style.borderColor='#0369a1';this.style.background='#fff'"
                  onblur="this.style.borderColor='#e2e8f0'">
            </td>
            @endforeach
            <td style="padding:0.3rem 0.5rem;text-align:right;color:#94a3b8;font-family:monospace;font-size:0.74rem;">
              {{ number_format($ss, 0, ',', '.') }}
            </td>
            <td style="padding:0.3rem 0.5rem;text-align:right;font-weight:700;color:#166534;font-family:monospace;font-size:0.78rem;"
                data-total="{{ $clave }}">
              {{ number_format($ss + ($c['administracion'] ?? $resp['administracion']) + $seguroBase, 0, ',', '.') }}
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      </div>
      <div style="font-size:0.66rem;color:#94a3b8;margin-top:0.4rem;">
        Total mes = seguridad social sobre salario mínimo + admon + seguro (${{ number_format($seguroBase, 0, ',', '.') }}).
        Es un <strong>estimado</strong>: la seguridad social real depende del salario de cada contrato.
      </div>
    </div>
    @endforeach

  </div>
</div>
