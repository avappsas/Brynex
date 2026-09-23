/**
 * Los bloqueos de subsidio monetario que Comfandi reporta de unos trabajadores.
 *
 * Es el mismo recorrido que hace la extensión BryNex Portales en el navegador
 * de la persona, pero con un Chrome propio, para que la revisión pueda correr
 * de noche sin nadie delante. El portal no tiene API: es un Next.js con Server
 * Actions, así que todo se opera por pantalla, igual que en Colmena.
 *
 * Tres detalles del portal mandan sobre el código:
 *
 *  - El **tipo de documento** del login es un typeahead de PatternFly: la lista
 *    vive oculta en el DOM y sólo se despliega con el foco. Con "CC" el portal
 *    contesta "Documento o contraseña incorrectos" aunque la clave sea buena.
 *  - El **2FA** son dos pantallas: la primera sólo ofrece "Continuar" y el
 *    "Omitir por ahora" está en la del código QR. Se omite a propósito: con
 *    Authenticator haría falta un código del teléfono en cada corrida.
 *  - Los **filtros del portal** son react-select, donde el input va vacío
 *    aunque haya valor y el menú no se abre con un clic: se abre con la flecha
 *    abajo y se pulsa la opción.
 *
 * No es un script para correr a mano: lo invoca ComfandiSubsidiosHeadless.
 *
 *   echo '{"usuario":"9016037383","contrasena":"…","documentos":["1144132276"]}' \
 *     | node scripts/comfandi-subsidios.mjs
 *
 * Imprime en stdout {ok, nit, empresa, movimientos[], revisados[], errores[]}.
 * Las credenciales entran por stdin y nunca se escriben en el log.
 */
import puppeteer from 'puppeteer-core';

const HOST = 'https://afiliaciones.sucursalcomfandi.com';
const BASE = `${HOST}/sakaar`;

const CHROME_CANDIDATOS = [
  process.env.CHROME_PATH,
  '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  '/usr/bin/google-chrome-stable',
  '/usr/bin/google-chrome',
  '/usr/bin/chromium-browser',
  '/usr/bin/chromium',
].filter(Boolean);

const esperar = (ms) => new Promise(r => setTimeout(r, ms));
const salir = (data) => { console.log(JSON.stringify(data)); process.exit(data.ok ? 0 : 1); };

const leerStdin = async () => {
  let datos = '';
  for await (const trozo of process.stdin) datos += trozo;
  return datos.trim();
};

/**
 * Reintenta una comprobación hasta que devuelva algo verdadero.
 *
 * El portal repinta sin avisar —Next.js— y una espera por selector no alcanza:
 * lo que hace falta es volver a intentar la acción entera.
 */
const insistir = async (pagina, fn, args = [], limite = 25000) => {
  const hasta = Date.now() + limite;
  while (Date.now() < hasta) {
    try {
      const r = await pagina.evaluate(fn, ...args);
      if (r) return r;
    } catch { /* la página está cambiando */ }
    await esperar(500);
  }
  return null;
};

let entrada;
try {
  entrada = JSON.parse(process.argv[2] || await leerStdin() || '{}');
} catch {
  salir({ ok: false, error: 'Entrada JSON inválida.' });
}

const usuario = String(entrada.usuario || '').replace(/\D/g, '');
const contrasena = String(entrada.contrasena || '');
const documentos = (entrada.documentos || []).map(d => String(d).replace(/\D/g, '')).filter(Boolean);
const meses = Math.max(1, Math.min(12, parseInt(entrada.meses) || 4));

if (!usuario || !contrasena) salir({ ok: false, error: 'Faltan usuario o contraseña.' });
if (!documentos.length) salir({ ok: false, error: 'No llegó ninguna cédula para consultar.' });

const ejecutable = await (async () => {
  const { access } = await import('node:fs/promises');
  for (const ruta of CHROME_CANDIDATOS) {
    try { await access(ruta); return ruta; } catch {}
  }
  return null;
})();

if (!ejecutable) salir({ ok: false, error: 'No se encontró Chrome. Instálalo o define CHROME_PATH.' });

const navegador = await puppeteer.launch({
  executablePath: ejecutable,
  headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-blink-features=AutomationControlled', '--window-size=1400,900'],
});

