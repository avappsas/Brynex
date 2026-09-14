{{--
    Modal de reingreso en Nueva EPS, autocontenido.

    Lo abre `abrirReingresoNuevaEps(contratoId)` desde el radicado de EPS. Va en
    tres pasos para que la persona vea lo que se va a enviar antes de radicar:
    revisar (sin portal) → consultar en Nueva EPS → registrar.
--}}
<style>
.nep-bg { display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:10000;align-items:center;justify-content:center;padding:1rem }
.nep-bg.open { display:flex }
.nep-box { background:#fff;border-radius:14px;max-width:540px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.3);overflow:hidden;max-height:92vh;overflow-y:auto }
.nep-head { background:linear-gradient(135deg,#be123c,#e11d48);padding:.85rem 1.1rem;display:flex;justify-content:space-between;align-items:center }
.nep-head h3 { color:#fff;font-size:.92rem;font-weight:800;margin:0 }
.nep-x { background:none;border:none;color:#fecdd3;font-size:1.15rem;cursor:pointer;line-height:1 }
.nep-body { padding:1.1rem }
.nep-resumen { background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:.65rem .8rem;font-size:.76rem;line-height:1.65;margin-bottom:.8rem }
.nep-resumen span { color:#64748b }
.nep-prob { background:#fef2f2;border:1px solid #fecaca;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#991b1b }
.nep-prob ul { margin:.35rem 0 0 1rem;padding:0 }
.nep-aviso { background:#fffbeb;border:1px solid #fcd34d;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#92400e;line-height:1.5 }
.nep-info { background:#eff6ff;border:1px solid #bfdbfe;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#1e3a8a;line-height:1.55 }
.nep-ok { background:#f0fdf4;border:1px solid #86efac;border-radius:9px;padding:.8rem .9rem;font-size:.8rem;color:#166534;line-height:1.6 }
.nep-btn { width:100%;margin-top:.5rem;background:linear-gradient(135deg,#be123c,#e11d48);color:#fff;border:none;border-radius:10px;padding:.6rem 1.2rem;font-size:.86rem;font-weight:700;cursor:pointer }
.nep-btn.sec { background:#fff;color:#be123c;border:1px solid #fda4af }
.nep-btn:disabled { opacity:.5;cursor:not-allowed }
</style>

<div class="nep-bg" id="nepModal">
  <div class="nep-box">
    <div class="nep-head">
      <h3>🏥 Reingreso en Nueva EPS</h3>
      <button class="nep-x" onclick="cerrarReingresoNuevaEps()">✕</button>
    </div>
    <div class="nep-body">
      <div id="nepCargando" style="text-align:center;color:#64748b;font-size:.82rem;padding:1.2rem">⏳ Revisando los datos del contrato...</div>

      <div id="nepContenido" style="display:none">
        <div class="nep-resumen" id="nepResumen"></div>
        <div class="nep-prob" id="nepProblemas" style="display:none">
          <strong>No se puede tramitar todavía:</strong>
          <ul id="nepProblemasLista"></ul>
        </div>
        <div class="nep-info" id="nepPortal" style="display:none"></div>
        <div class="nep-aviso" id="nepAviso" style="display:none"></div>

        <button class="nep-btn sec" id="nepBtnConsultar" onclick="consultarNuevaEps()">🔎 Consultar en Nueva EPS</button>
        <button class="nep-btn" id="nepBtnRegistrar" style="display:none" onclick="registrarNuevaEps()">🏥 Registrar reingreso</button>
      </div>

      <div id="nepResultado" class="nep-ok" style="display:none"></div>
    </div>
  </div>
</div>

<script>
let nepContratoId = null;
const NEP_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const nepEsc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function cerrarReingresoNuevaEps() { document.getElementById('nepModal').classList.remove('open'); }

// Consultar y registrar abren un navegador en el servidor: cerca de un minuto.
function nepEsperar(btn, texto) {
    const desde = Date.now();
    const pintar = () => btn.textContent = `⏳ ${texto} ${Math.round((Date.now() - desde) / 1000)}s`;
    btn.disabled = true; pintar();
    const reloj = setInterval(pintar, 1000);
    return () => clearInterval(reloj);
}

async function nepPedir(url, metodo, limiteSeg) {
    const corte = new AbortController();
    const alarma = setTimeout(() => corte.abort(), limiteSeg * 1000);
    try {
        const r = await fetch(url, { method: metodo, signal: corte.signal,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': NEP_CSRF } });
        return await r.json();
    } finally {
        clearTimeout(alarma);
    }
}

// Cada consulta o registro revisa de paso los radicados en trámite de la empresa.
function nepTextoConciliacion(c) {
    if (!c) return '';
    if (c.error) return `<div style="margin-top:.4rem;color:#92400e">⚠️ No se pudieron revisar los demás radicados de la empresa: ${nepEsc(c.error)}</div>`;
    const cerrados = c.cerrados ? ` <strong>${c.cerrados} pasaron a OK</strong>${c.nombres_cerrados?.length ? ' (' + c.nombres_cerrados.map(nepEsc).join(', ') + ')' : ''},` : '';
    return `<div style="margin-top:.4rem;color:#475569">🩺 De paso se revisaron ${c.revisados} radicados en trámite de la empresa:${cerrados} ${c.tramite} siguen en trámite, ${c.faltan} sin reingreso en Nueva EPS.</div>`;
}

function nepPintarResumen(r) {
    const li = (k, v) => v ? `<div><span>${k}:</span> <strong>${nepEsc(v)}</strong></div>` : '';
    document.getElementById('nepResumen').innerHTML =
        li('Trabajador', `${r.trabajador} — ${r.documento}`) +
        li('Empresa', `${r.razon_social} (NIT ${r.nit})`) +
        li('Plan / EPS', `${r.plan ?? '—'} · ${r.eps ?? '—'}`) +
        li('IBC', r.ibc ? '$' + Number(r.ibc).toLocaleString('es-CO') : null) +
        li('Inicio relación laboral', r.fecha_inicio) +
        li('Cargo', r.cargo_eps ? `${r.cargo_eps} (BryNex: ${r.cargo_brynex}, nivel ARL ${r.nivel_arl ?? '—'})` : r.cargo_brynex) +
        li('Asesor', r.asesor ?? 'se asigna al consultar');
}

async function abrirReingresoNuevaEps(contratoId) {
    nepContratoId = contratoId;
    ['nepContenido', 'nepResultado', 'nepPortal', 'nepAviso'].forEach(id => document.getElementById(id).style.display = 'none');
    document.getElementById('nepCargando').style.display = 'block';
    document.getElementById('nepCargando').textContent = '⏳ Revisando los datos del contrato...';
    document.getElementById('nepBtnRegistrar').style.display = 'none';
    document.getElementById('nepModal').classList.add('open');

    let d;
    try {
        d = await nepPedir(`/admin/afiliaciones/${contratoId}/nueva-eps/precheck`, 'GET', 30);
    } catch (e) {
        document.getElementById('nepCargando').textContent = '⚠️ No se pudo revisar el contrato.';
        return;
    }

    document.getElementById('nepCargando').style.display = 'none';
    document.getElementById('nepContenido').style.display = 'block';
    nepPintarResumen(d.resumen || {});

    const problemas = d.problemas || [];
    document.getElementById('nepProblemas').style.display = problemas.length ? 'block' : 'none';
    document.getElementById('nepProblemasLista').innerHTML = problemas.map(p => `<li>${nepEsc(p)}</li>`).join('');
    const btn = document.getElementById('nepBtnConsultar');
    btn.disabled = problemas.length > 0;
    btn.textContent = problemas.length ? '🚫 Completa los datos primero' : '🔎 Consultar en Nueva EPS';
}

async function consultarNuevaEps() {
    const btn = document.getElementById('nepBtnConsultar');
    const parar = nepEsperar(btn, 'Consultando en Nueva EPS...');
    let d;
    try {
        d = await nepPedir(`/admin/afiliaciones/${nepContratoId}/nueva-eps/consultar`, 'POST', 280);
    } catch (e) {
        d = { ok: false, error: 'Se perdió la conexión con el servidor.' };
    }
    parar();
    btn.disabled = false; btn.textContent = '🔎 Consultar de nuevo';

    if (!d.ok) { alert(d.error || (d.problemas || []).join('\n') || 'No se pudo consultar.'); return; }
    if (d.resumen) nepPintarResumen(d.resumen);

    const portal = document.getElementById('nepPortal');
    portal.innerHTML =
        `<div>En Nueva EPS: <strong>${nepEsc(d.nombre_eps || 'NO ENCONTRADO')}</strong> ${d.nombre_eps ? (d.apellido_coincide ? '✅ coincide con BryNex' : '⚠️ el apellido no coincide') : ''}</div>` +
        `<div>Cargo en el catálogo: <strong>${d.cargo_portal ? nepEsc(d.cargo_portal.codigo + ' ' + d.cargo_portal.descripcion) : '⚠️ no está'}</strong></div>` +
        nepTextoConciliacion(d.conciliacion);
    portal.style.display = 'block';

    const aviso = document.getElementById('nepAviso');
    const puede = d.nombre_eps && d.apellido_coincide && d.cargo_portal && !(d.existentes || []).length;
    if ((d.existentes || []).length) {
        const e = d.existentes[0];
        aviso.innerHTML = `⚠️ Ya tiene reingreso en Nueva EPS: radicado <strong>${nepEsc(e.radicado)}</strong> del ${nepEsc(e.fecha_radicacion)} (${nepEsc(e.estado)}). ` +
            'Al registrar no se repite: se vincula ese radicado.';
        aviso.style.display = 'block';
    } else if (!d.nombre_eps) {
        aviso.innerHTML = '⚠️ Nueva EPS no encuentra a la persona: el reingreso no aplica (puede ser un traslado o afiliación nueva).';
        aviso.style.display = 'block';
    } else {
        aviso.style.display = 'none';
    }

    const reg = document.getElementById('nepBtnRegistrar');
    reg.style.display = (puede || (d.existentes || []).length) ? 'block' : 'none';
    reg.textContent = (d.existentes || []).length ? '🔗 Vincular el radicado existente' : '🏥 Registrar reingreso';
}

async function registrarNuevaEps() {
    if (!confirm('¿Registrar la solicitud de reingreso en Nueva EPS con estos datos?\n\nQueda radicada en el portal y no se puede anular desde aquí.')) return;

    const btn = document.getElementById('nepBtnRegistrar');
    const parar = nepEsperar(btn, 'Registrando en Nueva EPS...');
    let d;
    try {
        d = await nepPedir(`/admin/afiliaciones/${nepContratoId}/nueva-eps/registrar`, 'POST', 280);
    } catch (e) {
        // No se reintenta solo: pudo quedar radicado. Consultar lo detecta.
        d = { ok: false, error: 'Se perdió la conexión. Antes de reintentar, usa "Consultar": si quedó radicado, aparecerá.' };
    }
    parar();

    if (!d.ok) {
        btn.disabled = false; btn.textContent = '🏥 Reintentar';
        alert(d.error || 'No se pudo registrar.');
        return;
    }

    document.getElementById('nepContenido').style.display = 'none';
    const caja = document.getElementById('nepResultado');
    caja.innerHTML = d.ya_existia
        ? `🔗 Se vinculó el radicado <strong>${nepEsc(d.radicado)}</strong> (${nepEsc(d.estado_eps)}). No se volvió a radicar.`
        : `✅ Reingreso radicado en Nueva EPS: <strong>${nepEsc(d.radicado)}</strong><br>` +
          `<span style="color:#475569">El radicado de EPS quedó en trámite${d.pdf ? ' con el certificado adjunto' : ''}. Pasa a OK cuando Nueva EPS lo procese.</span>`;
    caja.innerHTML += nepTextoConciliacion(d.conciliacion);
    caja.style.display = 'block';
    setTimeout(() => location.reload(), d.conciliacion?.cerrados ? 7000 : 3500);
}
</script>
