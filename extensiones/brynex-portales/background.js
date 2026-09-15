/**
 * BryNex Portales — trámites de afiliación en los portales de las EPS.
 *
 * Por qué una extensión: el login de S.O.S. pide reCAPTCHA y, desde un Chrome
 * automatizado en el servidor, Google no lo deja pasar nunca (sep-2026). Aquí la
 * persona inicia sesión en su propio navegador, como siempre, y BryNex le pide a
 * la extensión que haga el trámite en esa pestaña: consultar, radicar, adjuntar
 * el lado B y bajar el certificado. La extensión nunca ve ni guarda claves.
 *
 * Solo acepta pedidos del puente que corre dentro de BryNex (ver puente.js).
 *
 * Pedidos S.O.S. (portal: 'sos'):
 *  estado                          → {abierta, sesion, usuario, empresa}
 *  abrir                           → abre (o enfoca) la pestaña del login
 *  consultar  {tipo, documento, desde, hasta}                 (DD/MM/AAAA)
 *  certificado {tipo, documento, desde, hasta}                → {pdf: base64}
 *  registrar  {tipoId, documento, ibc, fecha, arl, afp, guardar}
 *  adjuntar   {tipo, documento, desde, hasta, archivo}        (URL de BryNex)
 */

const ORIGENES_BRYNEX = ['https://brynex.co', 'https://www.brynex.co', 'http://localhost:8000'];

const SOS_ORIGEN = 'https://centralaplicaciones.sos.com.co';
const SOS_BASE = `${SOS_ORIGEN}/PortalTransaccionalWeb/paginas/empleadores`;
const SOS_LOGIN = `${SOS_BASE}/Logueo/loginEmpleadores.jsf`;
const SOS_MENU = `${SOS_BASE}/view/micuenta/mimenuempleadores.jsf`;

const esperar = (ms) => new Promise(r => setTimeout(r, ms));

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  const origen = sender.origin || (sender.url ? new URL(sender.url).origin : '');
  if (msg?.canal !== 'brynex-portales' || !ORIGENES_BRYNEX.includes(origen) || sender.id !== chrome.runtime.id) return;

  // Ver el estado o abrir la pestaña no espera a que termine un trámite en curso.
  const directo = ['estado', 'abrir'].includes(msg.accion);
  (directo ? atender(msg, origen) : enCola(() => atender(msg, origen)))
    .then(sendResponse)
    .catch(e => sendResponse({ ok: false, error: String(e?.message || e).slice(0, 400) }));
  return true;
});

// Un trámite a la vez: todos usan la misma pestaña del portal.
let cola = Promise.resolve();
const enCola = (fn) => (cola = cola.then(fn, fn));

async function atender({ portal, accion, datos = {} }, origen) {
  if (portal !== 'sos') throw new Error(`Portal desconocido: ${portal}`);

  if (accion === 'estado') return sosEstado();
  if (accion === 'abrir') return sosAbrir(datos);

  const pestana = await pestanaSos();
  if (!pestana) throw new Error('No hay una pestaña de S.O.S. abierta. Pulsa "Abrir S.O.S." e inicia sesión.');
  const estado = await leerCabecera(pestana.id);
  if (!estado.sesion) throw new Error('La pestaña de S.O.S. no tiene la sesión iniciada. Inicia sesión en ella y vuelve a intentar.');

  switch (accion) {
    case 'consultar':   return { ok: true, empresa: estado.empresa, filas: await sosConsultar(pestana.id, datos) };
    case 'certificado': return sosCertificado(pestana.id, datos);
    case 'registrar':   return sosRegistrar(pestana.id, datos);
    case 'adjuntar':    return sosAdjuntar(pestana.id, datos, origen);
    default: throw new Error(`Acción desconocida: ${accion}`);
  }
}

// ── Pestaña y ejecución en la página ─────────────────────────────────────

async function pestanaSos() {
  const pestanas = await chrome.tabs.query({ url: `${SOS_ORIGEN}/*` });
  return pestanas[0] || null;
}

/** Ejecuta `func` en la página (mundo principal, con sus variables JSF). */
async function ejecutar(tabId, func, args = []) {
  const [r] = await chrome.scripting.executeScript({ target: { tabId }, func, args, world: 'MAIN' });
  if (r?.result?.__error) throw new Error(r.result.__error);
  return r?.result;
}

