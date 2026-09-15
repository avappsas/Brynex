/**
 * Sesión del portal de empleadores de S.O.S., viva mientras se trabaja.
 *
 * El login de S.O.S. tiene reCAPTCHA de imágenes, así que no se puede entrar
 * solo: este proceso abre el login en un Chrome del servidor, BryNex le muestra
 * a la persona la imagen del reto y le reenvía sus clics. Resuelto el captcha,
 * entra con la clave del módulo de claves y queda escuchando órdenes en
 * 127.0.0.1 (con token) hasta que pasa un rato sin uso.
 *
 * Nunca intenta resolver el captcha por su cuenta: solo toma fotos y hace los
 * clics que manda la persona.
 *
 * Uso: node scripts/sos-sesion.mjs <archivo-entrada.json>
 *   {usuario, contrasena, puerto, token, inactividadMinutos}
 * El archivo se borra apenas se lee (lleva la clave).
 *
 * Órdenes (HTTP, cabecera X-Token):
 *  GET  /estado                → {etapa, mensaje, captcha?: {imagen, ancho, alto}}
 *  POST /clic {x, y}           → clic de la persona sobre la imagen del reto
 *  POST /captcha/reiniciar     → vuelve a marcar "No soy un robot"
 *  POST /consultar {tipo, documento, desde, hasta}          (fechas DD/MM/AAAA)
 *  POST /certificado {tipo, documento, desde, hasta}        → PDF en base64
 *  POST /registrar {tipoId, documento, ibc, fecha, arl, afp, guardar}
 *  POST /adjuntar {tipo, documento, desde, hasta, archivo}  (JPG/PNG en disco)
 *  POST /cerrar
 */
import http from 'node:http';
import { readFile, unlink } from 'node:fs/promises';
import puppeteer from 'puppeteer-core';
import { rutaChrome } from './arl-sura-sesion-comun.mjs';

const BASE = 'https://centralaplicaciones.sos.com.co/PortalTransaccionalWeb/paginas/empleadores';
const URL_LOGIN = `${BASE}/Logueo/loginEmpleadores.jsf`;

const esperar = (ms) => new Promise(r => setTimeout(r, ms));
const log = (...a) => console.log(new Date().toISOString(), ...a);
const sel = (id) => `[id="${id}"]`;

const archivo = process.argv[2];
let entrada;
try {
  entrada = JSON.parse(await readFile(archivo, 'utf8'));
} finally {
  await unlink(archivo).catch(() => {});
}
const { usuario, contrasena, puerto, token } = entrada;
const INACTIVIDAD_MS = (entrada.inactividadMinutos || 30) * 60000;

const estado = { etapa: 'abriendo', mensaje: 'Abriendo el portal de S.O.S.…' };
let ultimoUso = Date.now();

const ejecutable = await (async () => {
  const { access } = await import('node:fs/promises');
  for (const r of rutaChrome()) { try { await access(r); return r; } catch {} }
  return null;
})();
if (!ejecutable) { log('No se encontró Chrome'); process.exit(1); }

// En el servidor (Linux) Chrome ignora --lang y el captcha salía en inglés: el
// idioma va también en la cabecera y en el locale de la página.
const navegador = await puppeteer.launch({
  executablePath: ejecutable,
  headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-blink-features=AutomationControlled', '--lang=es-CO', '--accept-lang=es-CO,es'],
  env: { ...process.env, LANG: 'es_CO.UTF-8', LANGUAGE: 'es_CO:es' },
  defaultViewport: { width: 1280, height: 900 },
});
const pagina = await navegador.newPage();
await pagina.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36');
await pagina.setExtraHTTPHeaders({ 'Accept-Language': 'es-CO,es;q=0.9' });
await (await pagina.createCDPSession()).send('Emulation.setLocaleOverride', { locale: 'es-CO' }).catch(() => {});
// Lo que de verdad decide el idioma del reCAPTCHA es `hl` en la carga de api.js.
await pagina.setRequestInterception(true);
pagina.on('request', (req) => {
  const url = req.url();
  if (/google\.com\/recaptcha\/api\.js/.test(url) && !/[?&]hl=/.test(url)) {
    return req.continue({ url: url + (url.includes('?') ? '&' : '?') + 'hl=es-419' }).catch(() => {});
  }
  return req.continue().catch(() => {});
});
pagina.on('dialog', d => d.accept().catch(() => {}));

const terminar = async (codigo = 0) => {
  estado.etapa = 'cerrada';
  await navegador.close().catch(() => {});
  process.exit(codigo);
};
process.on('SIGTERM', () => terminar(0));

// ── Utilidades de página ──────────────────────────────────────────────────

