/**
 * Los trabajadores que Comfenalco Valle reporta morosos o inexactos.
 *
 * Es el equivalente de los bloqueos de subsidio de Comfandi, pero mucho más
 * barato: la Sucursal Virtual tiene «Trabajadores Morosos y con Inexactitud
 * **Por Empresa**», así que una sola consulta cubre la nómina entera en vez de
 * entrar trabajador por trabajador.
 *
 * Por eso, cuando la consulta responde, **todos los candidatos quedan
 * revisados**: quien no aparezca en las tablas es que ya no tiene el problema, y
 * su tarea se puede cerrar.
 *
 * El portal es jQuery con `ejecutarAjax("CmndX")` contra `ServiciosWebRyA-Back`,
 * y el login vive en AuthComfe —correo y contraseña, sin código ni imagen que
 * descifrar—, así que la corrida nocturna entra sola.
 *
 * No es un script para correr a mano: lo invoca ComfenalcoSubsidiosHeadless.
 *
 *   echo '{"usuario":"empresa@correo.com","contrasena":"…"}' \
 *     | node scripts/comfenalco-subsidios.mjs
 *
 * Imprime {ok, empresa, movimientos[], sucursales, error}. Las credenciales
 * entran por stdin y nunca se escriben en el log.
 */
import puppeteer from 'puppeteer-core';

const HOST = 'https://virtual.comfenalcovalle.com.co';
const BASE = `${HOST}/ServiciosWebRyA`;
const LOGIN = 'https://authcomfeempresasprod.web.app/login?app_id=comfenalco.sucursalvirtual.empresas.app&tipo=E';

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

const usuario = String(entrada.usuario || '').trim();
const contrasena = String(entrada.contrasena || '');

if (!usuario || !contrasena) salir({ ok: false, error: 'Faltan usuario o contraseña.' });

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
  headless: entrada.visible ? false : 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-blink-features=AutomationControlled', '--window-size=1400,900'],
});

