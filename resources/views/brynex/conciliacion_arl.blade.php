@extends('layouts.app')

@section('modulo','Conciliación ARL')

@section('contenido')
<div style="max-width:1180px;margin:0 auto;padding:1.25rem;">

    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.35rem;">
        <a href="{{ route('brynex.hub') }}" style="color:#64748b;text-decoration:none;font-size:.8rem;">← BryNex</a>
    </div>
    <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;margin:0 0 .2rem;">🛡️ Conciliación ARL Sura</h1>
    <p style="font-size:.8rem;color:#64748b;margin:0 0 1.1rem;">
        Compara los afiliados que ARL Sura tiene en cada póliza contra los contratos vigentes de BryNex, de todos los aliados.
    </p>

    <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:.7rem .9rem;margin-bottom:1rem;font-size:.76rem;color:#92400e;line-height:1.55;">
        Cada empresa se consulta cuando le das a <strong>Conciliar</strong>: el sistema entra al portal con la clave de esa
        empresa y recorre sus afiliados, así que tarda entre uno y dos minutos la primera vez.
    </div>

    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
            <thead>
                <tr style="background:#0f172a;color:#fff;text-align:left;">
                    <th style="padding:.6rem .8rem;">Empresa</th>
                    <th style="padding:.6rem .8rem;">Póliza</th>
                    <th style="padding:.6rem .8rem;text-align:center;">Vigentes<br><span style="font-weight:400;font-size:.68rem;color:#94a3b8;">en BryNex</span></th>
                    <th style="padding:.6rem .8rem;text-align:center;">En Sura</th>
                    <th style="padding:.6rem .8rem;text-align:center;">Sobran<br><span style="font-weight:400;font-size:.68rem;color:#94a3b8;">sin contrato</span></th>
                    <th style="padding:.6rem .8rem;text-align:center;">Faltan<br><span style="font-weight:400;font-size:.68rem;color:#94a3b8;">sin cobertura</span></th>
                    <th style="padding:.6rem .8rem;text-align:center;width:120px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($empresas as $e)
                <tr id="empresa-{{ $e->nit }}" data-fila="{{ $e->nit }}" style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:.55rem .8rem;">
                        <strong>{{ $e->razon_social }}</strong>
                        <div style="font-size:.7rem;color:#94a3b8;">NIT {{ $e->nit }} · en {{ $e->aliados }} aliado(s)</div>
                    </td>
                    <td style="padding:.55rem .8rem;font-family:monospace;font-size:.75rem;">{{ $e->poliza }}</td>
                    <td style="padding:.55rem .8rem;text-align:center;font-weight:700;">{{ $e->vigentes }}</td>
                    <td class="c-sura"   style="padding:.55rem .8rem;text-align:center;color:#94a3b8;">—</td>
                    <td class="c-sobran" style="padding:.55rem .8rem;text-align:center;color:#94a3b8;">—</td>
                    <td class="c-faltan" style="padding:.55rem .8rem;text-align:center;color:#94a3b8;">—</td>
                    <td style="padding:.55rem .8rem;text-align:center;white-space:nowrap;">
                        @if($e->tiene_clave)
                            <button onclick="conciliar('{{ $e->nit }}', this)"
                                    style="background:#1e40af;color:#fff;border:none;border-radius:6px;padding:.3rem .75rem;font-size:.75rem;font-weight:600;cursor:pointer;">
                                Conciliar
                            </button>
                        @else
                            <span title="Carga la clave del portal desde Gestión ARL o Afiliaciones"
                                  style="font-size:.72rem;color:#b91c1c;">🔒 sin clave</span>
                        @endif
                    </td>
                </tr>
                <tr id="detalle-{{ $e->nit }}" style="display:none;background:#f8fafc;">
                    <td colspan="7" style="padding:.8rem 1rem;"></td>
                </tr>
            @empty
                <tr><td colspan="7" style="padding:2.5rem;text-align:center;color:#94a3b8;">
                    Ninguna razón social tiene póliza ARL registrada todavía.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

