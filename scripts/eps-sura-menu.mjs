/**
 * Mapa del portal de empleadores de EPS SURA: qué opciones ofrece a una empresa.
 *
 * Exploración pura —entra y lista los enlaces con su destino, no toca nada—,
 * para encontrar dónde publica la cartera y la mora por trabajador.
 *
 * Entrada por stdin: {tipoDocumento, usuario, contrasena, nitEmpresa}
 * Salida por stdout: {ok, url, enlaces: [{texto, destino}], pantalla, error}
 */
import puppeteer from 'puppeteer-core';
import { rutaChrome } from './arl-sura-sesion-comun.mjs';
import { entrarEmpresaEps, esperar, texto } from './eps-sura-sesion-comun.mjs';

const salir = (d) => { console.log(JSON.stringify(d)); process.exit(d.ok ? 0 : 1); };

const leerStdin = async () => {
  let datos = '';
  for await (const t of process.stdin) datos += t;
  return datos.trim();
};

const entrada = JSON.parse((await leerStdin()) || '{}');

const navegador = await puppeteer.launch({
  executablePath: await rutaChrome(),
  headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--window-size=1400,900'],
});

let paso = 'inicio';

try {
  const pagina = await navegador.newPage();
  await pagina.setViewport({ width: 1400, height: 900 });

  paso = 'entrar';
  await entrarEmpresaEps(pagina, entrada);
  await esperar(2500);

  paso = 'leer menú';
  // El menú de JSF cuelga de enlaces y de nodos con onclick; se listan los dos.
  const enlaces = await pagina.evaluate(() => [...document.querySelectorAll('a, [onclick]')]
    .map((e) => ({
      texto: (e.innerText || e.textContent || '').replace(/\s+/g, ' ').trim(),
      destino: (e.getAttribute('href') || e.getAttribute('onclick') || '').replace(/\s+/g, ' ').slice(0, 100),
    }))
    .filter((e) => e.texto && e.texto.length < 70));

  salir({
    ok: true,
    url: pagina.url(),
    titulo: await pagina.title().catch(() => null),
    enlaces,
    pantalla: (await texto(pagina)).replace(/\s+/g, ' ').slice(0, 1500),
  });
} catch (e) {
  salir({ ok: false, paso, error: String(e?.message || e).slice(0, 300) });
} finally {
  await navegador.close().catch(() => null);
}