let pagina;
try {
  pagina = await navegador.newPage();
  await pagina.setViewport({ width: 1400, height: 900 });

  // ── Entrar ────────────────────────────────────────────────────────────────
  await pagina.goto(LOGIN, { waitUntil: 'networkidle2', timeout: 60000 });

  const campos = await insistir(pagina, () =>
    !!document.querySelector('input[type=password]') &&
    !!document.querySelector('input[name=email], input[type=text], input[type=email]'), [], 30000);

  if (!campos) salir({ ok: false, error: 'No apareció el formulario de acceso de Comfenalco.' });

  // Se teclea de verdad: AuthComfe es un formulario de Angular que valida con
  // los eventos del teclado, y con el valor puesto por script daba el campo por
  // vacío —dejaba el botón sin habilitar y el acceso no salía de esta pantalla—.
  const campoUsuario = await pagina.$('input[name=email], input[type=email]')
    ?? await pagina.$('input[type=text]');
  const campoClave = await pagina.$('input[type=password]');

  if (!campoUsuario || !campoClave) salir({ ok: false, error: 'El formulario de Comfenalco no tiene los campos esperados.' });

  await campoUsuario.click({ clickCount: 3 });
  await campoUsuario.type(usuario, { delay: 40 });
  await campoClave.click({ clickCount: 3 });
  await campoClave.type(contrasena, { delay: 40 });

  await esperar(800);

  let pulsado = false;

  for (const b of await pagina.$$('button,input[type=submit]')) {
    const texto = await b.evaluate(e => (e.innerText || e.value || '')).catch(() => '');
    if (!/iniciar sesi/i.test(texto)) continue;

    const apagado = await b.evaluate(e => e.disabled === true).catch(() => false);
    if (apagado) break;

    await b.click().catch(() => null);
    pulsado = true;
    break;
  }

  if (!pulsado) salir({ ok: false, error: 'El botón de acceso de Comfenalco no se dejó pulsar (¿quedó deshabilitado?).' });

  // La señal de estar dentro es el `usuario` del localStorage del portal, no la
  // pantalla de AuthComfe, que es una aplicación aparte.
  const limite = Date.now() + 90000;
  let dentro = false;

  while (Date.now() < limite && !dentro) {
    await esperar(2000);
    dentro = await pagina.evaluate(() =>
      /comfenalcovalle/.test(location.host) && !!localStorage.getItem('usuario')).catch(() => false);
  }

  if (!dentro) {
    const pantalla = await pagina.evaluate(() => ({
      donde: location.host + location.pathname,
      texto: (document.body.innerText || '').replace(/\s+/g, ' ').slice(0, 250),
    })).catch(() => null);

    salir({
      ok: false,
      error: /incorrect|inv[aá]lid|no existe/i.test(pantalla?.texto || '')
        ? 'Comfenalco rechazó el usuario o la clave guardada en BryNex.'
        : 'No se llegó a abrir la sesión de Comfenalco.'
          + (pantalla ? ` Quedó en ${pantalla.donde}: "${pantalla.texto}"` : ''),
    });
  }

  // ── La consulta, una por sucursal ─────────────────────────────────────────
  await pagina.goto(`${BASE}/consultaTrabajadorMoroso.html`, { waitUntil: 'networkidle2', timeout: 60000 });

  const listo = await insistir(pagina, () => !!document.getElementById('cmbSucursalEmpresa'), [], 30000);
  if (!listo) salir({ ok: false, error: 'No cargó la consulta de trabajadores morosos.' });

  await esperar(2000);

  const empresa = await pagina.evaluate(() => {
    try { return (JSON.parse(localStorage.getItem('empresa') || 'null') || {}).razonSocial || null; } catch { return null; }
  }).catch(() => null);

  const sucursales = await pagina.evaluate(() =>
    [...document.querySelectorAll('#cmbSucursalEmpresa option')]
      .map(o => ({ valor: o.value, nombre: (o.text || '').trim() }))
      .filter(o => o.valor && o.valor !== '-1')).catch(() => []);

  if (!sucursales.length) salir({ ok: false, error: 'La empresa no tiene sucursales en Comfenalco.' });

  const movimientos = [];

  for (const sucursal of sucursales) {
    await pagina.evaluate((v) => {
      const s = document.getElementById('cmbSucursalEmpresa');
      s.value = v;
      // Los combos del portal son Chosen: sin avisarle, el que manda sigue
      // mostrando lo de antes y la consulta sale con la sucursal vieja.
      if (window.$) $(s).trigger('change').trigger('chosen:updated');
      else s.dispatchEvent(new Event('change', { bubbles: true }));
    }, sucursal.valor);

    await esperar(800);

    for (const b of await pagina.$$('button')) {
      const texto = await b.evaluate(e => (e.innerText || '')).catch(() => '');
      if (/^\s*consultar\s*$/i.test(texto.trim())) { await b.click().catch(() => null); break; }
    }

    await esperar(6000);

    const filas = await pagina.evaluate(() => {
      const leer = (id, clase) => [...document.querySelectorAll(`#${id} tbody tr`)]
        .map(r => [...r.querySelectorAll('td')].map(c => c.innerText.trim()))
        .filter(c => c.length >= 4)
        .map(c => ({ periodo: c[0], clase: c[1], documento: c[2], nombre: c[3], valor: c[4] || '0', origen: clase }));

      return [...leer('tablaMorosidad', 'mora'), ...leer('tablaInexactitud', 'inexactitud')];
    }).catch(() => []);

    filas.forEach(f => movimientos.push({ ...f, sucursal: sucursal.valor }));

    // El "Atención: no hay información" tapa la pantalla y deja la siguiente
    // consulta sin poder pulsarse.
    await pagina.evaluate(() => {
      [...document.querySelectorAll('button,a')]
        .filter(e => /^\s*cerrar\s*$/i.test((e.innerText || '').trim()))
        .forEach(e => e.click());
    }).catch(() => null);

    await esperar(800);
  }

  salir({ ok: true, empresa, sucursales: sucursales.length, movimientos });
} catch (e) {
  const donde = await pagina?.evaluate(() => location.host + location.pathname).catch(() => '');
  salir({ ok: false, error: String(e?.message || e).slice(0, 400), url: donde });
} finally {
  await navegador.close().catch(() => {});
}
