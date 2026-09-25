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

// rutaChrome() da candidatos, no una ruta: el del Mac y los del servidor.
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
  await entrarEmpresaEps(pagina, entrada);
  await esperar(2500);

  // Con `opcion`, en vez del menú se abre esa pantalla y se describe: qué
  // filtros pide y qué columnas trae. Sigue sin tocar nada.
  if (entrada.opcion) {
    paso = `abrir ${entrada.opcion}`;

    const destino = await pagina.evaluate((patron) => {
      const re = new RegExp(patron, 'i');
      const a = [...document.querySelectorAll('a')].find((x) => re.test((x.innerText || '').trim()));
      return a ? a.getAttribute('href') : null;
    }, entrada.opcion);

    if (!destino) throw new Error(`El menú no tiene la opción ${entrada.opcion}.`);

    await pagina.goto(new URL(destino, pagina.url()).href, { waitUntil: 'networkidle2', timeout: 60000 });
    await esperar(3000);

    const campos = await pagina.evaluate(() => [...document.querySelectorAll('input, select, textarea')]
      .filter((e) => e.type !== 'hidden')
      .map((e) => ({
        etiqueta: e.tagName.toLowerCase(),
        id: e.id || null,
        tipo: e.getAttribute('type') || null,
        valor: (e.value || '').slice(0, 30) || null,
        opciones: e.tagName === 'SELECT' ? [...e.options].slice(0, 16).map((o) => `${o.value}=${o.text}`.slice(0, 44)) : undefined,
      })));

    const tablas = await pagina.evaluate(() => [...document.querySelectorAll('table')]
      .map((t) => ({
        columnas: [...t.querySelectorAll('th')].map((c) => (c.innerText || '').replace(/\s+/g, ' ').trim()).filter(Boolean),
        filas: t.querySelectorAll('tbody tr').length,
      }))
      .filter((t) => t.columnas.length));

    salir({
      ok: true, opcion: entrada.opcion, url: pagina.url(),
      campos, tablas, pantalla: (await texto(pagina)).replace(/\s+/g, ' ').slice(0, 1800),
    });
  }

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
