---
name: blade-alpine-brynex
description: >
  Patrones de UI en Brynex con Blade y Alpine.js. Actívate cuando el usuario pida
  crear o modificar vistas, modales, tablas, formularios, filtros, botones de descarga,
  componentes Alpine.js, layouts Blade, partials, notificaciones, animaciones de carga,
  o cualquier cambio de interfaz de usuario en el panel admin.
---

# Skill: UI con Blade + Alpine.js en Brynex

## ⚠️ Regla Principal
**NUNCA inventar colores, clases o estilos**. Usar SOLO los tokens y clases definidas en este documento, que corresponden exactamente al sistema de diseño de Brynex en `resources/views/layouts/app.blade.php`.

---

## 🎨 Tokens de Color (CSS Variables)

```css
:root {
    --azul-oscuro: #0a1628;   /* Fondo del header y sidebar */
    --azul-medio:  #0d2550;   /* Gradiente medio del header */
    --azul-vivo:   #1e40af;   /* Acento en gradientes */
    --azul-btn:    #2563eb;   /* Botones primarios */
    --acento:      #3b82f6;   /* Hover, bordes de foco, highlights */
    --texto:       #e2e8f0;   /* Texto principal sobre fondo oscuro */
    --fondo:       #f0f4f8;   /* Fondo del body (gris azulado claro) */
}

/* Colores de acento frecuentes (usados inline): */
/* - Azul claro:  #93c5fd  (texto destacado sobre oscuro) */
/* - Rojo suave:  #fca5a5  (acciones peligrosas) */
/* - Glassmorphism: rgba(59,130,246, 0.15~0.35) */
```

## 🔤 Tipografía

```css
/* Fuente: Inter (Google Fonts, ya cargada en el layout) */
font-family: 'Inter', sans-serif;

/* Tamaños frecuentes en Brynex: */
/* 0.62rem → etiquetas de íconos de menú */
/* 0.68rem → textos secundarios, roles */
/* 0.72rem → labels pequeños */
/* 0.78rem → texto de botones e ítems */
/* 0.82rem → texto de usuario */
/* 0.95rem → subtítulos de sección */
/* 1.1rem  → títulos de card */
/* 1.4rem+ → h1 de página */
```

---

## 🧩 Componentes del Sistema

### Header / Layout
```css
/* Gradiente del header */
background: linear-gradient(135deg, var(--azul-oscuro) 0%, var(--azul-medio) 60%, var(--azul-vivo) 100%);
box-shadow: 0 2px 12px rgba(0,0,0,0.35);

/* Fondo de cards sobre --fondo */
background: #fff;
border-radius: 12px;
box-shadow: 0 2px 8px rgba(0,0,0,0.06);
```

### Botones

```blade
{{-- Primario (azul) --}}
<button class="btn-accion">
    <i class="fas fa-plus"></i> Nuevo
</button>

{{-- Secundario (ghost) --}}
<button class="btn-secundario">Cancelar</button>

{{-- Peligro (rojo, para eliminar) --}}
<button class="btn-danger">Eliminar</button>
```

```css
/* Patrón de botón en Brynex */
.btn-accion {
    background: var(--azul-btn);
    color: #fff;
    border: none;
    padding: 0.45rem 1rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
}
.btn-accion:hover {
    background: var(--acento);
    transform: translateY(-1px);
}

/* Ghost (glassmorphism) */
.btn-glass {
    background: rgba(59,130,246,0.15);
    border: 1px solid rgba(59,130,246,0.35);
    color: #93c5fd;
    border-radius: 8px;
    padding: 0.35rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-glass:hover { background: rgba(59,130,246,0.3); }
```

### Menú de Ítems (Sidebar / Dropdown)

```css
/* Ítem de menú estándar */
.menu-item { /* o cualquier link de lista */
    padding: 0.45rem 0.65rem;
    border-radius: 7px;
    color: rgba(255,255,255,0.75);
    font-size: 0.78rem;
    font-weight: 500;
    transition: background 0.12s, color 0.12s;
}
.menu-item:hover {
    background: rgba(59,130,246,0.2);
    color: #93c5fd;
}
.menu-item.activo {
    background: rgba(59,130,246,0.2);
    border: 1px solid rgba(59,130,246,0.4);
}
```

### Badges / Estados

```blade
{{-- Verde: activo / pagado / exitoso --}}
<span class="badge-ok">Activo</span>

{{-- Rojo: inactivo / vencido / error --}}
<span class="badge-err">Vencido</span>

{{-- Amarillo: pendiente / en proceso --}}
<span class="badge-warn">Pendiente</span>

{{-- Azul: informativo --}}
<span class="badge-info">En revisión</span>
```

