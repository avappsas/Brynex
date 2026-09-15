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
 *
 * Pedidos Sanitas (portal: 'sanitas'):
 *  estado / abrir / estadoAfiliacion   Oficina Virtual de Empleadores (conciliación)
 *  novedadEstado                       → {abierta, listo, error} del formulario web de novedades
 *  novedadAbrir                        → abre (o enfoca) el formulario web de novedades
 *  novedadLlenar {tipoDoc, documento, departamento, municipio, municipioDane, telefonoFijo,
 *                 celular, correo, tipoNovedad, observaciones, archivo, nombreArchivo}
 *                                      → llena y adjunta; el clic en Enviar lo da la persona
 *  novedadResultado {documento}        → {enviado, radicado, texto, errores, captura}
 *
 * Pedidos Boxalud (portal: 'boxalud', Emssanar; datos.host dice cuál):
 *  boxEstado {host}                    → {abierta, sesion, empresa}
 *  boxAbrir {host, usuario, contrasena} → abre el login y deja escrito el usuario (y la clave si llegó)
 *  boxLlenar {…datos del contrato}     → Ingreso de afiliación lleno y documentos adjuntos; ACEPTAR y GUARDAR los pulsa la persona
 *  boxResultado {host, documento}      → {guardado, numero, texto, mensajes}
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
  const directo = ['estado', 'abrir', 'novedadEstado', 'novedadAbrir', 'novedadResultado', 'boxEstado', 'boxAbrir', 'boxResultado'].includes(msg.accion);
  (directo ? atender(msg, origen) : enCola(() => atender(msg, origen)))
    .then(sendResponse)
    .catch(e => sendResponse({ ok: false, error: String(e?.message || e).slice(0, 400) }));
  return true;
});

// Un trámite a la vez: todos usan la misma pestaña del portal.
let cola = Promise.resolve();
const enCola = (fn) => (cola = cola.then(fn, fn));

async function atender({ portal, accion, datos = {} }, origen) {
  if (portal === 'sanitas') return atenderSanitas(accion, datos, origen);
  if (portal === 'boxalud') return atenderBoxalud(accion, datos, origen);
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
      // La columna "Estado" es un ícono (✓ / reloj / X) y la "Causal" trae el texto
      // del estado (Aprobado, No aprobado…). El motivo de una devolución no está en
      // la tabla: sale en la ventana que abre la X (ver pMotivoDevolucion).
      icono: [...r.cells[5].querySelectorAll('img')].map(i => i.title || i.alt || i.src.split('/').pop()).join(' '),
      estado: r.cells[6].innerText.trim(),
      causal: '',
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

  const filas = await ejecutar(tabId, pFilas, [String(documento)]);

  // Para las devueltas se abre la X de cada una y se lee el motivo.
  for (const fila of filas) {
    if (!/no aprobad|incorrect|declinad|devuelt|rechaz/i.test(fila.estado)) continue;
    fila.causal = (await sosMotivoDevolucion(tabId, fila.radicado)) || '';
  }
  return filas;
}