const quieta = () => pagina.waitForNetworkIdle({ idleTime: 700, timeout: 45000 }).catch(() => {});

/** Clic que puede disparar navegación completa (jsfcljs) o AJAX (A4J). */
const clicYEsperar = async (manejador) => {
  const nav = pagina.waitForNavigation({ waitUntil: 'networkidle2', timeout: 45000 }).catch(() => null);
  await manejador();
  await Promise.race([nav, esperar(1500).then(quieta)]);
  await quieta();
};

const clicTexto = (patron, dentroDeModal = false) => clicYEsperar(() => pagina.evaluate((p, modal) => {
  const re = new RegExp(p, 'i');
  const visible = e => !!(e.offsetWidth || e.offsetHeight || e.getClientRects().length);
  const raiz = modal
    ? [...document.querySelectorAll('.rich-mpnl-body')].filter(visible).pop() || document
    : document;
  const a = [...raiz.querySelectorAll('a,input[type=button],input[type=submit],button')]
    .filter(visible).find(e => re.test((e.value || e.textContent || '').trim()));
  if (!a) throw new Error(`No se encontró "${p}" en el portal.`);
  a.click();
}, patron, dentroDeModal));

const textoModales = () => pagina.evaluate(() => [...new Set([...document.querySelectorAll('.rich-mpnl-body')]
  .filter(e => e.offsetHeight > 0).map(e => e.innerText.replace(/\s+/g, ' ').trim()).filter(Boolean))]);

const sesionCaida = () => pagina.evaluate(() => /loginEmpleadores/.test(location.pathname)
  || [...document.querySelectorAll('[id*=messageInvalidSession]')].some(e => e.offsetHeight > 0)).catch(() => true);

const irAlMenu = () => pagina.goto(`${BASE}/view/micuenta/mimenuempleadores.jsf`, { waitUntil: 'networkidle2', timeout: 45000 });

async function irA(submenu) {
  // Siempre desde el menú: recargar una página JSF que vino de un POST lo reenvía.
  const destino = { consultas: 'consultaenviofirma.jsf', inicio: 'iniciorelacionlaboral.jsf' }[submenu];
  await irAlMenu();
  if (await sesionCaida()) {
    estado.etapa = 'vencida';
    estado.mensaje = 'S.O.S. cerró la sesión por inactividad. Hay que volver a iniciarla.';
    throw new Error(estado.mensaje);
  }
  await clicTexto('^Novedades$');
  await clicTexto(submenu === 'consultas' ? '^Consultas y Env' : '^Inicio relaci');
  if (!pagina.url().includes(destino)) throw new Error(`No se pudo abrir ${destino} en S.O.S.`);
}

// ── Login con captcha asistido ────────────────────────────────────────────

const marcoAncla = () => pagina.frames().find(f => /recaptcha\/api2\/anchor/.test(f.url()));

async function captchaResuelto() {
  const ancla = marcoAncla();
  if (!ancla) return false;
  return ancla.$eval('#recaptcha-anchor', e => e.getAttribute('aria-checked') === 'true').catch(() => false);
}

async function cajaReto() {
  const h = await pagina.$('iframe[src*="api2/bframe"]');
  if (!h) return null;
  const caja = await h.boundingBox();
  return caja && caja.height > 100 ? caja : null;
}

async function abrirLogin() {
  estado.etapa = 'abriendo';
  estado.mensaje = 'Abriendo el portal de S.O.S.…';
  await pagina.goto(URL_LOGIN, { waitUntil: 'networkidle2', timeout: 60000 });
  await pagina.waitForSelector(sel('formLogin:loginUsrEMail'), { visible: true, timeout: 30000 });
  await pagina.$eval(sel('formLogin:loginUsrEMail'), (e, v) => { e.value = v; }, usuario);
  await pagina.$eval(sel('formLogin:input_contrasena'), (e, v) => { e.value = v; }, contrasena);
  await marcarCaptcha();
}

async function marcarCaptcha() {
  for (let i = 0; i < 20 && !marcoAncla(); i++) await esperar(500);
  const ancla = marcoAncla();
  if (!ancla) throw new Error('El login de S.O.S. no mostró el captcha.');
  await ancla.waitForSelector('#recaptcha-anchor', { visible: true, timeout: 30000 });
  await esperar(800);
  await ancla.click('#recaptcha-anchor');
  await esperar(3000);
  estado.etapa = 'captcha';
  estado.mensaje = 'Resuelve el captcha: haz clic en las imágenes que pide y luego en Verificar.';
  await revisarCaptcha();
}