// El contador da señal de vida: entrar al portal y recorrer los afiliados de
// una empresa tarda más de un minuto la primera vez.
function esperando(btn, titulo) {
    const desde = Date.now();
    // Solo los segundos: el texto largo ensanchaba el botón y la columna de
    // acciones se salía de la tabla. Lo que está haciendo va en el title.
    const pintar = () => btn.textContent = `⏳ ${Math.round((Date.now() - desde) / 1000)}s`;

    btn.dataset.ancho = btn.dataset.ancho || btn.offsetWidth;
    btn.style.minWidth = btn.dataset.ancho + 'px';
    btn.title = titulo;
    btn.disabled = true;
    pintar();

    const reloj = setInterval(pintar, 1000);
    return () => { clearInterval(reloj); btn.title = ''; };
}

async function conciliar(nit, btn) {
    const parar = esperando(btn, 'Consultando los afiliados en el portal de Sura');
    const fila  = document.getElementById('empresa-' + nit);

    let d;
    try {
        const r = await fetch(`/brynex/conciliacion-arl/${nit}`, { headers: { 'Accept': 'application/json' } });
        d = await r.json();
    } catch (e) {
        d = { ok: false, mensaje: 'Se perdió la conexión con el servidor.' };
    }

    parar();
    btn.disabled = false; btn.textContent = 'Conciliar';

    if (!d.ok) { alert(d.mensaje || 'No se pudo consultar el portal.'); return; }

    const pinta = (sel, valor, color) => {
        const c = fila.querySelector(sel);
        c.textContent = valor;
        c.style.color = color;
        c.style.fontWeight = '700';
    };
    pinta('.c-sura',   d.en_sura, '#0f172a');
    pinta('.c-sobran', d.sobran.length, d.sobran.length ? '#b91c1c' : '#16a34a');
    pinta('.c-faltan', d.faltan.length, d.faltan.length ? '#b91c1c' : '#16a34a');

    mostrarDetalle(nit, d);
}

function mostrarDetalle(nit, d) {
    const tr = document.getElementById('detalle-' + nit);
    const td = tr.querySelector('td');

    const tabla = (titulo, filas, columnas) => {
        if (!filas.length) return `<div style="font-size:.76rem;color:#16a34a;margin-bottom:.6rem;">✓ ${titulo}: sin diferencias</div>`;
        return `<div style="margin-bottom:.9rem;">
            <div style="font-size:.78rem;font-weight:700;color:#991b1b;margin-bottom:.35rem;">${titulo} (${filas.length})</div>
            <table style="width:100%;border-collapse:collapse;font-size:.74rem;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                <tr style="background:#f1f5f9;text-align:left;">${columnas.map(c => `<th style="padding:.35rem .6rem;">${c.t}</th>`).join('')}</tr>
                ${filas.map(f => `<tr style="border-top:1px solid #f1f5f9;">${columnas.map(c => `<td style="padding:.35rem .6rem;">${c.v(f) ?? ''}</td>`).join('')}</tr>`).join('')}
            </table></div>`;
    };

    td.innerHTML =
        tabla('En Sura sin contrato vigente en esa empresa', d.sobran, [
            { t: 'Documento', v: f => f.documento },
            { t: 'Nombre',    v: f => f.nombre },
            { t: 'Situación en BryNex', v: f => f.situacion + (f.otra_empresa ? `: <strong>${f.otra_empresa}</strong>` : '') },
            { t: 'Aliado',    v: f => f.aliado ?? '—' },
        ]) +
        tabla('Vigentes en BryNex sin cobertura en Sura', d.faltan, [
            { t: 'Documento', v: f => f.documento },
            { t: 'Nombre',    v: f => f.nombre },
            { t: 'Plan',      v: f => f.plan ?? '—' },
            { t: 'Riesgo',    v: f => 'N' + (f.riesgo ?? '?') },
            { t: 'Desde',     v: f => f.desde ?? '—' },
            { t: 'Aliado',    v: f => f.aliado ?? '—' },
        ]) +
        `<div style="border-top:1px solid #e2e8f0;padding-top:.6rem;">
            <button onclick="verRiesgos('${nit}', this)"
                    style="background:#0d9488;color:#fff;border:none;border-radius:6px;padding:.3rem .75rem;font-size:.75rem;font-weight:600;cursor:pointer;">
                Comparar niveles de riesgo
            </button>
            <span style="font-size:.7rem;color:#64748b;margin-left:.5rem;">
                Revisa uno por uno a los que están en ambos lados; tarda más.
            </span>
            <div id="riesgos-${nit}" style="margin-top:.6rem;"></div>
        </div>
        <div style="border-top:1px solid #e2e8f0;padding-top:.6rem;margin-top:.6rem;">
            <button onclick="verEpsSura('${nit}', this)"
                    style="background:#0033a0;color:#fff;border:none;border-radius:6px;padding:.3rem .75rem;font-size:.75rem;font-weight:600;cursor:pointer;">
                Revisar EPS SURA
            </button>
            <span style="font-size:.7rem;color:#64748b;margin-left:.5rem;">
                Quién está activo en EPS SURA con esta empresa sin deberlo (p. ej. Gestión ARL o retirados). Solo consulta.
            </span>
            <div id="eps-${nit}" style="margin-top:.6rem;"></div>
        </div>`;

    tr.style.display = '';
}

