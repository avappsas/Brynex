@extends('layouts.app')
@section('modulo', 'Publicidad')

@section('contenido')
<div style="max-width:820px;margin:0 auto;">

<a href="{{ route('admin.publicidad.index') }}"
   style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.85rem">
    ← Volver al historial
</a>

<h1 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0 0 .25rem;">🚁 Piloto automático de marketing</h1>
<p style="font-size:.83rem;color:#64748b;margin:0 0 1.25rem;">
    La IA crea cada día una pieza nueva (tema, texto e imagen) sin repetirse, usando los planes y precios reales de {{ $aliado->nombre }}.
</p>

@if(session('success'))
    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;color:#15803d;padding:.65rem 1rem;margin-bottom:1rem;font-size:.83rem;">✅ {{ session('success') }}</div>
@endif
@if($errors->any())
    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:.65rem 1rem;margin-bottom:1rem;font-size:.83rem;">
        @foreach($errors->all() as $e) · {{ $e }} @endforeach
    </div>
@endif

@if(!$tieneIaTexto || !$tieneGemini)
    <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;color:#92400e;padding:.65rem 1rem;margin-bottom:1rem;font-size:.8rem;">
        ⚠️ Para que el piloto funcione falta:
        @if(!$tieneIaTexto) clave de IA de texto (Claude/OpenAI). @endif
        @if(!$tieneGemini) clave de Gemini para imágenes. @endif
        Configúralas en <strong>Asistente Virtual IA</strong>.
    </div>
@endif

