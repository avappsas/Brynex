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
    <a href="{{ route('admin.publicidad.pauta.config') }}"
       style="background:#fdf4ff;color:#a21caf;border:1px solid #f0abfc;font-size:0.8rem;font-weight:700;padding:0.5rem 0.9rem;border-radius:8px;text-decoration:none;">
        💸 Pauta pagada
    </a>
    @if($tieneGemini)
    <button type="button" id="btnAbrirModalVideo"
       style="background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe;font-size:0.8rem;font-weight:700;padding:0.5rem 0.9rem;border-radius:8px;cursor:pointer;">
        🎬 Generar video
    </button>
    @endif
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

@if($tieneGemini)
{{-- ══ Modal: Generar video con IA ══ --}}
<div id="modalVideoIa" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:50;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:14px;padding:1.4rem;max-width:420px;width:100%;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.9rem;">
      <h2 style="font-size:1rem;font-weight:800;color:#0f172a;margin:0;">🎬 Generar video con IA</h2>
      <button type="button" id="btnCerrarModalVideo" style="background:none;border:none;font-size:1.1rem;cursor:pointer;color:#94a3b8;">✕</button>
    </div>
    <p style="font-size:0.76rem;color:#64748b;margin:0 0 0.8rem;">
      La IA redacta sola la escena, el diálogo del personaje y el texto animado — solo elige el nivel, la duración y, si quieres, dale un tema.
    </p>

    <label style="display:block;font-size:0.72rem;font-weight:600;color:#334155;margin-bottom:0.3rem;">Tema (opcional)</label>
    <input type="text" id="mvTema" maxlength="300" placeholder="Ej: independientes que no saben que pueden cotizar"
           style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;margin-bottom:0.8rem;">

    <label style="display:block;font-size:0.72rem;font-weight:600;color:#334155;margin-bottom:0.3rem;">Nivel</label>
    <div style="display:flex;gap:0.4rem;margin-bottom:0.8rem;">
      <button type="button" class="mv-nivel activo" data-nivel="lite" style="flex:1;padding:0.4rem;border-radius:8px;border:1.5px solid #7c3aed;background:#f5f3ff;color:#6d28d9;font-size:0.75rem;font-weight:700;cursor:pointer;">⚡ Lite</button>
      <button type="button" class="mv-nivel" data-nivel="standard" style="flex:1;padding:0.4rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;color:#475569;font-size:0.75rem;font-weight:700;cursor:pointer;">🎥 Standard</button>
    </div>

    <label style="display:block;font-size:0.72rem;font-weight:600;color:#334155;margin-bottom:0.3rem;">Duración</label>
    <div style="display:flex;gap:0.4rem;margin-bottom:1rem;">
      <button type="button" class="mv-duracion" data-duracion="4" style="flex:1;padding:0.4rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;color:#475569;font-size:0.75rem;font-weight:700;cursor:pointer;">4s</button>
      <button type="button" class="mv-duracion" data-duracion="6" style="flex:1;padding:0.4rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;color:#475569;font-size:0.75rem;font-weight:700;cursor:pointer;">6s</button>
      <button type="button" class="mv-duracion activo" data-duracion="8" style="flex:1;padding:0.4rem;border-radius:8px;border:1.5px solid #7c3aed;background:#f5f3ff;color:#6d28d9;font-size:0.75rem;font-weight:700;cursor:pointer;">8s</button>
      <button type="button" class="mv-duracion" data-duracion="16" style="flex:1;padding:0.4rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;color:#475569;font-size:0.75rem;font-weight:700;cursor:pointer;">16s</button>
      <button type="button" class="mv-duracion" data-duracion="24" style="flex:1;padding:0.4rem;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;color:#475569;font-size:0.75rem;font-weight:700;cursor:pointer;">24s</button>
    </div>
    <p style="font-size:0.68rem;color:#94a3b8;margin:-0.6rem 0 0.9rem;">16s/24s son 2-3 escenas unidas con corte — tardan más y cuestan un poco más.</p>

    <button type="button" id="btnGenerarVideoModal" style="width:100%;background:#7c3aed;color:#fff;border:none;font-size:0.85rem;font-weight:700;padding:0.6rem;border-radius:9px;cursor:pointer;">
      🎬 Generar video
    </button>

    <div id="mvEstado" style="margin-top:0.9rem;font-size:0.78rem;color:#64748b;"></div>
    <video id="mvPreview" controls style="display:none;width:100%;border-radius:8px;margin-top:0.6rem;"></video>
    <a id="mvUsarVideo" href="#" style="display:none;margin-top:0.8rem;text-align:center;background:#2563eb;color:#fff;font-size:0.83rem;font-weight:700;padding:0.6rem;border-radius:9px;text-decoration:none;">
      Usar este video →
    </a>
  </div>
