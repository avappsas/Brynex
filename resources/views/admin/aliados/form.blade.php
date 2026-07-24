@extends('layouts.app')
@section('modulo', isset($aliado->id) ? 'Editar Aliado' : 'Nuevo Aliado')

@section('contenido')
<div style="max-width:720px;margin:0 auto;">
<div style="background:#fff;border-radius:14px;padding:1.75rem 2rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);">

    @include('admin.partials.table-header', [
        'titulo' => isset($aliado->id) ? '✏️ Editar Aliado' : '🏢 Nuevo Aliado',
    ])

@if($errors->any())
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
            <strong>Corrige los siguientes errores:</strong>
            <ul style="margin:0.4rem 0 0 1rem;">
                @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ isset($aliado->id) ? route('admin.aliados.update', $aliado) : route('admin.aliados.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if(isset($aliado->id)) @method('PUT') @endif

        {{-- Fila 1 --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $aliado->nombre) }}" required
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">NIT</label>
                <input type="text" name="nit" value="{{ old('nit', $aliado->nit) }}"
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
        </div>

        {{-- Razón social --}}
        <div style="margin-bottom:1rem;">
            <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Razón Social</label>
            <input type="text" name="razon_social" value="{{ old('razon_social', $aliado->razon_social) }}"
                style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
        </div>

        {{-- Fila contacto --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:1rem;margin-bottom:1rem;">
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Contacto</label>
                <input type="text" name="contacto" value="{{ old('contacto', $aliado->contacto) }}"
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Teléfono</label>
                <input type="text" name="telefono" value="{{ old('telefono', $aliado->telefono) }}"
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Celular</label>
                <input type="text" name="celular" value="{{ old('celular', $aliado->celular) }}"
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">WhatsApp Notificaciones</label>
                <input type="text" name="whatsapp" value="{{ old('whatsapp', $aliado->whatsapp) }}" placeholder="Ej: 573001234567"
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
        </div>

        {{-- Correo + Dirección --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Correo</label>
                <input type="email" name="correo" value="{{ old('correo', $aliado->correo) }}"
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Ciudad</label>
                <input type="text" name="ciudad" value="{{ old('ciudad', $aliado->ciudad) }}"
                    style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
        </div>

        {{-- Dirección --}}
        <div style="margin-bottom:1rem;">
            <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Dirección</label>
            <input type="text" name="direccion" value="{{ old('direccion', $aliado->direccion) }}"
                style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;"
                onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
        </div>

        {{-- Logo + Color + Estado --}}
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:1rem;margin-bottom:1.5rem;align-items:end;">
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Logo — fondos claros (PNG/JPG)</label>
                @if(isset($aliado->id) && $aliado->logo)
                    <div style="margin-bottom:0.4rem;display:flex;align-items:center;gap:0.5rem;">
                        <img id="logo-preview"
                             src="{{ asset('storage/'.$aliado->logo) }}"
                             style="height:48px;border-radius:6px;border:1px solid #e2e8f0;object-fit:contain;"
                             onerror="this.style.display='none';document.getElementById('logo-error').style.display='flex';"
                        >
                        <span id="logo-error" style="display:none;align-items:center;gap:0.4rem;font-size:0.75rem;color:#dc2626;background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;padding:0.3rem 0.6rem;">
                            ⚠️ Imagen no encontrada en el servidor — sube una nueva
                        </span>
                    </div>
                @endif
                <input type="file" name="logo" accept="image/*"
                    style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;font-family:inherit;">
                <div style="font-size:0.68rem;color:#94a3b8;margin-top:0.25rem;">Versión oscura/de color — se usa cuando la foto de fondo es clara.</div>
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Color</label>
                <input type="color" name="color_primario" value="{{ old('color_primario', $aliado->color_primario ?? '#2563eb') }}"
                    style="width:100%;height:40px;border:1px solid #cbd5e1;border-radius:8px;cursor:pointer;padding:2px;">
            </div>
            <div style="display:flex;flex-direction:column;gap:0.4rem;">
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.04em;">Activo</label>
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;margin-top:0.25rem;">
                    <input type="checkbox" name="activo" value="1" {{ old('activo', $aliado->activo ?? true) ? 'checked' : '' }}
                        style="width:18px;height:18px;cursor:pointer;">
                    <span style="font-size:0.85rem;color:#334155;">Sí</span>
                </label>
            </div>
        </div>

        {{-- Logo — fondos oscuros --}}
        <div style="margin-bottom:1.5rem;">
            <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Logo — fondos oscuros (opcional)</label>
            @if(isset($aliado->id) && $aliado->logo_oscuro)
                <div style="margin-bottom:0.4rem;display:flex;align-items:center;gap:0.5rem;background:#0f172a;padding:0.5rem;border-radius:6px;width:fit-content;">
                    <img src="{{ asset('storage/'.$aliado->logo_oscuro) }}" style="height:48px;object-fit:contain;">
                </div>
            @endif
            <input type="file" name="logo_oscuro" accept="image/*"
                style="width:100%;max-width:400px;padding:0.5rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;font-family:inherit;">
            <div style="font-size:0.68rem;color:#94a3b8;margin-top:0.25rem;">Versión clara/blanca — se usa automáticamente cuando la foto de fondo es oscura. Si no la subes, siempre se usa el logo de arriba.</div>
        </div>

        {{-- ── Afiliaciones BryNex ── --}}
        <div style="margin-bottom:1.5rem;padding:1rem;background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;">
            <div style="font-size:0.78rem;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.8rem;">
                📋 Gestión de Afiliaciones
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:start;">
                <div>
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" name="afiliaciones_brynex" value="1"
                            {{ old('afiliaciones_brynex', $aliado->afiliaciones_brynex ?? false) ? 'checked' : '' }}
                            style="width:18px;height:18px;cursor:pointer;" id="chkAfiliacionesBrynex"
                            onchange="toggleEncargado(this.checked)">
                        <span style="font-size:0.85rem;font-weight:600;color:#0369a1;">BryNex gestiona las afiliaciones</span>
                    </label>
                    <p style="font-size:0.72rem;color:#64748b;margin:0.3rem 0 0 1.7rem;">
                        Activa si BryNex es responsable de tramitar las afiliaciones de los cotizantes de este aliado.
                    </p>
                </div>
                <div id="divEncargadoAfil" style="{{ old('afiliaciones_brynex', $aliado->afiliaciones_brynex ?? false) ? '' : 'opacity:0.4;pointer-events:none;' }}">
                    <label style="display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">
                        Encargado de Afiliación (BryNex)
                    </label>
                    <select name="encargado_afil_id"
                        style="width:100%;padding:0.6rem 0.85rem;border:1px solid #bae6fd;border-radius:8px;font-size:0.9rem;outline:none;font-family:inherit;background:#fff;">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuariosBrynex ?? [] as $ub)
                        <option value="{{ $ub->id }}" {{ old('encargado_afil_id', $aliado->encargado_afil_id) == $ub->id ? 'selected' : '' }}>
                            {{ $ub->nombre }}
                        </option>
                        @endforeach
                    </select>
                    <p style="font-size:0.72rem;color:#64748b;margin:0.25rem 0 0;">
                        Usuario BryNex asignado por defecto al crear contratos de este aliado.
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Módulos BryNex contratados ── --}}
        @php
            $todosModulos = \App\Models\BrynexModulo::orderBy('orden')->get();
            $modulosContratados = isset($aliado->id)
                ? \App\Models\BrynexModuloAliado::where('aliado_id', $aliado->id)->pluck('activo', 'modulo_id')->toArray()
                : [];
        @endphp
        <div style="margin-bottom:1.5rem;padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
            <div style="font-size:0.78rem;font-weight:700;color:#1e3a8a;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.8rem;">
                🔵 Módulos BryNex Contratados (Cobros por Uso)
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:0.75rem;">
                @foreach($todosModulos as $mod)
                    @php
                        $moduloActivo = !isset($aliado->id) && $mod->id == 1;
                        if (isset($aliado->id)) {
                            $moduloActivo = isset($modulosContratados[$mod->id]) && $modulosContratados[$mod->id] == 1;
                        }
                    @endphp
                    <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;background:#fff;padding:0.4rem 0.6rem;border-radius:6px;border:1px solid #cbd5e1;">
                        <input type="checkbox" name="modulos[{{ $mod->id }}]" value="1" {{ $moduloActivo ? 'checked' : '' }} style="width:16px;height:16px;cursor:pointer;">
                        <div>
                            <span style="font-size:0.8rem;font-weight:600;color:#334155;">{{ $mod->nombre }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
            <p style="font-size:0.7rem;color:#64748b;margin:0.5rem 0 0 0;">
                Nota: Las tarifas personalizadas para cada módulo contratado pueden gestionarse desde el menú BryNex → Consumo y Cobros.
            </p>
        </div>

        {{-- Botones --}}
        <div style="display:flex;gap:0.75rem;justify-content:flex-end;border-top:1px solid #f1f5f9;padding-top:1.25rem;">
            <a href="{{ route('admin.aliados.index') }}"
                style="padding:0.6rem 1.25rem;border:1px solid #cbd5e1;border-radius:8px;color:#475569;text-decoration:none;font-size:0.85rem;font-weight:500;">
                Cancelar
            </a>
            <button type="submit"
                style="padding:0.6rem 1.5rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;border-radius:8px;color:#fff;font-size:0.85rem;font-weight:600;cursor:pointer;box-shadow:0 3px 10px rgba(37,99,235,0.35);">
                💾 {{ isset($aliado->id) ? 'Actualizar' : 'Crear Aliado' }}
            </button>
        </div>

    </form>

</div>
</div>
@endsection

@push('scripts')
<script>
function toggleEncargado(checked) {
    const div = document.getElementById('divEncargadoAfil');
    div.style.opacity = checked ? '1' : '0.4';
    div.style.pointerEvents = checked ? 'auto' : 'none';
}
</script>
@endpush