{{-- ── Configuración ── --}}
<form method="POST" action="{{ route('admin.publicidad.autopilot.update') }}"
      style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:1.5rem;">
    @csrf

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <div style="font-size:.9rem;font-weight:700;color:#0f172a;">Piloto automático</div>
            <div style="font-size:.73rem;color:#94a3b8;">Genera una pieza al día a partir de la hora elegida.</div>
        </div>
        <select name="activo" style="padding:.45rem .7rem;border:1.5px solid {{ $config->activo ? '#86efac' : '#cbd5e1' }};border-radius:8px;font-size:.85rem;font-weight:700;color:{{ $config->activo ? '#15803d' : '#475569' }};">
            <option value="1" @selected($config->activo)>🟢 Activo</option>
            <option value="0" @selected(!$config->activo)>⚪ Apagado</option>
        </select>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
        <div>
            <label style="display:block;font-size:.72rem;font-weight:600;color:#334155;margin-bottom:.3rem;">Modo</label>
            <select name="modo" style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.83rem;">
                <option value="aprobar" @selected($config->modo === 'aprobar')>✋ Con aprobación previa (queda pendiente)</option>
                <option value="auto" @selected($config->modo === 'auto')>⚡ 100% automático (publica sola)</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:.72rem;font-weight:600;color:#334155;margin-bottom:.3rem;">Hora de generación (Colombia)</label>
            <input type="time" name="hora" value="{{ $config->hora }}" required
                   style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.83rem;">
        </div>
    </div>

    <div style="margin-bottom:1rem;">
        <label style="display:block;font-size:.72rem;font-weight:600;color:#334155;margin-bottom:.3rem;">Estilo de imagen</label>
        <select name="estilo_imagen" style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.83rem;">
            <option value="ilustracion" @selected($config->estilo_imagen === 'ilustracion')>🎨 Económicas — ilustración (flat design)</option>
            <option value="fotorrealista" @selected($config->estilo_imagen === 'fotorrealista')>📷 Caras — fotorrealista (fotos de personas reales)</option>
            <option value="alternar" @selected($config->estilo_imagen === 'alternar')>🔀 Alternar (varía cada día entre las dos)</option>
        </select>
        <div class="form-hint" style="font-size:.72rem;color:#94a3b8;margin-top:.3rem;">Las económicas usan el modelo de ilustración (más barato, ya probado). Las caras usan un modelo premium de Gemini para fotos realistas de personas; el mensaje va en el texto del post, no escrito dentro de la foto.</div>
    </div>

    <label style="display:block;font-size:.72rem;font-weight:600;color:#334155;margin-bottom:.4rem;">Días activos</label>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.1rem;">
        @php($nombresDias = [1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'])
        @foreach($nombresDias as $num => $nombre)
            <label style="display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.35rem .6rem;cursor:pointer;">
                <input type="checkbox" name="dias[]" value="{{ $num }}"
                       @checked(empty($config->dias) || in_array($num, array_map('intval', $config->dias ?? []), true))>
                {{ $nombre }}
            </label>
        @endforeach
    </div>

    <button type="submit" style="background:#2563eb;color:#fff;border:none;font-size:.85rem;font-weight:700;padding:.6rem 1.4rem;border-radius:8px;cursor:pointer;">
        Guardar configuración
    </button>
</form>

{{-- ── Rendimiento real (métricas de redes) ── --}}
@if($ranking->isNotEmpty())
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:1.5rem;">
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.35rem;">📈 Rendimiento real</div>
    <div style="font-size:.72rem;color:#94a3b8;margin-bottom:.75rem;">Interacciones (likes + comentarios + compartidos) y alcance en Instagram. La IA usa este ranking para aprender qué contenido atrae más.</div>

    @foreach($ranking as $i => $fila)
        <a href="{{ route('admin.publicidad.show', $fila['pieza']->id) }}"
           style="display:flex;gap:.7rem;align-items:center;padding:.5rem 0;border-bottom:1px solid #f1f5f9;text-decoration:none;">
            <span style="font-size:.78rem;font-weight:700;color:{{ $i < 3 ? '#0891b2' : '#94a3b8' }};width:1.4rem;text-align:center;flex-shrink:0;">{{ $i + 1 }}</span>
            <img src="{{ asset('storage/' . $fila['pieza']->imagen_path) }}" style="width:40px;height:40px;object-fit:cover;border-radius:6px;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
                <div style="font-size:.8rem;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $fila['pieza']->titulo }}</div>
                <div style="font-size:.68rem;color:#94a3b8;">{{ $fila['pieza']->tema ?: 'sin tema' }}{{ $fila['pieza']->estilo_imagen ? ' · ' . $fila['pieza']->estilo_imagen : '' }}</div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:.8rem;font-weight:700;color:#0f172a;">{{ $fila['interacciones'] }} <span style="font-weight:400;color:#94a3b8;font-size:.68rem;">interacciones</span></div>
                @if($fila['conversaciones_wa'])<div style="font-size:.68rem;color:#16a34a;font-weight:600;">💬 {{ $fila['conversaciones_wa'] }} conversación(es)</div>@endif
                @if($fila['alcance'])<div style="font-size:.68rem;color:#94a3b8;">alcance {{ number_format($fila['alcance'], 0, ',', '.') }}</div>@endif
            </div>
        </a>
    @endforeach
</div>
@endif

{{-- ── Historial de piezas del piloto ── --}}
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;">
    <div style="font-size:.72rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.75rem;">🤖 Últimas piezas generadas por la IA</div>

    @forelse($piezas as $p)
        <a href="{{ route('admin.publicidad.show', $p->id) }}"
           style="display:flex;gap:.8rem;align-items:center;padding:.6rem 0;border-bottom:1px solid #f1f5f9;text-decoration:none;">
            <img src="{{ asset('storage/' . $p->imagen_path) }}" style="width:52px;height:52px;object-fit:cover;border-radius:8px;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
                <div style="font-size:.83rem;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $p->titulo }}</div>
                <div style="font-size:.7rem;color:#94a3b8;">{{ $p->tema ?: 'sin tema' }} · {{ $p->created_at->format('d/m/Y H:i') }}</div>
            </div>
            <span style="font-size:.68rem;font-weight:700;padding:.2rem .55rem;border-radius:999px;flex-shrink:0;
                {{ $p->estado === 'publicada' ? 'background:#dcfce7;color:#15803d;' : ($p->estado === 'pendiente' ? 'background:#fef9c3;color:#a16207;' : 'background:#f1f5f9;color:#64748b;') }}">
                {{ $p->etiquetaEstado() }}
            </span>
        </a>
    @empty
        <p style="font-size:.8rem;color:#94a3b8;margin:.25rem 0;">Aún no hay piezas generadas por el piloto. Actívalo y la primera saldrá hoy a la hora configurada.</p>
    @endforelse
</div>

</div>
@endsection
