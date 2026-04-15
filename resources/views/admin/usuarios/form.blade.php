@extends('layouts.app')
@section('modulo', isset($usuario->id) ? 'Editar Usuario' : 'Nuevo Usuario')

@section('contenido')
<div style="max-width:680px;margin:0 auto;">
<div style="background:#fff;border-radius:14px;padding:1.75rem 2rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);">

    @include('admin.partials.table-header', [
        'titulo' => isset($usuario->id) ? '✏️ Editar Usuario' : '👤 Nuevo Usuario',
    ])

    @if($errors->any())
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
            <strong>Corrige los siguientes errores:</strong>
            <ul style="margin:0.4rem 0 0 1rem;">
                @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    @php $esEdicion = isset($usuario->id); @endphp

    <form method="POST"
          action="{{ $esEdicion ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}">
        @csrf
        @if($esEdicion) @method('PUT') @endif

        {{-- Fila 1: Nombre + Cédula --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Nombre completo *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $usuario->nombre) }}" required
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Cédula * (login)</label>
                <input type="text" name="cedula" value="{{ old('cedula', $usuario->cedula) }}" required
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;font-family:monospace;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
        </div>

        {{-- Fila 2: Email + Teléfono --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Correo (opcional)</label>
                <input type="email" name="email" value="{{ old('email', $usuario->email) }}"
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Teléfono</label>
                <input type="text" name="telefono" value="{{ old('telefono', $usuario->telefono) }}"
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
        </div>

        {{-- Fila 3: Aliado + Rol --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Aliado *</label>
                <select name="aliado_id" required
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;background:#fff;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
                    <option value="">— Seleccionar —</option>
                    @foreach($aliados as $aliado)
                        <option value="{{ $aliado->id }}"
                            {{ old('aliado_id', $usuario->aliado_id) == $aliado->id ? 'selected' : '' }}>
                            {{ $aliado->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Rol *</label>
                <select name="rol" required
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;background:#fff;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
                    <option value="">— Seleccionar —</option>
                    @foreach($roles as $rol)
                        <option value="{{ $rol }}"
                            {{ old('rol', $usuario->getRoleNames()->first()) == $rol ? 'selected' : '' }}>
                            {{ ucfirst($rol) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Contraseña --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">
                    Contraseña {{ $esEdicion ? '(dejar en blanco para no cambiar)' : '*' }}
                </label>
                <input type="password" name="password" {{ !$esEdicion ? 'required' : '' }}
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" {{ !$esEdicion ? 'required' : '' }}
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
        </div>

        {{-- Flags: es_brynex + activo --}}
        <div style="display:flex;gap:2rem;margin-bottom:1.5rem;padding:1rem;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                <input type="checkbox" name="es_brynex" value="1"
                    {{ old('es_brynex', $usuario->es_brynex ?? false) ? 'checked' : '' }}
                    style="width:18px;height:18px;cursor:pointer;">
                <div>
                    <div style="font-weight:600;font-size:0.85rem;color:#0f172a;">🔵 Usuario BryNex</div>
                    <div style="font-size:0.72rem;color:#64748b;">Puede cambiar de aliado sin cambiar sesión</div>
                </div>
            </label>
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                <input type="checkbox" name="activo" value="1"
                    {{ old('activo', $usuario->activo ?? true) ? 'checked' : '' }}
                    style="width:18px;height:18px;cursor:pointer;">
                <div>
                    <div style="font-weight:600;font-size:0.85rem;color:#0f172a;">✅ Usuario activo</div>
                    <div style="font-size:0.72rem;color:#64748b;">Puede iniciar sesión en el sistema</div>
                </div>
            </label>
        </div>

        {{-- Botones --}}
        <div style="display:flex;gap:0.75rem;justify-content:flex-end;border-top:1px solid #f1f5f9;padding-top:1.25rem;">
            <a href="{{ route('admin.usuarios.index') }}"
                style="padding:0.6rem 1.25rem;border:1px solid #cbd5e1;border-radius:8px;color:#475569;text-decoration:none;font-size:0.85rem;font-weight:500;">
                Cancelar
            </a>
            <button type="submit"
                style="padding:0.6rem 1.5rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;border-radius:8px;color:#fff;font-size:0.85rem;font-weight:600;cursor:pointer;box-shadow:0 3px 10px rgba(37,99,235,0.35);">
                💾 {{ $esEdicion ? 'Actualizar' : 'Crear Usuario' }}
            </button>
        </div>
    </form>

</div>
</div>
@endsection
