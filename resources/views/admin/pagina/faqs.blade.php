@extends('layouts.app')
@section('modulo', 'Página Web')

@section('contenido')
<div style="max-width:900px;margin:0 auto;">

<div style="margin-bottom:1.1rem;">
    <a href="{{ route('admin.pagina.index') }}"
       style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.35rem">
        ← Volver a Página Web
    </a>
    <h1 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0;">🌐 Página Web Pública</h1>
    <p style="font-size:0.78rem;color:#64748b;margin:0;">brynex.co/aliado/{{ $aliado->slug }}</p>
</div>

<div style="display:flex;gap:0.4rem;margin-bottom:1rem;border-bottom:1px solid #e2e8f0;">
  <a href="{{ route('admin.pagina.index') }}" style="font-size:0.82rem;font-weight:600;color:#64748b;padding:0.6rem 0.9rem;text-decoration:none;">General</a>
  <span style="font-size:0.82rem;font-weight:700;color:#0f172a;padding:0.6rem 0.9rem;border-bottom:2px solid #2563eb;">Preguntas frecuentes</span>
  <a href="{{ route('admin.pagina.leads.index') }}" style="font-size:0.82rem;font-weight:600;color:#64748b;padding:0.6rem 0.9rem;text-decoration:none;">Leads</a>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">✅ {{ session('success') }}</div>
@endif
@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
  <strong>Errores:</strong> @foreach($errors->all() as $e) · {{ $e }} @endforeach
</div>
@endif

{{-- ══ NUEVA PREGUNTA ══ --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:1.25rem;">
  <div style="font-size:0.72rem;font-weight:700;color:#2563eb;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.85rem;">
    ➕ Agregar pregunta
  </div>
  <form method="POST" action="{{ route('admin.pagina.faqs.store') }}">
    @csrf
    <div style="margin-bottom:0.7rem;">
      <input type="text" name="pregunta" maxlength="255" required placeholder="¿Qué documentos necesito para afiliarme?"
             style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">
    </div>
    <div style="margin-bottom:0.7rem;">
      <textarea name="respuesta" maxlength="2000" rows="2" required placeholder="Respuesta..."
                style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;font-family:inherit;"></textarea>
    </div>
    <button type="submit" style="background:#2563eb;color:#fff;border:none;font-size:0.8rem;font-weight:700;padding:0.5rem 1.1rem;border-radius:8px;cursor:pointer;">
        Agregar
    </button>
  </form>
</div>

{{-- ══ LISTA ══ --}}
@forelse($faqs as $faq)
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:0.85rem;">
  <form method="POST" action="{{ route('admin.pagina.faqs.update', $faq->id) }}">
    @csrf
    @method('PUT')
    <div style="display:flex;gap:0.75rem;align-items:flex-start;margin-bottom:0.6rem;">
      <input type="number" name="orden" value="{{ $faq->orden }}" min="0" max="999"
             style="width:60px;padding:0.5rem 0.4rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;text-align:center;"
             title="Orden">
      <input type="text" name="pregunta" maxlength="255" required value="{{ $faq->pregunta }}"
             style="flex:1;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;font-weight:600;">
      <label style="display:flex;align-items:center;gap:0.35rem;white-space:nowrap;padding-top:0.5rem;cursor:pointer;">
        <input type="checkbox" name="activo" value="1" {{ $faq->activo ? 'checked' : '' }} style="width:15px;height:15px;">
        <span style="font-size:0.75rem;color:#64748b;">Visible</span>
      </label>
    </div>
    <textarea name="respuesta" maxlength="2000" rows="2" required
              style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.83rem;font-family:inherit;margin-bottom:0.6rem;">{{ $faq->respuesta }}</textarea>
    <div style="display:flex;justify-content:flex-end;">
      <button type="submit" style="background:#f1f5f9;color:#0f172a;border:1px solid #cbd5e1;font-size:0.75rem;font-weight:600;padding:0.4rem 0.9rem;border-radius:7px;cursor:pointer;">
          Guardar
      </button>
    </div>
  </form>
  <form method="POST" action="{{ route('admin.pagina.faqs.destroy', $faq->id) }}"
        onsubmit="return confirm('¿Eliminar esta pregunta de la página pública?');" style="margin-top:0.4rem;">
    @csrf
    @method('DELETE')
    <button type="submit" style="background:none;border:none;color:#dc2626;font-size:0.72rem;font-weight:600;cursor:pointer;padding:0;">
        🗑 Eliminar
    </button>
  </form>
</div>
@empty
<div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:1.5rem;text-align:center;color:#64748b;font-size:0.85rem;">
    Todavía no hay preguntas frecuentes. Agrega la primera arriba.
</div>
@endforelse

</div>
@endsection
