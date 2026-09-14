/**
 * Baja el "Informe de Afiliados a EPS Sura" detallado de una empresa: todos sus
 * cotizantes, con tipo de cotizante, fecha de ingreso a la empresa y estado para
 * la prestación del servicio. Solo lee.
 *
 * Sirve para conciliar al revés que la consulta por cédula: en vez de preguntar
 * por los que BryNex espera, trae a todos los que Sura tiene, y así aparecen los
 * que no deberían estar —por ejemplo los de Gestión ARL de ELITES, a los que la
 * afiliación a la ARL les activó la EPS sin que nadie la pidiera—.
 *
 * El informe es JSF con un datascroller de RichFaces que pagina de a 10 por ajax.
 *
 * Entrada por stdin: {tipoDocumento, usuario, contrasena, nitEmpresa}
 * Salida por stdout: {ok, empresa, total, afiliados: [{tipo, numero, apellido1,
 *                     apellido2, nombres, tipo_afiliado, tipo_cotizante,
 *                     fecha_ingreso, parentesco, cobertura, estado}], error}
 */
import puppeteer from 'puppeteer-core';
import { rutaChrome } from './arl-sura-sesion-comun.mjs';
import { entrarEmpresaEps, esperar, texto } from './eps-sura-sesion-comun.mjs';

const URL_INFORME = 'https://epsapps.suramericana.com/Semp/faces/pos/afiliadosporestado/parametros.jspx';

/** Tope de páginas: la empresa más grande de Brygar tiene 15; evita un bucle infinito. */
const MAX_PAGINAS = 300;

const salir = (d) => { console.log(JSON.stringify(d)); process.exit(d.ok ? 0 : 1); };

const leerStdin = async () => {
  let datos = '';
  for await (const t of process.stdin) datos += t;
  return datos.trim();
};

let entrada;
try { entrada = JSON.parse(await leerStdin() || '{}'); }
catch { salir({ ok: false, error: 'Entrada JSON inválida.' }); }

if (!entrada.usuario || !entrada.contrasena || !entrada.nitEmpresa) {
  salir({ ok: false, error: 'Faltan credenciales o NIT de la empresa.' });
}

const ejecutable = await (async () => {
  const { access } = await import('node:fs/promises');
  for (const r of rutaChrome()) { try { await access(r); return r; } catch {} }
  return null;
})();
if (!ejecutable) salir({ ok: false, error: 'No se encontró Chrome. Define CHROME_PATH.' });

const navegador = await puppeteer.launch({
  executablePath: ejecutable,
  headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-blink-features=AutomationControlled'],
});

/**
 * Las filas de la página visible, en orden. Las de un solo cell en mayúsculas
 * son el encabezado del grupo de estado ("TIENE DERECHO A COBERTURA INTEGRAL")
 * y se arrastran a las filas que siguen.
 */
const leerPagina = (pagina) => pagina.evaluate(() => {
  const filas = [];
  let estado = null;
  for (const tr of document.querySelectorAll('tr')) {
    const celdas = [...tr.children].map(td => td.innerText.trim());
    if (celdas.length === 1 && /^[A-ZÁÉÍÓÚÑ ()]{8,}$/.test(celdas[0])) {
      estado = celdas[0];
      continue;
    }
    if (celdas.length >= 9 && /^[A-Z]{2} \d+$/.test(celdas[0])) {
      filas.push({ celdas, estado });
    }
  }
  return filas;
}).catch(() => []);

let pagina;
let paso = 'inicio';

