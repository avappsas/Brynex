@extends('layouts.app')
@section('modulo', 'Documentos de la Razón Social')

@section('contenido')
{{-- Solo los documentos, para quien los sube sin poder editar los datos de
     la empresa (`razones_sociales.documentos` sin `razones_sociales.gestionar`). --}}
<style>
.rs-wrap{max-width:960px;margin:0 auto}
.rs-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:.9rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.7rem}
.card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1.3rem;margin-bottom:1rem}
.card-title{font-size:.82rem;font-weight:800;color:#0f172a;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid #e2e8f0;text-transform:uppercase;letter-spacing:.04em}
.flb{display:block;font-size:.67rem;font-weight:700;color:#475569;margin-bottom:.18rem;text-transform:uppercase;letter-spacing:.02em}
.flb span{color:#ef4444;margin-left:.15rem}
.finp{width:100%;padding:.42rem .6rem;border:1.5px solid #cbd5e1;border-radius:7px;font-size:.84rem;box-sizing:border-box;transition:border-color .15s;background:#fff}
.finp:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
</style>

<div class="rs-wrap">

<div class="rs-header">
    <div>
        <a href="{{ route('admin.configuracion.razones.index') }}" style="color:#94a3b8;font-size:.75rem;text-decoration:none">← Razones Sociales</a>
        <div style="font-size:1.1rem;font-weight:800;margin-top:.2rem">
            📁 {{ Str::limit($rs->razon_social, 50) }}
        </div>
    </div>
    <div style="font-size:.75rem;color:#94a3b8">NIT: {{ $rs->nit ?? $rs->id }}</div>
</div>

@if($errors->any())
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:9px;padding:.7rem 1rem;margin-bottom:.9rem;color:#dc2626;font-size:.82rem">
    @foreach($errors->all() as $e)<div>⚠️ {{ $e }}</div>@endforeach
</div>
@endif

@if(session('success'))
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:9px;padding:.7rem 1rem;margin-bottom:.9rem;color:#15803d;font-size:.82rem;font-weight:600">
    {{ session('success') }}
</div>
@endif

@include('admin.razones_sociales.partials.documentos')

</div>
@endsection
