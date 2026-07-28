import { chromium } from 'playwright';
import { randomUUID } from 'node:crypto';
import { leerReporte } from './pdf.mjs';

const URL_CONSULTA = 'https://aplicaciones.adres.gov.co/COM_4023/Frms/ConsultaCOM.aspx';

const MAX_INTENTOS = Number(process.env.ADRES_MAX_INTENTOS ?? 3);
const TTL_SESION_MS = Number(process.env.ADRES_TTL_SESION_MS ?? 12 * 60 * 1000);
const HEADLESS = process.env.ADRES_HEADLESS !== 'false';

/**
 * Sesiones vivas. El captcha de ADRES va atado a la sesión de ASP.NET, así que
 * entre pedir el captcha y responderlo hay que mantener el mismo contexto de
 * navegador abierto — no se puede reconstruir después.
 */
const sesiones = new Map();

let navegador = null;

async function obtenerNavegador() {
    if (navegador?.isConnected()) return navegador;
    navegador = await chromium.launch({ headless: HEADLESS });
    return navegador;
}

const norm = (s) =>
    (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

/**
 * ADRES baraja el orden de los radios de tipo de documento en cada carga
 * (se vio RblTipoDoc_13 y RblTipoDoc_2 para el mismo campo en dos corridas
 * seguidas). Hay que resolverlos por el texto del label, nunca por índice.
 */
async function marcarTipoDocumento(pagina, etiquetaBuscada) {
    const id = await pagina.evaluate((buscada) => {
        const n = (s) =>
            (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
        for (const lbl of document.querySelectorAll('label')) {
            if (n(lbl.textContent) === buscada) return lbl.htmlFor || null;
        }
        return null;
    }, norm(etiquetaBuscada));

    if (!id) {
        throw new Error(`No se encontró el tipo de documento "${etiquetaBuscada}" en el formulario de ADRES.`);
    }

    await pagina.check(`#${id}`);
    return id;
}

async function recortarCaptcha(pagina) {
    const img = pagina.locator('#RadCaptcha1_CaptchaImageUP');
    if (!(await img.count())) {
        throw new Error('ADRES no mostró el captcha (#RadCaptcha1_CaptchaImageUP). El formulario cambió.');
    }
    return img.screenshot();
}

/** Abre una consulta y deja la sesión esperando el texto del captcha. */
export async function abrirConsulta({ cedula, tipoDocumento = 'Cedula de Ciudadania' }) {
    if (!/^\d{4,15}$/.test(String(cedula))) {
        throw new Error('Cédula inválida.');
    }

    const nav = await obtenerNavegador();
    const contexto = await nav.newContext({ acceptDownloads: true });
    const pagina = await contexto.newPage();

    try {
        await pagina.goto(URL_CONSULTA, { waitUntil: 'domcontentloaded', timeout: 60000 });
        const idRadio = await marcarTipoDocumento(pagina, tipoDocumento);
        await pagina.fill('#txtNumDoc', String(cedula));

        const captcha = await recortarCaptcha(pagina);
        const sesionId = randomUUID();

        sesiones.set(sesionId, {
            contexto,
            pagina,
            cedula: String(cedula),
            tipoDocumento,
            idRadio,
            intentos: 0,
            creado: Date.now(),
        });

        return { sesion_id: sesionId, captcha_png: captcha, intentos_restantes: MAX_INTENTOS };
    } catch (e) {
        await contexto.close().catch(() => {});
        throw e;
    }
}

/**
 * El resultado trae la cédula consultada. Se compara con la que pedimos: si el
 * radio de tipo de documento quedó mal marcado o la sesión se cruzó, esto lo
 * atrapa antes de entregarle a nadie el historial de otra persona.
 */
async function verificarIdentidad(pagina, cedulaEsperada) {
    const encontrada = await pagina.evaluate(() => {
        const tabla = document.querySelector('#RadGrid1_ctl00');
        if (!tabla) return null;
        for (const fila of tabla.rows) {
            for (const celda of fila.cells) {
                const t = celda.innerText.trim();
                if (/^\d{4,15}$/.test(t)) return t;
            }
        }
        return null;
    });

    if (!encontrada) {
        throw new Error('ADRES respondió sin la tabla de datos básicos; no se pudo verificar la identidad.');
    }
    if (encontrada !== String(cedulaEsperada)) {
        throw new Error(
            `La cédula del resultado (${encontrada}) no coincide con la consultada (${cedulaEsperada}). Se descarta.`
        );
    }
    return encontrada;
}

/** Recibe el texto que resolvió un humano y termina la consulta. */
export async function responderCaptcha(sesionId, textoCrudo) {
    const sesion = sesiones.get(sesionId);
    if (!sesion) {
        throw new Error('La sesión de consulta expiró o no existe. Hay que empezar de nuevo.');
    }

    // El cliente escribe por WhatsApp: llegan espacios, saltos y mayúsculas mezcladas.
    const texto = String(textoCrudo || '').replace(/\s+/g, '').trim();
    if (!texto) throw new Error('Texto de captcha vacío.');

    const { pagina, cedula } = sesion;
    sesion.intentos += 1;

    await pagina.fill('#RadCaptcha1_CaptchaTextBox', texto);
    await pagina.click('#btnConsultar');

    let hayResultado = true;
    try {
        await pagina.waitForFunction(
            () => /INFORMACI[OÓ]N B[AÁ]SICA DEL AFILIADO/i.test(document.body.innerText),
            null,
            { timeout: 30000 }
        );
    } catch {
        hayResultado = false;
    }

    if (!hayResultado) {
        const restantes = MAX_INTENTOS - sesion.intentos;
        if (restantes <= 0) {
            await cerrarConsulta(sesionId);
            return { ok: false, motivo: 'sin_intentos', intentos_restantes: 0 };
        }
        // ADRES rota la imagen tras un fallo: hay que mandar la nueva, no la vieja.
        return {
            ok: false,
            motivo: 'captcha_incorrecto',
            captcha_png: await recortarCaptcha(pagina),
            intentos_restantes: restantes,
        };
    }

    await verificarIdentidad(pagina, cedula);

    // El PDF trae los registros completos; la tabla en pantalla solo muestra la
    // primera de 16 páginas. Descargarlo evita tener que paginar.
    const [descarga] = await Promise.all([
        pagina.waitForEvent('download', { timeout: 45000 }),
        pagina.click('#btnDescargar'),
    ]);

    const flujo = await descarga.createReadStream();
    const trozos = [];
    for await (const t of flujo) trozos.push(t);
    const pdf = Buffer.concat(trozos);

    const reporte = await leerReporte(pdf);
    const declarado = await pagina.evaluate(() => {
        const m = document.body.innerText.match(/(\d+)\s+Registros?\s+en\s+(\d+)\s+P[aá]ginas?/i);
        return m ? Number(m[1]) : null;
    });

    await cerrarConsulta(sesionId);

    return {
        ok: true,
        cedula,
        filas: reporte.filas,
        total_filas: reporte.total_filas,
        // Si no cuadran, el PDF se extrajo a medias y el diagnóstico sería falso.
        total_declarado: declarado,
        completo: declarado === null || declarado === reporte.total_filas,
        pdf_base64: pdf.toString('base64'),
        nombre_pdf: descarga.suggestedFilename(),
    };
}

export async function cerrarConsulta(sesionId) {
    const sesion = sesiones.get(sesionId);
    if (!sesion) return false;
    sesiones.delete(sesionId);
    await sesion.contexto.close().catch(() => {});
    return true;
}

/** Cierra sesiones que nadie respondió, para no dejar contextos colgados. */
export function barrerSesionesVencidas() {
    const ahora = Date.now();
    let cerradas = 0;
    for (const [id, s] of sesiones) {
        if (ahora - s.creado > TTL_SESION_MS) {
            sesiones.delete(id);
            s.contexto.close().catch(() => {});
            cerradas += 1;
        }
    }
    return cerradas;
}

export const estadoWorker = () => ({
    sesiones_abiertas: sesiones.size,
    navegador_conectado: Boolean(navegador?.isConnected()),
    max_intentos: MAX_INTENTOS,
    ttl_sesion_ms: TTL_SESION_MS,
});

export async function apagar() {
    for (const id of [...sesiones.keys()]) await cerrarConsulta(id);
    await navegador?.close().catch(() => {});
    navegador = null;
}
