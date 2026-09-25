/**
 * Mapa del portal de ARL SURA: qué opciones ofrece a una empresa.
 *
 * Exploración pura —entra, lista los enlaces y sale—, para encontrar dónde
 * publica la cartera y la mora. Mira los dos mundos del portal: la Sucursal
 * Virtual (Angular, con los controles en shadow DOM) y el legacy de arpsura,
 * que es donde viven los trámites.
 *
 * Entrada por stdin: {tipoDocumento, usuario, contrasena, nitEmpresa}
 * Salida por stdout: {ok, sve: [...], legacy: [...], pantalla, error}
 */
import puppeteer from 'puppeteer-core';
import { iniciarSesion, rutaChrome } from './arl-sura-sesion-comun.mjs';

const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const salir = (d) => { console.log(JSON.stringify(d)); process.exit(d.ok ? 0 : 1); };

const leerStdin = async () => {
  let datos = '';
  for await (const t of process.stdin) datos += t;
  return datos.trim();
};

const entrada = JSON.parse((await leerStdin()) || '{}');

const ejecutable = await (async () => {
  const { access } = await import('node:fs/promises');
  for (const r of rutaChrome()) { try { await access(r); return r; } catch {} }
  return null;
})();
if (!ejecutable) salir({ ok: false, error: 'No se encontró Chrome. Define CHROME_PATH.' });

const navegador = await puppeteer.launch({
  executablePath: ejecutable,
  headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--window-size=1400,900'],
});

let paso = 'inicio';

try {
  const pagina = await navegador.newPage();
  await pagina.setViewport({ width: 1400, height: 900 });

  paso = 'entrar';
  await iniciarSesion(pagina, entrada);
  await esperar(2500);

  // Los enlaces del legacy, que es donde el portal tiene los trámites.
  paso = 'leer el legacy';
  const legacy = await pagina.evaluate(() => [...document.querySelectorAll('a, [onclick]')]
    .map((e) => ({
      texto: (e.innerText || e.textContent || '').replace(/\s+/g, ' ').trim(),
      destino: (e.getAttribute('href') || e.getAttribute('onclick') || '').replace(/\s+/g, ' ').slice(0, 90),
    }))
    .filter((e) => e.texto && e.texto.length < 70));

  const pantallaLegacy = await pagina.evaluate(() => (document.body?.innerText || '').replace(/\s+/g, ' ').slice(0, 1200)).catch(() => '');

  // Y los de la Sucursal Virtual, que están dentro de shadow roots.
  paso = 'leer la sucursal virtual';
  await pagina.goto('https://sucursalempresas.suramericana.com/', { waitUntil: 'networkidle2', timeout: 60000 }).catch(() => null);
  await esperar(4000);

  const sve = await pagina.evaluate(() => {
    const salida = [];
    const recorrer = (raiz, hondo = 0) => {
      if (hondo > 6) return;
      for (const e of raiz.querySelectorAll('a, button, [role=menuitem], [role=link]')) {
        const texto = (e.innerText || e.textContent || '').replace(/\s+/g, ' ').trim();
        if (texto && texto.length < 70) salida.push({ texto, destino: (e.getAttribute('href') || '').slice(0, 90) });
      }
      for (const e of raiz.querySelectorAll('*')) {
        if (e.shadowRoot) recorrer(e.shadowRoot, hondo + 1);
      }
    };
    recorrer(document);

    return salida;
  }).catch(() => []);

  salir({
    ok: true,
    url: pagina.url(),
    legacy,
    sve,
    pantalla: pantallaLegacy,
    sve_pantalla: await pagina.evaluate(() => (document.body?.innerText || '').replace(/\s+/g, ' ').slice(0, 800)).catch(() => ''),
  });
} catch (e) {
  salir({ ok: false, paso, error: String(e?.message || e).slice(0, 300) });
} finally {
  await navegador.close().catch(() => null);
}