// La depuración se pide a la EPS: el portal no deja anular, y el retiro solo
// acepta fechas desde el primer día del mes anterior. Aquí solo se muestra.
async function verEpsSura(nit, btn) {
    const parar = esperando(btn, 'Leyendo el informe de afiliados de EPS SURA');
    const caja  = document.getElementById('eps-' + nit);
    const esc   = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    let d;
    try {
        const r = await fetch(`/brynex/conciliacion-arl/${nit}/eps-sura`, { headers: { 'Accept': 'application/json' } });
        d = await r.json();
    } catch (e) {
        d = { ok: false, mensaje: 'Se perdió la conexión con el servidor.' };
    }

    parar();
    btn.disabled = false; btn.textContent = 'Revisar EPS SURA';

    if (!d.ok) { caja.innerHTML = `<div style="font-size:.76rem;color:#b91c1c;">❌ ${esc(d.mensaje || 'No se pudo leer el portal de EPS.')}</div>`; return; }

    const graves = d.sobran.filter(f => f.grave).length;
    let html = `<div style="font-size:.74rem;color:#475569;margin-bottom:.5rem;">${d.en_eps} cotizantes en EPS SURA con esta empresa.</div>`;

    html += !d.sobran.length
        ? '<div style="font-size:.76rem;color:#16a34a;margin-bottom:.6rem;">✓ Nadie activo en EPS SURA sin deberlo.</div>'
        : `<div style="margin-bottom:.9rem;">
            <div style="font-size:.78rem;font-weight:700;color:#991b1b;margin-bottom:.2rem;">
                Activos en EPS SURA sin deberlo (${d.sobran.length}${graves !== d.sobran.length ? `, ${graves} a depurar con la EPS` : ''})</div>
            <div style="font-size:.7rem;color:#64748b;margin-bottom:.35rem;">
                Los marcados en rojo hacen que la EPS espere aportes de la empresa. Se depuran pidiéndolo a EPS SURA.</div>
            <table style="width:100%;border-collapse:collapse;font-size:.74rem;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                <tr style="background:#f1f5f9;text-align:left;">
                    <th style="padding:.35rem .6rem;">Documento</th><th style="padding:.35rem .6rem;">Nombre</th>
                    <th style="padding:.35rem .6rem;">Ingreso EPS</th><th style="padding:.35rem .6rem;">En EPS como</th>
                    <th style="padding:.35rem .6rem;">Por qué no debería</th><th style="padding:.35rem .6rem;">Aliado</th>
                </tr>
                ${d.sobran.map(f => `<tr style="border-top:1px solid #f1f5f9;${f.grave ? 'background:#fef2f2;' : ''}">
                    <td style="padding:.35rem .6rem;white-space:nowrap;">${esc(f.documento)}</td>
                    <td style="padding:.35rem .6rem;">${esc(f.nombre)}</td>
                    <td style="padding:.35rem .6rem;white-space:nowrap;">${esc(f.ingreso_eps)}</td>
                    <td style="padding:.35rem .6rem;">${esc(f.tipo_afiliado)}${f.parentesco && f.parentesco !== 'TITULAR' ? ' · ' + esc(f.parentesco) : ''}</td>
                    <td style="padding:.35rem .6rem;font-weight:${f.grave ? 700 : 400};color:${f.grave ? '#991b1b' : '#475569'};">${esc(f.motivo)}</td>
                    <td style="padding:.35rem .6rem;">${esc(f.aliado ?? '—')}</td>
                </tr>`).join('')}
            </table></div>`;

    html += !d.faltan.length
        ? '<div style="font-size:.76rem;color:#16a34a;">✓ Todos los vigentes con EPS SURA en el plan están en EPS.</div>'
        : `<div>
            <div style="font-size:.78rem;font-weight:700;color:#92400e;margin-bottom:.35rem;">Vigentes con EPS SURA en el plan que no están en EPS (${d.faltan.length})</div>
            <table style="width:100%;border-collapse:collapse;font-size:.74rem;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                <tr style="background:#f1f5f9;text-align:left;">
                    <th style="padding:.35rem .6rem;">Documento</th><th style="padding:.35rem .6rem;">Nombre</th>
                    <th style="padding:.35rem .6rem;">Plan</th><th style="padding:.35rem .6rem;">Desde</th><th style="padding:.35rem .6rem;">Aliado</th>
                </tr>
                ${d.faltan.map(f => `<tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:.35rem .6rem;">${esc(f.documento)}</td><td style="padding:.35rem .6rem;">${esc(f.nombre)}</td>
                    <td style="padding:.35rem .6rem;">${esc(f.plan ?? '—')}</td><td style="padding:.35rem .6rem;">${esc(f.desde ?? '—')}</td>
                    <td style="padding:.35rem .6rem;">${esc(f.aliado ?? '—')}</td>
                </tr>`).join('')}
            </table></div>`;

    caja.innerHTML = html;
}