/** Abre la ventana "Motivos de devolución" de la fila y devuelve el texto. */
async function sosMotivoDevolucion(tabId, radicado) {
  const abierto = await ejecutar(tabId, (rad) => {
    const fila = [...document.querySelectorAll('tr')].find(r => r.cells.length >= 7 && r.cells[0].innerText.trim() === rad);
    if (!fila) return false;
    const candidatos = [...fila.cells[5].querySelectorAll('a, img, input, span, div')];
    const clicable = candidatos.find(e => e.getAttribute('onclick') || e.tagName === 'A') || candidatos[0];
    if (!clicable) return false;
    clicable.click();
    return true;
  }, [radicado]).catch(() => false);
  if (!abierto) return null;

  const texto = await esperarQue(tabId, () => {
    const caja = [...document.querySelectorAll('.rich-mpnl-body, .rich-modalpanel, [id*="modalMostrarMotivo"]')]
      .find(e => e.offsetHeight > 0 && /motivos? de devoluci/i.test(e.innerText));
    if (!caja) return null;
    const t = caja.innerText.replace(/\s+/g, ' ')
      .replace(/motivos? de devoluci[oó]n:?/gi, '').replace(/entendido/gi, '').trim();
    return t || null;
  }, [], 12000);

  // Cerrar la ventana para dejar la página como estaba.
  await ejecutar(tabId, () => {
    const b = [...document.querySelectorAll('a, input[type=button], button')]
      .find(e => e.offsetHeight > 0 && /^\s*entendido\s*$/i.test(e.value || e.textContent || ''));
    b?.click();
    return true;
  }).catch(() => {});
  await esperar(600);

  return texto;
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

// ── Sanitas (Oficina Virtual de Empleadores) ──────────────────────────────
// Detrás de Radware: todo se pide desde la propia pestaña de la persona, con
// su sesión, sin navegar por el sitio (navegar mucho dispara el captcha).

const SANITAS_EMPLEADORES = 'https://www.epssanitas.com/usuarios/group/empleadores';
const SANITAS_PORTLET_ESTADO = 'consultarestadosafiliados_WAR_radicacionincapacidadesportlet';

async function pestanaSanitas() {
  const pestanas = await chrome.tabs.query({ url: ['https://www.epssanitas.com/*', 'https://epssanitas.com/*'] });
  // Mejor una que ya esté dentro de la Oficina Virtual.
  return pestanas.find(p => /\/group\/empleadores/.test(p.url || '')) || pestanas[0] || null;
}

async function atenderSanitas(accion, datos, origen) {
  if (accion.startsWith('novedad')) return atenderSanitasNovedad(accion, datos, origen);

  if (accion === 'abrir') {
    const p = await pestanaSanitas();
    if (p) {
      await chrome.tabs.update(p.id, { active: true });
      await chrome.windows.update(p.windowId, { focused: true });
    } else {
      await chrome.tabs.create({ url: `${SANITAS_EMPLEADORES}/inicio`, active: true });
    }
    return { ok: true, abierta: true };
  }

  const pestana = await pestanaSanitas();
  if (!pestana) return { ok: true, abierta: false, sesion: false };

  if (accion === 'estado') {
    return { ok: true, abierta: true, ...(await sanitasSesion(pestana.id)) };
  }

  if (accion === 'estadoAfiliacion') {
    const s = await sanitasSesion(pestana.id);
    if (!s.sesion) throw new Error('La pestaña de Sanitas no tiene la Oficina Virtual de Empleadores abierta. Inicia sesión y vuelve a intentar.');
    const txt = await ejecutar(pestana.id, async (base, portlet) => {
      const u = `${base}/estado-de-afiliacion?p_p_id=${portlet}&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_cacheability=cacheLevelPage&p_p_col_id=column-1&p_p_col_count=1&_${portlet}_tipoReporte=txt&_${portlet}_accion=desrcargarReporte`;
      const r = await fetch(u, { credentials: 'include' });
      const t = new TextDecoder('utf-8').decode(await r.arrayBuffer());
      if (!/numero identificaci/i.test(t.slice(0, 400))) return { __error: 'Sanitas no entregó el Estado de Afiliación (¿se cerró la sesión o salió el captcha?).' };
      return t;
    }, [SANITAS_EMPLEADORES, SANITAS_PORTLET_ESTADO]);
    return { ok: true, nit: s.nit, empresa: s.empresa, txt };
  }

  throw new Error(`Acción de Sanitas desconocida: ${accion}`);
}

/**
 * ¿Hay sesión en la Oficina Virtual? Pide los datos de la empresa como hace la
 * página del estado de afiliación (JSON con nombre y NIT); sin sesión no es JSON.
 * Esa misma llamada deja listo el reporte que luego se descarga.
 */
async function sanitasSesion(tabId) {
  try {
    return await ejecutar(tabId, async (base, portlet) => {
      const u = `${base}/estado-de-afiliacion?p_p_id=${portlet}&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_cacheability=cacheLevelPage&p_p_col_id=column-1&p_p_col_count=1&_${portlet}_accion=consultaDatosEmpresa`;
      const r = await fetch(u, { method: 'POST', credentials: 'include' });
      const t = await r.text();
      let d;
      try { d = JSON.parse(t); } catch { return { sesion: false, captcha: /perfdrive|radware|captcha/i.test(r.url + t.slice(0, 2000)) }; }
      const b = d?.datosBasicos;
      if (!b?.identificacion?.numIdentificacion) return { sesion: false };
      return { sesion: true, empresa: b.nombreCompleto, nit: String(b.identificacion.numIdentificacion) };
    }, [SANITAS_EMPLEADORES, SANITAS_PORTLET_ESTADO]);
  } catch {
    return { sesion: false };
  }
}

// ── Sanitas: formulario web "Novedades a la afiliación" ───────────────────
// Portlet Liferay `radicarnovedades`. Tipo de novedad "Cambio de empleador" =
// 10513. El adjunto lo sube el cargador de Liferay en cuanto se elige el archivo;
// el Enviar lo pulsa la persona y la página se recarga con la respuesta.

const SANITAS_NOVEDADES = 'https://www.epssanitas.com/usuarios/web/nuevo-portal-eps/novedades-afiliacion';
const SANITAS_NS = '_radicarnovedades_WAR_radicarnovedadesportlet_';

async function pestanaNovedades() {
  const pestanas = await chrome.tabs.query({ url: ['https://www.epssanitas.com/*', 'https://epssanitas.com/*', 'https://validate.perfdrive.com/*'] });
  return pestanas.find(p => /novedades-afiliacion/.test(p.url || '')) || pestanas.find(p => /perfdrive/.test(p.url || '')) || null;
}

async function atenderSanitasNovedad(accion, datos, origen) {
  if (accion === 'novedadAbrir') {
    const p = await pestanaNovedades();
    if (p) {
      await chrome.tabs.update(p.id, { active: true });
      await chrome.windows.update(p.windowId, { focused: true });
    } else {
      await chrome.tabs.create({ url: SANITAS_NOVEDADES, active: true });
    }
    return { ok: true, abierta: true };
  }

  const pestana = await pestanaNovedades();
  if (!pestana) return { ok: true, abierta: false, listo: false };
  if (/perfdrive/.test(pestana.url || '')) {
    return { ok: true, abierta: true, listo: false, error: 'Sanitas pide verificar que no eres un robot: resuélvelo en la pestaña de Sanitas.' };
  }

  if (accion === 'novedadEstado') {
    let e;
    try { e = await ejecutar(pestana.id, pNovedadEstado, [SANITAS_NS]); } catch { e = { listo: false }; }
    return { ok: true, abierta: true, ...e };
  }

  if (accion === 'novedadLlenar') return sanitasNovedadLlenar(pestana, datos, origen);

  if (accion === 'novedadResultado') {
    const r = await ejecutar(pestana.id, pNovedadResultado, [SANITAS_NS, String(datos.documento || '')]);
    if (r?.enviado) {
      // La captura solo sale si la pestaña de Sanitas es la que se ve; si no, queda el texto.
      try {
        const actual = await chrome.tabs.get(pestana.id);
        if (actual.active) {
          const url = await chrome.tabs.captureVisibleTab(actual.windowId, { format: 'jpeg', quality: 70 });
          r.captura = url.split(',')[1] || null;
        }
      } catch { /* sin permiso o sin ventana visible: basta con el texto */ }
    }
    return { ok: true, ...r };
  }

  throw new Error(`Acción de Sanitas desconocida: ${accion}`);
}

function pNovedadEstado(ns) {
  const vis = e => !!(e && (e.offsetWidth || e.offsetHeight || e.getClientRects().length));
  const form = document.getElementById(ns + 'fmRadicarNov');
  const errores = ['msg-alert-error-login', 'msg-alert-error-titular', 'msg-alert-error-service', 'msg-alert-error-ajax']
    .map(id => document.getElementById(id)).filter(vis).map(e => e.innerText.trim()).filter(Boolean);
  const tipo = document.getElementById(ns + 'tipoNovedadSelect');
  const listo = !!form && !!tipo && [...tipo.options].some(o => o.value === '10513');
  return {
    listo: listo && !errores.length,
    formulario: !!form,
    error: errores.join(' ') || (form ? (listo ? null : 'El formulario de Sanitas aún no carga los tipos de novedad.') : 'La pestaña no muestra el formulario de novedades de Sanitas.'),
  };
}

async function sanitasNovedadLlenar(pestana, d, origen) {
  const estado = await ejecutar(pestana.id, pNovedadEstado, [SANITAS_NS]);
  if (!estado.listo) return { ok: false, error: estado.error || 'El formulario de Sanitas no está listo.' };

  // El formulario PDF lo genera BryNex; solo se acepta de ese mismo origen.
  const url = new URL(d.archivo, origen);
  if (url.origin !== origen) throw new Error('El formulario no viene de BryNex.');
  const res = await fetch(url, { credentials: 'include' });
  if (!res.ok || !/pdf/.test(res.headers.get('content-type') || '')) {
    let detalle = '';
    try { detalle = (await res.json()).error || ''; } catch { /* no era JSON */ }
    throw new Error(detalle || `BryNex no entregó el formulario (HTTP ${res.status}).`);
  }
  const bytes = new Uint8Array(await res.arrayBuffer());
  let binario = '';
  for (let i = 0; i < bytes.length; i += 0x8000) binario += String.fromCharCode(...bytes.subarray(i, i + 0x8000));

  // 1. Datos del afiliado y departamento (el municipio se carga por AJAX).
  await ejecutar(pestana.id, (ns, d) => {
    const $ = id => document.getElementById(ns + id);
    const poner = (id, v) => { const e = $(id); e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); e.dispatchEvent(new Event('change', { bubbles: true })); };
    poner('documentType', d.tipoDoc);
    window[ns + 'asignarNombreDocumento']?.();
    poner('login', d.documento);
    $('dptoSeleccionado').value = d.departamento;
    window[ns + 'consultarCiudadesAjax']?.();
    return true;
  }, [SANITAS_NS, d]);

  const ciudad = await esperarQue(pestana.id, (ns, d) => {
    const s = document.getElementById(ns + 'citySeleccionado');
    if (!s || s.options.length <= 1) return null;
    const norm = t => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/[^A-Z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
    const op = [...s.options].find(o => o.value && (o.value === d.municipioDane || o.value === d.municipioDane.slice(2) || norm(o.text) === norm(d.municipio)))
      || [...s.options].find(o => o.value && norm(o.text).startsWith(norm(d.municipio)));
    if (!op) return { encontrado: false, opciones: [...s.options].slice(1, 8).map(o => o.value + '=' + o.text.trim()) };
    s.value = op.value;
    s.dispatchEvent(new Event('change', { bubbles: true }));
    return { encontrado: true, valor: op.value, texto: op.text.trim() };
  }, [SANITAS_NS, d], 20000);
  if (!ciudad?.encontrado) {
    return { ok: false, error: `Sanitas no cargó el municipio ${d.municipio}${ciudad?.opciones ? ` (opciones: ${ciudad.opciones.join(', ')})` : ''}.` };
  }

  // 2. Contacto, tipo de novedad y observaciones.
  const requisitos = await ejecutar(pestana.id, (ns, d) => {
    const $ = id => document.getElementById(ns + id);
    const poner = (id, v) => { const e = $(id); e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); e.dispatchEvent(new Event('change', { bubbles: true })); };
    poner('telefonoFijoNumber', d.telefonoFijo);
    poner('celularNumber', d.celular);
    poner('emailAddress', d.correo);
    poner('tipoNovedadSelect', d.tipoNovedad);
    poner('observations', d.observaciones);
    return (document.getElementById('list-documents-requiered')?.innerText || '').trim();
  }, [SANITAS_NS, d]);

  // 3. Adjunto: se entrega al cargador de Liferay, que lo sube de una vez.
  const subido = await ejecutar(pestana.id, (ns, b64, nombre) => {
    const ya = [...document.querySelectorAll(`input[name="${ns}selectUploadedFileCheckbox"]`)].some(c => c.value === nombre);
    if (ya) return 'ya';
    const input = document.querySelector(`#${ns}uploaderContent input[type=file]`) || document.querySelector(`#${ns}fileUpload input[type=file]`);
    if (!input) return { __error: 'No se encontró el cargador de archivos del formulario de Sanitas.' };
    const bin = atob(b64);
    const u8 = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
    const dt = new DataTransfer();
    dt.items.add(new File([u8], nombre, { type: 'application/pdf' }));
    input.files = dt.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    return 'enviado';
  }, [SANITAS_NS, btoa(binario), d.nombreArchivo]);

  const adjunto = subido === 'ya' || await esperarQue(pestana.id, (ns, nombre) => {
    // Liferay puede renombrar el temporal: vale el que lleve el nombre, o el único que haya.
    const todos = [...document.querySelectorAll(`input[name="${ns}selectUploadedFileCheckbox"]`)];
    const base = nombre.replace(/\.pdf$/i, '');
    const c = todos.find(x => x.value === nombre) || todos.find(x => x.value.includes(base)) || (todos.length === 1 ? todos[0] : null);
    if (c && !c.checked) c.click();
    return c?.checked ? true : null;
  }, [SANITAS_NS, d.nombreArchivo], 60000);

  // 4. Deja todo a la vista con Enviar resaltado y marca el trámite en la pestaña.
  await ejecutar(pestana.id, (ns, doc) => {
    sessionStorage.setItem('brynexNovedad', JSON.stringify({ documento: doc, desde: Date.now() }));
    const b = document.getElementById(ns + 'btnSend');
    if (b) { b.style.outline = '3px solid #f59e0b'; b.style.outlineOffset = '3px'; b.scrollIntoView({ block: 'center' }); }
    return true;
  }, [SANITAS_NS, String(d.documento)]);
  await chrome.tabs.update(pestana.id, { active: true });
  await chrome.windows.update(pestana.windowId, { focused: true });

  return {
    ok: true,
    municipio: ciudad.texto,
    requisitos,
    adjunto: !!adjunto,
    aviso: adjunto ? null : 'El formulario quedó lleno pero Sanitas no confirmó el adjunto: adjúntalo a mano antes de Enviar.',
  };
}

