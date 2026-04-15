{{-- Componente: tabla de administración con estilo consistente --}}
{{-- Uso: @include('admin.partials.table-header', ['titulo' => '...', 'btnTexto' => '...', 'btnRuta' => '...']) --}}
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.75rem;">
    <div>
        <h2 style="font-size:1.15rem; font-weight:700; color:#0d2550;">{{ $titulo }}</h2>
        @isset($subtitulo)
        <p style="color:#64748b; font-size:0.82rem; margin-top:0.15rem;">{{ $subtitulo }}</p>
        @endisset
    </div>
    @isset($btnTexto)
    <a href="{{ $btnRuta }}" style="
        background:linear-gradient(135deg,#2563eb,#1d4ed8);
        color:#fff; text-decoration:none; padding:0.55rem 1.1rem;
        border-radius:8px; font-size:0.82rem; font-weight:600;
        box-shadow:0 3px 10px rgba(37,99,235,0.35);
        display:inline-flex; align-items:center; gap:0.4rem;
        transition:transform 0.15s, box-shadow 0.15s;
    " onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 5px 16px rgba(37,99,235,0.5)'"
       onmouseout="this.style.transform='';this.style.boxShadow='0 3px 10px rgba(37,99,235,0.35)'">
        ➕ {{ $btnTexto }}
    </a>
    @endisset
</div>