/** Espera a que la página termine de cargar. */
function esperarCarga(tabId, timeout = 45000) {
  return new Promise((resolve) => {
    const fin = () => { chrome.tabs.onUpdated.removeListener(oyente); clearTimeout(reloj); resolve(); };
    const oyente = (id, info) => { if (id === tabId && info.status === 'complete') fin(); };
    chrome.tabs.onUpdated.addListener(oyente);
    const reloj = setTimeout(fin, timeout);
  });
}

/** Clic que puede enviar el formulario (navegación) o hacer AJAX (A4J). */
async function clicYEsperar(tabId, func, args = []) {
  let navego = false;
  const inicio = new Promise((resolve) => {
    const oyente = (id, info) => {
      if (id === tabId && info.status === 'loading') { navego = true; chrome.tabs.onUpdated.removeListener(oyente); resolve(); }
    };
    chrome.tabs.onUpdated.addListener(oyente);
    setTimeout(() => { chrome.tabs.onUpdated.removeListener(oyente); resolve(); }, 2500);
  });
  let resultado;
  try {
    resultado = await ejecutar(tabId, func, args);
  } catch (e) {
    // La navegación puede destruir el marco antes de que el script responda.
    if (!/Frame|context|removed|navigat/i.test(String(e?.message))) throw e;
  }
  await inicio;
  if (navego) await esperarCarga(tabId);
  else await esperar(1200);
  return resultado;
}

/** Repite `func` en la página hasta que devuelva algo verdadero. */
async function esperarQue(tabId, func, args = [], timeout = 30000) {
  const limite = Date.now() + timeout;
  while (Date.now() < limite) {
    try {
      const r = await ejecutar(tabId, func, args);
      if (r) return r;
    } catch { /* la página está cambiando */ }
    await esperar(500);
  }
  return null;
}

// ── Funciones que corren dentro de la página de S.O.S. ────────────────────
// Deben ser autocontenidas: se serializan y se inyectan.

function pClicTexto(patron, dentroDeModal) {
  const re = new RegExp(patron, 'i');
  const visible = e => !!(e.offsetWidth || e.offsetHeight || e.getClientRects().length);
  const raiz = dentroDeModal
    ? [...document.querySelectorAll('.rich-mpnl-body')].filter(visible).pop() || document
    : document;
  const a = [...raiz.querySelectorAll('a,input[type=button],input[type=submit],button')]
    .filter(visible).find(e => re.test((e.value || e.textContent || '').trim()));
  if (!a) return { __error: `No se encontró "${patron}" en el portal de S.O.S.` };
  a.click();
  return true;
}

function pCabecera() {
  const texto = document.body?.innerText || '';
  const m = texto.match(/Bienvenido:\s*([^\n]+)\s*\n+\s*([^\n]+)\s*\n+\s*Cerrar sesi/i);
  const caida = [...document.querySelectorAll('[id*=messageInvalidSession]')].some(e => e.offsetHeight > 0);
  return {
    sesion: !!m && !caida && !/loginEmpleadores/.test(location.pathname),
    usuario: m ? m[1].trim() : null,
    empresa: m ? m[2].trim() : null,
    pagina: location.pathname.split('/').pop(),
  };
}

function pTextoModales() {
  return [...new Set([...document.querySelectorAll('.rich-mpnl-body')]
    .filter(e => e.offsetHeight > 0).map(e => e.innerText.replace(/\s+/g, ' ').trim()).filter(Boolean))].join(' ');
}

function pFilas(doc) {
  return [...document.querySelectorAll('tr')]
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
    }));
}

// ── S.O.S. ────────────────────────────────────────────────────────────────

async function leerCabecera(tabId) {
  try {
    return await ejecutar(tabId, pCabecera);
  } catch {
    return { sesion: false };
  }
}

async function sosEstado() {
  const pestana = await pestanaSos();
  if (!pestana) return { ok: true, abierta: false, sesion: false };
  return { ok: true, abierta: true, ...(await leerCabecera(pestana.id)) };
}

