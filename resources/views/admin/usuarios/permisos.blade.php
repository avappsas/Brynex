@extends('layouts.app')
@section('modulo','Usuarios')

@section('contenido')
<div x-data="{ buscar: '' }"
     style="background:#fff;border-radius:14px;padding:1.5rem;box-shadow:0 1px 8px rgba(0,0,0,0.06);">

    {{-- ── Cabecera ─────────────────────────────────────────────────── --}}
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem;">
        <div>
            <h2 style="font-size:1.15rem;font-weight:700;color:#0f172a;margin:0 0 0.25rem;">
                🔐 Permisos de {{ $usuario->nombre }}
            </h2>
            <p style="font-size:0.82rem;color:#64748b;margin:0;">
                Cédula {{ $usuario->cedula }} ·
                Rol
                <strong style="color:#334155;">
                    {{ $usuario->roles->pluck('name')->join(', ') ?: 'sin rol' }}
                </strong>
                @if($usuario->es_brynex)
                    · <span style="color:#2563eb;font-weight:600;">BryNex</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.usuarios.index') }}"
           style="padding:0.5rem 1rem;border:1px solid #cbd5e1;border-radius:8px;color:#475569;text-decoration:none;font-size:0.85rem;background:#fff;">
            ← Volver
        </a>
    </div>

    @if(session('success'))
        <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:8px;color:#065f46;padding:0.6rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
            ✅ {{ session('success') }}
        </div>
    @endif

    {{-- ── Cómo leer esta pantalla ──────────────────────────────────── --}}
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0.85rem 1rem;margin-bottom:1.25rem;font-size:0.8rem;color:#475569;line-height:1.7;">
        <span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#94a3b8;vertical-align:-1px;"></span>
        <strong>Gris</strong>: ya lo da el rol, no hace falta marcarlo (para quitarlo, cambia el rol).
        &nbsp;·&nbsp;
        <span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#2563eb;vertical-align:-1px;"></span>
        <strong>Azul</strong>: otorgado a este usuario en particular.
        &nbsp;·&nbsp;
        🔒 <strong>Restringido</strong>: ningún rol lo trae, ni siquiera superadmin. Solo se consigue aquí.
        <br>
        <br>
        A la derecha de cada permiso van <strong>los del equipo que ya lo tienen</strong>:
        <span style="font-size:0.68rem;padding:0.1rem 0.4rem;border-radius:5px;background:#f1f5f9;color:#94a3b8;">gris</span> le llega por el rol,
        <span style="font-size:0.68rem;padding:0.1rem 0.4rem;border-radius:5px;background:#ede9fe;color:#6d28d9;font-weight:600;">★ morado</span> se lo dieron a mano,
        <span style="font-size:0.68rem;padding:0.1rem 0.4rem;border-radius:5px;background:#dbeafe;color:#1d4ed8;font-weight:700;">azul</span> es este usuario.
        Solo se cuentan los activos de este aliado.
        <br>
        <span style="color:#94a3b8;">Aquí solo aparece lo que hay que decidir. Lo que cualquier trabajador del aliado
        ya tiene por su rol (ver clientes, afiliar, radicar, incapacidades, cotizar…) no se muestra
        porque no hay nada que marcar.</span>
        @if($esSuper)
            <br><strong style="color:#b45309;">Este usuario es superadmin:</strong> ya tiene todo lo no restringido.
            Aquí solo tiene sentido marcarle los 🔒.
        @endif
    </div>

    <form method="POST" action="{{ route('admin.usuarios.permisos.update', $usuario) }}">
        @csrf

        {{-- Buscador de permisos --}}
        <input type="text" x-model="buscar" placeholder="🔍 Filtrar módulo o permiso…"
               style="width:100%;padding:0.55rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;outline:none;margin-bottom:1.25rem;">

        @foreach($grupos as $codigoGrupo => $nombreGrupo)
            @php $delGrupo = $modulos[$codigoGrupo] ?? collect(); @endphp
            @if($delGrupo->isNotEmpty())
                <div style="margin-bottom:1.5rem;">
                    <div style="font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#94a3b8;margin-bottom:0.6rem;">
                        {{ $nombreGrupo }}
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(430px,1fr));gap:0.85rem;">
                        @foreach($delGrupo as $modulo)
                            <div x-show="buscar === '' || '{{ Str::lower($modulo->nombre . ' ' . $modulo->codigo . ' ' . $modulo->permisosAsignables->pluck('etiqueta')->join(' ')) }}'.includes(buscar.toLowerCase())"
                                 style="border:1px solid #e2e8f0;border-radius:10px;padding:0.85rem 1rem;background:{{ $modulo->restringido ? '#fffbeb' : '#fff' }};">

                                <div style="font-size:0.88rem;font-weight:600;color:#0f172a;margin-bottom:0.6rem;">
                                    {{ $modulo->icono }} {{ $modulo->nombre }}
                                    @if($modulo->solo_brynex)
                                        <span style="font-size:0.68rem;background:#dbeafe;color:#1d4ed8;padding:0.1rem 0.4rem;border-radius:5px;font-weight:600;">BryNex</span>
                                    @endif
                                </div>

                                @foreach($modulo->permisosAsignables as $permiso)
                                    @php
                                        $viaRol      = in_array($permiso->name, $porRol, true);
                                        $directo     = in_array($permiso->name, $directos, true);
                                        $restringido = $permiso->restringido || $modulo->restringido;
                                        // Un superadmin ya tiene todo lo no restringido por Gate::before
                                        $heredado    = $viaRol || ($esSuper && ! $restringido);
                                        $loTienen    = $quienTiene[$permiso->name] ?? [];
                                    @endphp
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.75rem;padding:0.3rem 0;border-top:1px solid #f8fafc;">
                                        <label style="display:flex;align-items:flex-start;gap:0.5rem;font-size:0.82rem;flex:0 0 52%;color:{{ $heredado ? '#94a3b8' : '#334155' }};cursor:{{ $heredado ? 'default' : 'pointer' }};">
                                            <input type="checkbox"
                                                   name="permisos[]"
                                                   value="{{ $permiso->name }}"
                                                   @checked($heredado || $directo)
                                                   @disabled($heredado)
                                                   style="margin-top:0.18rem;accent-color:{{ $heredado ? '#94a3b8' : '#2563eb' }};">
                                            <span>
                                                {{ $permiso->etiqueta }}
                                                @if($restringido)
                                                    <span title="Permiso restringido: no lo da ningún rol">🔒</span>
                                                @endif
                                                @if($heredado)
                                                    <span style="font-size:0.7rem;color:#cbd5e1;">(del rol)</span>
                                                @endif
                                            </span>
                                        </label>

                                        {{-- Quién más del equipo ya lo tiene --}}
                                        <div style="flex:1;display:flex;flex-wrap:wrap;gap:0.2rem;justify-content:flex-end;align-items:flex-start;">
                                            @forelse($loTienen as $q)
                                                <span title="{{ $q['nombre'] }} — rol {{ $q['rol'] }}{{ $q['directo'] ? ' · permiso otorgado a mano' : ' · le llega por el rol' }}"
                                                      style="font-size:0.68rem;line-height:1.3;padding:0.1rem 0.4rem;border-radius:5px;white-space:nowrap;
                                                             {{ $q['yo']
                                                                ? 'background:#dbeafe;color:#1d4ed8;font-weight:700;'
                                                                : ($q['directo'] ? 'background:#ede9fe;color:#6d28d9;font-weight:600;' : 'background:#f1f5f9;color:#94a3b8;') }}">
                                                    {{ $q['directo'] && ! $q['yo'] ? '★ ' : '' }}{{ $q['corto'] }}
                                                </span>
                                            @empty
                                                <span style="font-size:0.68rem;color:#cbd5e1;font-style:italic;">nadie del equipo</span>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div style="position:sticky;bottom:0;background:#fff;border-top:1px solid #e2e8f0;padding-top:1rem;margin-top:1.5rem;display:flex;gap:0.75rem;">
            <button type="submit"
                    style="padding:0.6rem 1.5rem;background:var(--azul-btn);color:#fff;border:none;border-radius:8px;font-size:0.87rem;font-weight:600;cursor:pointer;">
                Guardar permisos
            </button>
            <a href="{{ route('admin.usuarios.index') }}"
               style="padding:0.6rem 1.25rem;border:1px solid #cbd5e1;border-radius:8px;color:#475569;text-decoration:none;font-size:0.87rem;">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
