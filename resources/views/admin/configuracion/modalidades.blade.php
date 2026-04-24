@extends('layouts.app')
@section('modulo', 'Configuración de Modalidades')

@section('contenido')
@php
// Clasificar planes por tipo
$planesIndep = $planes->filter(fn($p) => !$p->incluye_arl);   // Sin ARL → para independientes
$planesDep   = $planes->filter(fn($p) =>  $p->incluye_arl);   // Con ARL → para dependientes

// Clasificar modalidades (todas — activas e inactivas)
$modsTP    = $modalidades->filter(fn($m) => $m->esTiempoParcial());
$modsIndep = $modalidades->filter(fn($m) => !$m->esTiempoParcial() && in_array($m->id, [10, 11, 14]));
$modsDep   = $modalidades->filter(fn($m) => !$m->esTiempoParcial() && !in_array($m->id, [10, 11, 14]));
@endphp
<style>
.mc-header { background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;padding:1rem 1.4rem;margin-bottom:1rem;color:#fff; }
.mc-h-nom  { font-size:1.1rem;font-weight:800; }
.mc-h-sub  { font-size:.75rem;color:#94a3b8;margin-top:.2rem; }
.mc-wrap   { background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:.85rem; }
.mc-sec-hdr{ padding:.6rem 1rem;display:flex;align-items:center;gap:.5rem;border-bottom:2px solid #e2e8f0; }
.mc-sec-ttl{ font-size:.8rem;font-weight:800; }
.mc-sec-sub{ font-size:.65rem;margin-left:.35rem; }
.mc-tbl    { width:100%;border-collapse:collapse;font-size:.78rem; }
.mc-tbl th { background:#f8fafc;color:#64748b;font-size:.62rem;text-transform:uppercase;letter-spacing:.05em;padding:.5rem .7rem;text-align:center;border-bottom:1px solid #e2e8f0;white-space:nowrap; }
.mc-tbl th:first-child { text-align:left;min-width:170px; }
.mc-tbl th:last-child  { text-align:center;min-width:100px; }
.mc-tbl td { padding:.4rem .7rem;border-bottom:1px solid #f1f5f9;text-align:center;vertical-align:middle; }
.mc-tbl td:first-child { text-align:left;font-weight:600;color:#1e293b;white-space:nowrap; }
.mc-tbl tr:hover td { background:#f8fafc; }
.mc-tbl tr:last-child td { border-bottom:none; }
/* Fila inactiva */
.mc-tbl tr.mod-inactiva td { background:#f8fafc;opacity:.6; }
.mc-tbl tr.mod-inactiva td:first-child { color:#94a3b8; }
.chk { width:16px;height:16px;accent-color:#2563eb;cursor:pointer; }
.plan-hdr { font-size:.6rem;font-weight:700; }
.plan-sub  { font-size:.54rem;color:#94a3b8; }
.badge-indep  { background:#ede9fe;color:#7c3aed;font-size:.55rem;padding:.1rem .35rem;border-radius:20px;margin-left:.3rem;font-weight:700; }
.badge-tp     { background:#fef3c7;color:#78350f;font-size:.52rem;padding:.1rem .35rem;border-radius:12px;margin-left:.3rem;font-weight:700; }
.badge-activo { background:#dcfce7;color:#15803d;font-size:.55rem;padding:.1rem .38rem;border-radius:20px;font-weight:700; }
.badge-inact  { background:#fee2e2;color:#991b1b;font-size:.55rem;padding:.1rem .38rem;border-radius:20px;font-weight:700; }
.btn-save   { padding:.55rem 2rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;border-radius:9px;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(37,99,235,.35); }
.btn-save:hover { opacity:.9; }
.dias-tp-cell { font-size:.63rem;white-space:nowrap;text-align:left!important; }
/* Toggle btn */
.btn-toggle {
    display:inline-flex;align-items:center;gap:.3rem;
    padding:.22rem .6rem;border:none;border-radius:20px;
    font-size:.62rem;font-weight:700;cursor:pointer;
    transition:background .18s,color .18s,opacity .18s;
    white-space:nowrap;
}
.btn-toggle.activo   { background:#dcfce7;color:#15803d; }
.btn-toggle.activo:hover { background:#bbf7d0; }
.btn-toggle.inactivo  { background:#fee2e2;color:#991b1b; }
.btn-toggle.inactivo:hover { background:#fecaca; }
.btn-toggle:disabled { opacity:.5;cursor:wait; }
</style>

{{-- ENCABEZADO --}}
<div class="mc-header">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <a href="{{ route('admin.configuracion.hub') }}" style="color:#94a3b8;font-size:.75rem;text-decoration:none;">← Configuración</a>
            <div class="mc-h-nom">🎛️ Planes permitidos por Modalidad</div>
            <div class="mc-h-sub">Marque qué planes son válidos para cada tipo de modalidad, y active/inactiye modalidades. Los cambios aplican en tiempo real.</div>
        </div>
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:.55rem 1rem;margin-bottom:.75rem;font-size:.82rem;">✓ {{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('admin.configuracion.modalidades.guardar') }}">
    @csrf

    {{-- ══════════════════════════════════════════════════════
         SECCIÓN 1: TIEMPO PARCIAL (solo informativa, fija)
    ══════════════════════════════════════════════════════ --}}
    <div class="mc-wrap">
        <div class="mc-sec-hdr" style="background:#fffbeb;">
            <span style="font-size:1.1rem;">⏱</span>
            <span class="mc-sec-ttl" style="color:#78350f;">Tiempo Parcial</span>
            <span class="mc-sec-sub" style="color:#92400e;">
                Plan fijo: <strong>ARL (30d) + AFP + CAJA</strong> — sin EPS. Los días son fijos según el plan.
            </span>
        </div>
        <table class="mc-tbl">
            <thead>
                <tr>
                    <th>Modalidad</th>
                    <th style="text-align:center;color:#1d4ed8;">ARL<div class="plan-sub">30 días</div></th>
                    <th style="text-align:center;color:#7c3aed;">AFP<div class="plan-sub">días fijos</div></th>
                    <th style="text-align:center;color:#d97706;">CCF<div class="plan-sub">días fijos</div></th>
                    <th>Plan Activo</th>
                    <th>Días ARL / AFP / CCF</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($modsTP as $mod)
                @php
                    $nombre  = $mod->observacion ?: $mod->tipo_modalidad;
                    $diasP   = $mod->diasPorEntidad();
                    $planActivo = null;
                    foreach ($planes as $p) {
                        if (isset($mapa[$mod->id][$p->id])) { $planActivo = $p; break; }
                    }
                @endphp
                <tr class="{{ $mod->activo ? '' : 'mod-inactiva' }}" id="row-mod-{{ $mod->id }}">
                    <td>
                        {{ $nombre }}
                        <span class="badge-tp">⏱ T.PARCIAL</span>
                    </td>
                    <td>
                        <span style="color:#1d4ed8;font-weight:700;font-size:.8rem;">✓</span>
                    </td>
                    <td>
                        <span style="color:#7c3aed;font-weight:700;font-size:.8rem;">✓</span>
                    </td>
                    <td>
                        <span style="color:#d97706;font-weight:700;font-size:.8rem;">✓</span>
                    </td>
                    <td style="font-size:.7rem;color:#475569;">
                        {{ $planActivo ? $planActivo->nombre : '—' }}
                    </td>
                    <td class="dias-tp-cell">
                        <span style="color:#1d4ed8;">ARL: <strong>{{ $diasP['arl'] }}d</strong></span>
                        &nbsp;·&nbsp;
                        <span style="color:#7c3aed;">AFP: <strong>{{ $diasP['afp'] }}d</strong></span>
                        &nbsp;·&nbsp;
                        <span style="color:#d97706;">CCF: <strong>{{ $diasP['caja'] }}d</strong></span>
                    </td>
                    <td>
                        <button type="button"
                            class="btn-toggle {{ $mod->activo ? 'activo' : 'inactivo' }}"
                            id="btn-mod-{{ $mod->id }}"
                            data-id="{{ $mod->id }}"
                            onclick="toggleModalidad({{ $mod->id }}, this)">
                            {{ $mod->activo ? '✅ Activa' : '🔴 Inactiva' }}
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ══════════════════════════════════════════════════════
         SECCIÓN 2: INDEPENDIENTES (planes sin ARL)
    ══════════════════════════════════════════════════════ --}}
    <div class="mc-wrap">
        <div class="mc-sec-hdr" style="background:#f5f3ff;">
            <span style="font-size:1.1rem;">👤</span>
            <span class="mc-sec-ttl" style="color:#6d28d9;">Independientes</span>
            <span class="mc-sec-sub" style="color:#7c3aed;">
                Planes disponibles: EPS+AFP, EPS+AFP+CCF, Solo AFP, etc.
            </span>
        </div>
        <table class="mc-tbl">
            <thead>
                <tr>
                    <th>Modalidad</th>
                    @foreach($planesIndep as $plan)
                    <th>
                        <div class="plan-hdr" style="color:#6d28d9;">{{ $plan->nombre }}</div>
                        <div class="plan-sub">
                            {{ $plan->incluye_eps ? 'EPS ' : '' }}{{ $plan->incluye_pension ? 'AFP ' : '' }}{{ $plan->incluye_caja ? 'CCF' : '' }}
                        </div>
                    </th>
                    @endforeach
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($modsIndep as $mod)
                @php $nombre = $mod->observacion ?: $mod->tipo_modalidad; @endphp
                <tr class="{{ $mod->activo ? '' : 'mod-inactiva' }}" id="row-mod-{{ $mod->id }}">
                    <td>
                        {{ $nombre }}
                        <span class="badge-indep">Indep.</span>
                    </td>
                    @foreach($planesIndep as $plan)
                    <td>
                        <input type="checkbox"
                               class="chk"
                               name="relaciones[{{ $mod->id }}][{{ $plan->id }}]"
                               value="1"
                               {{ isset($mapa[$mod->id][$plan->id]) ? 'checked' : '' }}>
                    </td>
                    @endforeach
                    <td>
                        <button type="button"
                            class="btn-toggle {{ $mod->activo ? 'activo' : 'inactivo' }}"
                            id="btn-mod-{{ $mod->id }}"
                            data-id="{{ $mod->id }}"
                            onclick="toggleModalidad({{ $mod->id }}, this)">
                            {{ $mod->activo ? '✅ Activa' : '🔴 Inactiva' }}
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ══════════════════════════════════════════════════════
         SECCIÓN 3: DEPENDIENTES / OTROS (planes con ARL)
    ══════════════════════════════════════════════════════ --}}
    <div class="mc-wrap">
        <div class="mc-sec-hdr" style="background:#eff6ff;">
            <span style="font-size:1.1rem;">🏢</span>
            <span class="mc-sec-ttl" style="color:#1d4ed8;">Dependientes y Otros</span>
            <span class="mc-sec-sub" style="color:#3b82f6;">
                Planes disponibles: EPS+ARL, EPS+ARL+CCF, EPS+ARL+AFP, EPS+ARL+AFP+CCF, etc.
            </span>
        </div>
        <table class="mc-tbl">
            <thead>
                <tr>
                    <th>Modalidad</th>
                    @foreach($planesDep as $plan)
                    <th>
                        <div class="plan-hdr" style="color:#1d4ed8;">{{ $plan->nombre }}</div>
                        <div class="plan-sub">
                            {{ $plan->incluye_eps ? 'EPS ' : '' }}{{ $plan->incluye_arl ? 'ARL ' : '' }}{{ $plan->incluye_pension ? 'AFP ' : '' }}{{ $plan->incluye_caja ? 'CCF' : '' }}
                        </div>
                    </th>
                    @endforeach
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($modsDep as $mod)
                @php $nombre = $mod->observacion ?: $mod->tipo_modalidad; @endphp
                <tr class="{{ $mod->activo ? '' : 'mod-inactiva' }}" id="row-mod-{{ $mod->id }}">
                    <td>{{ $nombre }}</td>
                    @foreach($planesDep as $plan)
                    <td>
                        <input type="checkbox"
                               class="chk"
                               name="relaciones[{{ $mod->id }}][{{ $plan->id }}]"
                               value="1"
                               {{ isset($mapa[$mod->id][$plan->id]) ? 'checked' : '' }}>
                    </td>
                    @endforeach
                    <td>
                        <button type="button"
                            class="btn-toggle {{ $mod->activo ? 'activo' : 'inactivo' }}"
                            id="btn-mod-{{ $mod->id }}"
                            data-id="{{ $mod->id }}"
                            onclick="toggleModalidad({{ $mod->id }}, this)">
                            {{ $mod->activo ? '✅ Activa' : '🔴 Inactiva' }}
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Sección: RS Independientes --}}
    <div class="mc-wrap">
        <div style="padding:.7rem 1rem;background:#f8fafc;border-bottom:2px solid #e2e8f0;">
            <div style="font-size:.78rem;font-weight:800;color:#0f172a;">🏢 Razones Sociales Independientes</div>
            <div style="font-size:.65rem;color:#64748b;margin-top:.15rem;">
                Marque las Razones Sociales que <strong>solo</strong> pueden usar modalidades independientes
                (I Act, I Venc, En el Exterior). Esto aplica solo a su aliado.
            </div>
        </div>
        <div style="padding:.85rem 1rem;display:flex;flex-wrap:wrap;gap:.6rem;">
            @forelse($razionesSociales as $rs)
            <label style="display:flex;align-items:center;gap:.35rem;font-size:.78rem;background:#f1f5f9;border-radius:8px;padding:.3rem .65rem;cursor:pointer;border:1.5px solid {{ $rs->es_independiente ? '#7c3aed' : '#e2e8f0' }};color:{{ $rs->es_independiente ? '#7c3aed' : '#334155' }};">
                <input type="checkbox"
                       name="rs_independientes[]"
                       value="{{ $rs->id }}"
                       style="accent-color:#7c3aed;"
                       onchange="this.closest('label').style.borderColor=this.checked?'#7c3aed':'#e2e8f0';this.closest('label').style.color=this.checked?'#7c3aed':'#334155';"
                       {{ $rs->es_independiente ? 'checked' : '' }}>
                {{ $rs->razon_social }}
            </label>
            @empty
            <div style="font-size:.78rem;color:#94a3b8;">No hay razones sociales configuradas.</div>
            @endforelse
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         SECCIÓN: REGLAS DE NEGOCIO
    ══════════════════════════════════════════════════════ --}}
    <div class="mc-wrap">
        <div class="mc-sec-hdr" style="background:#fff7ed;">
            <span style="font-size:1.1rem;">⚖️</span>
            <span class="mc-sec-ttl" style="color:#c2410c;">Reglas de Negocio</span>
            <span class="mc-sec-sub" style="color:#9a3412;">
                Controles globales que restringen la selección de planes en los contratos.
            </span>
        </div>
        <div style="padding:1rem 1.1rem;">

            {{-- Regla: AFP obligatorio --}}
            <label style="display:flex;align-items:flex-start;gap:.9rem;cursor:pointer;padding:.75rem .9rem;border-radius:10px;border:1.5px solid {{ $reglaAfpActiva ? '#f97316' : '#e2e8f0' }};background:{{ $reglaAfpActiva ? '#fff7ed' : '#f8fafc' }};transition:all .2s;">
                <div style="padding-top:.1rem;">
                    <input type="checkbox"
                           name="regla_afp_obligatorio"
                           value="1"
                           id="chk_regla_afp"
                           style="width:17px;height:17px;accent-color:#f97316;cursor:pointer;margin-top:.05rem;"
                           {{ $reglaAfpActiva ? 'checked' : '' }}
                           onchange="
                               const lbl = this.closest('label');
                               lbl.style.borderColor = this.checked ? '#f97316' : '#e2e8f0';
                               lbl.style.background  = this.checked ? '#fff7ed' : '#f8fafc';
                           ">
                </div>
                <div>
                    <div style="font-size:.82rem;font-weight:700;color:#1e293b;">
                        AFP obligatorio en modalidades Dependiente E e Independientes
                    </div>
                    <div style="font-size:.71rem;color:#64748b;margin-top:.25rem;line-height:1.45;">
                        Cuando está <strong>activada</strong>, los contratos de modalidad
                        <span style="background:#fff7ed;color:#c2410c;padding:.05rem .35rem;border-radius:4px;font-weight:700;">Dependiente E</span>,
                        <span style="background:#fff7ed;color:#c2410c;padding:.05rem .35rem;border-radius:4px;font-weight:700;">I Venc</span> e
                        <span style="background:#fff7ed;color:#c2410c;padding:.05rem .35rem;border-radius:4px;font-weight:700;">I Act</span>
                        <strong>no pueden seleccionar planes sin AFP</strong> (p.ej. Solo EPS, EPS+ARL, EPS+ARL+CAJA).<br>
                        <span style="color:#7c3aed;">Se exceptúan clientes con:</span>
                        tipo_doc ≠ CC (extrajeros),
                        mujeres ≥ 50 años o hombres ≥ 55 años.
                    </div>
                </div>
            </label>

        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;margin-top:.85rem;gap:.75rem;align-items:center;">
        <span style="font-size:.72rem;color:#94a3b8;">Los cambios aplican inmediatamente a todos los contratos nuevos</span>
        <button type="submit" class="btn-save">💾 Guardar Configuración</button>
    </div>
</form>


{{-- Notificación flash AJAX --}}
<div id="toast-modal" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
    background:#1e293b;color:#f1f5f9;padding:.55rem 1.1rem;border-radius:10px;
    font-size:.8rem;font-weight:600;box-shadow:0 4px 18px rgba(0,0,0,.3);
    transition:opacity .3s;pointer-events:none;"></div>

@push('scripts')
<script>
const TOGGLE_URL   = '{{ url("admin/configuracion/modalidades") }}';
const CSRF_TOKEN   = document.querySelector('meta[name="csrf-token"]').content;

/**
 * Activa o inactiva una modalidad via AJAX sin recargar la página.
 * Actualiza el botón y la fila visualmente de inmediato.
 */
function toggleModalidad(id, btn) {
    btn.disabled = true;

    fetch(`${TOGGLE_URL}/${id}/toggle`, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (!data.ok) throw new Error('Error del servidor');

        const row = document.getElementById(`row-mod-${id}`);

        if (data.activo) {
            btn.textContent = '✅ Activa';
            btn.className   = 'btn-toggle activo';
            row?.classList.remove('mod-inactiva');
        } else {
            btn.textContent = '🔴 Inactiva';
            btn.className   = 'btn-toggle inactivo';
            row?.classList.add('mod-inactiva');
        }

        showToast(data.activo
            ? `✅ "${data.label}" activada`
            : `🔴 "${data.label}" inactivada`
        );
    })
    .catch(() => {
        showToast('❌ Error al cambiar el estado. Intente de nuevo.');
    })
    .finally(() => {
        btn.disabled = false;
    });
}

function showToast(msg) {
    const t = document.getElementById('toast-modal');
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