```css
.badge-ok   { background: rgba(34,197,94,0.15);  color: #4ade80; border: 1px solid rgba(34,197,94,0.3);  border-radius: 999px; padding: 0.15rem 0.65rem; font-size: 0.72rem; font-weight: 600; }
.badge-err  { background: rgba(239,68,68,0.12);  color: #fca5a5; border: 1px solid rgba(239,68,68,0.25); border-radius: 999px; padding: 0.15rem 0.65rem; font-size: 0.72rem; font-weight: 600; }
.badge-warn { background: rgba(234,179,8,0.12);  color: #fde047; border: 1px solid rgba(234,179,8,0.25); border-radius: 999px; padding: 0.15rem 0.65rem; font-size: 0.72rem; font-weight: 600; }
.badge-info { background: rgba(59,130,246,0.12); color: #93c5fd; border: 1px solid rgba(59,130,246,0.25);border-radius: 999px; padding: 0.15rem 0.65rem; font-size: 0.72rem; font-weight: 600; }
```

### Separadores

```css
.sep { height: 1px; background: rgba(255,255,255,0.07); margin: 0.3rem 0; }
/* Para fondo claro: */
.sep-light { height: 1px; background: #e5e7eb; margin: 1rem 0; }
```

---

## 🏗️ Layout Base de Vista

```blade
@extends('layouts.admin')

@section('titulo', 'Nombre del Módulo')
@section('modulo', 'Subtítulo')

@section('content')
<div class="page-container">

    {{-- Cabecera de página --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Título Principal</h1>
            <p class="page-subtitle">Descripción breve del módulo</p>
        </div>
        <div class="page-actions">
            <button class="btn-accion">
                <i class="fas fa-plus"></i> Nueva acción
            </button>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="filtros-bar">
        <input type="text" placeholder="Buscar..." class="input-busqueda">
        <select class="select-filtro">
            <option>Todos</option>
        </select>
    </div>

    {{-- Tarjeta de contenido --}}
    <div class="card-tabla">
        <table class="tabla-brynex">
            <thead>
                <tr>
                    <th>Columna</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>{{ $item->campo }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="tabla-vacia">
                            <i class="fas fa-inbox"></i>
                            <p>Sin registros</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    {{ $items->links() }}

</div>
@endsection
```

---

## 💬 Patrón de Modal con Alpine.js

```blade
<div x-data="{ open: false, item: {} }">

    {{-- Trigger --}}
    <button @click="item = {{ json_encode($dato) }}; open = true" class="btn-accion">
        Ver detalle
    </button>

    {{-- Overlay --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="modal-overlay"
         @click.self="open = false">

        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="modal-box">

            <div class="modal-head">
                <h3 x-text="item.titulo ?? 'Detalle'"></h3>
                <button @click="open = false" class="modal-close">&times;</button>
            </div>

            <div class="modal-body">
                {{-- Contenido --}}
            </div>

            <div class="modal-foot">
                <button @click="open = false" class="btn-glass">Cerrar</button>
                <button class="btn-accion">Confirmar</button>
            </div>
        </div>
    </div>
</div>
```

```css
.modal-overlay {
    position: fixed; inset: 0; z-index: 9998;
    background: rgba(0,0,0,0.55);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}
.modal-box {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    width: 100%; max-width: 540px;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
}
.modal-head h3 { font-size: 1rem; font-weight: 600; color: #1e293b; }
.modal-close {
    background: none; border: none; font-size: 1.4rem;
    color: #94a3b8; cursor: pointer; line-height: 1;
}
.modal-close:hover { color: #ef4444; }
.modal-body { padding: 1.25rem; }
.modal-foot {
    display: flex; justify-content: flex-end; gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid #e5e7eb;
}
```

---

## ⚡ Botón con Loading State

```blade
<div x-data="{ cargando: false }">
    <button @click="
        cargando = true;
        fetch('{{ route('admin.alguna.ruta') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } })
            .then(r => r.json())
            .then(d => { /* manejar respuesta */ })
            .finally(() => cargando = false);
    "
    :disabled="cargando"
    class="btn-accion">
        <span x-show="!cargando"><i class="fas fa-save"></i> Guardar</span>
        <span x-show="cargando" x-cloak><i class="fas fa-spinner fa-spin"></i> Guardando...</span>
    </button>
</div>
```

---

## 🔔 Notificaciones Flash

```blade
{{-- En cualquier vista, las notificaciones se renderizan así: --}}
@if(session('success'))
    <div x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 4500)"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="notif-success">
        <i class="fas fa-check-circle"></i>
        {{ session('success') }}
        <button @click="show = false" class="notif-close">&times;</button>
    </div>
@endif

@if(session('error'))
    <div x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 5000)"
         class="notif-error">
        <i class="fas fa-exclamation-circle"></i>
        {{ session('error') }}
    </div>
@endif
```