async function sosAbrir({ usuario, contrasena } = {}) {
  let pestana = await pestanaSos();
  if (pestana) {
    await chrome.tabs.update(pestana.id, { active: true });
    await chrome.windows.update(pestana.windowId, { focused: true });
  } else {
    pestana = await chrome.tabs.create({ url: SOS_LOGIN, active: true });
    await esperarCarga(pestana.id);
  }

  // Deja escritos usuario y clave del módulo de claves (la clave solo si BryNex la
  // mandó: depende del permiso de la persona). No se guardan; el captcha y el
  // botón Ingresar los hace la persona.
  if (usuario) {
    await esperarQue(pestana.id, (u, c) => {
      const campo = document.getElementById('formLogin:loginUsrEMail');
      if (!campo) return false;
      const poner = (e, v) => {
        e.value = v;
        e.dispatchEvent(new Event('input', { bubbles: true }));
        e.dispatchEvent(new Event('change', { bubbles: true }));
      };
      if (!campo.value || /correo/i.test(campo.value)) poner(campo, u);
      const clave = document.getElementById('formLogin:input_contrasena');
      if (clave && c) {
        // S.O.S. lo tiene como input de texto y lo enmascara con JS: se deja tipo password.
        clave.type = 'password';
        poner(clave, c);
      } else {
        clave?.focus();
      }
      return true;
    }, [String(usuario), contrasena ? String(contrasena) : ''], 15000);
  }
  return { ok: true, abierta: true };
}

async function irA(tabId, submenu) {
  const destino = { consultas: 'consultaenviofirma.jsf', inicio: 'iniciorelacionlaboral.jsf' }[submenu];
  // Siempre desde el menú: recargar una página JSF que vino de un POST la reenvía.
  await chrome.tabs.update(tabId, { url: SOS_MENU });
  await esperarCarga(tabId);
  const cab = await leerCabecera(tabId);
  if (!cab.sesion) throw new Error('S.O.S. cerró la sesión. Inicia sesión otra vez en la pestaña de S.O.S.');

  await clicYEsperar(tabId, pClicTexto, ['^Novedades$', false]);
  await clicYEsperar(tabId, pClicTexto, [submenu === 'consultas' ? '^Consultas y Env' : '^Inicio relaci', false]);
  const pagina = await ejecutar(tabId, () => location.pathname);
  if (!pagina.includes(destino)) throw new Error(`No se pudo abrir ${destino} en S.O.S.`);
}

async function sosConsultar(tabId, { tipo = 'CC', documento, desde, hasta }) {
  await irA(tabId, 'consultas');

  await ejecutar(tabId, (tipo, doc, desde, hasta) => {
    const s = document.getElementById('formGeneral:idTipoIdentificacion');
    const op = [...s.options].find(o => o.text.trim() === tipo);
    if (op) s.value = op.value;
    document.getElementById('formGeneral:idNumeroIdentificacion').value = doc;
    document.getElementById('formGeneral:calendarDesdeInputDate').value = desde;
    document.getElementById('formGeneral:calendarHastaInputDate').value = hasta;
    // Marca para saber cuándo A4J volvió a pintar los resultados.
    document.querySelectorAll('table').forEach(t => { if (/Causal/.test(t.textContent)) t.dataset.brynexViejo = '1'; });
    return true;
  }, [tipo, String(documento), desde, hasta]);

  await ejecutar(tabId, pClicTexto, ['^Buscar$', false]);
  await esperarQue(tabId, () => ![...document.querySelectorAll('table')].some(t => t.dataset.brynexViejo === '1'), [], 25000);
  await esperar(500);

  return ejecutar(tabId, pFilas, [String(documento)]);
}

