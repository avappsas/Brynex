/**
 * Worker de consulta a ADRES.
 *
 * Servicio HTTP mínimo que Laravel invoca. Existe como proceso aparte porque el
 * captcha va atado a la sesión de ASP.NET: entre pedirlo y responderlo pueden
 * pasar minutos, y el contexto del navegador tiene que seguir vivo. Un script
 * de un solo uso no serviría.
 *
 * EL CAPTCHA LO RESUELVE UNA PERSONA. Este worker recorta la imagen y espera el
 * texto; no lo interpreta ni lo delega a ningún servicio de resolución.
 *
 * Escucha solo en loopback por defecto: puede consultar el historial de salud de
 * cualquier cédula, así que no debe quedar expuesto en red.
 */

import http from 'node:http';
import {
    abrirConsulta,
    responderCaptcha,
    cerrarConsulta,
    barrerSesionesVencidas,
    estadoWorker,
    apagar,
} from './lib/consulta.mjs';

const PUERTO = Number(process.env.ADRES_WORKER_PUERTO ?? 8801);
const HOST = process.env.ADRES_WORKER_HOST ?? '127.0.0.1';
const TOKEN = process.env.ADRES_WORKER_TOKEN ?? '';

if (!TOKEN) {
    console.error('Falta ADRES_WORKER_TOKEN. El worker no arranca sin token compartido.');
    process.exit(1);
}

const log = (...a) => console.log(new Date().toISOString(), ...a);

const responder = (res, codigo, cuerpo) => {
    const json = JSON.stringify(cuerpo);
    res.writeHead(codigo, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(json);
};

async function leerJson(req) {
    const trozos = [];
    let bytes = 0;
    for await (const t of req) {
        bytes += t.length;
        if (bytes > 1_000_000) throw new Error('Cuerpo demasiado grande.');
        trozos.push(t);
    }
    if (!trozos.length) return {};
    return JSON.parse(Buffer.concat(trozos).toString('utf8'));
}

const servidor = http.createServer(async (req, res) => {
    const url = new URL(req.url, `http://${req.headers.host}`);
    const ruta = url.pathname.replace(/\/+$/, '') || '/';

    if (ruta === '/salud' && req.method === 'GET') {
        return responder(res, 200, { ok: true, ...estadoWorker() });
    }

    if (req.headers['x-worker-token'] !== TOKEN) {
        return responder(res, 401, { ok: false, error: 'Token inválido.' });
    }

    try {
        if (ruta === '/consultas' && req.method === 'POST') {
            const { cedula, tipo_documento } = await leerJson(req);
            const r = await abrirConsulta({ cedula, tipoDocumento: tipo_documento });
            log(`consulta abierta ${r.sesion_id} para ${String(cedula).slice(-4).padStart(8, '*')}`);
            return responder(res, 200, {
                ok: true,
                sesion_id: r.sesion_id,
                captcha_png_base64: r.captcha_png.toString('base64'),
                intentos_restantes: r.intentos_restantes,
            });
        }

        const mCaptcha = ruta.match(/^\/consultas\/([\w-]+)\/captcha$/);
        if (mCaptcha && req.method === 'POST') {
            const { texto } = await leerJson(req);
            const r = await responderCaptcha(mCaptcha[1], texto);

            if (!r.ok) {
                log(`captcha fallido en ${mCaptcha[1]} (${r.motivo}), quedan ${r.intentos_restantes}`);
                return responder(res, 200, {
                    ok: false,
                    motivo: r.motivo,
                    intentos_restantes: r.intentos_restantes,
                    captcha_png_base64: r.captcha_png ? r.captcha_png.toString('base64') : null,
                });
            }

            log(`consulta ${mCaptcha[1]} resuelta: ${r.total_filas} filas, completo=${r.completo}`);
            return responder(res, 200, r);
        }

        const mCerrar = ruta.match(/^\/consultas\/([\w-]+)$/);
        if (mCerrar && req.method === 'DELETE') {
            return responder(res, 200, { ok: true, cerrada: await cerrarConsulta(mCerrar[1]) });
        }

        return responder(res, 404, { ok: false, error: 'Ruta no encontrada.' });
    } catch (e) {
        log('ERROR', e.message);
        return responder(res, 422, { ok: false, error: e.message });
    }
});

const barrido = setInterval(() => {
    const n = barrerSesionesVencidas();
    if (n) log(`barridas ${n} sesiones vencidas`);
}, 60_000);

servidor.listen(PUERTO, HOST, () => log(`worker ADRES escuchando en http://${HOST}:${PUERTO}`));

for (const senal of ['SIGINT', 'SIGTERM']) {
    process.on(senal, async () => {
        log(`${senal} recibido, cerrando…`);
        clearInterval(barrido);
        servidor.close();
        await apagar();
        process.exit(0);
    });
}
