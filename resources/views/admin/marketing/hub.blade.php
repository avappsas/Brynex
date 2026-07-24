@extends('layouts.app')
@section('modulo', 'Marketing')

@section('contenido')
<div style="max-width:760px;margin:0 auto;">

    <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 .25rem;">📣 Marketing</h1>
    <p style="font-size:.85rem;color:#64748b;margin:0 0 1.5rem;">Elige qué quieres gestionar.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.25rem;">

        <a href="{{ route('admin.marketing.campanas.index') }}" style="text-decoration:none;color:inherit;">
            <div style="background:#fff;border:2px solid transparent;border-radius:16px;padding:1.75rem 1.5rem;box-shadow:0 1px 8px rgba(0,0,0,.06);transition:border-color .15s,transform .15s;height:100%;"
                 onmouseover="this.style.borderColor='#25d366';this.style.transform='translateY(-3px)'"
                 onmouseout="this.style.borderColor='transparent';this.style.transform=''">
                <div style="font-size:2.2rem;margin-bottom:.75rem;">📢</div>
                <div style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:.35rem;">Campañas WhatsApp</div>
                <div style="font-size:.82rem;color:#64748b;line-height:1.4;">Listas de contactos y envíos masivos de campañas por WhatsApp.</div>
            </div>
        </a>

        <a href="{{ route('admin.publicidad.index') }}" style="text-decoration:none;color:inherit;">
            <div style="background:#fff;border:2px solid transparent;border-radius:16px;padding:1.75rem 1.5rem;box-shadow:0 1px 8px rgba(0,0,0,.06);transition:border-color .15s,transform .15s;height:100%;position:relative;"
                 onmouseover="this.style.borderColor='#2563eb';this.style.transform='translateY(-3px)'"
                 onmouseout="this.style.borderColor='transparent';this.style.transform=''">
                @if($pendientes > 0)
                    <span style="position:absolute;top:1.1rem;right:1.1rem;background:#fef9c3;color:#a16207;font-size:.68rem;font-weight:700;padding:.2rem .55rem;border-radius:999px;">{{ $pendientes }} pendiente(s)</span>
                @endif
                <div style="font-size:2.2rem;margin-bottom:.75rem;">🖼️</div>
                <div style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:.35rem;">Redes y Publicaciones</div>
                <div style="font-size:.82rem;color:#64748b;line-height:1.4;">Genera imágenes (plantilla, IA o piloto automático), aprueba y publica en la web, Facebook e Instagram.</div>
            </div>
        </a>

    </div>
</div>
@endsection
