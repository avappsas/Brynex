@extends('layouts.app')
@section('modulo', 'Publicidad')

@section('contenido')
<div style="max-width:1000px;margin:0 auto;">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem;flex-wrap:wrap;gap:0.5rem;">
  <div>
    <a href="{{ route('admin.marketing.index') }}"
       style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.35rem">
        ← Volver a Marketing
    </a>
    <h1 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0;">🎨 Generador de Publicidad</h1>
    <p style="font-size:0.78rem;color:#64748b;margin:0;">
      @if($pendientes > 0)<strong style="color:#b45309;">{{ $pendientes }} pendiente(s) de aprobación</strong> · @endif
      Piezas para la página web y redes sociales.
    </p>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <a href="{{ route('admin.publicidad.autopilot') }}"
       style="background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe;font-size:0.8rem;font-weight:700;padding:0.5rem 0.9rem;border-radius:8px;text-decoration:none;">
        🚁 Piloto automático
    </a>
    <a href="{{ route('admin.redes-sociales.index') }}"
       style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;font-size:0.8rem;font-weight:700;padding:0.5rem 0.9rem;border-radius:8px;text-decoration:none;">
        🔗 Redes sociales
    </a>
    <a href="{{ route('admin.publicidad.create') }}"
       style="background:#2563eb;color:#fff;font-size:0.82rem;font-weight:700;padding:0.55rem 1rem;border-radius:8px;text-decoration:none;">
        + Nueva pieza
    </a>
  </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">❌ {{ session('error') }}</div>
@endif

<div style="display:flex;gap:0.5rem;margin-bottom:1rem;flex-wrap:wrap;">
  <a href="{{ route('admin.publicidad.index') }}"
     style="font-size:0.75rem;font-weight:600;padding:0.4rem 0.8rem;border-radius:999px;text-decoration:none;
            background:{{ !request('estado') ? '#0f172a' : '#f1f5f9' }};color:{{ !request('estado') ? '#fff' : '#475569' }};">Todas</a>
  @foreach(['pendiente'=>'Pendientes','aprobada'=>'Aprobadas','publicada'=>'Publicadas','rechazada'=>'Rechazadas','borrador'=>'Borradores'] as $key=>$label)
  <a href="{{ route('admin.publicidad.index', ['estado'=>$key]) }}"
     style="font-size:0.75rem;font-weight:600;padding:0.4rem 0.8rem;border-radius:999px;text-decoration:none;
            background:{{ request('estado')===$key ? '#0f172a' : '#f1f5f9' }};color:{{ request('estado')===$key ? '#fff' : '#475569' }};">{{ $label }}</a>
  @endforeach
</div>

@php
$colorEstado = [
    'borrador'  => ['#f1f5f9', '#475569'],
    'pendiente' => ['#fef9c3', '#a16207'],
    'aprobada'  => ['#dbeafe', '#1e40af'],
    'rechazada' => ['#fee2e2', '#991b1b'],
    'publicada' => ['#dcfce7', '#166534'],
];
@endphp

@forelse($publicaciones as $pub)
<a href="{{ route('admin.publicidad.show', $pub->id) }}"
   style="display:flex;gap:1rem;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:0.9rem 1.1rem;margin-bottom:0.75rem;text-decoration:none;color:inherit;align-items:center;">
  <img src="{{ asset('storage/' . $pub->imagen_path) }}" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:8px;flex-shrink:0;background:#f1f5f9;">
  <div style="flex:1;min-width:0;">
    <div style="font-size:0.9rem;font-weight:700;color:#0f172a;">{{ $pub->titulo }}</div>
    <div style="font-size:0.75rem;color:#64748b;">
      {{ $pub->created_at->format('d/m/Y H:i') }} · {{ ucfirst($pub->origen) }}
      @if($pub->destinos) · {{ implode(', ', $pub->destinos) }} @endif
    </div>
  </div>
  @php [$bg,$fg] = $colorEstado[$pub->estado] ?? ['#f1f5f9','#475569']; @endphp
  <span style="background:{{ $bg }};color:{{ $fg }};font-size:0.7rem;font-weight:700;padding:0.25rem 0.65rem;border-radius:999px;white-space:nowrap;">
    {{ $pub->etiquetaEstado() }}
  </span>
</a>
@empty
<div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:2rem;text-align:center;color:#64748b;font-size:0.85rem;">
    Todavía no hay piezas publicitarias. <a href="{{ route('admin.publicidad.create') }}" style="color:#2563eb;font-weight:600;">Crea la primera</a>.
</div>
@endforelse

<div style="margin-top:1rem;">{{ $publicaciones->links() }}</div>

</div>
@endsection