</div>

@push('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const modal = document.getElementById('modalVideoIa');
    let nivel = 'lite';
    let duracion = '8';
    let pollingModalVideo = null;

    document.getElementById('btnAbrirModalVideo')?.addEventListener('click', () => { modal.style.display = 'flex'; });
    document.getElementById('btnCerrarModalVideo')?.addEventListener('click', () => { modal.style.display = 'none'; });

    document.querySelectorAll('.mv-nivel').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mv-nivel').forEach((b) => {
                b.classList.remove('activo');
                b.style.border = '1.5px solid #cbd5e1'; b.style.background = '#fff'; b.style.color = '#475569';
            });
            btn.classList.add('activo');
            btn.style.border = '1.5px solid #7c3aed'; btn.style.background = '#f5f3ff'; btn.style.color = '#6d28d9';
            nivel = btn.dataset.nivel;
        });
    });

    document.querySelectorAll('.mv-duracion').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mv-duracion').forEach((b) => {
                b.classList.remove('activo');
                b.style.border = '1.5px solid #cbd5e1'; b.style.background = '#fff'; b.style.color = '#475569';
            });
            btn.classList.add('activo');
            btn.style.border = '1.5px solid #7c3aed'; btn.style.background = '#f5f3ff'; btn.style.color = '#6d28d9';
            duracion = btn.dataset.duracion;
        });
    });

    document.getElementById('btnGenerarVideoModal').addEventListener('click', function () {
        const btn = this;
        const tema = document.getElementById('mvTema').value.trim();
        const estadoEl = document.getElementById('mvEstado');
        const previewEl = document.getElementById('mvPreview');
        const usarEl = document.getElementById('mvUsarVideo');

        btn.disabled = true;
        btn.textContent = '⏳ Iniciando...';
        previewEl.style.display = 'none';
        usarEl.style.display = 'none';
        estadoEl.textContent = 'Redactando la escena y el diálogo con IA...';

        fetch(@json(route('admin.publicidad.generar_video')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ tema, nivel, duracion }),
        })
        .then((r) => r.json())
        .then((data) => {
            if (!data.ok) {
                estadoEl.textContent = '❌ ' + (data.error || 'No se pudo iniciar el video.');
                btn.disabled = false;
                btn.textContent = '🎬 Generar video';
                return;
            }
            estadoEl.textContent = 'Generando video con IA (usualmente 1-3 min)...';
            if (pollingModalVideo) clearInterval(pollingModalVideo);
            pollingModalVideo = setInterval(() => consultarModalVideo(data.id, btn, estadoEl, previewEl, usarEl), 8000);
        })
        .catch(() => {
            estadoEl.textContent = '❌ Error de conexión al iniciar el video.';
            btn.disabled = false;
            btn.textContent = '🎬 Generar video';
        });
    });

    function consultarModalVideo(id, btn, estadoEl, previewEl, usarEl) {
        const url = @json(route('admin.publicidad.video.estado', ['id' => '__ID__'])).replace('__ID__', id);
        fetch(url, { headers: { 'Accept': 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            if (!data.ok) return;

            if (data.estado === 'error') {
                clearInterval(pollingModalVideo);
                estadoEl.textContent = '❌ ' + (data.error || 'Ocurrió un error generando el video.');
                btn.disabled = false;
                btn.textContent = '🎬 Generar video';
                return;
            }

            if (data.estado === 'lista') {
                clearInterval(pollingModalVideo);
                estadoEl.textContent = '✅ Video listo.';
                previewEl.src = data.video_url;
                previewEl.poster = data.poster_url || '';
                previewEl.style.display = 'block';
                usarEl.href = @json(route('admin.publicidad.create')) + '?video_id=' + id;
                usarEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = '🎬 Generar otro';
                return;
            }

            if (data.progreso) {
                estadoEl.textContent = data.progreso;
            }
        });
    }
})();
</script>
@endpush
@endif

@endsection
