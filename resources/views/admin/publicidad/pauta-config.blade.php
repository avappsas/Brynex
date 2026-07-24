@extends('layouts.app')
@section('modulo', 'Publicidad')

@section('contenido')
<div style="max-width:640px;margin:0 auto;">

<a href="{{ route('admin.publicidad.index') }}"
   style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.85rem">
    ← Volver al historial
</a>
<h1 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0 0 .25rem;">💸 Pauta pagada</h1>
<p style="font-size:.83rem;color:#64748b;margin:0 0 1.25rem;">Impulsa piezas ya publicadas en Facebook con presupuesto real, dentro de un tope mensual duro.</p>

@if(session('success'))
    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;color:#15803d;padding:.65rem 1rem;margin-bottom:1rem;font-size:.83rem;">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:.65rem 1rem;margin-bottom:1rem;font-size:.83rem;">❌ {{ session('error') }}</div>
@endif
@if($errors->any())
    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:.65rem 1rem;margin-bottom:1rem;font-size:.83rem;">
        @foreach($errors->all() as $e) · {{ $e }} @endforeach
    </div>
@endif

<form method="POST" action="{{ route('admin.publicidad.pauta.config.update') }}"
      style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;">
    @csrf

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <div style="font-size:.9rem;font-weight:700;color:#0f172a;">Pauta pagada</div>
            <div style="font-size:.73rem;color:#94a3b8;">Sin esto activo, no se puede crear ni activar ninguna pauta.</div>
        </div>
        <select name="activo" style="padding:.45rem .7rem;border:1.5px solid {{ $config->activo ? '#86efac' : '#cbd5e1' }};border-radius:8px;font-size:.85rem;font-weight:700;color:{{ $config->activo ? '#15803d' : '#475569' }};">
            <option value="1" @selected($config->activo)>🟢 Activa</option>
            <option value="0" @selected(!$config->activo)>⚪ Apagada</option>
        </select>
    </div>

    <div style="margin-bottom:1rem;">
        <label style="display:block;font-size:.72rem;font-weight:600;color:#334155;margin-bottom:.3rem;">ID de la cuenta publicitaria de Meta</label>
        <input type="text" name="ad_account_id" value="{{ $config->ad_account_id }}" placeholder="763050131388073"
               style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.83rem;font-family:monospace;">
        <div style="font-size:.7rem;color:#94a3b8;margin-top:.25rem;">Meta Business Settings → Cuentas publicitarias → el "Identificador" (sin el prefijo act_).</div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:.5rem;">
        <div>
            <label style="display:block;font-size:.72rem;font-weight:600;color:#334155;margin-bottom:.3rem;">Tope mensual duro (COP)</label>
            <input type="number" step="1" min="0" name="limite_mensual_cop" value="{{ (int) $config->limite_mensual_cop }}"
                   style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.83rem;">
            <div style="font-size:.7rem;color:#94a3b8;margin-top:.25rem;">Nunca se puede crear ni activar una pauta que lo supere, sin excepción.</div>
        </div>
        <div>
            <label style="display:block;font-size:.72rem;font-weight:600;color:#334155;margin-bottom:.3rem;">Presupuesto diario de prueba (COP)</label>
            <input type="number" step="1" min="1000" name="presupuesto_diario_default_cop" value="{{ (int) $config->presupuesto_diario_default_cop }}" required
                   style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.83rem;">
            <div style="font-size:.7rem;color:#94a3b8;margin-top:.25rem;">Punto de partida para piezas sin historial. Mínimo real de Meta: ~$3.300 COP/día.</div>
        </div>
    </div>

    <div style="font-size:.72rem;color:#64748b;background:#f8fafc;border-radius:8px;padding:.65rem .85rem;margin:1rem 0;">
        Gastado este mes: <strong>${{ number_format($config->gastadoEsteMes(), 0, ',', '.') }} COP</strong>
        · Disponible: <strong>${{ number_format($config->disponibleEsteMes(), 0, ',', '.') }} COP</strong>
    </div>

    <button type="submit" style="background:#2563eb;color:#fff;border:none;font-size:.85rem;font-weight:700;padding:.6rem 1.4rem;border-radius:8px;cursor:pointer;">
        Guardar
    </button>
</form>

</div>
@endsection
