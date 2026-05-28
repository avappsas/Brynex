@extends('layouts.app')

@section('titulo', 'BryNex')
@section('modulo', 'Configuración Global')

@section('contenido')
<div class="brynex-hub">

    {{-- Header --}}
    <div class="hub-header">
        <div class="hub-header-left">
            <div class="hub-logo">🔵</div>
            <div>
                <h1 class="hub-title">BryNex Global</h1>
                <p class="hub-sub">Panel de administración del sistema</p>
            </div>
        </div>
        <div class="hub-stats">
            <div class="hub-stat">
                <span class="hs-num">{{ $totalAliados }}</span>
                <span class="hs-label">Aliados</span>
            </div>
            <div class="hub-stat">
                <span class="hs-num">{{ $activos }}</span>
                <span class="hs-label">Activos</span>
            </div>
            <div class="hub-stat">
                <span class="hs-num">{{ $usuariosBrynex }}</span>
                <span class="hs-label">Usuarios BN</span>
            </div>
            <div class="hub-stat">
                <span class="hs-num" style="color:#4ade80">{{ $totalWaConfigurados }}</span>
                <span class="hs-label">WA Activos</span>
            </div>
        </div>
    </div>

    {{-- Grid de módulos --}}
    <div class="hub-grid">

        {{-- ── Aliados ──────────────────────────────────────────────────── --}}
        <div class="hub-section">
            <div class="hub-section-title">🏢 Aliados</div>
            <div class="hub-cards">
                <a href="{{ route('admin.aliados.index') }}" class="hub-card">
                    <div class="hc-icon">🏢</div>
                    <div class="hc-body">
                        <div class="hc-name">Ver Aliados</div>
                        <div class="hc-desc">Lista y gestión de todos los aliados</div>
                    </div>
                    <div class="hc-arrow">→</div>
                </a>
                <a href="{{ route('admin.aliados.create') }}" class="hub-card accent">
                    <div class="hc-icon">➕</div>
                    <div class="hc-body">
                        <div class="hc-name">Nuevo Aliado</div>
                        <div class="hc-desc">Registrar un nuevo aliado en el sistema</div>
                    </div>
                    <div class="hc-arrow">→</div>
                </a>
            </div>
        </div>

        {{-- ── Superadmin únicamente ────────────────────────────────────── --}}
        @role('superadmin')
        <div class="hub-section">
            <div class="hub-section-title">🔐 Control de Accesos <span class="badge-sa">Solo Superadmin</span></div>
            <div class="hub-cards">
                <a href="{{ route('brynex.accesos') }}" class="hub-card">
                    <div class="hc-icon">👥</div>
                    <div class="hc-body">
                        <div class="hc-name">Accesos de Usuarios BryNex</div>
                        <div class="hc-desc">Configura qué usuarios BryNex pueden acceder a cada aliado</div>
                    </div>
                    <div class="hc-arrow">→</div>
                </a>
            </div>
        </div>

        <div class="hub-section">
            <div class="hub-section-title">⚙️ Configuración Global <span class="badge-sa">Solo Superadmin</span></div>
            <div class="hub-cards">
                <a href="{{ route('admin.usuarios.index') }}" class="hub-card">
                    <div class="hc-icon">👤</div>
                    <div class="hc-body">
                        <div class="hc-name">Usuarios del Sistema</div>
                        <div class="hc-desc">Gestión de todos los usuarios BryNex y de aliados</div>
                    </div>
                    <div class="hc-arrow">→</div>
                </a>
                <a href="{{ route('admin.bitacora.index') }}" class="hub-card">
                    <div class="hc-icon">👁️</div>
                    <div class="hc-body">
                        <div class="hc-name">Auditoría / Bitácora</div>
                        <div class="hc-desc">Registro de actividad global del sistema</div>
                    </div>
                    <div class="hc-arrow">→</div>
                </a>
            </div>
        </div>

        {{-- ── WhatsApp Business API ─────────────────────────────────────── --}}
        <div class="hub-section wa-section">
            <div class="hub-section-title">
                💬 WhatsApp Business API
                <span class="badge-sa" style="background:#dcfce7;color:#15803d;border-color:#bbf7d0">
                    {{ $totalWaConfigurados }}/{{ $totalAliados }} configurados
                </span>
            </div>

            <div class="wa-desc">
                Configura las credenciales de Meta WhatsApp para cada aliado. Los aliados sin
                configuración propia usarán la cuenta global de Brynex.
            </div>

            {{-- Tabla de aliados con estado WhatsApp --}}
            <div class="wa-aliados-table-wrap">
                <table class="wa-aliados-table">
                    <thead>
                        <tr>
                            <th>Aliado</th>
                            <th>Número</th>
                            <th>Cuenta</th>
                            <th>Estado</th>
                            <th>Webhook</th>
                            <th style="text-align:right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($aliados as $al)
                        @php $waCfg = $whatsappConfigs->get($al->id); @endphp
                        <tr>
                            <td>
                                <div style="font-weight:700;color:#0f172a">{{ $al->nombre }}</div>
                                <div style="font-size:.72rem;color:#94a3b8">{{ $al->razon_social ?? '' }}</div>
                            </td>
                            <td style="font-size:.82rem;color:#334155">
                                @if($waCfg && $waCfg->numero_telefono)
                                    <span style="font-family:monospace">{{ $waCfg->numero_telefono }}</span>
                                @else
                                    <span style="color:#cbd5e1">—</span>
                                @endif
                            </td>
                            <td>
                                @if(!$waCfg)
                                    <span class="wa-badge wa-badge-brynex">Usa Brynex</span>
                                @elseif($waCfg->usa_cuenta_brynex)
                                    <span class="wa-badge wa-badge-brynex">Global Brynex</span>
                                @else
                                    <span class="wa-badge wa-badge-propia">Cuenta Propia</span>
                                @endif
                            </td>
                            <td>
                                @if(!$waCfg || !$waCfg->activo)
                                    <span class="wa-estado wa-estado-sin">⚠️ Sin config</span>
                                @else
                                    <span class="wa-estado wa-estado-ok">✅ Activo</span>
                                @endif
                            </td>
                            <td>
                                @if($waCfg && $waCfg->webhook_verificado)
                                    <span style="color:#16a34a;font-size:.8rem">✅</span>
                                @else
                                    <span style="color:#dc2626;font-size:.8rem">❌</span>
                                @endif
                            </td>
                            <td style="text-align:right">
                                <a href="{{ route('admin.whatsapp.config.edit', $al->id) }}"
                                   class="wa-btn-config">
                                    {{ $waCfg ? '✏️ Editar' : '⚙️ Configurar' }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Acceso rápido a config general --}}
            <div style="margin-top:1rem;padding-top:.85rem;border-top:1px solid #f0fdf4;display:flex;gap:.65rem;flex-wrap:wrap">
                <a href="{{ route('admin.whatsapp.config.index') }}" class="hub-card" style="flex:1;min-width:200px;border-color:#bbf7d0;background:#f0fdf4">
                    <div class="hc-icon">⚙️</div>
                    <div class="hc-body">
                        <div class="hc-name" style="color:#15803d">Configuración WhatsApp</div>
                        <div class="hc-desc">Ver y editar todas las credenciales de Meta</div>
                    </div>
                    <div class="hc-arrow" style="color:#16a34a">→</div>
                </a>
                <a href="https://business.facebook.com" target="_blank" class="hub-card" style="flex:1;min-width:200px;border-color:#bfdbfe;background:#eff6ff">
                    <div class="hc-icon">🌐</div>
                    <div class="hc-body">
                        <div class="hc-name" style="color:#1d4ed8">Meta Business Suite</div>
                        <div class="hc-desc">Abrir panel de Meta (webhooks, plantillas)</div>
                    </div>
                    <div class="hc-arrow" style="color:#2563eb">↗</div>
                </a>
            </div>
        </div>
        @endrole

        {{-- ── Aliados activos (resumen rápido) ────────────────────────── --}}
        <div class="hub-section">
            <div class="hub-section-title">📋 Resumen de Aliados</div>
            <div class="aliados-table-wrap">
                <table class="aliados-table">
                    <thead>
                        <tr>
                            <th>Aliado</th>
                            <th>Razón Social</th>
                            <th>Estado</th>
                            <th>WhatsApp</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($aliados as $al)
                        @php $waCfg = $whatsappConfigs->get($al->id); @endphp
                        <tr>
                            <td style="font-weight:600">{{ $al->nombre }}</td>
                            <td style="color:#64748b;font-size:.82rem">{{ $al->razon_social ?? '—' }}</td>
                            <td>
                                @if($al->activo)
                                <span class="badge-activo">● Activo</span>
                                @else
                                <span class="badge-inactivo">● Inactivo</span>
                                @endif
                            </td>
                            <td>
                                @if($waCfg && $waCfg->activo)
                                    <span style="font-size:.75rem;color:#16a34a;font-weight:600">✅ {{ $waCfg->numero_telefono }}</span>
                                @else
                                    <span style="font-size:.75rem;color:#94a3b8">Sin config</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.aliados.edit', $al->id) }}"
                                   style="font-size:.75rem;color:#3b82f6;text-decoration:none">Editar →</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:1.5rem">Sin aliados registrados</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
