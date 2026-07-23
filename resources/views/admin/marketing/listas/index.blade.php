@extends('layouts.app')
@section('titulo', 'Marketing')
@section('modulo', 'Listas de contactos')

@push('styles')
<style>
.page-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.5rem; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:.75rem; }
.page-title { font-size:1.05rem; font-weight:700; color:#0f172a; }
.btn { padding:.45rem 1rem; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:opacity .15s; }
.btn:hover { opacity:.87; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-danger  { background:#fff; color:#dc2626; border:1px solid #fecaca; }
.btn-sm { padding:.3rem .7rem; font-size:.76rem; }
.stats-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:.75rem; margin-bottom:1.5rem; }
.stat-box { background:#f8fafc; border-radius:10px; padding:.9rem 1.1rem; }
.stat-box .num { font-size:1.4rem; font-weight:700; color:#0f172a; }
.stat-box .label { font-size:.75rem; color:#64748b; }
.wa-table { width:100%; border-collapse:collapse; }
.wa-table th, .wa-table td { padding:.6rem .9rem; border-bottom:1px solid #f1f5f9; font-size:.82rem; }
.wa-table th { background:#f8fafc; font-weight:600; color:#475569; text-align:left; }
.wa-table tr:hover td { background:#fafafa; }
.empty-state { text-align:center; padding:3rem 1rem; color:#94a3b8; }
.empty-state .empty-icon { font-size:3rem; margin-bottom:.75rem; }
</style>
@endpush

@section('contenido')
<div class="contenido">
    @if(session('ok'))
        <div class="flash success">✅ {{ session('ok') }}</div>
    @endif
    @if($errors->any())
        <div class="flash" style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.83rem;">
            @foreach($errors->all() as $e) <div>❌ {{ $e }}</div> @endforeach
        </div>
    @endif

    <div class="stats-row">
        <div class="stat-box"><div class="num">{{ $totalContactos }}</div><div class="label">Contactos en el pool</div></div>
        <div class="stat-box"><div class="num">{{ $listas->count() }}</div><div class="label">Listas creadas</div></div>
        <div class="stat-box"><div class="num">{{ $totalBloqueados }}</div><div class="label">🚫 En lista negra</div></div>
    </div>

    <div class="page-card">
        <div class="page-header">
            <div>
                <div class="page-title">📋 Listas de contactos de marketing</div>
                <small style="color:#64748b">Cada número vive una sola vez en tu pool aunque esté en varias listas.</small>
            </div>
            <a href="{{ route('admin.marketing.listas.create') }}" class="btn btn-primary">+ Cargar contactos</a>
        </div>

        @if($listas->isEmpty())
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <p>Todavía no has cargado ninguna lista de contactos.</p>
            </div>
        @else
            <table class="wa-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Contactos</th>
                        <th>Creada</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($listas as $lista)
                        <tr>
                            <td><a href="{{ route('admin.marketing.listas.show', $lista->id) }}" style="color:#2563eb;text-decoration:none;font-weight:600">{{ $lista->nombre }}</a></td>
                            <td style="color:#64748b">{{ $lista->descripcion ?? '—' }}</td>
                            <td>{{ $lista->contactos_count }}</td>
                            <td style="color:#64748b">{{ $lista->created_at->format('d/m/Y') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.marketing.listas.destroy', $lista->id) }}"
                                      onsubmit="return confirm('¿Eliminar esta lista? Los contactos siguen en el pool general.')" style="display:inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Eliminar lista</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
