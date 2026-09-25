/**
 * Portal de empleadores de Coosalud (`sinergia.coosalud.com`, Boxalud).
 *
 * ASP.NET con DevExpress y un reCAPTCHA **invisible** en el login: no hay nada
 * que resolver, el portal puntúa el comportamiento y decide. Por eso se entra
 * con un Chrome de verdad —en el servidor, con Xvfb— y con las credenciales de
 * la empresa; si el portal no quiere, lo dice y se acabó.
 *
 * Es el mismo Boxalud que usa Emssanar, así que lo que se aprenda aquí sirve
 * para las dos.
 *
 * Entrada por stdin: {usuario, contrasena, modo?: 'menu'|'pantalla', opcion?}
 * Salida por stdout: {ok, url, enlaces, pantalla, error}
 */
import puppeteer from 'puppeteer-core';
import { rutaChrome } from './arl-sura-sesion-comun.mjs';

const LOGIN = 'https://sinergia.coosalud.com/Externo/BoxaludExterno/Seguridad/Login.aspx';

const esperar = (ms) => new Promise((r) => setTimeout(r, ms));
const salir = (d) => { console.log(JSON.stringify(d)); process.exit(d.ok ? 0 : 1); };

const leerStdin = async () => {
  let datos = '';
  for await (const t of process.stdin) datos += t;
  return datos.trim();
};

const entrada = JSON.parse((await leerStdin()) || '{}');
const { usuario, contrasena } = entrada;
if (!usuario || !contrasena) salir({ ok: false, error: 'Faltan usuario o contraseña.' });

const ejecutable = await (async () => {
  const { access } = await import('node:fs/promises');
  for (const r of rutaChrome()) { try { await access(r); return r; } catch {} }
  return null;
})();
if (!ejecutable) salir({ ok: false, error: 'No se encontró Chrome. Define CHROME_PATH.' });

const navegador = await puppeteer.launch({
  executablePath: ejecutable,
  headless: entrada.visible ? false : 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--window-size=1400,900'],
});

let paso = 'inicio';