/** Después del Enviar: la página se recarga (el documento vuelve vacío) y muestra la respuesta. */
function pNovedadResultado(ns, documento) {
  const vis = e => !!(e && (e.offsetWidth || e.offsetHeight || e.getClientRects().length));
  let marca = null;
  try { marca = JSON.parse(sessionStorage.getItem('brynexNovedad') || 'null'); } catch { /* sin marca */ }
  if (!marca || (documento && marca.documento !== documento)) return { enviado: false, sinTramite: true };

  const login = document.getElementById(ns + 'login');
  const errores = [...document.querySelectorAll('.alert-error, .portlet-msg-error, .form-validator-stack, .help-inline')]
    .filter(vis).map(e => e.innerText.trim()).filter(Boolean);
  // Sigue en el formulario lleno: no han dado Enviar o la validación lo frenó.
  if (login && login.value === marca.documento) return { enviado: false, errores };

  // Sanitas responde en una ventana emergente: "La radicación no. 0014885197 ha sido
  // registrada exitosamente. La respuesta será enviada a: … en los próximos tres días hábiles".
  const limpiar = t => (t || '').replace(/\s+/g, ' ').trim();
  const ventana = [...document.querySelectorAll('.modal, .aui-dialog, .yui3-widget-bd, [role=dialog], .alert, .portlet-msg-success, .portlet-msg-info')]
    .filter(vis).map(e => limpiar(e.innerText)).find(t => /radicaci[oó]n|radicad[oa]/i.test(t)) || '';
  const portlet = document.getElementById('p_p_id' + ns) || document.querySelector('.portlet-body') || document.body;
  // Con la ventana de respuesta basta; sin ella, el texto del portlet (sin las listas del formulario).
  const texto = (ventana || limpiar(portlet.innerText)).slice(0, 6000);
  const buscar = t => t.match(/radicaci[oó]n\s*(?:no\.?|n[°º.]*|n[uú]mero)?\s*:?\s*(\d[\d-]{4,})/i)
    || t.match(/radicad[oa][^0-9]{0,80}?(\d[\d-]{4,})/i) || t.match(/n[uú]mero[^0-9]{0,60}?(\d[\d-]{4,})/i);
  const m = buscar(texto) || buscar(limpiar(document.body.innerText));
  const exito = [...document.querySelectorAll('.alert-success, .portlet-msg-success')].filter(vis).map(e => e.innerText.trim()).join(' ');
  sessionStorage.removeItem('brynexNovedad');
  return { enviado: true, radicado: m ? m[1] : null, texto, exito, errores };
}

