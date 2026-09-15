{{--
    Modal de novedad de inicio laboral en S.O.S., autocontenido.

    Lo abre `abrirNovedadSos(contratoId)` desde el radicado de EPS. El login de
    S.O.S. pide captcha: el servidor abre el portal y aquí se muestra la imagen
    del reto; cada clic sobre ella se reenvía al portal. Con la sesión abierta,
    consultar y registrar funcionan como en Salud Total. La sesión es por
    empresa y dura un rato, así que el siguiente contrato no vuelve a pedirlo.
--}}
<style>
.sosn-bg { display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:10000;align-items:center;justify-content:center;padding:1rem }
.sosn-bg.open { display:flex }
.sosn-box { background:#fff;border-radius:14px;max-width:560px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.3);overflow:hidden;max-height:94vh;overflow-y:auto }
.sosn-head { background:linear-gradient(135deg,#1d4ed8,#2563eb);padding:.85rem 1.1rem;display:flex;justify-content:space-between;align-items:center }
.sosn-head h3 { color:#fff;font-size:.92rem;font-weight:800;margin:0 }
.sosn-x { background:none;border:none;color:#dbeafe;font-size:1.15rem;cursor:pointer;line-height:1 }
.sosn-body { padding:1.1rem }
.sosn-resumen { background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:.65rem .8rem;font-size:.76rem;line-height:1.65;margin-bottom:.8rem }
.sosn-resumen span { color:#64748b }
.sosn-prob { background:#fef2f2;border:1px solid #fecaca;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#991b1b }
.sosn-prob ul { margin:.35rem 0 0 1rem;padding:0 }
.sosn-aviso { background:#fffbeb;border:1px solid #fcd34d;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#92400e;line-height:1.5 }
.sosn-info { background:#eff6ff;border:1px solid #bfdbfe;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#1e3a8a;line-height:1.55 }
.sosn-ok { background:#f0fdf4;border:1px solid #86efac;border-radius:9px;padding:.8rem .9rem;font-size:.8rem;color:#166534;line-height:1.6 }
.sosn-btn { width:100%;margin-top:.5rem;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;border:none;border-radius:10px;padding:.6rem 1.2rem;font-size:.86rem;font-weight:700;cursor:pointer }
.sosn-btn.sec { background:#fff;color:#1d4ed8;border:1px solid #93c5fd }
.sosn-btn:disabled { opacity:.5;cursor:not-allowed }
.sosn-captcha { text-align:center;margin:.4rem 0 .6rem;position:relative;display:flex;justify-content:center }
.sosn-captcha-marco { position:relative;display:inline-block;line-height:0 }
.sosn-captcha img { max-width:100%;border:2px solid #2563eb;border-radius:6px;cursor:crosshair;user-select:none;-webkit-user-select:none;-webkit-user-drag:none }
.sosn-punto { position:absolute;width:14px;height:14px;margin:-7px 0 0 -7px;border-radius:50%;background:rgba(220,38,38,.85);border:2px solid #fff;pointer-events:none }
.sosn-fecha { display:flex;gap:.5rem;align-items:center;font-size:.78rem;margin:.3rem 0 .2rem }
.sosn-fecha input { border:1px solid #cbd5e1;border-radius:7px;padding:.3rem .5rem;font-size:.8rem }
</style>

<div class="sosn-bg" id="sosnModal">
  <div class="sosn-box">
    <div class="sosn-head">
      <h3>🏥 Novedad de inicio laboral en S.O.S.</h3>
      <button class="sosn-x" onclick="cerrarNovedadSos()">✕</button>
    </div>
    <div class="sosn-body">
      <div id="sosnCargando" style="text-align:center;color:#64748b;font-size:.82rem;padding:1.2rem">⏳ Revisando los datos del contrato...</div>

      <div id="sosnContenido" style="display:none">
        <div class="sosn-resumen" id="sosnResumen"></div>
        <div class="sosn-prob" id="sosnProblemas" style="display:none">
          <strong>No se puede tramitar todavía:</strong>
          <ul id="sosnProblemasLista"></ul>
        </div>

        {{-- Sesión del portal --}}
        <div class="sosn-info" id="sosnSesion"></div>
        <div id="sosnCaptcha" style="display:none">
          <div class="sosn-captcha"><div class="sosn-captcha-marco" id="sosnCaptchaMarco">
            <img id="sosnCaptchaImg" alt="Captcha de S.O.S." draggable="false" onmousedown="event.preventDefault()" onclick="clicCaptchaSos(event)">
          </div></div>
          <div style="text-align:center;font-size:.72rem;color:#64748b;margin:-.2rem 0 .4rem">Un clic por imagen; espera a que se actualice antes del siguiente.</div>
          <div style="display:flex;gap:.4rem">
            <button class="sosn-btn sec" style="margin-top:0" onclick="pintarSesionSos(true)">🔄 Actualizar imagen</button>
            <button class="sosn-btn sec" style="margin-top:0" onclick="reiniciarCaptchaSos()">↩️ Otro captcha</button>
          </div>
        </div>
        <button class="sosn-btn sec" id="sosnBtnSesion" onclick="iniciarSesionSos()">🔐 Iniciar sesión en S.O.S.</button>

        <div class="sosn-info" id="sosnPortal" style="display:none"></div>
        <div class="sosn-aviso" id="sosnAviso" style="display:none"></div>

        <button class="sosn-btn sec" id="sosnBtnConsultar" style="display:none" onclick="consultarSos()">🔎 Consultar en S.O.S.</button>
        <div id="sosnRegistro" style="display:none">
          <div class="sosn-fecha">
            <label for="sosnFecha"><strong>Fecha de ingreso a reportar:</strong></label>
            <input type="date" id="sosnFecha">
          </div>
          <div id="sosnFechaNota" style="font-size:.72rem;color:#64748b"></div>
          <button class="sosn-btn" id="sosnBtnRegistrar" onclick="registrarSos()">🏥 Registrar y adjuntar lado B</button>
        </div>
      </div>

      <div id="sosnResultado" class="sosn-ok" style="display:none"></div>
    </div>
  </div>
</div>

<script>
let sosnContratoId = null, sosnResumen = {}, sosnReloj = null, sosnAncho = 0, sosnAlto = 0;
const SOSN_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const sosnEsc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const sosnFmt = iso => iso ? iso.split('-').reverse().join('/') : '—';
const sosnEl = id => document.getElementById(id);

function cerrarNovedadSos() { sosnEl('sosnModal').classList.remove('open'); clearInterval(sosnReloj); }

async function sosnPedir(ruta, metodo = 'GET', cuerpo = null, limiteSeg = 60) {
    const corte = new AbortController();
    const alarma = setTimeout(() => corte.abort(), limiteSeg * 1000);
    try {
        const r = await fetch(`/admin/afiliaciones/${sosnContratoId}/sos/${ruta}`, {
            method: metodo, signal: corte.signal,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': SOSN_CSRF },
            body: cuerpo ? JSON.stringify(cuerpo) : null,
        });
        return await r.json();
    } finally {
        clearTimeout(alarma);
    }
}

function sosnEsperar(btn, texto) {
    const desde = Date.now();
    const pintar = () => btn.textContent = `⏳ ${texto} ${Math.round((Date.now() - desde) / 1000)}s`;
    btn.disabled = true; pintar();
    const reloj = setInterval(pintar, 1000);
    return () => clearInterval(reloj);
}

function sosnPintarResumen(r) {
    const li = (k, v) => v ? `<div><span>${k}:</span> <strong>${sosnEsc(v)}</strong></div>` : '';
    sosnEl('sosnResumen').innerHTML =
        li('Trabajador', `${r.trabajador} — ${r.documento}`) +
        li('Empresa', `${r.razon_social} (NIT ${r.nit})`) +
        li('Plan / EPS', `${r.plan ?? '—'} · ${r.eps ?? '—'}`) +
        li('IBC', r.ibc ? '$' + Number(r.ibc).toLocaleString('es-CO') : null) +
        li('ARL / AFP', `${r.arl} · ${r.afp}`) +
        li('Ingreso real', sosnFmt(r.fecha_ingreso) + (r.en_plazo ? '' : ' ⚠️ fuera del plazo de S.O.S. (±10 días)'));
}

async function abrirNovedadSos(contratoId) {
    sosnContratoId = contratoId;
    ['sosnContenido', 'sosnResultado', 'sosnPortal', 'sosnAviso', 'sosnCaptcha', 'sosnRegistro', 'sosnBtnConsultar'].forEach(id => sosnEl(id).style.display = 'none');
    sosnEl('sosnCargando').style.display = 'block';
    sosnEl('sosnModal').classList.add('open');

    let d;
    try { d = await sosnPedir('precheck'); }
    catch (e) { sosnEl('sosnCargando').textContent = '⚠️ No se pudo revisar el contrato.'; return; }

    sosnEl('sosnCargando').style.display = 'none';
    sosnEl('sosnContenido').style.display = 'block';
    sosnResumen = d.resumen || {};
    sosnPintarResumen(sosnResumen);

    const problemas = d.problemas || [];
    sosnEl('sosnProblemas').style.display = problemas.length ? 'block' : 'none';
    sosnEl('sosnProblemasLista').innerHTML = problemas.map(p => `<li>${sosnEsc(p)}</li>`).join('');
    sosnEl('sosnBtnSesion').disabled = problemas.length > 0;
    aplicarSesionSos(d.sesion || {});
}

/** Pinta la etapa de la sesión y, mientras haya captcha o se esté entrando, sigue preguntando. */
function aplicarSesionSos(s) {
    const etapa = s.etapa || 'cerrada';
    const textos = {
        cerrada: 'Sin sesión en S.O.S. para esta empresa.',
        abriendo: '⏳ Abriendo el portal de S.O.S. en el servidor…',
        captcha: '🧩 Resuelve el captcha: haz clic sobre las imágenes que pide y luego en <strong>VERIFY</strong>. Si pide otra ronda, sigue igual.',
        entrando: '⏳ Captcha resuelto. Entrando con la clave del módulo de claves…',
        lista: '✅ Sesión iniciada en S.O.S.',
        vencida: '⌛ S.O.S. cerró la sesión por inactividad.',
        rechazada: '❌ S.O.S. rechazó la clave: ' + sosnEsc(s.mensaje || '') + ' Actualízala en el módulo de claves.',
        error: '⚠️ ' + sosnEsc(s.mensaje || 'Error en la sesión.'),
    };
    sosnEl('sosnSesion').innerHTML = textos[etapa] || sosnEsc(s.mensaje || etapa);

    const conCaptcha = etapa === 'captcha' && s.captcha;
    sosnEl('sosnCaptcha').style.display = conCaptcha ? 'block' : 'none';
    if (conCaptcha) {
        sosnEl('sosnCaptchaImg').src = 'data:image/png;base64,' + s.captcha.imagen;
        sosnAncho = s.captcha.ancho; sosnAlto = s.captcha.alto;
    }

    const btn = sosnEl('sosnBtnSesion');
    btn.style.display = ['cerrada', 'vencida', 'error', 'rechazada'].includes(etapa) ? 'block' : 'none';
    btn.textContent = etapa === 'cerrada' ? '🔐 Iniciar sesión en S.O.S.' : '🔐 Volver a iniciar sesión';

    sosnEl('sosnBtnConsultar').style.display = etapa === 'lista' ? 'block' : 'none';

    clearInterval(sosnReloj);
    if (['abriendo', 'captcha', 'entrando'].includes(etapa)) {
        sosnReloj = setInterval(() => pintarSesionSos(false), etapa === 'captcha' ? 4000 : 2000);
    }
}

async function pintarSesionSos() {
    try { const d = await sosnPedir('sesion'); aplicarSesionSos(d.sesion || {}); } catch (e) {}
}

async function iniciarSesionSos() {
    const btn = sosnEl('sosnBtnSesion');
    const parar = sosnEsperar(btn, 'Abriendo S.O.S....');
    let d;
    try { d = await sosnPedir('sesion', 'POST', {}, 60); }
    catch (e) { d = { ok: false, error: 'Se perdió la conexión con el servidor.' }; }
    parar(); btn.disabled = false;
    if (!d.ok) { alert(d.error || 'No se pudo abrir la sesión.'); return; }
    aplicarSesionSos(d.sesion || {});
}

let sosnEnviando = false;
async function clicCaptchaSos(ev) {
    // Un clic a la vez: dos seguidos marcaban y desmarcaban la misma casilla.
    if (sosnEnviando) return;
    const img = ev.currentTarget;
    const caja = img.getBoundingClientRect();
    const x = (ev.clientX - caja.left) * (sosnAncho / caja.width);
    const y = (ev.clientY - caja.top) * (sosnAlto / caja.height);

    const punto = document.createElement('div');
    punto.className = 'sosn-punto';
    punto.style.left = (ev.clientX - caja.left) + 'px';
    punto.style.top = (ev.clientY - caja.top) + 'px';
    sosnEl('sosnCaptchaMarco').appendChild(punto);

    sosnEnviando = true;
    clearInterval(sosnReloj);
    img.style.opacity = .6;
    try {
        const d = await sosnPedir('sesion/clic', 'POST', { x, y }, 30);
        if (!d.ok) alert(d.error || 'No se pudo enviar el clic.');
        else aplicarSesionSos(d.sesion || {});
    } finally {
        img.style.opacity = 1;
        punto.remove();
        sosnEnviando = false;
    }
}

async function reiniciarCaptchaSos() {
    clearInterval(sosnReloj);
    sosnEl('sosnSesion').textContent = '⏳ Pidiendo otro captcha…';
    const d = await sosnPedir('sesion/clic', 'POST', { x: 0, y: 0, reiniciar: true }, 90);
    if (!d.ok) alert(d.error || 'No se pudo reiniciar el captcha.');
    else aplicarSesionSos(d.sesion || {});
}

async function consultarSos() {
    const btn = sosnEl('sosnBtnConsultar');
    const parar = sosnEsperar(btn, 'Consultando en S.O.S....');
    let d;
    try { d = await sosnPedir('consultar', 'POST', {}, 200); }
    catch (e) { d = { ok: false, error: 'Se perdió la conexión con el servidor.' }; }
    parar(); btn.disabled = false; btn.textContent = '🔎 Consultar de nuevo';

    if (!d.ok) { alert(d.error || (d.problemas || []).join('\n') || 'No se pudo consultar.'); pintarSesionSos(); return; }

    const n = d.novedad;
    const portal = sosnEl('sosnPortal');
    portal.innerHTML = n
        ? `<div>Ya tiene novedad en S.O.S.: radicado <strong>${sosnEsc(n.radicado)}</strong> del ${sosnEsc(n.fecha_radicacion)}</div>` +
          `<div>Estado: <strong>${sosnEsc(n.estado)}</strong>${n.causal ? ' — ' + sosnEsc(n.causal) : ''}</div>`
        : '<div>No tiene novedad de inicio laboral reciente en S.O.S.</div>';
    portal.style.display = 'block';

    const aviso = sosnEl('sosnAviso');
    aviso.style.display = 'none';
    const reg = sosnEl('sosnBtnRegistrar');
    const fecha = sosnEl('sosnFecha');

    if (n) {
        sosnEl('sosnFecha').parentElement.style.display = 'none';
        sosnEl('sosnFechaNota').textContent = '';
        reg.textContent = /cara b/i.test(n.estado) ? '📎 Adjuntar lado B y actualizar radicado' : '🔗 Vincular al radicado';
    } else {
        fecha.parentElement.style.display = 'flex';
        fecha.min = sosnResumen.fecha_minima; fecha.max = sosnResumen.fecha_maxima;
        fecha.value = sosnResumen.en_plazo ? sosnResumen.fecha_ingreso : '';
        sosnEl('sosnFechaNota').textContent = `S.O.S. acepta del ${sosnFmt(sosnResumen.fecha_minima)} al ${sosnFmt(sosnResumen.fecha_maxima)}.` +
            (sosnResumen.en_plazo ? '' : ' El ingreso real está fuera de ese rango: elige la fecha a reportar.');
        if (!sosnResumen.en_plazo) {
            aviso.innerHTML = `⚠️ El ingreso real (${sosnFmt(sosnResumen.fecha_ingreso)}) está fuera del plazo de S.O.S. La fecha que elijas queda anotada en el radicado junto con la real.`;
            aviso.style.display = 'block';
        }
        reg.textContent = '🏥 Registrar y adjuntar lado B';
    }
    reg.disabled = false;
    sosnEl('sosnRegistro').style.display = 'block';
}

async function registrarSos() {
    const fecha = sosnEl('sosnFecha').value || sosnResumen.fecha_ingreso;
    const nueva = sosnEl('sosnFecha').parentElement.style.display !== 'none';
    if (nueva && !fecha) { alert('Elige la fecha de ingreso a reportar.'); return; }
    if (!confirm(nueva
        ? `¿Radicar la novedad en S.O.S. con fecha de ingreso ${sosnFmt(fecha)} y adjuntar el lado B firmado?\n\nQueda radicada en el portal y no se puede anular desde aquí.`
        : '¿Actualizar el radicado con la novedad que ya existe en S.O.S.?')) return;

    const btn = sosnEl('sosnBtnRegistrar');
    const parar = sosnEsperar(btn, 'Trabajando en S.O.S....');
    let d;
    try { d = await sosnPedir('registrar', 'POST', { fecha }, 400); }
    catch (e) { d = { ok: false, error: 'Se perdió la conexión. Antes de reintentar, usa "Consultar": si quedó radicada, aparecerá.' }; }
    parar();

    if (!d.ok) { btn.disabled = false; btn.textContent = '🏥 Reintentar'; alert(d.error || 'No se pudo registrar.'); return; }

    sosnEl('sosnContenido').style.display = 'none';
    const caja = sosnEl('sosnResultado');
    caja.innerHTML = `✅ S.O.S. — radicado <strong>${sosnEsc(d.radicado)}</strong>: ${sosnEsc(d.estado_eps || '')}<br>` +
        `<span style="color:#475569">${d.lado_b ? 'Lado B adjuntado. ' : ''}${d.certificado ? 'Certificado guardado; el radicado quedó en OK.' : d.rechazada ? 'S.O.S. la rechazó: enviar formulario completo y carta de derechos.' : 'El radicado de EPS quedó en trámite; S.O.S. responde en unas 24 horas.'}</span>`;
    caja.style.display = 'block';
    setTimeout(() => location.reload(), 4000);
}
</script>