async function verRiesgos(nit, btn) {
    const parar = esperando(btn, 'Revisando la cobertura de cada trabajador en el portal');
    const caja  = document.getElementById('riesgos-' + nit);

    let d;
    try {
        const r = await fetch(`/brynex/conciliacion-arl/${nit}/riesgos`, { headers: { 'Accept': 'application/json' } });
        d = await r.json();
    } catch (e) {
        d = { ok: false, mensaje: 'Se perdió la conexión con el servidor.' };
    }

    parar();
    btn.disabled = false; btn.textContent = 'Comparar niveles de riesgo';

    if (!d.ok) { alert(d.mensaje || 'No se pudo comparar.'); return; }

    if (!d.diferencias.length) {
        caja.innerHTML = '<div style="font-size:.76rem;color:#16a34a;">✓ Todos los que están en ambos lados tienen el mismo nivel de riesgo.</div>';
        return;
    }

    caja.innerHTML = `
        <div style="font-size:.78rem;font-weight:700;color:#991b1b;margin-bottom:.35rem;">
            Con distinto nivel de riesgo (${d.diferencias.length})</div>
        <table style="width:100%;border-collapse:collapse;font-size:.74rem;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
            <tr style="background:#f1f5f9;text-align:left;">
                <th style="padding:.35rem .6rem;">Documento</th><th style="padding:.35rem .6rem;">Nombre</th>
                <th style="padding:.35rem .6rem;">En BryNex</th><th style="padding:.35rem .6rem;">En Sura</th>
                <th style="padding:.35rem .6rem;">Centro en Sura</th><th style="padding:.35rem .6rem;">Aliado</th>
            </tr>
            ${d.diferencias.map(f => `<tr style="border-top:1px solid #f1f5f9;">
                <td style="padding:.35rem .6rem;">${f.documento}</td>
                <td style="padding:.35rem .6rem;">${f.nombre}</td>
                <td style="padding:.35rem .6rem;font-weight:700;color:#1e40af;">N${f.riesgo_brynex}</td>
                <td style="padding:.35rem .6rem;font-weight:700;color:#b91c1c;">N${f.riesgo_sura}</td>
                <td style="padding:.35rem .6rem;">${f.centro_sura ?? '—'}</td>
                <td style="padding:.35rem .6rem;">${f.aliado ?? '—'}</td>
            </tr>`).join('')}
        </table>`;
}
</script>
@endsection