// ── Boxalud (Emssanar): Ingreso de afiliación ─────────────────────────────
// ASP.NET + DevExpress. Los controles del formulario son globales del mundo MAIN
// (comboBoxAfiliadoTipoIdentificacion, afiliadoConsultar()…); con su API se
// llenan igual que a mano. Mapeado el 15-sep-2026 sin guardar ninguna afiliación.

const BOXALUD_HOSTS = ['boxalud.emssanareps.co'];
const boxBase = (host) => `https://${host}/Externo/BoxaludExternoNS`;

async function pestanaBoxalud(host) {
  if (!BOXALUD_HOSTS.includes(host)) throw new Error(`Portal Boxalud no permitido: ${host}`);
  const ps = await chrome.tabs.query({ url: `https://${host}/*` });
  return ps.find(p => p.active) || ps[0] || null;
}

function pBoxEstado() {
  const login = !!document.querySelector('[id$="textName_I"]');
  const lineas = (document.body?.innerText || '').split('\n').map(l => l.trim()).filter(Boolean);
  const i = lineas.indexOf('Afiliaciones');
  const empresa = !login && i > 0 && !/^Plan/.test(lineas[i - 1]) ? lineas[i - 1] : null;
  return { sesion: !login && !!empresa, login, empresa, pagina: location.pathname.split('/').pop(), titulo: document.title };
}

