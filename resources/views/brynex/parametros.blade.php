@extends('layouts.app')
@section('modulo', 'BryNex')

@section('contenido')
<div style="max-width:1100px;margin:0 auto;">

<div style="margin-bottom:1.1rem;">
  <a href="{{ route('brynex.hub') }}"
     style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.35rem">
    ← Volver a BryNex
  </a>
  <h1 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0;">🔒 Parámetros BryNex</h1>
  <p style="font-size:0.78rem;color:#64748b;margin:0;">
    Valores del sistema, iguales para todos los aliados: cambiar uno mueve la cotización de la plataforma entera.
  </p>
</div>

@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
  <strong>Errores:</strong> @foreach($errors->all() as $e) · {{ $e }} @endforeach
</div>
@endif

<form method="POST" action="{{ route('brynex.parametros.guardar') }}">
@csrf

{{-- ══ Porcentajes y valores de seguridad social ══ --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:1rem;">
  <div style="font-size:0.72rem;font-weight:700;color:#0891b2;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.85rem;">
    🏛️ Seguridad social y salario mínimo
  </div>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0.75rem;">
    @foreach([
      'salario_minimo'                => ['label'=>'Salario Mínimo',   'prefix'=>'$',  'suffix'=>'',  'step'=>'1',    'decimals'=>0],
      'pct_salud_dependiente'         => ['label'=>'EPS Dependiente',  'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_salud_independiente'       => ['label'=>'EPS Independiente','prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_pension_dependiente'       => ['label'=>'Pensión Dep.',     'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_pension_independiente'     => ['label'=>'Pensión Indep.',   'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_caja_dependiente'          => ['label'=>'Caja Dep.',        'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_caja_independiente_alto'   => ['label'=>'Caja Indep. Alt.', 'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_caja_independiente_bajo'   => ['label'=>'Caja Indep. Baj.', 'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_ibc_independiente_sugerido'=> ['label'=>'% IBC Sugerido',   'prefix'=>'',   'suffix'=>'%', 'step'=>'1',    'decimals'=>2],
      'porcentaje_iva'                => ['label'=>'IVA Admin',        'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'tasa_mora_pila'                => ['label'=>'Tasa Mora PILA (Art.635 ET)', 'prefix'=>'', 'suffix'=>'% E.A.', 'step'=>'0.01', 'decimals'=>2],
    ] as $clave => $cfg)
    @php $valor = $configBrynex[$clave]->valor ?? null; @endphp
    <div style="background:#f8fafc;border-radius:8px;padding:0.65rem 0.75rem;border:1px solid #bfdbfe;overflow:hidden;">
      <div style="font-size:0.6rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:0.3rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $cfg['label'] }}</div>
      <div style="display:flex;align-items:center;gap:0.2rem;">
        @if($cfg['prefix']) <span style="color:#64748b;font-size:0.72rem;flex-shrink:0;">{{ $cfg['prefix'] }}</span> @endif
        <input type="number" step="{{ $cfg['step'] }}" min="0"
            name="brynex[{{ $clave }}]"
            value="{{ $valor !== null ? $valor : '' }}"
            style="width:100%;padding:0.28rem 0.35rem;border:1px solid #93c5fd;border-radius:5px;font-size:0.82rem;font-family:monospace;font-weight:700;background:#fff;min-width:0;color:#0f172a;box-sizing:border-box;">
        @if($cfg['suffix']) <span style="color:#64748b;font-size:0.72rem;flex-shrink:0;">{{ $cfg['suffix'] }}</span> @endif
      </div>
    </div>
    @endforeach
  </div>
</div>

{{-- ══ Tarifas ARL por nivel de riesgo ══ --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:1rem;">
  <div style="font-size:0.72rem;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.85rem;">
    🦺 Tarifas ARL por Nivel de Riesgo
    <span style="font-size:0.65rem;color:#94a3b8;text-transform:none;font-weight:400;margin-left:0.5rem;">
      Las que usa el sistema para cotizar la ARL de todos los aliados
    </span>
  </div>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0.75rem;">
    @foreach($arlGlobal as $nivel => $global)
    <div style="border:1px solid #e2e8f0;border-radius:10px;padding:0.85rem;background:#f8fafc;">
      <div style="font-size:0.8rem;font-weight:700;color:#0f172a;margin-bottom:0.5rem;">Nivel {{ $nivel }}</div>
      <div style="font-size:0.68rem;color:#64748b;margin-bottom:0.5rem;line-height:1.3;min-height:2.4em;">
        {{ $global->descripcion ?? '' }}
      </div>
      <label style="display:block;font-size:0.65rem;font-weight:700;color:#475569;margin-bottom:0.2rem;">PORCENTAJE</label>
      <div style="display:flex;align-items:center;gap:0.35rem;">
        <input type="number" step="0.0001" min="0" max="100" required
            name="arl[{{ $nivel }}][porcentaje]"
            value="{{ $global->porcentaje }}"
            style="flex:1;padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem;font-family:monospace;">
        <span style="color:#64748b;font-size:0.75rem;">%</span>
      </div>
    </div>
    @endforeach
  </div>
</div>

<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:0.7rem 1rem;margin-bottom:1rem;font-size:0.74rem;color:#78350f;line-height:1.5;">
  Al guardar se recalcula la grilla de seguridad social de <strong>todos</strong> los aliados, que es la
  que alimenta el «Total mes» del tarifario y el cotizador. Las facturas ya emitidas no cambian.
</div>

<div style="display:flex;justify-content:flex-end;gap:0.75rem;">
  <a href="{{ route('brynex.hub') }}"
     style="padding:0.6rem 1.25rem;border:1px solid #cbd5e1;border-radius:8px;color:#475569;text-decoration:none;font-size:0.85rem;">
    Cancelar
  </a>
  <button type="submit"
      style="padding:0.6rem 2rem;background:linear-gradient(135deg,#0891b2,#0e7490);border:none;border-radius:9px;color:#fff;font-size:0.88rem;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(8,145,178,0.35);">
    💾 Guardar Parámetros
  </button>
</div>

</form>
</div>
@endsection
