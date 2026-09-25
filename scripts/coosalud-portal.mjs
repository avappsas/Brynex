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
  await pagina.type(campo('textName'), String(usuario), { delay: 45 });
  await pagina.type(campo('textPassword'), String(contrasena), { delay: 45 });

  const navegacion = pagina.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }).catch(() => null);
  await pagina.click(campo('buttonLogin')).catch(() => null);
  await navegacion;
  await esperar(3500);

  const texto = await pagina.evaluate(() => (document.body?.innerText || '').replace(/\s+/g, ' ')).catch(() => '');
  const sigueEnLogin = /ingreso al sistema/i.test(texto) && await pagina.$(campo('textPassword'));

  if (sigueEnLogin) {
    const motivo = (texto.match(/[^.]*(incorrect|inv[aá]lid|bloquead|no existe|errad|intente)[^.]*\.?/i) || [])[0];
    salir({ ok: false, paso: 'login', url: pagina.url(), error: (motivo || 'El portal no pasó del ingreso.').trim().slice(0, 220) });
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
