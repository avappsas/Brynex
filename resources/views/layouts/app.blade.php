<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'BryNex') — @yield('modulo', 'Dashboard')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Alpine.js: requerido para el cotizador reactivo --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --azul-oscuro: #0a1628;
            --azul-medio:  #0d2550;
            --azul-vivo:   #1e40af;
            --azul-btn:    #2563eb;
            --acento:      #3b82f6;
            --texto:       #e2e8f0;
            --fondo:       #f0f4f8;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--fondo);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Barra superior aliado (solo BryNex) ───────────────────────── */
        .bar-brynex {
            background: linear-gradient(90deg, #0a1628, #0d2550);
            padding: 0.35rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .bar-brynex span {
            color: rgba(255,255,255,0.6);
            font-size: 0.72rem;
        }

        .bar-brynex .aliado-actual {
            color: #93c5fd;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .btn-cambiar {
            background: rgba(59,130,246,0.2);
            border: 1px solid rgba(59,130,246,0.4);
            color: #93c5fd;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn-cambiar:hover { background: rgba(59,130,246,0.35); }

        /* ── Header principal ───────────────────────────────────────────── */
        .header {
            background: linear-gradient(135deg, var(--azul-oscuro) 0%, var(--azul-medio) 60%, var(--azul-vivo) 100%);
            padding: 0.65rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 12px rgba(0,0,0,0.35);
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-logo img.logo-aliado {
            height: 44px;
            object-fit: contain;
            border-radius: 8px;
            background: rgba(255,255,255,0.08);
            padding: 2px;
        }

        .header-aliado-info h2 {
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .header-aliado-info small {
            color: rgba(255,255,255,0.5);
            font-size: 0.72rem;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .user-info {
            text-align: right;
        }

        .user-info .nombre {
            color: #fff;
            font-size: 0.82rem;
            font-weight: 500;
        }

        .user-info .rol {
            color: rgba(255,255,255,0.45);
            font-size: 0.68rem;
            text-transform: capitalize;
        }

        .btn-salir {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .btn-salir:hover { background: rgba(239,68,68,0.3); }

        /* ── Menú de iconos (integrado en header) ────────────────────────── */
        .menu-iconos {
            background: transparent;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 0.1rem;
            flex: 1;
            justify-content: center;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .menu-iconos::-webkit-scrollbar { display: none; }

        .menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.18rem;
            padding: 0.3rem 0.7rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s, transform 0.15s;
            min-width: 58px;
            border: 1px solid transparent;
        }

        .menu-item:hover {
            background: rgba(59,130,246,0.15);
            border-color: rgba(59,130,246,0.25);
            transform: translateY(-1px);
        }

        .menu-item.activo {
            background: rgba(59,130,246,0.2);
            border-color: rgba(59,130,246,0.4);
        }

        .menu-item .icono {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            background: rgba(255,255,255,0.06);
        }

        .menu-item .label {
            color: rgba(255,255,255,0.75);
            font-size: 0.62rem;
            font-weight: 500;
            text-align: center;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .menu-item:hover .label,
        .menu-item.activo .label {
            color: #93c5fd;
        }

        .menu-sep {
            width: 1px;
            height: 36px;
            background: rgba(255,255,255,0.08);
            margin: 0 0.25rem;
            flex-shrink: 0;
        }

        /* ── Dropdown de menú ───────────────────────────────────────────── */
        .menu-dropdown {
            position: relative;
        }

        .menu-dropdown-trigger {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.18rem;
            padding: 0.3rem 0.7rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s, transform 0.15s;
            min-width: 60px;
            border: 1px solid transparent;
            background: none;
            outline: none;
            user-select: none;
        }

        .menu-dropdown-trigger:hover,
        .menu-dropdown:hover .menu-dropdown-trigger {
            background: rgba(59,130,246,0.15);
            border-color: rgba(59,130,246,0.25);
            transform: translateY(-1px);
        }

        .menu-dropdown-trigger.activo,
        .menu-dropdown:hover .menu-dropdown-trigger.activo {
            background: rgba(59,130,246,0.2);
            border-color: rgba(59,130,246,0.4);
        }

        .menu-dropdown-trigger .icono {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            background: rgba(255,255,255,0.06);
        }

        .menu-dropdown-trigger .label {
            color: rgba(255,255,255,0.75);
            font-size: 0.62rem;
            font-weight: 500;
            text-align: center;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .menu-dropdown:hover .menu-dropdown-trigger .label {
            color: #93c5fd;
        }

        /* Icono de flecha pequeña */
        .menu-dropdown-trigger .arrow {
            font-size: 0.48rem;
            color: rgba(255,255,255,0.4);
            margin-top: -2px;
        }

        .menu-dropdown:hover .menu-dropdown-trigger .arrow {
            color: #93c5fd;
        }

        /* Panel desplegable */
        .menu-dropdown-panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            min-width: 180px;
            background: linear-gradient(135deg, #0d2550 0%, #0a1628 100%);
            border: 1px solid rgba(59,130,246,0.25);
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.45), 0 2px 8px rgba(0,0,0,0.3);
            padding: 0.4rem;
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transform: translateX(-50%) translateY(-6px);
            transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s;
            pointer-events: none;
        }

        .menu-dropdown:hover .menu-dropdown-panel {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
            pointer-events: all;
        }

        /* Flecha decorativa del panel */
        .menu-dropdown-panel::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-bottom: 6px solid rgba(59,130,246,0.25);
        }

        .menu-dropdown-panel::after {
            content: '';
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-bottom: 5px solid #0d2550;
        }

        /* Cabecera del panel (título del grupo) */
        .panel-header {
            padding: 0.3rem 0.5rem 0.2rem;
            font-size: 0.58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.35);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 0.2rem;
        }

        /* Separador dentro del panel */
        .panel-sep {
            height: 1px;
            background: rgba(255,255,255,0.07);
            margin: 0.3rem 0;
        }

        /* Ítem del panel */
        .panel-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.45rem 0.65rem;
            border-radius: 7px;
            text-decoration: none;
            color: rgba(255,255,255,0.75);
            font-size: 0.78rem;
            font-weight: 500;
            transition: background 0.12s, color 0.12s;
            white-space: nowrap;
        }

        .panel-item:hover {
            background: rgba(59,130,246,0.2);
            color: #93c5fd;
        }

        .panel-item.activo {
            background: rgba(59,130,246,0.25);
            color: #93c5fd;
        }

        .panel-item .pi {
            width: 22px;
            height: 22px;
            border-radius: 5px;
            background: rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        /* Colores especiales para el dropdown BryNex */
        .menu-dropdown.brynex .menu-dropdown-trigger .icono {
            background: rgba(29,78,216,0.3);
        }

        .menu-dropdown.brynex .menu-dropdown-panel {
            background: linear-gradient(135deg, #1e3a8a 0%, #0a1628 100%);
            border-color: rgba(99,179,237,0.3);
        }

        .menu-dropdown.brynex .panel-item:hover {
            background: rgba(99,179,237,0.2);
            color: #bfdbfe;
        }

        .menu-dropdown.brynex .menu-dropdown-panel::before {
            border-bottom-color: rgba(99,179,237,0.3);
        }

        .menu-dropdown.brynex .menu-dropdown-panel::after {
            border-bottom-color: #1e3a8a;
        }

        /* ── Contenido ──────────────────────────────────────────────────── */
        .contenido {
            flex: 1;
            padding: 1.5rem;
        }

        /* ── Flash messages ─────────────────────────────────────────────── */
        .flash {
            padding: 0.65rem 1rem;
            border-radius: 8px;
            font-size: 0.83rem;
            margin-bottom: 1rem;
        }

        .flash.success {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.3);
            color: #065f46;
        }

        /* ── Responsive ─────────────────────────────────────────────────── */
        @media (max-width: 640px) {
            .user-info { display: none; }
            .menu-item .label { font-size: 0.55rem; }
        }
    </style>
    @stack('styles')
</head>
<body>

    {{-- Barra BryNex: solo visible para usuarios BryNex --}}
    @if (Auth::check() && Auth::user()->es_brynex)
    <div class="bar-brynex">
        <span>🔵 Modo BryNex · Operando como: <span class="aliado-actual">{{ $alidoActivo->nombre ?? 'Sin aliado' }}</span></span>
        <a href="{{ route('aliado.selector') }}" class="btn-cambiar">⇄ Cambiar aliado</a>
    </div>
    @endif

    {{-- Header con logo del aliado activo --}}
    <header class="header">
        <div class="header-logo">
            @if (!empty($alidoActivo?->logo))
                <img class="logo-aliado" src="{{ asset('storage/' . $alidoActivo->logo) }}" alt="{{ $alidoActivo->nombre }}">
            @else
                <img class="logo-aliado" src="{{ asset('img/logo-brynex.png') }}" alt="BryNex">
            @endif
            <div class="header-aliado-info">
                <h2>{{ $alidoActivo->nombre ?? 'BryNex' }}</h2>
                <small>{{ $alidoActivo->razon_social ?? 'Asesores en Seguridad Social' }}</small>
            </div>
        </div>

        {{-- Menú de iconos integrado en el header --}}
        <nav class="menu-iconos">
            <a href="{{ route('dashboard') }}" class="menu-item {{ request()->routeIs('dashboard') ? 'activo' : '' }}">
                <div class="icono">🏠</div>
                <div class="label">Inicio</div>
            </a>

            <a href="{{ route('admin.clientes.index') }}" class="menu-item {{ request()->routeIs('admin.clientes*') ? 'activo' : '' }}">
                <div class="icono">👥</div>
                <div class="label">Clientes</div>
            </a>

            <a href="{{ route('admin.facturacion.index') }}" class="menu-item {{ request()->routeIs('admin.facturacion*') ? 'activo' : '' }}">
                <div class="icono">🏢</div>
                <div class="label">Empresas</div>
            </a>

            @role('superadmin|admin')
            <a href="{{ route('admin.afiliaciones.index') }}" class="menu-item {{ request()->routeIs('admin.afiliaciones*') ? 'activo' : '' }}">
                <div class="icono">🤝</div>
                <div class="label">Afiliaciones</div>
            </a>
            @endrole

            @can('ver-planos')
            <a href="{{ route('admin.planos.index') }}" class="menu-item {{ request()->routeIs('admin.planos*') ? 'activo' : '' }}">
                <div class="icono">📄</div>
                <div class="label">Planos SS</div>
            </a>
            @endcan

            @role('superadmin|admin')
            <a href="{{ route('admin.cobros.index') }}" class="menu-item {{ request()->routeIs('admin.cobros*') ? 'activo' : '' }}">
                <div class="icono">💰</div>
                <div class="label">Cobros</div>
            </a>
            @endrole

            <a href="{{ route('admin.tareas.index') }}" class="menu-item {{ request()->routeIs('admin.tareas*') ? 'activo' : '' }}">
                <div class="icono">📌</div>
                <div class="label">Tareas</div>
            </a>

            <a href="{{ route('admin.incapacidades.index') }}" class="menu-item {{ request()->routeIs('admin.incapacidades*') ? 'activo' : '' }}">
                <div class="icono">🏥</div>
                <div class="label">Incapacidades</div>
            </a>

            @role('superadmin|admin')
            <div class="menu-sep"></div>

            <a href="{{ route('admin.cuadre-diario.index') }}"
               class="menu-item {{ request()->routeIs('admin.cuadre-diario*') ? 'activo' : '' }}">
                <div class="icono">🧾</div>
                <div class="label">Cuadre Caja</div>
            </a>
            @endrole

            {{-- ───────────────────────────────────────────────────────────── --}}
            {{-- DROPDOWN ADMIN: visible para admin y superadmin              --}}
            {{-- ───────────────────────────────────────────────────────────── --}}
            @role('admin|superadmin')
            <div class="menu-sep"></div>
            <div class="menu-dropdown">
                <a href="{{ route('admin.configuracion.hub') }}" class="menu-dropdown-trigger {{ request()->routeIs('admin.asesores*', 'admin.bitacora*', 'admin.usuarios*', 'admin.configuracion*') ? 'activo' : '' }}">
                    <div class="icono">⚙️</div>
                    <div class="label">Admin</div>
                </a>
                <div class="menu-dropdown-panel">
                    <div class="panel-header">Administración</div>

                    <a href="#" class="panel-item">
                        <div class="pi">📊</div> Contabilidad
                    </a>
                    <a href="{{ route('admin.asesores.index') }}" class="panel-item {{ request()->routeIs('admin.asesores*') ? 'activo' : '' }}">
                        <div class="pi">🤝</div> Asesores
                    </a>
                    <a href="{{ route('admin.bitacora.index') }}" class="panel-item {{ request()->routeIs('admin.bitacora*') ? 'activo' : '' }}">
                        <div class="pi">👁️</div> Auditoría
                    </a>

                    <div class="panel-sep"></div>

                    <a href="{{ route('admin.usuarios.index') }}" class="panel-item {{ request()->routeIs('admin.usuarios*') ? 'activo' : '' }}">
                        <div class="pi">👥</div> Usuarios
                    </a>
                    <a href="{{ route('admin.configuracion.hub') }}" class="panel-item {{ request()->routeIs('admin.configuracion*') ? 'activo' : '' }}">
                        <div class="pi">⚙️</div> Configuración
                    </a>
                    <a href="{{ route('admin.configuracion.index') }}" class="panel-item {{ request()->routeIs('admin.configuracion.index') ? 'activo' : '' }}">
                        <div class="pi">💲</div> Parámetros / Precios
                    </a>
                </div>
            </div>
            @endrole

            {{-- ───────────────────────────────────────────────────────────── --}}
            {{-- DROPDOWN BRYNEX: solo para superadmin                        --}}
            {{-- ───────────────────────────────────────────────────────────── --}}
            @role('superadmin')
            <div class="menu-sep"></div>
            <div class="menu-dropdown brynex">
                <a href="{{ route('admin.aliados.index') }}" class="menu-dropdown-trigger {{ request()->routeIs('admin.aliados*', 'admin.asesores*', 'admin.bitacora*', 'admin.usuarios*', 'admin.configuracion*') ? 'activo' : '' }}">
                    <div class="icono">🔵</div>
                    <div class="label">BryNex</div>
                </a>
                <div class="menu-dropdown-panel">
                    <div class="panel-header">BryNex Global</div>

                    <a href="{{ route('admin.aliados.index') }}" class="panel-item {{ request()->routeIs('admin.aliados*') ? 'activo' : '' }}">
                        <div class="pi">🏢</div> Aliados
                    </a>

                    <div class="panel-sep"></div>
                    <div class="panel-header" style="margin-top:0.2rem">Operaciones</div>

                    <a href="{{ route('admin.asesores.index') }}" class="panel-item {{ request()->routeIs('admin.asesores*') ? 'activo' : '' }}">
                        <div class="pi">🤝</div> Asesores
                    </a>
                    <a href="{{ route('admin.bitacora.index') }}" class="panel-item {{ request()->routeIs('admin.bitacora*') ? 'activo' : '' }}">
                        <div class="pi">👁️</div> Auditoría
                    </a>
                    <a href="{{ route('admin.usuarios.index') }}" class="panel-item {{ request()->routeIs('admin.usuarios*') ? 'activo' : '' }}">
                        <div class="pi">👥</div> Usuarios
                    </a>

                    <div class="panel-sep"></div>
                    <div class="panel-header" style="margin-top:0.2rem">Configuración Global</div>

                    <a href="{{ route('admin.configuracion.hub') }}" class="panel-item {{ request()->routeIs('admin.configuracion*') ? 'activo' : '' }}">
                        <div class="pi">⚙️</div> Config. General
                    </a>
                    <a href="{{ route('admin.configuracion.index') }}" class="panel-item {{ request()->routeIs('admin.configuracion.index') ? 'activo' : '' }}">
                        <div class="pi">💲</div> Parámetros del Sistema
                    </a>
                </div>
            </div>
            @endrole
        </nav>

        <div class="header-user">
            <div class="user-info">
                <div class="nombre">{{ Auth::user()->nombre }}</div>
                <div class="rol">{{ Auth::user()->getRoleNames()->first() ?? 'usuario' }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-salir">⏻ Salir</button>
            </form>
        </div>
    </header>



    {{-- Contenido de la página --}}
    <main class="contenido">
        @if (session('success'))
            <div class="flash success">✅ {{ session('success') }}</div>
        @endif

        @yield('contenido')
    </main>

    @stack('scripts')
</body>
</html>