async function atenderBoxalud(accion, d, origen) {
  const host = String(d.host || '');
  if (accion === 'boxAbrir') {
    let p = await pestanaBoxalud(host);
    if (p) {
      await chrome.tabs.update(p.id, { active: true });
      await chrome.windows.update(p.windowId, { focused: true });
    } else {
      p = await chrome.tabs.create({ url: `https://${host}/Externo/BoxaludExterno/Seguridad/login.aspx`, active: true });
      await esperarCarga(p.id);
    }
    if (d.usuario) {
      await esperarQue(p.id, (u, c) => {
        const campo = document.querySelector('[id$="textName_I"]');
        if (!campo) return true;              // ya tiene sesión
        const poner = (e, v) => { e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); e.dispatchEvent(new Event('change', { bubbles: true })); };
        poner(campo, u);
        const clave = document.querySelector('[id$="textPassword_I"]');
        if (clave && c) poner(clave, c); else clave?.focus();
        return true;
      }, [String(d.usuario), d.contrasena ? String(d.contrasena) : ''], 15000);
    }
    return { ok: true, abierta: true };
  }

  const pestana = await pestanaBoxalud(host);
  if (!pestana) return { ok: true, abierta: false, sesion: false };

  if (accion === 'boxEstado') {
    let e;
    try { e = await ejecutar(pestana.id, pBoxEstado); } catch { e = { sesion: false }; }
    return { ok: true, abierta: true, ...e };
  }
  if (accion === 'boxLlenar') return boxaludLlenar(pestana, d, origen);
  if (accion === 'boxResultado') return { ok: true, ...(await ejecutar(pestana.id, pBoxResultado, [String(d.documento || '')])) };

  throw new Error(`Acción de Boxalud desconocida: ${accion}`);
}

