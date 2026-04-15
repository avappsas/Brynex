@extends('layouts.app')
@section('modulo', 'Configuración de Modalidades')

@section('contenido')
<style>
.mc-header { background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;padding:1rem 1.4rem;margin-bottom:1rem;color:#fff; }
.mc-h-nom  { font-size:1.1rem;font-weight:800; }
.mc-h-sub  { font-size:.75rem;color:#94a3b8;margin-top:.2rem; }
.mc-wrap   { background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden; }
.mc-tbl    { width:100%;border-collapse:collapse;font-size:.78rem; }
.mc-tbl th { background:#f8fafc;color:#64748b;font-size:.62rem;text-transform:uppercase;letter-spacing:.05em;padding:.5rem .7rem;text-align:center;border-bottom:2px solid #e2e8f0;white-space:nowrap; }
.mc-tbl th:first-child { text-align:left; }
.mc-tbl td { padding:.4rem .7rem;border-bottom:1px solid #f1f5f9;text-align:center;vertical-align:middle; }
.mc-tbl td:first-child { text-align:left;font-weight:600;color:#1e293b;white-space:nowrap; }
.mc-tbl tr:hover td { background:#f8fafc; }
.mc-tbl tr:last-child td { border-bottom:none; }
.chk { width:16px;height:16px;accent-color:#2563eb;cursor:pointer; }
.plan-hdr { font-size:.6rem;font-weight:700;color:#1d4ed8; }
.plan-sub  { font-size:.55rem;color:#94a3b8; }
.badge-indep { background:#ede9fe;color:#7c3aed;font-size:.55rem;padding:.1rem .35rem;border-radius:20px;margin-left:.3rem;font-weight:700; }
.btn-save { padding:.55rem 2rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;border-radius:9px;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(37,99,235,.35); }
.btn-save:hover { opacity:.9; }
</style>

{{-- ENCABEZADO --}}
<div class="mc-header">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <a href="{{ route('admin.configuracion.hub') }}" style="color:#94a3b8;font-size:.75rem;text-decoration:none;">← Configuración</a>
            <div class="mc-h-nom">🎛️ Planes permitidos por Modalidad</div>
            <div class="mc-h-sub">Marque qué planes son válidos para cada tipo de modalidad. Los cambios aplican al formulario de contratos en tiempo real.</div>
        </div>
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:.55rem 1rem;margin-bottom:.75rem;font-size:.82rem;">✓ {{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('admin.configuracion.modalidades.guardar') }}">
    @csrf

    <div class="mc-wrap">
        <table class="mc-tbl">
            <thead>
                <tr>
                    <th style="min-width:160px;">Modalidad</th>
                    @foreach($planes as $plan)
                    <th>
                        <div class="plan-hdr">{{ $plan->nombre }}</div>
                        <div class="plan-sub">
                            {{ $plan->incluye_eps ? 'EPS ' : '' }}
                            {{ $plan->incluye_arl ? 'ARL ' : '' }}
                            {{ $plan->incluye_pension ? 'AFP ' : '' }}
                            {{ $plan->incluye_caja ? 'CCF' : '' }}
                        </div>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($modalidades as $mod)
                @php
                    $esIndep = in_array($mod->id, [10, 11, 14]);
                    $nombre  = $mod->observacion ?: $mod->tipo_modalidad;
                @endphp
                <tr>
                    <td>
                        {{ $nombre }}
                        @if($esIndep)
                        <span class="badge-indep">Indep.</span>
                        @endif
                    </td>
                    @foreach($planes as $plan)
                    <td>
                        <input type="checkbox"
                               class="chk"
                               name="relaciones[{{ $mod->id }}][{{ $plan->id }}]"
                               value="1"
                               {{ isset($mapa[$mod->id][$plan->id]) ? 'checked' : '' }}>
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Sección: RS Independientes --}}
    <div class="mc-wrap" style="margin-top:.85rem;">
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

    <div style="display:flex;justify-content:flex-end;margin-top:.85rem;gap:.75rem;align-items:center;">
        <span style="font-size:.72rem;color:#94a3b8;">Los cambios aplican inmediatamente a todos los contratos nuevos</span>
        <button type="submit" class="btn-save">💾 Guardar Configuración</button>
    </div>
</form>

@endsection
