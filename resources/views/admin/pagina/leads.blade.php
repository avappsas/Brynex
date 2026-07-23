@extends('layouts.app')
@section('modulo', 'Página Web')

@section('contenido')
<div style="max-width:1000px;margin:0 auto;">

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
  <a href="{{ route('admin.pagina.faqs.index') }}" style="font-size:0.82rem;font-weight:600;color:#64748b;padding:0.6rem 0.9rem;text-decoration:none;">Preguntas frecuentes</a>
  <span style="font-size:0.82rem;font-weight:700;color:#0f172a;padding:0.6rem 0.9rem;border-bottom:2px solid #2563eb;">Leads ({{ $leads->total() }})</span>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">✅ {{ session('success') }}</div>
@endif

{{-- ══ FILTROS ══ --}}
<div style="display:flex;gap:0.5rem;margin-bottom:1rem;flex-wrap:wrap;">
  <a href="{{ route('admin.pagina.leads.index') }}"
     style="font-size:0.75rem;font-weight:600;padding:0.4rem 0.8rem;border-radius:999px;text-decoration:none;
            background:{{ !request('estado') ? '#0f172a' : '#f1f5f9' }};color:{{ !request('estado') ? '#fff' : '#475569' }};">
      Todos ({{ $conteos->sum() }})
  </a>
  @foreach(['nuevo'=>'Nuevos','contactado'=>'Contactados','convertido'=>'Convertidos','descartado'=>'Descartados'] as $key => $label)
  <a href="{{ route('admin.pagina.leads.index', ['estado'=>$key]) }}"
     style="font-size:0.75rem;font-weight:600;padding:0.4rem 0.8rem;border-radius:999px;text-decoration:none;
            background:{{ request('estado')===$key ? '#0f172a' : '#f1f5f9' }};color:{{ request('estado')===$key ? '#fff' : '#475569' }};">
      {{ $label }} ({{ $conteos[$key] ?? 0 }})
  </a>
  @endforeach
</div>

{{-- ══ LISTA ══ --}}
@forelse($leads as $lead)
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:0.75rem;display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
  <div style="flex:1;min-width:200px;">
    <div style="font-size:0.92rem;font-weight:700;color:#0f172a;margin-bottom:0.2rem;">{{ $lead->nombre }}</div>
    <div style="font-size:0.78rem;color:#64748b;margin-bottom:0.5rem;">
      📱 {{ $lead->celular }} · {{ $lead->created_at->format('d/m/Y H:i') }} · origen: {{ $lead->origen }}
    </div>
    <div style="display:flex;gap:0.35rem;flex-wrap:wrap;margin-bottom:0.4rem;">
      @if($lead->perfil)<span style="background:#f1f5f9;color:#475569;font-size:0.68rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:999px;">{{ ucfirst($lead->perfil) }}</span>@endif
      @foreach(['eps'=>'EPS','arl'=>'ARL','pension'=>'Pensión','caja'=>'Caja'] as $k=>$label)
        @if(!empty($lead->coberturas[$k]))<span style="background:#dbeafe;color:#1e40af;font-size:0.68rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:999px;">{{ $label }}</span>@endif
      @endforeach
    </div>
    @if($lead->plan_interes)
      <div style="font-size:0.78rem;color:#334155;">Plan: <strong>{{ $lead->plan_interes }}</strong>
        @if($lead->valor_mensual_cotizado) — ${{ number_format($lead->valor_mensual_cotizado,0,',','.') }}/mes @endif
      </div>
    @endif
  </div>
  <div style="display:flex;flex-direction:column;gap:0.4rem;align-items:flex-end;">
    <form method="POST" action="{{ route('admin.pagina.leads.update', $lead->id) }}">
      @csrf @method('PATCH')
      <select name="estado" onchange="this.form.submit()"
              style="font-size:0.75rem;font-weight:600;padding:0.35rem 0.5rem;border-radius:7px;border:1px solid #cbd5e1;">
        @foreach(['nuevo'=>'Nuevo','contactado'=>'Contactado','convertido'=>'Convertido','descartado'=>'Descartado'] as $key=>$label)
          <option value="{{ $key }}" {{ $lead->estado === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </form>
    <a href="https://wa.me/{{ preg_replace('/\D/', '', (str_starts_with(preg_replace('/\D/','',$lead->celular),'57') ? '' : '57') . $lead->celular) }}"
       target="_blank" rel="noopener"
       style="font-size:0.75rem;font-weight:700;color:#16a34a;text-decoration:none;">💬 WhatsApp</a>
  </div>
</div>
@empty
<div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:1.5rem;text-align:center;color:#64748b;font-size:0.85rem;">
    Todavía no hay leads capturados desde el cotizador.
</div>
@endforelse

<div style="margin-top:1rem;">{{ $leads->links() }}</div>

</div>
@endsection
