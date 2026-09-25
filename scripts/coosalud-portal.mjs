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