```css
.notif-success {
    background: rgba(34,197,94,0.12);
    border: 1px solid rgba(34,197,94,0.3);
    color: #166534;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    display: flex; align-items: center; gap: 0.5rem;
    margin-bottom: 1rem;
}
.notif-error {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    color: #991b1b;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    display: flex; align-items: center; gap: 0.5rem;
    margin-bottom: 1rem;
}
```

---

## 📅 Selector Mes/Año (Patrón Brynex)

```blade
{{-- Muchas vistas usan este patrón para filtrar por mes/año --}}
<form method="GET" action="{{ route('admin.modulo.index') }}" class="filtros-bar">
    <select name="mes" class="select-filtro" onchange="this.form.submit()">
        @foreach(range(1,12) as $m)
            <option value="{{ $m }}" @selected($mes == $m)>
                {{ \Carbon\Carbon::create()->month($m)->locale('es')->monthName }}
            </option>
        @endforeach
    </select>
    <select name="anio" class="select-filtro" onchange="this.form.submit()">
        @foreach(range(2024, now()->year + 1) as $a)
            <option value="{{ $a }}" @selected($anio == $a)>{{ $a }}</option>
        @endforeach
    </select>
</form>
```

---

## 📁 Archivos de Referencia del Código

- Layout principal: `resources/views/layouts/app.blade.php` (914 líneas — fuente de verdad de todos los estilos)
- Logo: `public/img/logo-brynex.png`
- Alpine.js: CDN `alpinejs@3.x.x` (cargado en el layout)
- Iconos: Font Awesome 5/6 (via CDN en el layout)

---

## 🖼️ Referencia Visual del Diseño

> Las siguientes imágenes son referencias generadas del diseño real de Brynex.
> **ÚSALAS** para replicar el estilo exacto: colores, espaciados, tipografía, bordes.

### Header y Navegación Principal
Imagen: `.agents/skills/blade-alpine-brynex/resources/header-nav.png`
- Gradiente horizontal: `#0a1628` → `#0d2550` → `#1e40af`
- Menú de ítems centrado con emoji + label 0.62rem
- Ítem activo: fondo `rgba(59,130,246,0.2)` + borde `rgba(59,130,246,0.4)`
- Badge rojo en WhatsApp para mensajes no leídos

### Vista de Empresas (Lista con Grid de Cards)
Imagen: `.agents/skills/blade-alpine-brynex/resources/vista-empresas.png`
- Encabezado de sección: gradiente oscuro `#0f172a → #1e3a5f`, padding `1.5rem 2rem`, `border-radius: 14px`
- Grid de cards: `auto-fill, minmax(290px, 1fr)`, gap `1rem`
- Card activa (con contratos): `border-left: 4px solid #10b981`
- Card hover: `border-color: #3b82f6`, `box-shadow: 0 4px 18px rgba(59,130,246,0.12)`, `transform: translateY(-2px)`
- Badge contratos: `background: #d1fae5`, `color: #065f46`, `border: 1px solid #a7f3d0`

### Componentes UI (Botones, Badges, Modal, Tabla, Notificaciones)
Imagen: `.agents/skills/blade-alpine-brynex/resources/componentes-ui.png`
- Botón primario: `#2563eb`, texto blanco, `border-radius: 8px`
- Botón ghost: borde `rgba(59,130,246,0.35)`, texto `#93c5fd`
- Botón eliminar: fondo rojo translúcido, texto `#fca5a5`
- Botón acción verde: `#10b981` (solo para "Nueva Empresa" y similares)
- Modal: fondo blanco, `border-radius: 14px`, sombra `0 20px 60px rgba(0,0,0,0.25)`
- Tabla: fondo blanco, `border-radius: 12px`, borde `#e2e8f0`
- Notificación éxito: `rgba(16,185,129,0.1)`, borde `rgba(16,185,129,0.3)`, texto `#065f46`
- Notificación error: `rgba(239,68,68,0.1)`, borde `rgba(239,68,68,0.3)`, texto `#991b1b`

---

## 🚫 Lo que NUNCA hacer en Brynex UI

- ❌ No usar Tailwind CSS (el proyecto usa CSS vanilla en el layout)
- ❌ No inventar colores fuera de la paleta definida
- ❌ No usar fondos blancos puros `#ffffff` para headers de sección (usar el gradiente oscuro)
- ❌ No usar `border-radius > 16px` en cards principales
- ❌ No usar tipografías distintas a Inter
- ❌ No poner `background: blue` o colores planos en botones primarios — siempre usar `#2563eb` o `var(--azul-btn)`