.brynex-hub { max-width: 960px; margin: 0 auto; }

/* Header */
.hub-header {
    display: flex; align-items: center; justify-content: space-between;
    background: linear-gradient(135deg, #0a1628 0%, #1e3a8a 100%);
    border-radius: 14px; padding: 1.5rem 2rem; margin-bottom: 1.5rem;
    box-shadow: 0 4px 20px rgba(30,58,138,.25);
}
.hub-header-left { display: flex; align-items: center; gap: 1rem; }
.hub-logo { font-size: 2.5rem; line-height: 1; }
.hub-title { color: #fff; font-size: 1.4rem; font-weight: 700; margin: 0; }
.hub-sub { color: rgba(255,255,255,.5); font-size: .8rem; margin: 0; }
.hub-stats { display: flex; gap: 1.5rem; }
.hub-stat { text-align: center; }
.hs-num { display: block; color: #93c5fd; font-size: 1.6rem; font-weight: 700; line-height: 1; }
.hs-label { display: block; color: rgba(255,255,255,.45); font-size: .7rem; margin-top: .2rem; }

/* Sections */
.hub-grid { display: flex; flex-direction: column; gap: 1.25rem; }
.hub-section {
    background: #fff; border-radius: 12px; padding: 1.25rem 1.5rem;
    box-shadow: 0 1px 6px rgba(0,0,0,.07);
}
.hub-section-title {
    font-size: .8rem; font-weight: 700; color: #475569; text-transform: uppercase;
    letter-spacing: .06em; margin-bottom: .85rem;
    display: flex; align-items: center; gap: .5rem;
}
.badge-sa {
    background: #fef3c7; color: #92400e; border: 1px solid #fde68a;
    border-radius: 999px; padding: .1rem .6rem; font-size: .65rem;
    font-weight: 600; text-transform: none;
}

/* Cards */
.hub-cards { display: flex; flex-direction: column; gap: .6rem; }
.hub-card {
    display: flex; align-items: center; gap: 1rem;
    padding: .85rem 1rem; border-radius: 10px;
    border: 1px solid #e2e8f0; background: #f8fafc;
    text-decoration: none; transition: all .15s;
}
.hub-card:hover {
    border-color: #3b82f6; background: #eff6ff;
    transform: translateX(3px);
}
.hub-card.accent { border-color: #bfdbfe; background: #eff6ff; }
.hub-card.accent:hover { border-color: #3b82f6; background: #dbeafe; }
.hc-icon { font-size: 1.3rem; width: 38px; text-align: center; flex-shrink: 0; }
.hc-body { flex: 1; }
.hc-name { font-weight: 600; font-size: .9rem; color: #1e293b; }
.hc-desc { font-size: .75rem; color: #64748b; margin-top: .1rem; }
.hc-arrow { color: #3b82f6; font-weight: 700; font-size: 1rem; }

/* WhatsApp section */
.wa-section { border: 1px solid #d1fae5; }
.wa-desc {
    font-size: .8rem; color: #475569; margin-bottom: 1rem;
    padding: .6rem .8rem; background: #f0fdf4; border-radius: 8px;
}
.wa-aliados-table-wrap { overflow-x: auto; }
.wa-aliados-table {
    width: 100%; border-collapse: collapse; font-size: .82rem;
}
.wa-aliados-table th {
    text-align: left; padding: .45rem .75rem; font-size: .68rem; font-weight: 700;
    color: #64748b; text-transform: uppercase; letter-spacing: .05em;
    border-bottom: 2px solid #f1f5f9; background: #f8fafc;
}
.wa-aliados-table td {
    padding: .6rem .75rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle;
}
.wa-aliados-table tbody tr:hover { background: #f0fdf4; }
.wa-badge {
    display: inline-block; padding: .15rem .55rem; border-radius: 999px;
    font-size: .68rem; font-weight: 700;
}
.wa-badge-brynex { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.wa-badge-propia { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.wa-estado { font-size: .78rem; font-weight: 600; }
.wa-estado-ok { color: #15803d; }
.wa-estado-sin { color: #d97706; }
.wa-btn-config {
    display: inline-block; padding: .3rem .75rem; border-radius: 7px;
    background: #0a1628; color: #fff; font-size: .73rem; font-weight: 600;
    text-decoration: none; transition: background .15s; white-space: nowrap;
}
.wa-btn-config:hover { background: #1e3a8a; color: #fff; }

/* Aliados table */
.aliados-table-wrap { overflow-x: auto; }
.aliados-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.aliados-table th {
    text-align: left; padding: .5rem .75rem; font-size: .7rem; font-weight: 600;
    color: #64748b; text-transform: uppercase; letter-spacing: .05em;
    border-bottom: 1px solid #f1f5f9;
}
.aliados-table td { padding: .6rem .75rem; border-bottom: 1px solid #f8fafc; }
.aliados-table tbody tr:hover { background: #f8fafc; }
.badge-activo { color: #15803d; font-size: .72rem; font-weight: 600; }
.badge-inactivo { color: #dc2626; font-size: .72rem; font-weight: 600; }

@media (max-width: 640px) {
    .hub-stats { gap: .85rem; }
    .hs-num { font-size: 1.2rem; }
    .hub-header { flex-direction: column; gap: 1rem; align-items: flex-start; }
}
</style>
@endpush
