@extends('layouts.app')
@section('modulo', 'Configuración de Operadores de Planilla')

@section('contenido')
<style>
.op-header { background:linear-gradient(135deg,#0f172a,#164e63 60%,#0891b2);color:#fff;
             border-radius:14px;padding:1.1rem 1.5rem;margin-bottom:1.25rem;
             display:flex;align-items:center;justify-content:space-between;gap:1rem }
.op-header h1 { font-size:1.15rem;font-weight:800;margin:0 0 .2rem }
.op-header p  { font-size:.74rem;color:rgba(255,255,255,.55);margin:0 }
.op-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:.85rem }
.op-card {
    background:#fff;border:2px solid #e2e8f0;border-radius:14px;
    padding:1.1rem 1.2rem;
    display:flex;align-items:center;gap:1rem;
    transition:all .18s;position:relative;overflow:hidden;
}
.op-card.activo   { border-color:#10b981; }
.op-card.inactivo { border-color:#e2e8f0;opacity:.7; }
.op-card:hover    { box-shadow:0 4px 18px rgba(0,0,0,.09);transform:translateY(-1px); }

.op-icon { font-size:1.8rem;line-height:1;flex-shrink:0 }

.op-info { flex:1;min-width:0 }
.op-nombre { font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:.15rem }
.op-codigo { font-size:.7rem;color:#64748b }
.op-ni { display:inline-block;background:#eff6ff;color:#1d4ed8;
         font-size:.62rem;font-weight:700;padding:.12rem .45rem;
         border-radius:20px;margin-left:.35rem }

.op-toggle {
    position:relative;width:46px;height:26px;flex-shrink:0;
    background:#e2e8f0;border:none;border-radius:20px;cursor:pointer;
    transition:background .2s;outline:none;
}
.op-toggle::after {
    content:'';position:absolute;top:3px;left:3px;
    width:20px;height:20px;border-radius:50%;background:#fff;
    transition:transform .2s,background .2s;box-shadow:0 1px 4px rgba(0,0,0,.2);
}
.op-toggle.on { background:#10b981; }
.op-toggle.on::after { transform:translateX(20px); }
.op-toggle:disabled { opacity:.5;cursor:wait; }

.op-badge {
    position:absolute;top:.55rem;right:.6rem;
    font-size:.6rem;font-weight:700;padding:.12rem .45rem;border-radius:20px;
    text-transform:uppercase;letter-spacing:.04em;
}
.badge-on  { background:#d1fae5;color:#065f46 }
.badge-off { background:#fee2e2;color:#991b1b }

.notice {
    background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;
    padding:.7rem 1rem;margin-bottom:1rem;font-size:.78rem;color:#1d4ed8;
    display:flex;align-items:flex-start;gap:.55rem;
}
.notice-icon { font-size:1rem;flex-shrink:0 }

.toast-op { display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
    background:#0f172a;color:#f1f5f9;padding:.5rem 1.1rem;border-radius:10px;
    font-size:.8rem;font-weight:600;box-shadow:0 4px 18px rgba(0,0,0,.3);
    transition:opacity .3s;pointer-events:none; }
</style>

{{-- ENCABEZADO --}}
<div class="op-header">
    <div>
        <a href="{{ route('admin.configuracion.hub') }}"
           style="color:rgba(255,255,255,.55);font-size:.73rem;text-decoration:none;display:block;margin-bottom:.3rem">
            ← Configuración
        </a>
        <h1>🏦 Operadores de Planilla SS</h1>
        <p>Active o desactive los operadores que aparecerán en el selector al descargar la planilla Excel.</p>
    </div>
    <div style="text-align:right;font-size:.75rem;color:rgba(255,255,255,.55)">
        @if($tieneConfig)
            <span style="background:rgba(255,255,255,.12);border-radius:8px;padding:.3rem .7rem;">
                ⚙️ Configuración personalizada
            </span>
        @else
            <span style="background:rgba(255,255,255,.08);border-radius:8px;padding:.3rem .7rem;">
                📋 Usando configuración global
            </span>
        @endif
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:.55rem 1rem;margin-bottom:.9rem;font-size:.82rem">
    ✓ {{ session('success') }}
</div>
@endif

<div class="notice">
    <span class="notice-icon">ℹ️</span>
    <div>
        <strong>¿Cómo funciona?</strong>
        Por defecto todos los operadores globales están activos.
        Al desactivar uno, <strong>no aparecerá en el dropdown</strong> cuando sus usuarios descarguen la planilla Excel.
        Los cambios aplican de inmediato.
    </div>
</div>

{{-- GRID DE OPERADORES --}}
<div class="op-grid" id="op-grid">
    @foreach($operadoresGlobales as $op)
    @php
        // Si tiene config guardada, usa pivot; si no, por defecto activo
        $estaActivo = $tieneConfig
            ? (bool)($pivotActivo[$op->id] ?? false)
            : true;

        $iconos = [
            'SIMPLE'  => '🟢', 'MIPLANI' => '🔵', 'ASOPAGO' => '🟣',
            'APL'     => '🟡', 'ARUS'    => '🔴', 'ENLACE'  => '🟠',
            'SOI'     => '⚫', 'OTROS'   => '⚪',
        ];
        $icono = $iconos[$op->codigo] ?? '🏦';
    @endphp
    <div class="op-card {{ $estaActivo ? 'activo' : 'inactivo' }}"
         id="card-op-{{ $op->id }}">

        <span class="op-badge {{ $estaActivo ? 'badge-on' : 'badge-off' }}"
              id="badge-op-{{ $op->id }}">
            {{ $estaActivo ? 'Activo' : 'Inactivo' }}
        </span>

        <div class="op-icon">{{ $icono }}</div>

        <div class="op-info">
            <div class="op-nombre">{{ $op->nombre }}</div>
            <div class="op-codigo">
                Código: <strong>{{ $op->codigo }}</strong>
                @if($op->codigo_ni)
                    <span class="op-ni">PILA: {{ $op->codigo_ni }}</span>
                @else
                    <span style="color:#94a3b8;font-size:.65rem"> · código PILA pendiente</span>
                @endif
            </div>
        </div>

        <button class="op-toggle {{ $estaActivo ? 'on' : '' }}"
                id="toggle-op-{{ $op->id }}"
                title="{{ $estaActivo ? 'Click para desactivar' : 'Click para activar' }}"
                onclick="toggleOperador({{ $op->id }}, this)">
        </button>
    </div>
    @endforeach
</div>

<div class="toast-op" id="toast-op"></div>

@push('scripts')
<script>
const TOGGLE_URL = '{{ url("admin/configuracion/operadores-planilla") }}';
const CSRF       = document.querySelector('meta[name="csrf-token"]').content;

async function toggleOperador(id, btn) {
    btn.disabled = true;
    try {
        const resp = await fetch(`${TOGGLE_URL}/${id}/toggle`, {
            method : 'PATCH',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
        const data = await resp.json();
        if (!data.ok) throw new Error(data.mensaje || 'Error');

        const card  = document.getElementById(`card-op-${id}`);
        const badge = document.getElementById(`badge-op-${id}`);

        if (data.activo) {
            btn.classList.add('on');
            card.classList.replace('inactivo','activo');
            badge.className = 'op-badge badge-on';
            badge.textContent = 'Activo';
            btn.title = 'Click para desactivar';
        } else {
            btn.classList.remove('on');
            card.classList.replace('activo','inactivo');
            badge.className = 'op-badge badge-off';
            badge.textContent = 'Inactivo';
            btn.title = 'Click para activar';
        }

        showToast(data.activo
            ? `✅ "${data.nombre}" activado`
            : `🔴 "${data.nombre}" desactivado`
        );
    } catch (e) {
        showToast('❌ Error al cambiar el estado. Intente de nuevo.');
    } finally {
        btn.disabled = false;
    }
}

function showToast(msg) {
    const t = document.getElementById('toast-op');
    t.textContent = msg;
    t.style.display = 'block';
    t.style.opacity = '1';
    setTimeout(() => {
        t.style.opacity = '0';
        setTimeout(() => t.style.display = 'none', 300);
    }, 2800);
}
</script>
@endpush

@endsection