let entrando = false;
async function revisarCaptcha() {
  if (estado.etapa !== 'captcha' || entrando) return;
  if (await captchaResuelto()) await entrar();
}

async function entrar() {
  entrando = true;
  estado.etapa = 'entrando';
  estado.mensaje = 'Captcha resuelto. Entrando con la clave del módulo de claves…';
  try {
    await clicYEsperar(() => pagina.click(sel('formLogin:idIngresButton')));
    for (let i = 0; i < 30 && /loginEmpleadores/.test(pagina.url()); i++) await esperar(1000);

    if (/loginEmpleadores/.test(pagina.url())) {
      const aviso = (await textoModales()).join(' ')
        || await pagina.evaluate(() => [...document.querySelectorAll('.rich-message,.error,[class*=mensaje]')]
          .map(e => e.innerText.trim()).filter(Boolean).join(' '));
      if (/contrase|usuario|credencial|incorrect|inv[aá]lid|bloque/i.test(aviso)) {
        estado.etapa = 'rechazada';
        estado.mensaje = aviso.slice(0, 300);
        log('Clave rechazada');
        setTimeout(() => terminar(1), 5000);
        return;
      }
      // Captcha vencido u otro aviso: se vuelve a empezar.
      estado.mensaje = `S.O.S. no dejó entrar (${aviso.slice(0, 150) || 'sin detalle'}). Vuelve a resolver el captcha.`;
      await abrirLogin();
      return;
    }

    estado.etapa = 'lista';
    estado.mensaje = 'Sesión iniciada en S.O.S.';
    ultimoUso = Date.now();
    log('Sesión lista');
  } catch (e) {
    estado.etapa = 'error';
    estado.mensaje = String(e.message || e).slice(0, 300);
  } finally {
    entrando = false;
  }
}

// ── Operaciones ───────────────────────────────────────────────────────────

async function consultar({ tipo = 'CC', documento, desde, hasta }) {
  await irA('consultas');
  await pagina.evaluate((tipo, doc, desde, hasta) => {
    const s = document.getElementById('formGeneral:idTipoIdentificacion');
    const op = [...s.options].find(o => o.text.trim() === tipo);
    if (op) s.value = op.value;
    document.getElementById('formGeneral:idNumeroIdentificacion').value = doc;
    document.getElementById('formGeneral:calendarDesdeInputDate').value = desde;
    document.getElementById('formGeneral:calendarHastaInputDate').value = hasta;
  }, tipo, String(documento), desde, hasta);
  await clicTexto('^Buscar$');
  return filasConsulta(documento);
}