/** La empresa en la que está la sesión, o null si aún no se ha entrado. */
const empresaAbierta = (pagina) => pagina.evaluate(() => {
  const m = (document.body.innerText || '').match(/actualmente est[aá]s en:\s*\n+\s*([^\n]+)/i);
  return m && !/\/guest/.test(location.pathname) ? m[1].trim() : null;
});

/** Elige una opción de un react-select del portal. */
const elegirCombo = async (pagina, opcion, cual = 0) => {
  const estado = await insistir(pagina, (texto, i) => {
    const combo = [...document.querySelectorAll('input[role=combobox]')][i];
    if (!combo) return false;
    if (new RegExp('^\\s*' + texto, 'i').test(combo.closest('[class*=control]')?.innerText || '')) return 'ya';
    combo.focus();
    combo.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, cancelable: true, key: 'ArrowDown', code: 'ArrowDown', keyCode: 40, which: 40 }));
    return 'abierto';
  }, [opcion, cual], 15000);

  if (!estado) return false;
  if (estado === 'ya') return true;

  await esperar(600);

  return !!await insistir(pagina, (texto) => {
    const o = [...document.querySelectorAll('[class*=option]')].find(e => new RegExp('^\\s*' + texto, 'i').test(e.innerText || ''));
    if (!o) return false;
    ['pointerdown', 'mousedown', 'mouseup', 'click'].forEach(t => o.dispatchEvent(new MouseEvent(t, { bubbles: true, cancelable: true, view: window })));
    return true;
  }, [opcion], 10000);
};

/** Los bloqueos de un trabajador, o null si no se pudo llegar a su pantalla. */
const bloqueosDe = async (pagina, documento) => {
  await pagina.goto(`${BASE}/workers`, { waitUntil: 'networkidle2', timeout: 60000 });
  await esperar(2000);

  // Sin tipo de documento el portal no filtra: saca la lista entera paginada y
  // el trabajador podría no estar en la primera página.
  if (!await elegirCombo(pagina, 'C[ée]dula de Ciudadan')) return null;

  const buscado = await insistir(pagina, (doc) => {
    const num = [...document.querySelectorAll('input')].find(i => /documento del trabajador/i.test(i.placeholder || ''));
    if (!num) return false;
    const set = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    set.call(num, doc);
    num.dispatchEvent(new Event('input', { bubbles: true }));
    const btn = [...document.querySelectorAll('button')].find(b => /^\s*Buscar\s*$/i.test(b.innerText));
    if (!btn) return false;
    ['pointerdown', 'mousedown', 'mouseup', 'click'].forEach(t => btn.dispatchEvent(new MouseEvent(t, { bubbles: true, cancelable: true, view: window })));
    return true;
  }, [documento], 25000);

  if (!buscado) return null;

  const entro = await insistir(pagina, (doc) => {
    const fila = [...document.querySelectorAll('tbody tr')].find(r => r.innerText.replace(/\D/g, '').includes(doc));
    const b = fila?.querySelector('button');
    if (!b) return false;
    b.click();
    return true;
  }, [documento], 20000);

  if (!entro) return null;

  const abrio = await insistir(pagina, () => {
    const e = [...document.querySelectorAll('button,div,span')].filter(x => x.children.length === 0)
      .find(x => /^\s*Subsidio monetario\s*$/i.test(x.innerText || ''));
    if (!e) return false;
    const c = e.closest('button') || e;
    ['pointerdown', 'mousedown', 'mouseup', 'click'].forEach(t => c.dispatchEvent(new MouseEvent(t, { bubbles: true, cancelable: true, view: window })));
    return true;
  }, [], 20000);

  if (!abrio) return null;

  const listo = await insistir(pagina, () =>
    [...document.querySelectorAll('input')].some(i => /fecha inicial/i.test(i.placeholder || '')), [], 25000);

  if (!listo) return null;

  if (!await elegirCombo(pagina, 'Bloqueos de subsidio')) return null;

  await esperar(600);

  // La fecha inicial es un react-datepicker: se abre con un clic, se retrocede
  // con su flecha y se pulsa el día 1. Escribirle el texto no sirve.
  await pagina.evaluate(async (n) => {
    const espera = ms => new Promise(r => setTimeout(r, ms));
    const campo = [...document.querySelectorAll('input')].find(i => /fecha inicial/i.test(i.placeholder || ''));
    if (!campo) return false;
    campo.focus();
    ['pointerdown', 'mousedown', 'mouseup', 'click'].forEach(t => campo.dispatchEvent(new MouseEvent(t, { bubbles: true, cancelable: true, view: window })));
    await espera(600);
    for (let i = 0; i < n; i++) {
      document.querySelector('.react-datepicker__navigation--previous')?.click();
      await espera(250);
    }
    [...document.querySelectorAll('.react-datepicker__day:not(.react-datepicker__day--outside-month)')]
      .find(e => e.innerText.trim() === '1')?.click();
    return true;
  }, meses).catch(() => null);

  await esperar(600);

  const respondio = await insistir(pagina, () => {
    const btn = [...document.querySelectorAll('button')].find(b => /^\s*Buscar\s*$/i.test(b.innerText));
    if (!btn) return false;
    ['pointerdown', 'mousedown', 'mouseup', 'click'].forEach(t => btn.dispatchEvent(new MouseEvent(t, { bubbles: true, cancelable: true, view: window })));
    return true;
  }, [], 20000);

  if (!respondio) return null;

  // "No se encontraron movimientos" también es una respuesta: significa que ese
  // trabajador no tiene bloqueos, y su tarea se puede cerrar.
  const filas = await insistir(pagina, () => {
    const texto = document.body.innerText || '';
    if (/no se encontraron movimientos/i.test(texto)) return { vacio: true, filas: [] };

    const bloques = texto.split(/(?=Bloqueo de Subsidio Monetario)/i).filter(b => /Periodo de bloqueo/i.test(b));
    if (!bloques.length) return false;

    const vistos = new Set();
    const filas = [];

    for (const b of bloques) {
      const periodo = (b.match(/Periodo de bloqueo:\s*([^\n|]+)/i) || [])[1]?.trim() || '';
      const fecha = (b.match(/Fecha de Bloqueo:\s*([^\n|]+)/i) || [])[1]?.trim() || '';
      const motivo = (b.match(/Motivo de Bloqueo:\s*([^\n|]+)/i) || [])[1]?.trim() || '';
      const valor = (b.match(/-?\$\s*([\d.,]+)/) || [])[1] || '0';
      const llave = periodo + '|' + fecha + '|' + motivo;
      if (vistos.has(llave)) continue;
      vistos.add(llave);
      filas.push({ tipo: 'BLOQUEO', periodo, fecha, motivo, valor });
    }

    return { vacio: false, filas };
  }, [], 30000);

  return filas ? filas.filas : null;
};

