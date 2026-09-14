{{--
    Modal de novedad de inicio laboral en Salud Total, autocontenido.

    Lo abre `abrirNovedadSaludTotal(contratoId)` desde el radicado de EPS. Va en
    tres pasos, como el de Nueva EPS: revisar (sin portal) → consultar en Salud
    Total → registrar. Todo va por HTTP en el servidor: tarda segundos.
--}}
<style>
.stn-bg { display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:10000;align-items:center;justify-content:center;padding:1rem }
.stn-bg.open { display:flex }
.stn-box { background:#fff;border-radius:14px;max-width:540px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.3);overflow:hidden;max-height:92vh;overflow-y:auto }
.stn-head { background:linear-gradient(135deg,#15803d,#22c55e);padding:.85rem 1.1rem;display:flex;justify-content:space-between;align-items:center }
.stn-head h3 { color:#fff;font-size:.92rem;font-weight:800;margin:0 }
.stn-x { background:none;border:none;color:#dcfce7;font-size:1.15rem;cursor:pointer;line-height:1 }
.stn-body { padding:1.1rem }
.stn-resumen { background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:.65rem .8rem;font-size:.76rem;line-height:1.65;margin-bottom:.8rem }
.stn-resumen span { color:#64748b }
.stn-prob { background:#fef2f2;border:1px solid #fecaca;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#991b1b }
.stn-prob ul { margin:.35rem 0 0 1rem;padding:0 }
.stn-aviso { background:#fffbeb;border:1px solid #fcd34d;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#92400e;line-height:1.5 }
.stn-info { background:#eff6ff;border:1px solid #bfdbfe;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#1e3a8a;line-height:1.55 }
.stn-ok { background:#f0fdf4;border:1px solid #86efac;border-radius:9px;padding:.8rem .9rem;font-size:.8rem;color:#166534;line-height:1.6 }
.stn-btn { width:100%;margin-top:.5rem;background:linear-gradient(135deg,#15803d,#22c55e);color:#fff;border:none;border-radius:10px;padding:.6rem 1.2rem;font-size:.86rem;font-weight:700;cursor:pointer }
.stn-btn.sec { background:#fff;color:#15803d;border:1px solid #86efac }
.stn-btn:disabled { opacity:.5;cursor:not-allowed }
</style>

<div class="stn-bg" id="stnModal">
  <div class="stn-box">
    <div class="stn-head">
      <h3>🏥 Novedad de inicio laboral en Salud Total</h3>
      <button class="stn-x" onclick="cerrarNovedadSaludTotal()">✕</button>
    </div>
    <div class="stn-body">
      <div id="stnCargando" style="text-align:center;color:#64748b;font-size:.82rem;padding:1.2rem">⏳ Revisando los datos del contrato...</div>

      <div id="stnContenido" style="display:none">
        <div class="stn-resumen" id="stnResumen"></div>
        <div class="stn-prob" id="stnProblemas" style="display:none">
          <strong>No se puede tramitar todavía:</strong>
          <ul id="stnProblemasLista"></ul>
        </div>
        <div class="stn-info" id="stnPortal" style="display:none"></div>
        <div class="stn-aviso" id="stnAviso" style="display:none"></div>

        <button class="stn-btn sec" id="stnBtnConsultar" onclick="consultarSaludTotal()">🔎 Consultar en Salud Total</button>
        <button class="stn-btn" id="stnBtnRegistrar" style="display:none" onclick="registrarSaludTotal()">🏥 Registrar novedad</button>
      </div>

      <div id="stnResultado" class="stn-ok" style="display:none"></div>
    </div>
  </div>
</div>

<script>
let stnContratoId = null;
const STN_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const stnEsc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function cerrarNovedadSaludTotal() { document.getElementById('stnModal').classList.remove('open'); }

function stnEsperar(btn, texto) {
    const desde = Date.now();
    const pintar = () => btn.textContent = `⏳ ${texto} ${Math.round((Date.now() - desde) / 1000)}s`;
    btn.disabled = true; pintar();
    const reloj = setInterval(pintar, 1000);
    return () => clearInterval(reloj);
}

async function stnPedir(url, metodo, limiteSeg) {
    const corte = new AbortController();
    const alarma = setTimeout(() => corte.abort(), limiteSeg * 1000);
    try {
        const r = await fetch(url, { method: metodo, signal: corte.signal,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': STN_CSRF } });
        return await r.json();
    } finally {
        clearTimeout(alarma);
    }
}

function stnPintarResumen(r) {
    const li = (k, v) => v ? `<div><span>${k}:</span> <strong>${stnEsc(v)}</strong></div>` : '';
    document.getElementById('stnResumen').innerHTML =
        li('Trabajador', `${r.trabajador} — ${r.documento}`) +
        li('Empresa', `${r.razon_social} (NIT ${r.nit})`) +
        li('Plan / EPS', `${r.plan ?? '—'} · ${r.eps ?? '—'}`) +
        li('Tipo de cotizante', r.cotizante) +
        li('IBC', r.ibc ? '$' + Number(r.ibc).toLocaleString('es-CO') : null) +
        li('Fecha de ingreso', r.fecha_inicio + (r.fuera_de_plazo ? ' ⚠️ fuera del plazo del portal' : ''));
}

async function abrirNovedadSaludTotal(contratoId) {
    stnContratoId = contratoId;
    ['stnContenido', 'stnResultado', 'stnPortal', 'stnAviso'].forEach(id => document.getElementById(id).style.display = 'none');
    document.getElementById('stnCargando').style.display = 'block';
    document.getElementById('stnCargando').textContent = '⏳ Revisando los datos del contrato...';
    document.getElementById('stnBtnRegistrar').style.display = 'none';
    document.getElementById('stnModal').classList.add('open');

    let d;
    try {
        d = await stnPedir(`/admin/afiliaciones/${contratoId}/salud-total/precheck`, 'GET', 30);
    } catch (e) {
        document.getElementById('stnCargando').textContent = '⚠️ No se pudo revisar el contrato.';
        return;
    }

    document.getElementById('stnCargando').style.display = 'none';
    document.getElementById('stnContenido').style.display = 'block';
    stnPintarResumen(d.resumen || {});

    const problemas = d.problemas || [];
    document.getElementById('stnProblemas').style.display = problemas.length ? 'block' : 'none';
    document.getElementById('stnProblemasLista').innerHTML = problemas.map(p => `<li>${stnEsc(p)}</li>`).join('');
    const btn = document.getElementById('stnBtnConsultar');
    btn.disabled = problemas.length > 0;
    btn.textContent = problemas.length ? '🚫 Completa los datos primero' : '🔎 Consultar en Salud Total';
}

async function consultarSaludTotal() {
    const btn = document.getElementById('stnBtnConsultar');
    const parar = stnEsperar(btn, 'Consultando en Salud Total...');
    let d;
    try {
        d = await stnPedir(`/admin/afiliaciones/${stnContratoId}/salud-total/consultar`, 'POST', 280);
    } catch (e) {
        d = { ok: false, error: 'Se perdió la conexión con el servidor.' };
    }
    parar();
    btn.disabled = false; btn.textContent = '🔎 Consultar de nuevo';

    if (!d.ok) { alert(d.error || (d.problemas || []).join('\n') || 'No se pudo consultar.'); return; }
    if (d.resumen) stnPintarResumen(d.resumen);

    const activo = d.activo_empresa && d.activo_empresa.contrato_vigente && /^activo/i.test(d.activo_empresa.estado || '');
    const portal = document.getElementById('stnPortal');
    portal.innerHTML =
        `<div>En Salud Total: <strong>${stnEsc(d.nombre_eps || 'NO ENCONTRADO')}</strong> ${d.nombre_eps ? (d.apellido_coincide ? '✅ coincide con BryNex' : '⚠️ el apellido no coincide') : ''}</div>` +
        (d.estado_eps ? `<div>Estado en la EPS: <strong>${stnEsc(d.estado_eps)}</strong></div>` : '') +
        `<div>Con esta empresa: <strong>${d.activo_empresa ? stnEsc(`${d.activo_empresa.estado} desde ${d.activo_empresa.desde}`) : 'sin afiliación'}</strong></div>`;
    portal.style.display = 'block';

    const aviso = document.getElementById('stnAviso');
    let textoBoton = '🏥 Registrar novedad';
    const fueraDePlazo = d.resumen && d.resumen.fuera_de_plazo;
    let mostrar = d.nombre_eps && d.apellido_coincide && !fueraDePlazo;
    if (d.novedad) {
        aviso.innerHTML = `⚠️ Ya tiene novedad en Salud Total: formulario <strong>${stnEsc(d.novedad.numero)}</strong> (${stnEsc(d.novedad.estado)}, ingreso ${stnEsc(d.novedad.fecha_ingreso)}). ` +
            'Al continuar no se repite: se vincula ese formulario al radicado.';
        textoBoton = '🔗 Vincular la novedad existente';
        mostrar = true;
    } else if (activo) {
        aviso.innerHTML = '✅ Ya está activo con la empresa en Salud Total. No hace falta radicar: al continuar, el radicado pasa a OK.';
        textoBoton = '✅ Marcar radicado en OK';
        mostrar = true;
    } else if (!d.nombre_eps) {
        aviso.innerHTML = '⚠️ Salud Total no encuentra a la persona: la novedad de inicio laboral no aplica (es una afiliación nueva o un traslado).';
    } else if (!d.apellido_coincide) {
        aviso.innerHTML = '⚠️ El apellido en Salud Total no coincide con el de BryNex: revisa el documento antes de radicar.';
    } else if (fueraDePlazo) {
        aviso.innerHTML = '⚠️ ' + stnEsc(fueraDePlazo);
    }
    aviso.style.display = (d.novedad || activo || !d.nombre_eps || !d.apellido_coincide || fueraDePlazo) ? 'block' : 'none';

    const reg = document.getElementById('stnBtnRegistrar');
    reg.style.display = mostrar ? 'block' : 'none';
    reg.textContent = textoBoton;
}

async function registrarSaludTotal() {
    if (!confirm('¿Registrar la novedad de inicio laboral en Salud Total con estos datos?\n\nQueda radicada en el portal y no se puede anular desde aquí.')) return;

    const btn = document.getElementById('stnBtnRegistrar');
    const parar = stnEsperar(btn, 'Registrando en Salud Total...');
    let d;
    try {
        d = await stnPedir(`/admin/afiliaciones/${stnContratoId}/salud-total/registrar`, 'POST', 280);
    } catch (e) {
        // No se reintenta solo: pudo quedar radicada. Consultar lo detecta.
        d = { ok: false, error: 'Se perdió la conexión. Antes de reintentar, usa "Consultar": si quedó radicada, aparecerá.' };
    }
    parar();

    if (!d.ok) {
        btn.disabled = false; btn.textContent = '🏥 Reintentar';
        alert(d.error || 'No se pudo registrar.');
        return;
    }

    document.getElementById('stnContenido').style.display = 'none';
    const caja = document.getElementById('stnResultado');
    caja.innerHTML = d.ya_existia
        ? `🔗 Se vinculó el formulario <strong>${stnEsc(d.radicado)}</strong> (${stnEsc(d.estado_eps)}). No se volvió a radicar.`
        : d.ya_activo
        ? `✅ Ya estaba activo con la empresa desde ${stnEsc(d.desde)}. El radicado de EPS quedó en OK.`
        : `✅ Novedad radicada en Salud Total: formulario <strong>${stnEsc(d.radicado)}</strong><br>` +
          `<span style="color:#475569">El radicado de EPS quedó en trámite${d.pdf ? ' con el Formulario Único adjunto' : ''}. Pasa a OK cuando Salud Total la apruebe.</span>`;
    caja.style.display = 'block';
    setTimeout(() => location.reload(), 3500);
}
</script>