function filasConsulta(documento) {
  return pagina.evaluate((doc) => [...document.querySelectorAll('tr')]
    .filter(r => r.cells.length >= 7 && r.cells.length < 12 && /^\s*\d{6,}\s*$/.test(r.cells[0].innerText)
      && (!doc || r.cells[2].innerText.trim() === String(doc)))
    .map(r => ({
      radicado: r.cells[0].innerText.trim(),
      tipo: r.cells[1].innerText.trim(),
      documento: r.cells[2].innerText.trim(),
      nombre: r.cells[3].innerText.trim(),
      fecha_radicacion: r.cells[4].innerText.trim(),
      causal: r.cells[5].innerText.trim(),
      estado: r.cells[6].innerText.trim(),
      accion: (r.querySelector('a[onclick*="jsfcljs"]')?.getAttribute('onclick')?.match(/\{'([^']+)'/) || [])[1] || null,
    })), documento ? String(documento) : null);
}

async function certificado(datos) {
  const filas = await consultar(datos);
  const fila = filas.find(f => /aprobad/i.test(f.estado)) || filas[0];
  if (!fila?.accion) return { ok: false, error: 'No hay novedad con certificado para ese documento.' };
  const r = await pagina.evaluate(async (accion) => {
    const f = document.forms.formGeneral;
    const fd = new URLSearchParams(new FormData(f));
    fd.set(accion, accion);
    const res = await fetch(f.action, { method: 'POST', body: fd, credentials: 'include' });
    const buf = new Uint8Array(await res.arrayBuffer());
    if (!/pdf/.test(res.headers.get('content-type') || '')) return null;
    let s = '';
    for (let i = 0; i < buf.length; i += 0x8000) s += String.fromCharCode(...buf.subarray(i, i + 0x8000));
    return btoa(s);
  }, fila.accion);
  return r ? { ok: true, radicado: fila.radicado, estado: fila.estado, pdf: r } : { ok: false, error: 'S.O.S. no entregó un PDF.' };
}

async function registrar({ tipoId, documento, ibc, fecha, arl, afp, guardar }) {
  await irA('inicio');

  const precargados = await pagina.evaluate(() => [...document.querySelectorAll('tr')]
    .filter(r => r.cells.length >= 8 && /^\s*\d+\s*$/.test(r.cells[0].innerText) && /\d{5,}/.test(r.cells[2]?.innerText || ''))
    .map(r => r.cells[2].innerText.trim()));
  if (precargados.length) {
    return { ok: false, error: `Hay registros precargados en S.O.S. sin guardar (${precargados.join(', ')}). Bórralos en el portal antes de seguir.` };
  }

  await clicTexto('^\\s*A[ñn]adir registro');
  await pagina.waitForSelector(sel('formGeneral:idIbc'), { visible: true, timeout: 20000 });
  await pagina.evaluate((d) => {
    const set = (id, v) => { const e = document.getElementById(id); e.value = v; e.dispatchEvent(new Event('change', { bubbles: true })); };
    set('formGeneral:idTipoIdentificacion', d.tipoId);
    set('formGeneral:idNumeroIdentificacion', d.documento);
    set('formGeneral:idIbc', d.ibc);
    set('formGeneral:calendarDesdeInputDate', d.fecha);
    set('formGeneral:calendarRetiroInputDate', '');
    set('formGeneral:idArl', d.arl);
    set('formGeneral:idAfp', d.afp);
  }, { tipoId: String(tipoId), documento: String(documento), ibc: String(ibc), fecha, arl, afp });

  await clicTexto('^Cargar datos$', true);
  await esperar(1500);

  const fila = async () => pagina.evaluate((doc) => {
    const r = [...document.querySelectorAll('tr')].find(r => r.cells.length >= 8 && r.cells[2]?.innerText.trim() === doc);
    return r ? { nombre: r.cells[3].innerText.trim(), iconos: [...r.querySelectorAll('img')].map(i => i.title || i.alt) } : null;
  }, String(documento));

  if (!await fila()) {
    const avisos = await pagina.evaluate(() => [...document.querySelectorAll('*')]
      .filter(e => e.children.length === 0 && e.offsetHeight > 0 && /debe|inv[aá]lid|obligatori|no se|error/i.test(e.textContent))
      .map(e => e.textContent.trim()).filter(t => t.length < 300));
    return { ok: false, paso: 'cargar', error: [...new Set(avisos)].join(' ') || 'S.O.S. no aceptó los datos.' };
  }

  await clicYEsperar(() => pagina.click(sel('formGeneral:botonValidar')));
  const validada = await fila();
  if (!validada?.iconos.some(t => /validado/i.test(t))) {
    return { ok: false, paso: 'validar', nombre: validada?.nombre, error: `S.O.S. no validó el registro (${validada?.iconos.join(', ') || 'sin detalle'}).` };
  }
  if (!guardar) return { ok: true, validado: true, nombre: validada.nombre };

  await clicYEsperar(() => pagina.click(sel('formGeneral:botonGuardar')));
  await clicTexto('^Aceptar$', true);
  await esperar(1500);
  const avisos = (await textoModales()).join(' ');
  if (!/guardados exitosamente/i.test(avisos)) {
    return { ok: false, paso: 'guardar', nombre: validada.nombre, error: avisos.slice(0, 300) || 'S.O.S. no confirmó el guardado.' };
  }
  return { ok: true, validado: true, guardado: true, nombre: validada.nombre };
}

async function adjuntar({ tipo, documento, desde, hasta, archivo }) {
  const filas = await consultar({ tipo, documento, desde, hasta });
  const fila = filas.find(f => /cara b/i.test(f.estado));
  if (!fila) return { ok: false, error: 'La novedad no está pendiente del lado B.', filas };

  await clicYEsperar(() => pagina.evaluate((accion) => {
    const a = [...document.querySelectorAll('a[onclick*="jsfcljs"]')].find(e => e.getAttribute('onclick').includes(`'${accion}'`));
    a.click();
  }, fila.accion));

  const input = await pagina.waitForSelector(sel('formGeneral:upload6:file'), { timeout: 20000 });
  await input.uploadFile(archivo);
  await esperar(3000);
  await quieta();
  await clicTexto('^Guardar$', true);
  await esperar(2000);

  const despues = await consultar({ tipo, documento, desde, hasta });
  const ahora = despues.find(f => f.radicado === fila.radicado);
  return { ok: !!ahora && !/cara b/i.test(ahora.estado), radicado: fila.radicado, estado: ahora?.estado, avisos: await textoModales() };
}

// ── Servidor de órdenes ───────────────────────────────────────────────────

let cola = Promise.resolve();
const enCola = (fn) => (cola = cola.then(fn, fn));

const leerCuerpo = async (req) => {
  let d = '';
  for await (const t of req) d += t;
  return d ? JSON.parse(d) : {};
};

const OPERACIONES = { consultar: (d) => consultar(d).then(filas => ({ ok: true, filas })), certificado, registrar, adjuntar };

const servidor = http.createServer(async (req, res) => {
  const responder = (codigo, datos) => { res.writeHead(codigo, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(datos)); };
  if (req.headers['x-token'] !== token) return responder(403, { ok: false, error: 'Token inválido.' });

  try {
    const ruta = req.url.split('?')[0];
    const datos = req.method === 'POST' ? await leerCuerpo(req) : {};

    if (ruta === '/estado') {
      if (estado.etapa === 'captcha') await revisarCaptcha();
      const salida = { ...estado };
      if (estado.etapa === 'captcha') {
        const caja = await cajaReto();
        if (caja) {
          // Sin captureBeyondViewport: puppeteer cambiaba el tamaño de la página para
          // la foto, el reCAPTCHA se redibujaba y borraba las casillas ya marcadas.
          const imagen = await pagina.screenshot({ clip: caja, encoding: 'base64', captureBeyondViewport: false });
          salida.captcha = { imagen, ancho: Math.round(caja.width), alto: Math.round(caja.height) };
        }
      }
      return responder(200, salida);
    }

    if (ruta === '/clic') {
      if (estado.etapa !== 'captcha') return responder(409, { ok: false, error: 'No hay captcha pendiente.' });
      const caja = await cajaReto();
      if (!caja) { await revisarCaptcha(); return responder(200, { ok: true, etapa: estado.etapa }); }
      const x = Math.max(0, Math.min(caja.width, Number(datos.x)));
      const y = Math.max(0, Math.min(caja.height, Number(datos.y)));
      // Para el log: qué hay bajo el clic dentro del reto (casilla, botón…).
      const reto = pagina.frames().find(f => /bframe/.test(f.url()));
      const objetivo = reto ? await reto.evaluate((px, py) => {
        const e = document.elementFromPoint(px, py);
        return e ? `${e.tagName}.${String(e.className).slice(0, 60)}` : null;
      }, x, y).catch(() => null) : null;
      log(`Clic captcha (${Math.round(x)},${Math.round(y)}) → ${objetivo}`);
      await pagina.mouse.click(caja.x + x, caja.y + y, { delay: 60 });
      await esperar(1200);
      await revisarCaptcha();
      return responder(200, { ok: true, etapa: estado.etapa });
    }

    if (ruta === '/captcha/reiniciar') {
      await abrirLogin();
      return responder(200, { ok: true, etapa: estado.etapa });
    }

    if (ruta === '/cerrar') {
      responder(200, { ok: true });
      return terminar(0);
    }

    const op = OPERACIONES[ruta.slice(1)];
    if (!op) return responder(404, { ok: false, error: 'Orden desconocida.' });
    if (estado.etapa !== 'lista') return responder(409, { ok: false, etapa: estado.etapa, error: 'La sesión de S.O.S. no está iniciada.' });

    ultimoUso = Date.now();
    const resultado = await enCola(() => op(datos));
    ultimoUso = Date.now();
    return responder(200, resultado);
  } catch (e) {
    log('Error', e.message);
    return responder(500, { ok: false, etapa: estado.etapa, error: String(e.message || e).slice(0, 400) });
  }
});

servidor.listen(puerto, '127.0.0.1', () => log(`Escuchando en 127.0.0.1:${puerto}`));

// Sin uso por un rato se cierra; mientras tanto, la sesión de S.O.S. se mantiene
// viva visitando el menú cada 4 minutos (cierra por inactividad).
setInterval(async () => {
  if (Date.now() - ultimoUso > INACTIVIDAD_MS) { log('Cerrada por inactividad'); return terminar(0); }
  if (estado.etapa === 'lista') {
    await enCola(async () => {
      await irAlMenu().catch(() => {});
      if (await sesionCaida()) { estado.etapa = 'vencida'; estado.mensaje = 'S.O.S. cerró la sesión. Hay que volver a iniciarla.'; }
    });
  }
}, 4 * 60000);

// Si nadie resuelve el captcha en 10 minutos, no tiene sentido seguir abierto.
setTimeout(() => { if (['abriendo', 'captcha'].includes(estado.etapa)) { log('Captcha sin resolver'); terminar(0); } }, 10 * 60000);

try {
  await abrirLogin();
} catch (e) {
  estado.etapa = 'error';
  estado.mensaje = String(e.message || e).slice(0, 300);
  log('Error al abrir', e.message);
}