async function boxaludLlenar(pestana, d, origen) {
  const tab = pestana.id;
  const avisos = [];
  const norm = (t) => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/[^A-Z0-9]/g, '');

  // 0. Sesión y empresa correctas.
  const est = await ejecutar(tab, pBoxEstado);
  if (!est.sesion) return { ok: false, error: 'El portal no tiene la sesión iniciada. Entra con el usuario de la empresa y vuelve a intentar.' };
  if (!norm(est.empresa).includes(norm(d.empresa).slice(0, 12)) && !norm(d.empresa).includes(norm(est.empresa).slice(0, 12))) {
    return { ok: false, error: `El portal está abierto con ${est.empresa}, pero el contrato es de ${d.empresa}. Cierra sesión y entra con el usuario de ${d.empresa}.` };
  }

  // 1. Declaración de la carta de derechos → Diligenciar datos de afiliación.
  await chrome.tabs.update(tab, { url: `${boxBase(d.host)}/Pages/CartaDerechosDeberes.aspx?NA=true` });
  await esperarCarga(tab);
  await clicYEsperar(tab, () => { const b = document.querySelector('[id$="btnSiguiente_I"]'); if (!b) return { __error: 'No apareció la declaración de la carta de derechos.' }; b.click(); return true; });
  const diligenciar = await esperarQue(tab, () => typeof window.afiliadoAdicionar === 'function' && typeof window.dateEditAfiliacionFechaInicial === 'object', [], 30000);
  if (!diligenciar) return { ok: false, error: 'No se abrió "Diligenciar datos de afiliación" en el portal.' };

  // 2. Fecha de inicio y modal del afiliado.
  await ejecutar(tab, (f) => {
    const [a, m, dd] = f.split('-').map(Number);
    dateEditAfiliacionFechaInicial.SetDate(new Date(a, m - 1, dd)); dateEditAfiliacionFechaInicial.RaiseValueChangedEvent?.();
    afiliadoAdicionar();
    return true;
  }, [d.fechaIngreso]);
  const modal = await esperarQue(tab, () => modalDatosAfiliado.IsVisible() && !callbackPanelDatosAfiliado.InCallback() && !loadingPanel.IsVisible(), [], 30000);
  if (!modal) return { ok: false, error: 'No se abrió el formulario del afiliado.' };

  // 3. Documento → el portal valida en ADRES y precarga (hasta ~1 min).
  await ejecutar(tab, (tipo, doc) => {
    comboBoxAfiliadoTipoIdentificacion.SetValue(tipo); comboBoxAfiliadoTipoIdentificacion.RaiseValueChangedEvent?.();
    textBoxAfiliadoNumeroIdentificacion.SetValue(doc);
    afiliadoConsultar(false);
    return true;
  }, [d.tipoDoc, String(d.documento)]);
  await esperar(2000);
  const consulta = await esperarQue(tab, () => {
    const vis = e => !!(e && (e.offsetWidth || e.offsetHeight));
    if (window.ModalRedireccionarNovedadAdicion?.IsVisible?.()) return { estado: 'adicion', texto: ModalRedireccionarNovedadAdicion.GetMainElement().innerText.replace(/\s+/g, ' ').trim() };
    if (callbackPanelDatosAfiliado.InCallback() || loadingPanel.IsVisible()) return null;
    const mensajes = [...document.querySelectorAll('[id*="pupMensaje"], [id*="ucMensajeAplicacion"] .dxpc-content')].filter(vis).map(e => e.innerText.replace(/\s+/g, ' ').trim()).filter(Boolean);
    if (textBoxAfiliadoApellido1.GetValue()) return { estado: 'ok', mensajes };
    return mensajes.length ? { estado: 'mensaje', mensajes } : null;
  }, [], 150000);
  if (!consulta) return { ok: false, error: 'El portal no respondió la validación del afiliado en ADRES.' };
  if (consulta.estado === 'adicion') return { ok: false, adicion: true, error: `El portal pide hacerlo como "Adición de relación laboral": ${consulta.texto}` };
  if (consulta.estado !== 'ok') return { ok: false, error: `El portal no cargó al afiliado: ${(consulta.mensajes || []).join(' ')}` };
  avisos.push(...(consulta.mensajes || []));

  // 4. Que sea la misma persona.
  const apellidoPortal = await ejecutar(tab, () => textBoxAfiliadoApellido1.GetValue());
  if (norm(apellidoPortal) !== norm(d.apellido)) {
    return { ok: false, error: `En el portal el documento es de ${apellidoPortal}, que no coincide con el apellido de BryNex (${d.apellido}). Revisa antes de seguir.` };
  }

  // 5. Contacto y residencia (solo lo vacío; la dirección en nomenclatura DANE).
  const contacto = await ejecutar(tab, (d) => {
    const salida = { direccion: null };
    const caption = (c) => (document.getElementById(c.name)?.closest('.dxflItem, td, div')?.parentElement?.innerText || '').toUpperCase();
    const poner = (c, v) => { if (c && v && !c.GetValue()) { c.SetValue(v); c.RaiseValueChangedEvent?.(); } };
    function normalizar(t) {
      let s = String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase();
      s = s.replace(/\b(N[OO]\.?|NUMERO|NRO\.?)\s*(?=\d)/g, ' ').replace(/[^A-Z0-9 ]/g, ' ');
      const tipos = [[/\b(AVENIDA\s+CALLE|AV\s*CALLE|AC)\b/g, 'AC'], [/\b(AVENIDA\s+CARRERA|AV\s*CARRERA|AK)\b/g, 'AK'], [/\b(CALLE|CLL|CLLE|CL|CALL)\b/g, 'CL'], [/\b(CARRERA|CRA|KRA|KR|CR|CRR|K)\b/g, 'CR'], [/\b(AVENIDA|AVE|AV)\b/g, 'AV'], [/\b(DIAGONAL|DIAG|DG)\b/g, 'DG'], [/\b(TRANSVERSAL|TRANSV|TRV|TV)\b/g, 'TV'], [/\b(AUTOPISTA|AUT)\b/g, 'AUT'], [/\b(CIRCUNVALAR|CRV)\b/g, 'CRV']];
      for (const [re, v] of tipos) s = s.replace(re, v);
      s = s.replace(/(\d)([A-Z])/g, '$1 $2').replace(/([A-Z])(\d)/g, '$1 $2').replace(/\bN\b(?=\s+\d|$)/g, 'NORTE').replace(/\s+/g, ' ').trim();
      const m = s.match(/\b(AC|AK|AV|CL|CR|DG|TV|AUT|CRV)\b.*/);
      return m ? m[0] : s;
    }
    if (!textBoxAfiliadoDireccion.GetValue() && d.direccion) {
      const tok = normalizar(d.direccion).split(' ');
      for (let n = tok.length; n >= 3; n--) { const x = tok.slice(0, n).join(' '); if (validarDireccionRegex(x, 2)) { salida.direccion = x; break; } }
      if (salida.direccion) { textBoxAfiliadoDireccion.SetValue(salida.direccion); try { validarDireccion(textBoxAfiliadoDireccion, 2); } catch {} }
    } else {
      salida.direccion = textBoxAfiliadoDireccion.GetValue();
    }
    const tels = [textBoxAfiliadoTelefono1, textBoxAfiliadoTelefono2, textBoxAfiliadoTelefono3];
    const fijo = tels.find(c => /FIJO/.test(caption(c)));
    const celulares = tels.filter(c => c !== fijo);
    poner(celulares[0], d.celular); poner(celulares[1], d.celular2); poner(fijo, d.fijo);
    poner(textBoxCorreoElectronico1, d.correo);
    if (comboBoxAfiliadoOrientacionSexual.GetValue() == null || String(comboBoxAfiliadoOrientacionSexual.GetValue()) === '-1') {
      comboBoxAfiliadoOrientacionSexual.SetValue(100); comboBoxAfiliadoOrientacionSexual.RaiseValueChangedEvent?.();
    }
    return salida;
  }, [d]);
  if (!contacto.direccion) avisos.push('La dirección de BryNex no tiene formato válido para el portal: escríbela en el campo Dirección (ej. CR 94 1 A 128).');

  // 6. Relación laboral: fecha y tipo de cotizante (recarga la sección), luego el resto.
  await ejecutar(tab, (f) => {
    const [a, m, dd] = f.split('-').map(Number);
    dateEditRelacionLaboralFechaInicial.SetDate(new Date(a, m - 1, dd)); dateEditRelacionLaboralFechaInicial.RaiseValueChangedEvent?.();
    const cb = comboBoxRelacionLaboralTipoCotizante;
    for (let i = 0; i < cb.GetItemCount(); i++) if (String(cb.GetItem(i).value) === '1') cb.SetSelectedIndex(i);
    cb.RaiseValueChangedEvent();
    return true;
  }, [d.fechaIngreso]);
  await esperar(1500);
  await esperarQue(tab, () => !callbackPanelDatosRelacionLaboral.InCallback() && !loadingPanel.IsVisible(), [], 30000);

  const laboral = await ejecutar(tab, (d) => {
    const n = t => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase();
    const elegir = (cb, claves) => {
      for (let i = 0; i < cb.GetItemCount(); i++) { const t = n(cb.GetItem(i).text); if (claves.some(k => t.includes(k))) { cb.SetSelectedIndex(i); cb.RaiseValueChangedEvent?.(); return cb.GetItem(i).text; } }
      return null;
    };
    if (String(comboBoxRelacionLaboralTipoCotizante.GetValue()) !== '1') return { __error: 'El portal no tomó el tipo de cotizante Dependiente.' };
    const afp = n(d.afp), arl = n(d.arl);
    const claveAfp = [['PROTECCION', 'PROTECCI'], ['PORVENIR', 'PORVENIR'], ['COLFONDOS', 'COLFONDOS'], ['COLPENSIONES', 'COLPENSIONES'], ['SKANDIA', 'SKANDIA'], ['OLD MUTUAL', 'SKANDIA']].find(([k]) => afp.includes(k));
    const claveArl = [['SURA', 'SURA'], ['POSITIVA', 'POSITIVA'], ['BOLIVAR', 'BOLIVAR'], ['COLMENA', 'COLMENA'], ['EQUIDAD', 'EQUIDAD'], ['AXA', 'AXA'], ['COLPATRIA', 'AXA'], ['LIBERTY', 'LIBERTY'], ['ALFA', 'ALFA'], ['SANITAS', 'COLSANITAS'], ['MAPFRE', 'MAPFRE']].find(([k]) => arl.includes(k));
    const salida = {
      // Contrato sin pensión (p. ej. pensionado o extranjero): "Sin AFP".
      afp: claveAfp ? elegir(comboBoxRelacionLaboralAFP, [claveAfp[1]]) : (afp.trim() === '' ? elegir(comboBoxRelacionLaboralAFP, ['SIN AFP']) : null),
      arl: claveArl ? elegir(comboBoxRelacionLaboralARL, [claveArl[1]]) : null,
    };
    comboBoxAportanteTipoIdentificacionConsultar.SetValue(1); comboBoxAportanteTipoIdentificacionConsultar.RaiseValueChangedEvent?.();
    textBoxAportanteNumeroIdentificacionConsultar.SetValue(d.nit);
    buttonAportanteConsutar.DoClick();
    return salida;
  }, [d]);
  if (!laboral.afp) avisos.push(`No se encontró la AFP "${d.afp}" en el portal: elígela a mano.`);
  if (!laboral.arl) avisos.push(`No se encontró la ARL "${d.arl}" en el portal: elígela a mano.`);

  await esperar(1500);
  const aportante = await esperarQue(tab, (nit) => {
    if (loadingPanel.IsVisible()) return null;
    const modalTxt = modalDatosAfiliado.GetMainElement().innerText;
    const i = modalTxt.indexOf('Nombre o razón social:');
    if (i < 0) return null;
    const bloque = modalTxt.slice(i, i + 400);
    if (!bloque.includes(nit)) return null;
    const lineas = bloque.split('\n').map(l => l.trim()).filter(Boolean);
    const cartera = (bloque.match(/(\d+)\s*periodos/) || [])[1];
    return { razon: lineas[4] || lineas[1] || '', cartera: cartera ? Number(cartera) : 0 };
  }, [d.nit], 30000);
  if (!aportante) avisos.push('El portal no mostró los datos del aportante: pulsa VALIDAR en la sección del aportante.');
  else if (aportante.cartera > 0) avisos.push(`El portal marca ${aportante.cartera} periodo(s) en mora del aportante.`);

  await ejecutar(tab, (d) => {
    const ts = comboBoxRelacionLaboralTipoSalario;
    for (let i = 0; i < ts.GetItemCount(); i++) if (String(ts.GetItem(i).value) === '2') { ts.SetSelectedIndex(i); ts.RaiseValueChangedEvent?.(); }
    if (!textBoxRelacionLaboralIngresoMensual.GetValue()) { textBoxRelacionLaboralIngresoMensual.SetValue(String(d.salario)); textBoxRelacionLaboralIngresoMensual.RaiseValueChangedEvent?.(); }
    if (!textBoxRelacionLaboralCargo.GetValue()) { textBoxRelacionLaboralCargo.SetValue(d.cargo); textBoxRelacionLaboralCargo.RaiseValueChangedEvent?.(); }
    return true;
  }, [d]);

  // 7. Documentos: formulario firmado y encuesta de la carta de derechos.
  await ejecutar(tab, () => { pageControlDatosAfiliado.SetActiveTabIndex(1); return true; });
  await esperar(2500);
  const adjuntos = [];
  for (const doc of d.documentos || []) {
    const url = new URL(doc.url, origen);
    if (url.origin !== origen) throw new Error('El documento no viene de BryNex.');
    const res = await fetch(url, { credentials: 'include' });
    if (!res.ok || !/pdf/.test(res.headers.get('content-type') || '')) { avisos.push(`BryNex no entregó ${doc.nombre}.`); continue; }
    const bytes = new Uint8Array(await res.arrayBuffer());
    let bin = '';
    for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode(...bytes.subarray(i, i + 0x8000));

    const subido = await ejecutar(tab, (tipo, b64, nombre) => {
      const input = document.querySelector(`input[type=file][id$="Uploader_-1_${tipo}_Pag1_TextBox0_Input"]`);
      if (!input) return { __error: `No se encontró dónde adjuntar el documento ${tipo}.` };
      const raw = atob(b64); const u8 = new Uint8Array(raw.length);
      for (let i = 0; i < raw.length; i++) u8[i] = raw.charCodeAt(i);
      const dt = new DataTransfer(); dt.items.add(new File([u8], nombre, { type: 'application/pdf' }));
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      let ctrl = null;
      ASPx.GetControlCollection().ForEachControl(c => { if (!ctrl && c.name && c.name.endsWith(`Uploader_-1_${tipo}_Pag1`)) ctrl = c; });
      try { if (ctrl && !ctrl.autoStartUpload && typeof ctrl.UploadFile === 'function') ctrl.UploadFile(); } catch { /* sube solo */ }
      return true;
    }, [doc.tipo, btoa(bin), doc.nombre]);
    const listo = subido && await esperarQue(tab, (tipo) => {
      const cont = document.getElementById(`formContenedorAfiliadoDocumentos_${tipo}_0_2`);
      const fila = cont?.closest('tr');
      return fila && !/no ha sido seleccionado/i.test(fila.innerText) ? fila.innerText.replace(/\s+/g, ' ').trim() : null;
    }, [doc.tipo], 60000);
    adjuntos.push({ nombre: doc.nombre, ok: !!listo, estado: listo || 'sin confirmar' });
    if (!listo) avisos.push(`No se confirmó el adjunto ${doc.nombre}: adjúntalo a mano en la pestaña Documentos.`);
  }

  // 8. Todo a la vista: la persona revisa, pulsa ACEPTAR en el afiliado y GUARDAR.
  await ejecutar(tab, (doc) => { sessionStorage.setItem('brynexBoxalud', JSON.stringify({ documento: doc, desde: Date.now() })); return true; }, [String(d.documento)]);
  await chrome.tabs.update(tab, { active: true });
  await chrome.windows.update(pestana.windowId, { focused: true });

  return { ok: true, empresa: est.empresa, direccion: contacto.direccion, afp: laboral.afp, arl: laboral.arl, aportante, adjuntos, avisos };
}

