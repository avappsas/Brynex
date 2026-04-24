@extends('layouts.app')
@section('modulo','Configuración')

@section('contenido')
<style>
.cfg-wrap { max-width:1000px;margin:0 auto }
.cfg-hdr  { background:linear-gradient(135deg,#0f172a,#1e3a5f 60%,#1e40af);color:#fff;
            border-radius:14px;padding:1.2rem 1.6rem;margin-bottom:1.5rem;
            display:flex;align-items:center;gap:1rem }
.cfg-hdr .icon { font-size:2rem;line-height:1 }
.cfg-hdr h1 { font-size:1.3rem;font-weight:800;margin:0 0 .15rem }
.cfg-hdr p  { font-size:.78rem;color:rgba(255,255,255,.55);margin:0 }
.cfg-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem }
.cfg-card { background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;
            padding:1.2rem 1.4rem;text-decoration:none;color:inherit;
            transition:all .18s;display:block;position:relative;overflow:hidden }
.cfg-card::before { content:'';position:absolute;inset:0;background:var(--c);opacity:0;transition:opacity .18s }
.cfg-card:hover { border-color:var(--bc);box-shadow:0 6px 22px rgba(0,0,0,.1);transform:translateY(-2px) }
.cfg-card:hover::before { opacity:.04 }
.cfg-card .c-icon { font-size:1.6rem;margin-bottom:.5rem }
.cfg-card .c-title { font-size:.98rem;font-weight:700;color:#0f172a;margin-bottom:.2rem }
.cfg-card .c-desc  { font-size:.75rem;color:#64748b;line-height:1.45 }
.cfg-card .c-badge { position:absolute;top:.75rem;right:.75rem;
                     font-size:.6rem;font-weight:700;padding:.18rem .55rem;
                     border-radius:20px;text-transform:uppercase }
.cfg-sep { grid-column:1/-1;border:none;border-top:1px solid #e2e8f0;margin:.25rem 0 }
.cfg-sep-label { grid-column:1/-1;font-size:.65rem;font-weight:700;color:#94a3b8;
                 text-transform:uppercase;letter-spacing:.08em }
</style>

<div class="cfg-wrap">
    <div class="cfg-hdr">
        <div class="icon">⚙️</div>
        <div>
            <h1>Centro de Configuración</h1>
            <p>Administración del sistema — solo accesible para admin y superadmin</p>
        </div>
    </div>

    <div class="cfg-grid">

        {{-- ── FACTURACIÓN ─────────────────────────────────── --}}
        <div class="cfg-sep-label">📄 Facturación</div>

        <a class="cfg-card" href="{{ route('admin.configuracion.index') }}?seccion=parametros"
           style="--c:#7c3aed;--bc:#c4b5fd">
            <span class="c-badge" style="background:#ede9fe;color:#6d28d9">Parámetros</span>
            <div class="c-icon">🔧</div>
            <div class="c-title">Parámetros del Sistema</div>
            <div class="c-desc">Salario mínimo, porcentajes SS, tarifas ARL, comisiones y costos de administración por plan.</div>
        </a>

        <a class="cfg-card" href="{{ route('admin.facturacion.anuladas') }}"
           style="--c:#dc2626;--bc:#fca5a5">
            <span class="c-badge" style="background:#fee2e2;color:#991b1b">Auditoría</span>
            <div class="c-icon">🗑</div>
            <div class="c-title">Recibos Anulados</div>
            <div class="c-desc">Historial de facturas anuladas con motivo, fecha, usuario y opción de restaurar.</div>
        </a>

        <a class="cfg-card" href="{{ route('admin.configuracion.cuentas') }}"
           style="--c:#0891b2;--bc:#67e8f9">
            <span class="c-badge" style="background:#cffafe;color:#0e7490">Bancario</span>
            <div class="c-icon">🏦</div>
            <div class="c-title">Cuentas Bancarias</div>
            <div class="c-desc">Gestionar cuentas bancarias del aliado. Marcar cuáles aparecen en la <strong>Cuenta de Cobro</strong> (campo 💳 Para Cobro).</div>
        </a>


        {{-- ── USUARIOS Y ACCESO ────────────────────────────── --}}
        <hr class="cfg-sep">
        <div class="cfg-sep-label">👤 Usuarios y Acceso</div>

        <a class="cfg-card" href="{{ route('admin.usuarios.index') }}"
           style="--c:#0369a1;--bc:#7dd3fc">
            <span class="c-badge" style="background:#e0f2fe;color:#0369a1">Usuarios</span>
            <div class="c-icon">👥</div>
            <div class="c-title">Gestión de Usuarios</div>
            <div class="c-desc">Crear, editar y controlar el acceso de los usuarios al sistema según roles.</div>
        </a>

        @role('superadmin')
        <a class="cfg-card" href="{{ route('admin.aliados.index') }}"
           style="--c:#0f172a;--bc:#94a3b8">
            <span class="c-badge" style="background:#f1f5f9;color:#475569">Solo Superadmin</span>
            <div class="c-icon">🏢</div>
            <div class="c-title">Aliados</div>
            <div class="c-desc">Gestionar aliados/franquicias que operan en la plataforma BryNex.</div>
        </a>
        @endrole

        {{-- ── AUDITORÍA ────────────────────────────────────── --}}
        @role('superadmin')
        <hr class="cfg-sep">
        <div class="cfg-sep-label">🔍 Auditoría</div>

        <a class="cfg-card" href="{{ route('admin.bitacora.index') }}"
           style="--c:#7c3aed;--bc:#a78bfa">
            <span class="c-badge" style="background:#ede9fe;color:#6d28d9">Solo Superadmin</span>
            <div class="c-icon">👁️</div>
            <div class="c-title">Bitácora de Auditoría</div>
            <div class="c-desc">Registro completo de todas las acciones realizadas: creaciones, ediciones, eliminaciones y restauraciones.</div>
        </a>
        @endrole

        {{-- ── AFILIACIONES ────────────────────────────────── --}}
        <hr class="cfg-sep">
        <div class="cfg-sep-label">📋 Afiliaciones</div>

        @if($primeraEps ?? null)
        <a class="cfg-card" href="{{ route('admin.configuracion.eps.formulario', $primeraEps) }}"
           style="--c:#7c3aed;--bc:#c4b5fd">
            <span class="c-badge" style="background:#ede9fe;color:#6d28d9">Formularios</span>
            <div class="c-icon">🗺️</div>
            <div class="c-title">Editor de Formularios EPS</div>
            <div class="c-desc">Sube el PDF de cada EPS y arrastra los campos para definir dónde se escriben los datos del cotizante automáticamente.</div>
        </a>
        @endif

        {{-- ── CONTRATOS ────────────────────────────────────── --}}
        <hr class="cfg-sep">
        <div class="cfg-sep-label">📑 Contratos y Afiliaciones</div>

        <a class="cfg-card" href="{{ route('admin.configuracion.razones.index') }}"
           style="--c:#059669;--bc:#6ee7b7">
            <span class="c-badge" style="background:#d1fae5;color:#065f46">Empresas</span>
            <div class="c-icon">🏭</div>
            <div class="c-title">Razones Sociales</div>
            <div class="c-desc">Administre las empresas a través de las cuales afilia trabajadores. Configure ARL, Caja y si son de tipo independiente.</div>
        </a>

        <a class="cfg-card" href="{{ route('admin.configuracion.modalidades') }}"
           style="--c:#0369a1;--bc:#7dd3fc">
            <span class="c-badge" style="background:#e0f2fe;color:#0369a1">Planes</span>
            <div class="c-icon">🎛️</div>
            <div class="c-title">Modalidades y Planes</div>
            <div class="c-desc">Configure qué planes de seguridad social son válidos para cada tipo de modalidad y marque sus RS independientes.</div>
        </a>

        <a class="cfg-card" href="{{ route('admin.configuracion.operadores.index') }}"
           style="--c:#0891b2;--bc:#67e8f9">
            <span class="c-badge" style="background:#cffafe;color:#0e7490">Planillas SS</span>
            <div class="c-icon">🏦</div>
            <div class="c-title">Operadores de Planilla</div>
            <div class="c-desc">Active o desactive los operadores (Simple, ARUS, SOI, etc.) que aparecen en el selector al descargar la planilla Excel de seguridad social.</div>
        </a>

        {{-- ── PRÓXIMAMENTE ─────────────────────────────────── --}}
        <hr class="cfg-sep">
        <div class="cfg-sep-label">🔜 Próximamente</div>

        <div class="cfg-card" style="--c:#64748b;--bc:#cbd5e1;cursor:default;opacity:.65">
            <span class="c-badge" style="background:#f1f5f9;color:#64748b">Próximamente</span>
            <div class="c-icon">📊</div>
            <div class="c-title">Reportes y Contabilidad</div>
            <div class="c-desc">Informes financieros, cuadre de caja, exportaciones contables y conciliaciones.</div>
        </div>

        <div class="cfg-card" style="--c:#64748b;--bc:#cbd5e1;cursor:default;opacity:.65">
            <span class="c-badge" style="background:#f1f5f9;color:#64748b">Próximamente</span>
            <div class="c-icon">📬</div>
            <div class="c-title">Notificaciones</div>
            <div class="c-desc">Configurar alertas automáticas por vencimientos, pagos pendientes e incapacidades.</div>
        </div>

    </div>
</div>
@endsection