async function sosCertificado(tabId, filtro) {
  const filas = await sosConsultar(tabId, filtro);
  const fila = filas.find(f => /aprobad/i.test(f.estado) && !/no aprobad/i.test(f.estado)) || filas[0];
  if (!fila?.accion) return { ok: false, error: 'No hay novedad con certificado para ese documento.' };

  const pdf = await ejecutar(tabId, async (accion) => {
    const f = document.forms.formGeneral;
    const fd = new URLSearchParams(new FormData(f));
    fd.set(accion, accion);
    const res = await fetch(f.action, { method: 'POST', body: fd, credentials: 'include' });
    if (!/pdf/.test(res.headers.get('content-type') || '')) return null;
    const buf = new Uint8Array(await res.arrayBuffer());
    let s = '';
    for (let i = 0; i < buf.length; i += 0x8000) s += String.fromCharCode(...buf.subarray(i, i + 0x8000));
    return btoa(s);
  }, [fila.accion]);

  return pdf ? { ok: true, radicado: fila.radicado, estado: fila.estado, pdf } : { ok: false, error: 'S.O.S. no entregó un PDF.' };
}

async function sosRegistrar(tabId, { tipoId, documento, ibc, fecha, arl, afp, guardar }) {
  await irA(tabId, 'inicio');
  const doc = String(documento);

  const precargados = await ejecutar(tabId, () => [...document.querySelectorAll('tr')]
    .filter(r => r.cells.length >= 8 && /^\s*\d+\s*$/.test(r.cells[0].innerText) && /\d{5,}/.test(r.cells[2]?.innerText || ''))
    .map(r => r.cells[2].innerText.trim()));
  if (precargados.length) {
    return { ok: false, error: `Hay registros precargados en S.O.S. sin guardar (${precargados.join(', ')}). Bórralos en el portal antes de seguir.` };
  }

  await clicYEsperar(tabId, pClicTexto, ['^\\s*A[ñn]adir registro', false]);
  const abierto = await esperarQue(tabId, () => {
    const e = document.getElementById('formGeneral:idIbc');
    return !!(e && e.offsetHeight > 0);
  }, [], 20000);
  if (!abierto) return { ok: false, paso: 'abrir', error: 'No se abrió el formulario "Añadir registro" en S.O.S.' };

  await ejecutar(tabId, (d) => {
    const set = (id, v) => { const e = document.getElementById(id); e.value = v; e.dispatchEvent(new Event('change', { bubbles: true })); };
    set('formGeneral:idTipoIdentificacion', d.tipoId);
    set('formGeneral:idNumeroIdentificacion', d.documento);
    set('formGeneral:idIbc', d.ibc);
    set('formGeneral:calendarDesdeInputDate', d.fecha);
    set('formGeneral:calendarRetiroInputDate', '');
    set('formGeneral:idArl', d.arl);
    set('formGeneral:idAfp', d.afp);
    return true;
  }, [{ tipoId: String(tipoId), documento: doc, ibc: String(ibc), fecha, arl, afp }]);

  await ejecutar(tabId, pClicTexto, ['^Cargar datos$', true]);

  const filaCargada = (d) => {
    const r = [...document.querySelectorAll('tr')].find(r => r.cells.length >= 8 && r.cells[2]?.innerText.trim() === d);
    return r ? { nombre: r.cells[3].innerText.trim(), iconos: [...r.querySelectorAll('img')].map(i => i.title || i.alt) } : null;
  };
  const avisos = () => {
    const t = [...document.querySelectorAll('*')]
      .filter(e => e.children.length === 0 && e.offsetHeight > 0 && /debe|inv[aá]lid|obligatori|no se|error/i.test(e.textContent))
      .map(e => e.textContent.trim()).filter(x => x.length < 300);
    return t.length ? [...new Set(t)].join(' ') : null;
  };

  const cargado = await esperarQue(tabId, (d) => {
    const r = [...document.querySelectorAll('tr')].find(r => r.cells.length >= 8 && r.cells[2]?.innerText.trim() === d);
    if (r) return 'fila';
    const aviso = [...document.querySelectorAll('*')].some(e => e.children.length === 0 && e.offsetHeight > 0 && /debe estar|inv[aá]lid|obligatori/i.test(e.textContent));
    return aviso ? 'aviso' : null;
  }, [doc], 25000);

  if (cargado !== 'fila') {
    return { ok: false, paso: 'cargar', error: (await ejecutar(tabId, avisos)) || 'S.O.S. no aceptó los datos.' };
  }

  await clicYEsperar(tabId, () => { document.getElementById('formGeneral:botonValidar').click(); return true; });
  const validada = await esperarQue(tabId, filaCargada, [doc], 20000);
  if (!validada?.iconos.some(t => /validado/i.test(t))) {
    return { ok: false, paso: 'validar', nombre: validada?.nombre, error: `S.O.S. no validó el registro (${validada?.iconos.join(', ') || 'sin detalle'}).` };
  }
  if (!guardar) return { ok: true, validado: true, nombre: validada.nombre };

  await clicYEsperar(tabId, () => { document.getElementById('formGeneral:botonGuardar').click(); return true; });
  const confirmar = await esperarQue(tabId, () => /deseas guardar/i.test([...document.querySelectorAll('.rich-mpnl-body')].filter(e => e.offsetHeight > 0).map(e => e.innerText).join(' ')), [], 20000);
  if (!confirmar) return { ok: false, paso: 'guardar', nombre: validada.nombre, error: 'S.O.S. no pidió confirmar el guardado.' };

  await clicYEsperar(tabId, pClicTexto, ['^Aceptar$', true]);
  const texto = await esperarQue(tabId, () => {
    const t = [...document.querySelectorAll('.rich-mpnl-body')].filter(e => e.offsetHeight > 0).map(e => e.innerText).join(' ');
    return /guardados exitosamente|error|no se pudo/i.test(t) ? t.replace(/\s+/g, ' ') : null;
  }, [], 30000);

  if (!/guardados exitosamente/i.test(texto || '')) {
    return { ok: false, paso: 'guardar', nombre: validada.nombre, error: (texto || 'S.O.S. no confirmó el guardado.').slice(0, 300) };
  }
  return { ok: true, validado: true, guardado: true, nombre: validada.nombre };
}

