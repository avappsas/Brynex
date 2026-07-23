@extends('layouts.app')
@section('titulo', 'Marketing')
@section('modulo', 'Lista: ' . $lista->nombre)

@push('styles')
<style>
.page-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.5rem; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:.75rem; }
.page-title { font-size:1.05rem; font-weight:700; color:#0f172a; }
.filtros { display:flex; gap:.6rem; flex-wrap:wrap; margin-bottom:1.1rem; }
.filtros select, .filtros input { padding:.4rem .6rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.8rem; }
.btn { padding:.4rem .9rem; border-radius:8px; font-size:.8rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; }
.btn-primary { background:#2563eb; color:#fff; }
.wa-table { width:100%; border-collapse:collapse; }
.wa-table th, .wa-table td { padding:.55rem .8rem; border-bottom:1px solid #f1f5f9; font-size:.8rem; }
.wa-table th { background:#f8fafc; font-weight:600; color:#475569; text-align:left; }
.wa-table tr:hover td { background:#fafafa; }
.empty-state { text-align:center; padding:3rem 1rem; color:#94a3b8; }
</style>
@endpush

@section('contenido')
<div class="contenido">
    <div style="margin-bottom:1rem;">
        <a href="{{ route('admin.marketing.listas.index') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">← Listas de contactos</a>
    </div>

    @if(session('ok'))
        <div class="flash success">✅ {{ session('ok') }}</div>
    @endif

    <div class="page-card">
        <div class="page-header">
            <div>
                <div class="page-title">📋 {{ $lista->nombre }}</div>
                <small style="color:#64748b">{{ $lista->descripcion ?? 'Sin descripción' }} · {{ $contactos->total() }} contactos</small>
            </div>
        </div>

        <form method="GET" class="filtros">
            <input type="text" name="buscar" placeholder="Buscar nombre, celular o cédula..." value="{{ request('buscar') }}">
            @if($departamentos->isNotEmpty())
                <select name="departamento" onchange="this.form.submit()">
                    <option value="">Todos los departamentos</option>
                    @foreach($departamentos as $d)
                        <option value="{{ $d }}" @selected(request('departamento') == $d)>{{ $d }}</option>
                    @endforeach
                </select>
            @endif
            @if($ciudades->isNotEmpty())
                <select name="ciudad" onchange="this.form.submit()">
                    <option value="">Todas las ciudades</option>
                    @foreach($ciudades as $c)
                        <option value="{{ $c }}" @selected(request('ciudad') == $c)>{{ $c }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </form>

        @if($contactos->isEmpty())
            <div class="empty-state">No hay contactos que coincidan con el filtro.</div>
        @else
            <table class="wa-table">
                <thead>
                    <tr>
                        <th>Celular</th>
                        <th>Cédula</th>
                        <th>Nombres</th>
                        <th>Departamento</th>
                        <th>Ciudad</th>
                        <th>Observación</th>
                        <th>Veces contactado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contactos as $c)
                        <tr>
                            <td>{{ $c->celular }}</td>
                            <td>{{ $c->cedula ?? '—' }}</td>
                            <td>{{ $c->nombres ?? '—' }}</td>
                            <td>{{ $c->departamento ?? '—' }}</td>
                            <td>{{ $c->ciudad ?? '—' }}</td>
                            <td style="color:#64748b">{{ $c->observacion ?? '—' }}</td>
                            <td>{{ $c->veces_contactado }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:1rem">{{ $contactos->links() }}</div>
        @endif
    </div>
</div>
@endsection
