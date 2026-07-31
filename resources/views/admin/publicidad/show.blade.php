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
  @if($publicacion->tipo_pieza === 'video' && $publicacion->video_path)
    <video controls poster="{{ asset('storage/' . $publicacion->imagen_path) }}"
           style="width:100%;max-width:360px;border-radius:12px;display:block;margin:0 auto 1.25rem;">
      <source src="{{ asset('storage/' . $publicacion->video_path) }}" type="video/mp4">
    </video>
  @else
    <img src="{{ asset('storage/' . $publicacion->imagen_path) }}" alt=""
         style="width:100%;max-width:360px;border-radius:12px;display:block;margin:0 auto 1.25rem;">
  @endif

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
    @if($publicacion->costo_estimado_usd !== null)
      @php $costoCop = $publicacion->costo_estimado_usd * $trmCop; @endphp
      <span>·</span>
      <span>Costo estimado: <strong>${{ number_format($costoCop, 0, ',', '.') }} COP</strong> (≈ ${{ number_format($publicacion->costo_estimado_usd, 2) }} USD, TRM ${{ number_format($trmCop, 2) }})</span>
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

  {{-- ══ Métricas de redes ══ --}}
  @if($publicacion->metricas->isNotEmpty())
    <div style="margin-bottom:1rem;">
      <div style="font-size:0.72rem;font-weight:700;color:#0891b2;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.5rem;">📈 Métricas</div>
      <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
        @foreach($publicacion->metricas as $m)
          <div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:10px;padding:0.6rem 0.9rem;font-size:0.78rem;color:#134e4a;">
            <strong>{{ ucfirst($m->red) }}</strong>:
            👍 {{ $m->me_gusta }} · 💬 {{ $m->comentarios }} · 🔁 {{ $m->compartidos }}
            @if($m->alcance !== null) · 👀 alcance {{ number_format($m->alcance, 0, ',', '.') }} @endif
            <span style="color:#94a3b8;font-size:0.68rem;"> ({{ $m->medido_at->format('d/m H:i') }})</span>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  {{-- ══ Conversaciones de WhatsApp atribuidas ══ --}}
  @if($conversacionesWa->isNotEmpty())
    <div style="margin-bottom:1rem;">
      <div style="font-size:0.72rem;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.5rem;">
        💬 Conversaciones atribuidas ({{ $conversacionesWa->count() }})
      </div>
      <div style="font-size:0.72rem;color:#94a3b8;margin-bottom:0.5rem;">Clientes que escribieron por WhatsApp mencionando el link de esta pieza — atribución real, no estimada.</div>
      @foreach($conversacionesWa as $c)
        <a href="{{ route('admin.whatsapp.chat.show', $c->id) }}"
           style="display:block;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:0.5rem 0.85rem;margin-bottom:0.4rem;text-decoration:none;color:#166534;font-size:0.8rem;">
          {{ $c->nombre_contacto ?: $c->wa_contact_id }} · {{ $c->created_at->format('d/m/Y H:i') }}
        </a>
      @endforeach
    </div>
  @endif

  {{-- ══ Pauta pagada ══ --}}
  @if($publicacion->estado === 'publicada' && $publicacion->idPostFacebook())
    <div style="margin-bottom:1rem;border:1px solid #e2e8f0;border-radius:10px;padding:0.9rem 1rem;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
        <div style="font-size:0.72rem;font-weight:700;color:#c026d3;text-transform:uppercase;letter-spacing:0.04em;">💸 Pauta pagada</div>
        <a href="{{ route('admin.publicidad.pauta.config') }}" style="font-size:0.7rem;color:#94a3b8;text-decoration:none;">⚙️ Configurar cuenta/tope</a>
      </div>

      @if(!$publicacion->pauta_estado)
        <p style="font-size:0.8rem;color:#64748b;margin:0 0 0.75rem;">Sin pauta todavía. Se puede crear en pausa primero — $0 hasta que la actives.</p>
        <form method="POST" action="{{ route('admin.publicidad.pauta.crear', $publicacion->id) }}">
          @csrf
          <button type="submit" style="background:#faf5ff;color:#7c3aed;border:1px solid #d8b4fe;font-size:0.78rem;font-weight:700;padding:0.45rem 0.9rem;border-radius:8px;cursor:pointer;">
            Crear pauta en pausa (sin gastar)
          </button>
        </form>
      @else
        <div style="display:flex;gap:0.6rem;flex-wrap:wrap;font-size:0.8rem;color:#334155;margin-bottom:0.75rem;">
          <span>Estado: <strong>{{ ucfirst($publicacion->pauta_estado) }}</strong></span>
          <span>·</span>
          <span>Presupuesto diario: <strong>${{ number_format($publicacion->pauta_presupuesto_diario_cop, 0, ',', '.') }} COP</strong></span>
          <span>·</span>
          <span>Gastado real: <strong>${{ number_format($publicacion->pauta_gasto_total_cop, 0, ',', '.') }} COP</strong></span>
        </div>

        @if($publicacion->pauta_estado === 'borrador' || $publicacion->pauta_estado === 'pausada')
          <form method="POST" action="{{ route('admin.publicidad.pauta.activar', $publicacion->id) }}"
                style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;"
                onsubmit="return confirm('Esto va a gastar dinero real de la cuenta publicitaria (\${{ number_format($publicacion->pauta_presupuesto_diario_cop, 0, ',', '.') }} COP/día). ¿Activar la pauta?');">
            @csrf
            <label style="font-size:0.72rem;color:#64748b;">Días (opcional, se pausa sola):
              <input type="number" name="dias" min="1" max="90" placeholder="sin límite" style="width:70px;padding:0.3rem 0.4rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.78rem;">
            </label>
            <button type="submit" style="background:#dc2626;color:#fff;border:none;font-size:0.78rem;font-weight:700;padding:0.5rem 1rem;border-radius:8px;cursor:pointer;">
              ⚠️ Activar (gasta dinero real)
            </button>
          </form>
        @elseif($publicacion->pauta_estado === 'activa')
          <form method="POST" action="{{ route('admin.publicidad.pauta.pausar', $publicacion->id) }}">
            @csrf
            <button type="submit" style="background:#fff;color:#475569;border:1px solid #cbd5e1;font-size:0.78rem;font-weight:700;padding:0.5rem 1rem;border-radius:8px;cursor:pointer;">
              ⏸️ Pausar pauta
            </button>
          </form>
        @endif
      @endif
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
