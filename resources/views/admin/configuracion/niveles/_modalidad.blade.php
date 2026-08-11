{{-- Una tarjeta de la matriz: cabecera plegable → selector de opción → tabla de riesgos.
     Espera $t (tarjeta con sus opciones). Una opción es un par (modalidad, plan): en una
     tarjeta normal son los planes de la modalidad, en una de grupo (Solo ARL) son las
     modalidades del grupo. El estado `abierto` y `opcion` los aporta el x-data contenedor. --}}
@php
  $card    = $t['clave'];
  $primera = $t['opciones'][0]['modalidad_id'].'_'.$t['opciones'][0]['plan']->id;
@endphp

<div style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:0.6rem;overflow:hidden;">

  <div @click="abierto = (abierto === '{{ $card }}' ? null : '{{ $card }}')"
       style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;padding:0.65rem 0.9rem;cursor:pointer;background:#f8fafc;"
       onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
    <div style="display:flex;align-items:center;gap:0.6rem;min-width:0;">
      <i class="fas fa-chevron-right" style="color:#94a3b8;font-size:0.7rem;transition:transform .15s;"
         :style="abierto === '{{ $card }}' ? 'transform:rotate(90deg)' : ''"></i>
      <span style="font-weight:700;color:#0f172a;font-size:0.85rem;">{{ $t['nombre'] }}</span>
      <span style="font-size:0.66rem;color:#94a3b8;">{{ count($t['opciones']) }} {{ count($t['opciones']) === 1 ? 'opción' : 'opciones' }}</span>
    </div>
    <span data-resumen-card="{{ $card }}" style="font-size:0.7rem;color:#64748b;white-space:nowrap;"></span>
  </div>

  <div x-show="abierto === '{{ $card }}'" x-cloak style="padding:0.85rem 0.9rem;border-top:1px solid #e2e8f0;">

    {{-- Selector de opción --}}
    <div style="display:flex;flex-wrap:wrap;gap:0.45rem;margin-bottom:0.85rem;">
      @foreach($t['opciones'] as $o)
      @php
        $ok      = $o['modalidad_id'].'_'.$o['plan']->id;
        $llenasO = collect($o['filas'])->filter(fn ($f) => $f['asesor'] !== null)->count();
        $totalO  = count($o['niveles_arl']);
      @endphp
      <button type="button"
          @click="opcion['{{ $card }}'] = '{{ $ok }}'"
          :class="(opcion['{{ $card }}'] ?? '{{ $primera }}') === '{{ $ok }}' ? 'plan-on' : 'plan-off'"
          class="btn-plan">
        <span class="btn-plan-nombre">{{ $o['etiqueta'] }}</span>
        <span class="btn-plan-meta" data-avance-opcion="{{ $ok }}">
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
      $ok    = $modId.'_'.$plan->id;
    @endphp
    <div x-show="(opcion['{{ $card }}'] ?? '{{ $primera }}') === '{{ $ok }}'" x-cloak>

      <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.45rem;">
        <span style="font-size:0.7rem;color:#94a3b8;">
          {{ $o['etiqueta'] !== $plan->nombre ? $plan->nombre : '' }}
        </span>
        @if($plan->incluye_arl)
        <button type="button" onclick="replicarNivel({{ $plan->id }}, {{ $modId }})"
            style="font-size:0.68rem;padding:0.25rem 0.55rem;border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:#475569;cursor:pointer;"
            title="Copia lo del asesor del riesgo 1 a los riesgos 2 al 5">
          ⧉ Replicar riesgo 1
        </button>
        @endif
      </div>

      <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:0.79rem;">
        <thead>
          <tr style="background:#f8fafc;">
            <th style="padding:0.4rem 0.5rem;text-align:left;color:#475569;font-size:0.65rem;font-weight:700;">RIESGO</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#94a3b8;font-size:0.65rem;font-weight:700;">AFILIACIÓN</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#94a3b8;font-size:0.65rem;font-weight:700;">− RETIRO</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#94a3b8;font-size:0.65rem;font-weight:700;">− OTROS</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#7c3aed;font-size:0.65rem;font-weight:700;">− GANA ASESOR</th>
            <th style="padding:0.4rem 0.5rem;text-align:right;color:#166534;font-size:0.65rem;font-weight:700;">= QUEDA ALIADO</th>
          </tr>
        </thead>
        <tbody>
          @foreach($o['niveles_arl'] as $n)
          @php $f = $o['filas'][$n]; @endphp
          <tr style="border-bottom:1px solid #f1f5f9;">
            <td style="padding:0.3rem 0.5rem;font-weight:700;color:#0f172a;white-space:nowrap;">
              {{ $plan->incluye_arl ? "Nivel $n" : 'Único' }}
            </td>
            <td style="padding:0.3rem 0.5rem;text-align:right;font-family:monospace;color:#64748b;">
              {{ number_format($f['publico'], 0, ',', '.') }}
            </td>
            <td style="padding:0.3rem 0.5rem;text-align:right;font-family:monospace;color:#94a3b8;">
              {{ number_format($f['retiro'], 0, ',', '.') }}
            </td>
            <td style="padding:0.3rem 0.5rem;text-align:right;font-family:monospace;color:#94a3b8;">
              {{ number_format($f['otros'], 0, ',', '.') }}
            </td>
            <td style="padding:0.25rem 0.4rem;text-align:right;">
              <input type="text"
                  name="matriz[{{ $plan->id }}][{{ $modId }}][{{ $n }}]"
                  value="{{ $f['asesor'] !== null ? number_format($f['asesor'], 0, ',', '.') : '' }}"
                  placeholder="0"
                  class="input-miles celda-asesor"
                  data-clave="{{ $f['clave'] }}" data-card="{{ $card }}" data-opcion="{{ $ok }}"
                  data-plan="{{ $plan->id }}" data-mod="{{ $modId }}" data-nivel="{{ $n }}"
                  oninput="recalcAliado(this)"
                  style="width:105px;padding:0.3rem 0.45rem;border:1.5px solid #ddd6fe;border-radius:6px;font-size:0.8rem;font-family:monospace;text-align:right;font-weight:700;color:#7c3aed;background:#fff;">
            </td>
            <td style="padding:0.3rem 0.5rem;text-align:right;font-family:monospace;font-weight:700;"
                data-aliado="{{ $f['clave'] }}">
              {{ number_format($f['aliado'], 0, ',', '.') }}
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    </div>
    @endforeach

  </div>
</div>