try {
  const pagina = await navegador.newPage();
  await pagina.setViewport({ width: 1400, height: 900 });
  await pagina.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');

  paso = 'abrir el portal';
  await pagina.goto(LOGIN, { waitUntil: 'networkidle2', timeout: 60000 });

  paso = 'ingresar';
  const campo = (sufijo) => `#ctl00_ContentPlaceHolder1_ASPxFormLayout1_${sufijo}_I`;
  await pagina.waitForSelector(campo('textName'), { visible: true, timeout: 30000 });

  // Los cuadros de DevExpress guardan el valor aparte: lo tecleado en el input
  // visible no cuenta hasta que el control se entera, y el portal respondía
  // "Debe ingresar el nombre de usuario" con el campo lleno en pantalla.
  await pagina.type(campo('textName'), String(usuario), { delay: 45 });
  await pagina.type(campo('textPassword'), String(contrasena), { delay: 45 });

  await pagina.evaluate((u, c) => {
    const poner = (control, valor) => {
      const cliente = window[`ctl00_ContentPlaceHolder1_ASPxFormLayout1_${control}`];
      if (cliente && typeof cliente.SetValue === 'function') cliente.SetValue(valor);
    };
    poner('textName', u);
    poner('textPassword', c);
  }, String(usuario), String(contrasena)).catch(() => null);

  // DevExpress no se entera de un clic a secas en el input interno: el control
  // vive en JavaScript y hay que hablarle a él. Se prueban las tres formas, de
  // la más limpia a la más bruta, porque sin esto la pantalla se queda igual y
  // parece que la clave está mal.
  await pagina.keyboard.press('Tab').catch(() => null); // que registre el valor
  await esperar(600);

  const navegacion = pagina.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }).catch(() => null);

  const pulsado = await pagina.evaluate(() => {
    const cliente = window.ctl00_ContentPlaceHolder1_ASPxFormLayout1_buttonLogin;
    if (cliente && typeof cliente.DoClick === 'function') { cliente.DoClick(); return 'DoClick'; }

    const contenedor = document.getElementById('ctl00_ContentPlaceHolder1_ASPxFormLayout1_buttonLogin');
    if (contenedor) { contenedor.click(); return 'contenedor'; }

    return null;
  }).catch(() => null);

  if (!pulsado) {
    await pagina.focus(campo('textPassword')).catch(() => null);
    await pagina.keyboard.press('Enter').catch(() => null);
  }

  await navegacion;
  await esperar(4000);

  const texto = await pagina.evaluate(() => (document.body?.innerText || '').replace(/\s+/g, ' ')).catch(() => '');
  const sigueEnLogin = !! await pagina.$(campo('textPassword'));

  if (sigueEnLogin) {
    const motivo = (texto.match(/[^.]*(incorrect|inv[aá]lid|bloquead|no existe|errad|intente|captcha|verifi)[^.]*\.?/i) || [])[0];

    // Sin la pantalla no se distingue una clave mala de un botón que no llegó a
    // pulsarse, y son arreglos opuestos.
    salir({
      ok: false, paso: 'login', url: pagina.url(),
      error: (motivo || 'El portal no pasó del ingreso.').trim().slice(0, 220),
      pantalla: texto.slice(0, 900),
      pulsado,
      avisos: await pagina.evaluate(() => [...document.querySelectorAll('[class*=error], [class*=Error], [id*=error], [class*=alert]')]
        .map((e) => (e.innerText || '').replace(/\s+/g, ' ').trim()).filter(Boolean).slice(0, 5)).catch(() => []),
    });
  }

  // ── Los afiliados que Coosalud tiene de la empresa ──────────────────
  if (entrada.modo === 'afiliados') {
    paso = 'abrir la consulta';

    // Las páginas no aceptan que se entre directo: hay que pasar por el
    // redirector del portal, que es lo que hace su propio menú.
    const destino = await pagina.evaluate(() => {
      const m = window.ASPxMenuModulos;
      try { return m.GetItem(0).GetItem(0).GetItem(1).GetNavigateUrl(); } catch (e) { return null; }
    });

    await pagina.goto(
      destino || 'https://sinergia.coosalud.com/Externo/BoxaludExterno/Redireccionar?pagina=https://sinergia.coosalud.com/Externo/BoxaludExternoNS/Consulta%2fConsultaAfiliaciones.aspx',
      { waitUntil: 'networkidle2', timeout: 60000 },
    );
    await esperar(3000);

    paso = 'consultar';
    // "Radicada en" viene en "Hoy" y por eso la consulta sale vacía.
    const listo = await pagina.evaluate(() => {
      if (window.cbFiltro2 && window.cbFiltro2.SetValue) { window.cbFiltro2.SetValue('0'); window.cbFiltro2.SetText('Todos'); }

      for (const k in window) {
        if (/buttonConsultar$/.test(k) && window[k] && window[k].DoClick) { window[k].DoClick(); return true; }
      }

      return false;
    });

    if (!listo) throw new Error('No apareció el botón Consultar de la pantalla de afiliaciones.');

    // El grid responde por callback: se espera a que aparezcan filas.
    for (let i = 0; i < 40; i++) {
      await esperar(2000);
      const hay = await pagina.evaluate(() => document.querySelectorAll('tr.dxgvDataRow, tr[id*=DXDataRow]').length > 0
        || /no se encontraron resultados/i.test(document.body.innerText || '')).catch(() => false);
      if (hay) break;
    }

    paso = 'leer el grid';
    const leerPagina = () => pagina.evaluate(() => {
      const tabla = document.querySelector('table[id*=gv], table.dxgvTable') || document.querySelector('table');
      if (!tabla) return { columnas: [], filas: [] };

      return {
        columnas: [...tabla.querySelectorAll('td.dxgvHeader, th')].map((c) => (c.innerText || '').replace(/\s+/g, ' ').trim()).filter(Boolean),
        filas: [...tabla.querySelectorAll('tr.dxgvDataRow, tr[id*=DXDataRow]')]
          .map((f) => [...f.querySelectorAll('td')].map((c) => (c.innerText || '').replace(/\s+/g, ' ').trim()))
          .filter((f) => f.join('').trim() !== ''),
      };
    });

    const vistas = new Set();
    let columnas = [];
    const filas = [];

    for (let pag = 0; pag < 80; pag++) {
      const actual = await leerPagina();
      if (!columnas.length) columnas = actual.columnas;

      const antes = vistas.size;
      for (const f of actual.filas) {
        const clave = f.join('|');
        if (!vistas.has(clave)) { vistas.add(clave); filas.push(f); }
      }

      if (vistas.size === antes) break;

      // El grid de DevExpress pasa de página por su API.
      const avanzo = await pagina.evaluate(() => {
        for (const k in window) {
          const g = window[k];
          if (g && typeof g === 'object' && typeof g.NextPage === 'function' && typeof g.GetPageIndex === 'function') {
            const antes = g.GetPageIndex();
            g.NextPage();

            return antes;
          }
        }

        return null;
      }).catch(() => null);

      if (avanzo === null) break;
      await esperar(2500);
    }

    salir({ ok: true, modo: 'afiliados', columnas, filas });
  }

  paso = 'leer el menú';
  const enlaces = await pagina.evaluate(() => [...document.querySelectorAll('a, [onclick]')]
    .map((e) => ({
      texto: (e.innerText || e.textContent || '').replace(/\s+/g, ' ').trim(),
      destino: (e.getAttribute('href') || e.getAttribute('onclick') || '').replace(/\s+/g, ' ').slice(0, 90),
    }))
    .filter((e) => e.texto && e.texto.length < 70));

  salir({
    ok: true,
    url: pagina.url(),
    titulo: await pagina.title().catch(() => null),
    enlaces,
    pantalla: texto.slice(0, 1500),
  });
} catch (e) {
  salir({ ok: false, paso, error: String(e?.message || e).slice(0, 300) });
} finally {
  await navegador.close().catch(() => null);
}