/** Después de GUARDAR: mensajes del portal y, si aparece, el número. */
function pBoxResultado(documento) {
  const vis = e => !!(e && (e.offsetWidth || e.offsetHeight));
  let marca = null;
  try { marca = JSON.parse(sessionStorage.getItem('brynexBoxalud') || 'null'); } catch { /* sin marca */ }
  if (!marca || (documento && marca.documento !== documento)) return { guardado: false, sinTramite: true };

  const mensajes = [...document.querySelectorAll('[id*="pupMensaje"], [id*="ucMensajeAplicacion"] .dxpc-content, .dxpc-content')]
    .filter(vis).map(e => e.innerText.replace(/\s+/g, ' ').trim()).filter(t => t && t.length < 1500);
  const enModal = typeof window.modalDatosAfiliado === 'object' && window.modalDatosAfiliado.IsVisible?.();
  const texto = [...new Set(mensajes)].join(' — ');
  const exito = /(guardad|registrad|radicad|exitos|creada|satisfactori)/i.test(texto);
  const salioDeDiligenciar = !/Diligenciar/i.test(location.pathname);
  if (!exito && (enModal || !salioDeDiligenciar)) return { guardado: false, mensajes };

  const m = texto.match(/(?:radicad[oa]|solicitud|afiliaci[oó]n|n[uú]mero|consecutivo)[^0-9]{0,40}(\d{4,})/i);
  sessionStorage.removeItem('brynexBoxalud');
  return { guardado: true, numero: m ? m[1] : null, texto: texto || document.body.innerText.replace(/\s+/g, ' ').slice(0, 1500), mensajes, url: location.href };
}