let pagina;
try {
  pagina = await navegador.newPage();
  await pagina.setViewport({ width: 1400, height: 900 });

  // ── Entrar ────────────────────────────────────────────────────────────────
  await pagina.goto(`${BASE}/guest`, { waitUntil: 'networkidle2', timeout: 60000 });

  await insistir(pagina, () => {
    const b = [...document.querySelectorAll('button,a')].find(e => /iniciar sesi/i.test(e.innerText || ''));
    if (!b) return false;
    b.click();
    return true;
  }, [], 20000);

  await insistir(pagina, () =>
    !!document.querySelector('input[name=password]') && !!document.querySelector('input[name=identification_type_up]'), [], 30000);

  // El tipo de documento: con "CC" el portal rechaza la clave buena.
  await insistir(pagina, () => {
    const oculto = document.querySelector('input[name=identification_type_up]');
    if (!oculto) return false;
    if (oculto.value === 'NIT') return true;
    const caja = [...document.querySelectorAll('input')].find(e => /tipo de documento/i.test(e.placeholder || ''));
    if (!caja) return false;
    caja.focus();
    const set = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    set.call(caja, 'NIT');
    caja.dispatchEvent(new Event('input', { bubbles: true }));
    return false;
  }, [], 20000);

  await insistir(pagina, () => {
    if (document.querySelector('input[name=identification_type_up]')?.value === 'NIT') return true;
    const li = [...document.querySelectorAll('li')].filter(e => e.offsetParent && /^\s*NIT\b/i.test(e.innerText || ''))[0];
    if (!li) return false;
    const destino = li.querySelector('button,a,span') || li;
    ['pointerdown', 'mousedown', 'mouseup', 'click'].forEach(t => destino.dispatchEvent(new MouseEvent(t, { bubbles: true, cancelable: true, view: window })));
    return false;
  }, [], 20000);

  const tipoOk = await insistir(pagina, () =>
    document.querySelector('input[name=identification_type_up]')?.value === 'NIT', [], 8000);

  if (!tipoOk) salir({ ok: false, error: 'No se pudo escoger "NIT" en Tipo de documento: el portal cambió el formulario.' });

  await insistir(pagina, (u, c) => {
    const set = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    const num = document.querySelector('input[name=identificationNumber]');
    const clave = document.querySelector('input[name=password]');
    const btn = document.querySelector('input[name=login]');
    if (!num || !clave || !btn) return false;
    set.call(num, u);
    num.dispatchEvent(new Event('input', { bubbles: true }));
    num.dispatchEvent(new Event('change', { bubbles: true }));
    set.call(clave, c);
    clave.dispatchEvent(new Event('input', { bubbles: true }));
    clave.dispatchEvent(new Event('change', { bubbles: true }));
    btn.click();
    return true;
  }, [usuario, contrasena], 20000);

  // Las pantallas que el portal encadena después de Entrar, hasta quedar dentro.
  const hasta = Date.now() + 150000;
  let empresa = null;

  while (Date.now() < hasta && !empresa) {
    await esperar(1500);

    const paso = await pagina.evaluate((nit) => {
      const golpe = (e) => ['pointerdown', 'mousedown', 'mouseup', 'click']
        .forEach(t => e.dispatchEvent(new MouseEvent(t, { bubbles: true, cancelable: true, view: window })));
      const texto = document.body.innerText || '';

      if (/documento o contrase|credenciales inv[aá]lidas|usuario o contrase/i.test(texto)) return { fin: 'clave' };

      const dentro = texto.match(/actualmente est[aá]s en:\s*\n+\s*([^\n]+)/i);
      if (dentro && !/\/guest/.test(location.pathname)) return { fin: 'dentro', empresa: dentro[1].trim() };

      const botones = [...document.querySelectorAll('button,a,input[type=submit],[role=button]')]
        .filter(e => (e.innerText || e.value || '').trim());

      const omitir = botones.find(e => /omitir|m[aá]s tarde|ahora no|despu[eé]s/i.test(e.innerText || e.value || ''));
      if (omitir) { golpe(omitir); return { paso: '2fa-omitir' }; }

      const continuar = botones.find(e => /^\s*continuar\s*$/i.test((e.innerText || e.value || '').trim()));
      if (continuar) { golpe(continuar); return { paso: '2fa-continuar' }; }

      const empresas = [...document.querySelectorAll('button,[role=button],li,div[class*=card]')]
        .filter(e => {
          const t = (e.innerText || '').trim();
          return t && t.length < 200 && t.replace(/\D/g, '').includes(nit.slice(0, 9));
        });
      if (empresas.length) { golpe(empresas[empresas.length - 1]); return { paso: 'empresa' }; }

      return { paso: 'esperando' };
    }, usuario).catch(() => ({ paso: 'cargando' }));

    if (paso?.fin === 'clave') salir({ ok: false, error: 'Comfandi rechazó el usuario o la clave guardada en BryNex.' });
    if (paso?.fin === 'dentro') empresa = paso.empresa;
  }

  if (!empresa) empresa = await empresaAbierta(pagina);

  if (!empresa) {
    const donde = await pagina.evaluate(() => location.host + location.pathname).catch(() => '');
    salir({ ok: false, error: 'Se entró al portal pero no se llegó a la empresa.', url: donde });
  }

  // ── Recorrer a los trabajadores ───────────────────────────────────────────
  const movimientos = [];
  const revisados = [];
  const errores = [];

  for (const documento of documentos) {
    try {
      const filas = await bloqueosDe(pagina, documento);
      if (filas === null) {
        errores.push({ documento, error: 'No se pudo abrir su subsidio monetario.' });
        continue;
      }
      revisados.push(documento);
      filas.forEach(f => movimientos.push({ ...f, documento }));
    } catch (e) {
      errores.push({ documento, error: String(e?.message || e).slice(0, 150) });
    }
  }

  salir({ ok: true, nit: usuario, empresa, movimientos, revisados, errores });
} catch (e) {
  const donde = await pagina?.evaluate(() => location.host + location.pathname).catch(() => '');
  salir({ ok: false, error: String(e?.message || e).slice(0, 400), url: donde });
} finally {
  await navegador.close().catch(() => {});
}
