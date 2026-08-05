@extends('layouts.app')
@section('modulo','Usuarios')

@section('contenido')
<div style="background:#fff;border-radius:14px;padding:1.5rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);">

    @include('admin.partials.table-header', [
        'titulo'    => '👤 Usuarios del Sistema',
        'subtitulo' => 'Aliado activo: ' . ($alidoActivo->nombre ?? 'BryNex'),
        'btnTexto'  => auth()->user()->can('usuarios.gestionar') ? 'Nuevo Usuario' : null,
        'btnRuta'   => auth()->user()->can('usuarios.gestionar') ? route('admin.usuarios.create') : null,
    ])

    @if(session('success'))
        <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:8px;color:#065f46;padding:0.6rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
            ✅ {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.6rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Buscador superior --}}
    <form method="GET" action="{{ route('admin.usuarios.index') }}" id="filter-form" style="background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;padding:0.75rem 1rem;margin-bottom:1.25rem;display:flex;gap:0.75rem;align-items:center;">
        <div style="position:relative;flex:1;">
            <span style="position:absolute;left:0.75rem;top:52%;transform:translateY(-50%);color:#94a3b8;font-size:0.9rem;">🔍</span>
            <input type="text" name="q" value="{{ $buscar }}" placeholder="Buscar por cédula o parte del nombre..."
                style="width:100%;padding:0.55rem 0.75rem 0.55rem 2.2rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;outline:none;transition:border-color 0.15s;"
                onfocus="this.style.borderColor='var(--acento)'" onblur="this.style.borderColor='#cbd5e1'">
        </div>
        <button type="submit" style="padding:0.55rem 1.25rem;background:var(--azul-btn);color:#fff;border:none;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;transition:background 0.15s;"
            onmouseover="this.style.background='var(--acento)'" onmouseout="this.style.background='var(--azul-btn)'">
            Buscar
        </button>
        @if($buscar || $filtroRol || $filtroEstado)
        <a href="{{ route('admin.usuarios.index') }}" style="padding:0.55rem 1rem;border:1px solid #cbd5e1;border-radius:8px;color:#475569;text-decoration:none;font-size:0.85rem;background:#fff;transition:background 0.15s;text-align:center;display:inline-block;"
            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
            Limpiar Filtros
        </a>
        @endif
    </form>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
            <thead>
                <tr style="background:#f1f5f9;">
                    <th style="padding:0.7rem 1rem;text-align:left;color:#475569;font-weight:600;">Nombre</th>
                    <th style="padding:0.7rem 1rem;text-align:left;color:#475569;font-weight:600;">Cédula</th>
                    <th style="padding:0.7rem 1rem;text-align:left;color:#475569;font-weight:600;">Aliado</th>
                    <th style="padding:0.7rem 1rem;text-align:center;color:#475569;font-weight:600;">
                        <div style="display:inline-flex;flex-direction:column;align-items:center;gap:0.2rem;">
                            <span>Rol</span>
                            <select name="rol" form="filter-form" onchange="this.form.submit()" style="padding:0.15rem 0.35rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.72rem;background:#fff;color:#334155;font-weight:normal;cursor:pointer;outline:none;">
                                <option value="">Todos</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r }}" @selected($filtroRol === $r)>{{ ucfirst($r) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </th>
                    <th style="padding:0.7rem 1rem;text-align:center;color:#475569;font-weight:600;">BryNex</th>
                    <th style="padding:0.7rem 1rem;text-align:center;color:#475569;font-weight:600;">
                        <div style="display:inline-flex;flex-direction:column;align-items:center;gap:0.2rem;">
                            <span>Estado</span>
                            <select name="estado" form="filter-form" onchange="this.form.submit()" style="padding:0.15rem 0.35rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.72rem;background:#fff;color:#334155;font-weight:normal;cursor:pointer;outline:none;">
                                <option value="">Todos</option>
                                <option value="activo" @selected($filtroEstado === 'activo')>Activos</option>
                                <option value="pausado" @selected($filtroEstado === 'pausado')>Pausados</option>
                                <option value="inactivo" @selected($filtroEstado === 'inactivo')>Inactivos</option>
                            </select>
                        </div>
                    </th>
                    <th style="padding:0.7rem 1rem;text-align:center;color:#475569;font-weight:600;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $usuario)
                <tr style="border-bottom:1px solid #f1f5f9;{{ $usuario->trashed() ? 'opacity:0.5;' : '' }}">
                    <td style="padding:0.65rem 1rem;">
                        <div style="font-weight:600;color:#0f172a;">{{ $usuario->nombre }}</div>
                        <div style="font-size:0.75rem;color:#94a3b8;">{{ $usuario->email }}</div>
                    </td>
                    <td style="padding:0.65rem 1rem;font-family:monospace;color:#475569;">{{ $usuario->cedula }}</td>
                    <td style="padding:0.65rem 1rem;color:#334155;">{{ $usuario->aliado->nombre ?? '—' }}</td>
                    <td style="padding:0.65rem 1rem;text-align:center;">
                        @php $rol = $usuario->getRoleNames()->first() @endphp
                        @if($rol)
                        <span style="background:#ede9fe;color:#6d28d9;padding:0.15rem 0.65rem;border-radius:999px;font-size:0.72rem;font-weight:600;text-transform:capitalize;">
                            {{ $rol }}
                        </span>
                        @else
                        <span style="color:#94a3b8;font-size:0.75rem;">Sin rol</span>
                        @endif
                    </td>
                    <td style="padding:0.65rem 1rem;text-align:center;">
                        @if($usuario->es_brynex)
                            <span style="background:#dbeafe;color:#1d4ed8;padding:0.15rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:600;">✓ BryNex</span>
                        @else
                            <span style="color:#cbd5e1;font-size:0.75rem;">—</span>
                        @endif
                    </td>
                    <td style="padding:0.65rem 1rem;text-align:center;">
                        @if($usuario->trashed())
                            <span style="background:#fee2e2;color:#dc2626;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:600;">Inactivo</span>
                        @elseif($usuario->activo)
                            <span style="background:#dcfce7;color:#16a34a;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:600;">Activo</span>
                        @else
                            <span style="background:#fef9c3;color:#ca8a04;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:600;">Pausado</span>
                        @endif
                    </td>
                    <td style="padding:0.65rem 1rem;text-align:center;white-space:nowrap;">
                        @if($usuario->trashed())
                            <form method="POST" action="{{ route('admin.usuarios.restore', $usuario->id) }}" style="display:inline;">
                                @csrf @method('PATCH')
                                <button type="submit" style="background:#dcfce7;border:none;color:#16a34a;padding:0.35rem 0.75rem;border-radius:6px;font-size:0.76rem;font-weight:600;cursor:pointer;">
                                    ↩ Restaurar
                                </button>
                            </form>
                        @else
                            @can('usuarios.gestionar')
                            <a href="{{ route('admin.usuarios.edit', $usuario) }}"
                                style="background:#dbeafe;border:none;color:#2563eb;padding:0.35rem 0.75rem;border-radius:6px;font-size:0.76rem;font-weight:600;text-decoration:none;display:inline-block;margin-right:0.25rem;">
                                ✏️ Editar
                            </a>
                            @endcan
                            @can('usuarios.permisos')
                            <a href="{{ route('admin.usuarios.permisos.edit', $usuario) }}"
                                style="background:#fef3c7;border:none;color:#b45309;padding:0.35rem 0.75rem;border-radius:6px;font-size:0.76rem;font-weight:600;text-decoration:none;display:inline-block;margin-right:0.25rem;"
                                title="Permisos por módulo de este usuario">
                                🔐 Permisos
                            </a>
                            @endcan
                            @if($usuario->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.usuarios.destroy', $usuario) }}" style="display:inline;"
                                onsubmit="return confirm('¿Desactivar el usuario {{ $usuario->nombre }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:#fee2e2;border:none;color:#dc2626;padding:0.35rem 0.75rem;border-radius:6px;font-size:0.76rem;font-weight:600;cursor:pointer;">
                                    🗑 Desactivar
                                </button>
                            </form>
                            @endif
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;">
                        No hay usuarios. <a href="{{ route('admin.usuarios.create') }}" style="color:#2563eb;">Crear el primero →</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