async function sosAdjuntar(tabId, { tipo, documento, desde, hasta, archivo }, origen) {
  // El lado B lo genera BryNex; solo se acepta de ese mismo origen.
  const url = new URL(archivo, origen);
  if (url.origin !== origen) throw new Error('El archivo del lado B no viene de BryNex.');
  const res = await fetch(url, { credentials: 'include' });
  if (!res.ok || !/image\/(png|jpe?g)/.test(res.headers.get('content-type') || '')) {
    throw new Error(`BryNex no entregó la imagen del lado B (HTTP ${res.status}).`);
  }
  const bytes = new Uint8Array(await res.arrayBuffer());
  let binario = '';
  for (let i = 0; i < bytes.length; i += 0x8000) binario += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
  const tipoMime = res.headers.get('content-type').split(';')[0];
  const base64 = btoa(binario);

  const filas = await sosConsultar(tabId, { tipo, documento, desde, hasta });
  const fila = filas.find(f => /cara b/i.test(f.estado));
  if (!fila) return { ok: false, error: 'La novedad no está pendiente del lado B.', filas };

  await clicYEsperar(tabId, (accion) => {
    const a = [...document.querySelectorAll('a[onclick*="jsfcljs"]')].find(e => e.getAttribute('onclick').includes(`'${accion}'`));
    if (!a) return { __error: 'No se encontró la acción de adjuntar en la fila.' };
    a.click();
    return true;
  }, [fila.accion]);

  const listo = await esperarQue(tabId, () => !!document.getElementById('formGeneral:upload6:file'), [], 20000);
  if (!listo) return { ok: false, error: 'No se abrió la ventana de adjuntar en S.O.S.' };

  await ejecutar(tabId, (b64, mime, nombre) => {
    const bin = atob(b64);
    const u8 = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
    const dt = new DataTransfer();
    dt.items.add(new File([u8], nombre, { type: mime }));
    const input = document.getElementById('formGeneral:upload6:file');
    input.files = dt.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }, [base64, tipoMime, `lado_b_${documento}.${tipoMime.includes('png') ? 'png' : 'jpg'}`]);

  // La subida es inmediata (RichFaces); al terminar se habilita Guardar.
  await esperar(4000);
  await clicYEsperar(tabId, pClicTexto, ['^Guardar$', true]);
  await esperar(1500);

  const despues = await sosConsultar(tabId, { tipo, documento, desde, hasta });
  const ahora = despues.find(f => f.radicado === fila.radicado);
  return { ok: !!ahora && !/cara b/i.test(ahora.estado), radicado: fila.radicado, estado: ahora?.estado, novedad: ahora || null };
}
