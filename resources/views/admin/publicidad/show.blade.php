@extends('layouts.app')
@section('modulo', 'Publicidad')

@section('contenido')
<div style="max-width:700px;margin:0 auto;">

<a href="{{ route('admin.publicidad.index') }}"
   style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.85rem">
    ← Volver al historial
</a>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">❌ {{ session('error') }}</div>
@endif
@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
  @foreach($errors->all() as $e) · {{ $e }} @endforeach
</div>
@endif

<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.5rem;margin-bottom:1rem;">
  <img src="{{ asset('storage/' . $publicacion->imagen_path) }}" alt=""
       style="width:100%;max-width:360px;border-radius:12px;display:block;margin:0 auto 1.25rem;">

  <h1 style="font-size:1.1rem;font-weight:800;color:#0f172a;margin:0 0 0.4rem;">{{ $publicacion->titulo }}</h1>
  @if($publicacion->copy)
    <p style="font-size:0.88rem;color:#334155;background:#f8fafc;border-radius:8px;padding:0.75rem;margin:0 0 1rem;">{{ $publicacion->copy }}</p>
  @endif

  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;font-size:0.75rem;color:#64748b;margin-bottom:1rem;">
    <span>Origen: <strong>{{ ucfirst($publicacion->origen) }}</strong></span>
    <span>·</span>
    <span>Destinos: <strong>{{ implode(', ', $publicacion->destinos ?: []) }}</strong></span>
    @if($publicacion->programada_at)
      <span>·</span><span>Programada: <strong>{{ $publicacion->programada_at->format('d/m/Y H:i') }}</strong></span>
    @endif
  </div>

  <div style="font-size:0.8rem;color:#64748b;margin-bottom:1rem;">
    Creada por {{ $publicacion->creador?->nombre ?? '—' }} el {{ $publicacion->created_at->format('d/m/Y H:i') }}
    @if($publicacion->aprobador)
      · {{ $publicacion->estado === 'rechazada' ? 'Rechazada' : 'Aprobada' }} por {{ $publicacion->aprobador->nombre }}
    @endif
  </div>

  @if($publicacion->estado === 'rechazada' && $publicacion->motivo_rechazo)
    <div style="background:#fee2e2;border-radius:8px;padding:0.75rem;font-size:0.82rem;color:#991b1b;margin-bottom:1rem;">
      <strong>Motivo del rechazo:</strong> {{ $publicacion->motivo_rechazo }}
    </div>
  @endif

  {{-- ══ Resultado por red (si ya se publicó) ══ --}}
  @if($publicacion->estado === 'publicada' && $publicacion->resultado_publicacion)
    <div style="margin-bottom:1rem;">
      <div style="font-size:0.72rem;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.5rem;">Resultado por destino</div>
      @foreach($publicacion->resultado_publicacion as $red => $r)
        <div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;border-radius:8px;padding:0.6rem 0.85rem;margin-bottom:0.4rem;">
          <div style="font-size:0.82rem;">
            {{ $r['ok'] ? '✅' : '❌' }} <strong>{{ ucfirst($red) }}</strong> — {{ $r['mensaje'] }}
          </div>
          @if(!$r['ok'] && $red !== 'web')
            <form method="POST" action="{{ route('admin.publicidad.reintentar', [$publicacion->id, $red]) }}">
              @csrf
              <button type="submit" style="background:#fff;border:1px solid #cbd5e1;font-size:0.72rem;font-weight:600;padding:0.3rem 0.6rem;border-radius:6px;cursor:pointer;">Reintentar</button>
            </form>
          @endif
        </div>
      @endforeach
    </div>
  @endif

  {{-- ══ Acciones ══ --}}
  @if($publicacion->estado === 'pendiente')
    <div style="display:flex;gap:0.6rem;">
      <form method="POST" action="{{ route('admin.publicidad.aprobar', $publicacion->id) }}" style="flex:1;">
        @csrf
        <button type="submit" style="width:100%;background:#16a34a;color:#fff;border:none;font-size:0.85rem;font-weight:700;padding:0.65rem;border-radius:8px;cursor:pointer;">✓ Aprobar{{ $publicacion->programada_at ? ' y programar' : ' y publicar ahora' }}</button>
      </form>
    </div>
    <form method="POST" action="{{ route('admin.publicidad.rechazar', $publicacion->id) }}" style="margin-top:0.6rem;">
      @csrf
      <textarea name="motivo_rechazo" placeholder="Motivo del rechazo (obligatorio para rechazar)" rows="2" required
                style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;font-family:inherit;margin-bottom:0.5rem;"></textarea>
      <button type="submit" style="width:100%;background:#fff;color:#dc2626;border:1px solid #fca5a5;font-size:0.83rem;font-weight:700;padding:0.6rem;border-radius:8px;cursor:pointer;">✗ Rechazar</button>
    </form>
  @endif

  @if(in_array($publicacion->estado, ['borrador', 'rechazada']))
    <form method="POST" action="{{ route('admin.publicidad.destroy', $publicacion->id) }}"
          onsubmit="return confirm('¿Eliminar esta pieza?');" style="margin-top:0.75rem;">
      @csrf @method('DELETE')
      <button type="submit" style="background:none;border:none;color:#94a3b8;font-size:0.75rem;font-weight:600;cursor:pointer;padding:0;">🗑 Eliminar pieza</button>
    </form>
  @endif
</div>

</div>
@endsection
