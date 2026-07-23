@extends('layouts.app')
@section('modulo', 'Redes Sociales')

@section('contenido')
<div style="max-width:800px;margin:0 auto;">

<div style="margin-bottom:1.1rem;">
    <a href="{{ route('admin.configuracion.hub') }}"
       style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.35rem">
        ← Volver a Configuración
    </a>
    <h1 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0;">📲 Redes Sociales</h1>
    <p style="font-size:0.78rem;color:#64748b;margin:0;">Conecta Facebook e Instagram para publicar contenido desde Brynex.</p>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">✅ {{ session('success') }}</div>
@endif
@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
  <strong>Errores:</strong> @foreach($errors->all() as $e) · {{ $e }} @endforeach
</div>
@endif

@php
$meta = [
    'facebook'  => ['icono' => '📘', 'nombre' => 'Facebook', 'ayuda' => 'Necesitas el ID de tu Página de Facebook y un token de acceso de página (Meta Business Suite → Configuración → Usuarios del sistema).'],
    'instagram' => ['icono' => '📷', 'nombre' => 'Instagram', 'ayuda' => 'Necesitas el ID de tu cuenta de Instagram Business (vinculada a una Página de Facebook) y un token de acceso con permisos de Instagram Graph API.'],
];
@endphp

@foreach(\App\Models\RedSocialConfig::REDES_DISPONIBLES as $red)
@php $cfg = $configs[$red]; $info = $meta[$red]; @endphp
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1.1rem 1.25rem;margin-bottom:1rem;">
  <div style="display:flex;align-items:center;justify-content:between;gap:0.6rem;margin-bottom:0.85rem;">
    <div style="font-size:0.92rem;font-weight:700;color:#0f172a;">{{ $info['icono'] }} {{ $info['nombre'] }}</div>
    @if($cfg->verificado_en)
      <span style="background:#dcfce7;color:#16a34a;font-size:0.65rem;font-weight:700;padding:0.15rem 0.5rem;border-radius:999px;margin-left:auto;">✓ Verificado {{ $cfg->verificado_en->diffForHumans() }}</span>
    @elseif($cfg->credencialesCompletas())
      <span style="background:#fef9c3;color:#a16207;font-size:0.65rem;font-weight:700;padding:0.15rem 0.5rem;border-radius:999px;margin-left:auto;">Sin probar</span>
    @endif
  </div>
  <p style="font-size:0.72rem;color:#94a3b8;margin:0 0 0.85rem;">{{ $info['ayuda'] }}</p>

  <form method="POST" action="{{ route('admin.redes-sociales.update', $red) }}">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
      <div>
        <label style="display:block;font-size:0.72rem;font-weight:600;color:#334155;margin-bottom:0.3rem;">
          {{ $red === 'instagram' ? 'ID cuenta de Instagram Business' : 'ID de la Página de Facebook' }}
        </label>
        <input type="text" name="identificador" maxlength="100" value="{{ old('identificador', $cfg->identificador) }}"
               style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;">
      </div>
      <div>
        <label style="display:block;font-size:0.72rem;font-weight:600;color:#334155;margin-bottom:0.3rem;">Nombre de la cuenta (referencia)</label>
        <input type="text" name="nombre_cuenta" maxlength="150" value="{{ old('nombre_cuenta', $cfg->nombre_cuenta) }}"
               style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;">
      </div>
    </div>
    <div style="margin-bottom:0.85rem;">
      <label style="display:block;font-size:0.72rem;font-weight:600;color:#334155;margin-bottom:0.3rem;">Token de acceso</label>
      <input type="password" name="access_token" maxlength="2000"
             placeholder="{{ $cfg->access_token ? '•••••••••••••••••••• (configurado)' : 'Token de acceso de Meta' }}"
             style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;">
      @if($cfg->access_token)
        <p style="font-size:0.68rem;color:#94a3b8;margin:0.25rem 0 0;">Deja este campo vacío para conservar el token actual.</p>
      @endif
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;">
      <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
        <input type="checkbox" name="activo" value="1" {{ old('activo', $cfg->activo) ? 'checked' : '' }} style="width:16px;height:16px;">
        <span style="font-size:0.82rem;color:#0f172a;">Activo</span>
      </label>
      <div style="display:flex;gap:0.5rem;">
        <button type="button" class="btn-probar" data-red="{{ $red }}"
                style="background:#f1f5f9;color:#0f172a;border:1px solid #cbd5e1;font-size:0.78rem;font-weight:600;padding:0.45rem 0.9rem;border-radius:8px;cursor:pointer;">
          🔗 Probar conexión
        </button>
        <button type="submit" style="background:#2563eb;color:#fff;border:none;font-size:0.78rem;font-weight:700;padding:0.45rem 0.9rem;border-radius:8px;cursor:pointer;">
          Guardar
        </button>
      </div>
    </div>
    <div class="resultado-prueba" data-red-resultado="{{ $red }}" style="margin-top:0.6rem;font-size:0.78rem;"></div>
  </form>
</div>
@endforeach

</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-probar').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var red = btn.dataset.red;
        var resultado = document.querySelector('[data-red-resultado="' + red + '"]');
        btn.disabled = true;
        btn.textContent = '⏳ Probando...';
        resultado.textContent = '';

        fetch('/admin/redes-sociales/' + red + '/probar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            resultado.style.color = data.ok ? '#16a34a' : '#dc2626';
            resultado.textContent = (data.ok ? '✅ ' : '❌ ') + data.mensaje;
        })
        .catch(function () {
            resultado.style.color = '#dc2626';
            resultado.textContent = '❌ Error de conexión.';
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = '🔗 Probar conexión';
        });
    });
});
</script>
@endpush