try {
  pagina = await navegador.newPage();

  paso = 'login';
  await entrarEmpresaEps(pagina, entrada);

  paso = 'generar informe';
  await pagina.goto(URL_INFORME, { waitUntil: 'networkidle2', timeout: 60000 });
  await pagina.waitForSelector('input[type="radio"][value="det"]', { visible: true, timeout: 30000 });
  await pagina.click('input[type="radio"][value="det"]');
  await esperar(1500);

  const generar = await pagina.$('a[id$=":generar"]');
  if (!generar) throw new Error('No apareció el botón "Generar reporte".');
  await Promise.all([
    pagina.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }).catch(() => {}),
    generar.click(),
  ]);

  let t = '';
  for (let i = 0; i < 40; i++) {
    await esperar(800);
    t = await texto(pagina);
    if (/Detalle de afiliados por estado|no (se encontraron|hay) (registros|afiliados)/i.test(t)) break;
  }
  if (!/Detalle de afiliados por estado/i.test(t)) {
    if (/no (se encontraron|hay) (registros|afiliados)/i.test(t)) {
      salir({ ok: true, empresa: null, total: 0, afiliados: [] });
    }
    throw new Error('El informe no cargó: ' + t.replace(/\s+/g, ' ').trim().slice(0, 200));
  }

  const empresa = (t.match(/Empresa\t([^\n\t]+)/) || [])[1]?.trim() || null;
  const totalSura = Number((t.match(/Total afiliados\t(\d+)/) || [])[1] || 0) || null;

  // ── Paginar ──
  paso = 'paginar';
  // La llave es la fila completa y no el documento: una persona puede salir dos
  // veces (p. ej. "no tiene derecho por fin de vigencia" y "tiene derecho" tras
  // reingresar), y con el documento solo se perdía una y el total no cuadraba
  // (Construtech 13/12, Work at Home 54/53 el 14-sep-2026). Releer una página
  // sigue sin duplicar, porque la fila es idéntica.
  const vistos = new Map();
  const guardar = (filas) => filas.forEach(f => vistos.set(`${f.estado}|${f.celdas.join('|')}`, f));
  guardar(await leerPagina(pagina));

  for (let n = 2; n <= MAX_PAGINAS; n++) {
    const primera = (await leerPagina(pagina))[0]?.celdas[0];

    // El número de la página si está a la vista; si no, "siguiente".
    const pulsado = await pagina.evaluate((num) => {
      const botones = [...document.querySelectorAll('[id*="_ds_"]')];
      const b = botones.find(e => e.id.endsWith('_ds_' + num))
        ?? botones.find(e => /_ds_next$/.test(e.id) && !/dsbl/.test(e.className));
      if (!b || /rf-ds-act/.test(b.className)) return false;
      b.click();
      return true;
    }, n).catch(() => false);

    if (!pulsado) break;

    let cambio = false;
    for (let i = 0; i < 25; i++) {
      await esperar(400);
      if ((await leerPagina(pagina))[0]?.celdas[0] !== primera) { cambio = true; break; }
    }
    if (!cambio) break;

    guardar(await leerPagina(pagina));
  }

  const afiliados = [...vistos.values()].map(({ celdas: c, estado }) => {
    const [tipo, numero] = c[0].split(' ');
    return {
      tipo, numero,
      apellido1: c[1] || null, apellido2: c[2] || null, nombres: c[3] || null,
      tipo_afiliado: c[4] || null, tipo_cotizante: c[5] || null,
      fecha_ingreso: c[6] || null, parentesco: c[7] || null, cobertura: c[8] || null,
      estado,
    };
  });

  if (totalSura && afiliados.length < totalSura) {
    // Mejor fallar que conciliar con media lista: los que falten saldrían
    // como "no están en EPS" sin ser cierto.
    throw new Error(`El informe dice ${totalSura} afiliados pero solo se leyeron ${afiliados.length}.`);
  }

  salir({ ok: true, empresa, total: afiliados.length, afiliados });
} catch (e) {
  let captura = null;
  try {
    if (pagina && !pagina.isClosed() && process.env.ARL_DEBUG_DIR) {
      captura = `${process.env.ARL_DEBUG_DIR}/eps-sura-afiliados-fallo.png`;
      await pagina.screenshot({ path: captura, fullPage: true });
    }
  } catch {}
  salir({ ok: false, paso, error: String(e.message || e).slice(0, 300), captura });
} finally {
  await navegador.close().catch(() => {});
}
