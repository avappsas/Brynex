/**
 * Módulo Renta de la extensión BryNex: trae la exógena de la DIAN a BryNex Renta con la
 * sesión de MUISCA que la persona abre. Nunca ve, escribe ni guarda claves.
 *
 * Va en su propio archivo (lo carga background.js con importScripts) y su propio puente
 * (puente-renta.js, canal 'brynex-renta') para poder armar después un paquete solo de renta
 * para el público: alguien de afuera no debe instalar algo con permisos sobre los portales
 * de las EPS.
 *
 * Pedidos (desde renta.brynex.co o localhost:8010):
 *  estado            → {abierta, sesion}
 *  abrir             → abre (o enfoca) el login de MUISCA
 *  exogena {anio}    → {nombre, base64, bytes} el Excel "reporteExogena<anio>.xlsx"
 *
 * Cómo lo baja (mapeado el 22-sep-2026): en el tablero `WebDashboard/DefDashboard.faces`,
 * elegir el año en `anioSel` dispara un A4J que llena `hddFechaProcesamientoSel`; después un
 * submit del form con `_idcl = lnkDescargarReporteExogena` responde el xlsx como adjunto.
 * Aquí se hace con fetch para tener el archivo sin el diálogo de descarga.
 *
 * La consulta pública consultarenta.dian.gov.co NO trae la exógena (solo si debe declarar),
 * y su código trae una credencial de servicio de la DIAN que no se usa.
 */
(() => {
  const ORIGENES_RENTA = ['https://renta.brynex.co', 'http://localhost:8010'];
  const MUISCA = 'https://muisca.dian.gov.co';
  const LOGIN = `${MUISCA}/WebArquitectura/DefLogin.faces`;
  const TABLERO = `${MUISCA}/WebDashboard/DefDashboard.faces`;

  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    const origen = sender.origin || (sender.url ? new URL(sender.url).origin : '');
    if (msg?.canal !== 'brynex-renta' || !ORIGENES_RENTA.includes(origen) || sender.id !== chrome.runtime.id) return;

    atender(msg)
      .then(sendResponse)
      .catch(e => sendResponse({ ok: false, error: String(e?.message || e).slice(0, 400) }));
    return true;
  });

  async function atender({ accion, datos = {} }) {
    switch (accion) {
      case 'estado':  return { ok: true, ...(await estado()) };
      case 'abrir':   return abrir();
      case 'exogena': return exogena(Number(datos.anio));
      default: throw new Error(`Acción desconocida: ${accion}`);
    }
  }

  async function pestanaMuisca() {
    const pestanas = await chrome.tabs.query({ url: `${MUISCA}/*` });
    return pestanas[0] || null;
  }

  /** Ejecuta `func` en la página (mundo principal, con las funciones JSF de MUISCA). */
  async function ejecutar(tabId, func, args = []) {
    const [r] = await chrome.scripting.executeScript({ target: { tabId }, func, args, world: 'MAIN' });
    if (r?.result?.__error) throw new Error(r.result.__error);
    return r?.result;
  }

  async function estado() {
    const pestana = await pestanaMuisca();
    if (!pestana) return { abierta: false, sesion: false };

    // Quién inició sesión no se puede leer de la página; BryNex Renta valida que la exógena
    // sea de la misma cédula cuando la recibe.
    const sesion = await ejecutar(pestana.id, () => !!document.forms['vistaDashboard:frmDashboard']
      || /DefDashboard|WebDashboard/.test(location.pathname)).catch(() => false);

    return { abierta: true, sesion };
  }

  async function abrir() {
    const pestana = await pestanaMuisca();
    if (pestana) {
      await chrome.tabs.update(pestana.id, { active: true });
      await chrome.windows.update(pestana.windowId, { focused: true });
    } else {
      await chrome.tabs.create({ url: LOGIN, active: true });
    }
    return { ok: true };
  }

  async function exogena(anio) {
    if (!Number.isInteger(anio) || anio < 2020 || anio > 2100) throw new Error('Año no válido.');

    let pestana = await pestanaMuisca();
    if (!pestana) throw new Error('No hay una pestaña de MUISCA abierta. Pulsa "Abrir MUISCA" e inicia sesión.');

    // Si la persona está en otra página de MUISCA, se lleva la pestaña al tablero.
    const enTablero = await ejecutar(pestana.id, () => !!document.forms['vistaDashboard:frmDashboard']).catch(() => false);
    if (!enTablero) {
      await chrome.tabs.update(pestana.id, { url: TABLERO });
      await esperarCarga(pestana.id);
      const listo = await ejecutar(pestana.id, () => !!document.forms['vistaDashboard:frmDashboard']).catch(() => false);
      if (!listo) throw new Error('La pestaña de MUISCA no tiene la sesión iniciada. Inicia sesión y vuelve a intentar.');
    }

    const r = await ejecutar(pestana.id, bajarExogena, [anio]);
    return { ok: true, ...r };
  }

  function esperarCarga(tabId) {
    return new Promise((resolve) => {
      const listo = (id, info) => {
        if (id === tabId && info.status === 'complete') {
          chrome.tabs.onUpdated.removeListener(listo);
          resolve();
        }
      };
      chrome.tabs.onUpdated.addListener(listo);
      setTimeout(() => { chrome.tabs.onUpdated.removeListener(listo); resolve(); }, 20000);
    });
  }

  /** Corre dentro de MUISCA. */
  async function bajarExogena(anio) {
    const P = 'vistaDashboard:frmDashboard';
    const form = document.forms[P];
    const el = (id) => document.getElementById(`${P}:${id}`);
    const selector = el('anioSel');
    if (!form || !selector) return { __error: 'No se encontró la consulta de exógena en el tablero de MUISCA.' };

    const anios = [...selector.options].map(o => o.value).filter(Boolean);
    if (!anios.includes(String(anio))) return { __error: `MUISCA no ofrece la exógena de ${anio}. Años disponibles: ${anios.join(', ')}.` };

    // Lo mismo que hace la página al cambiar el año: un A4J que deja lista la fecha de proceso.
    el('hddFechaProcesamientoSel').value = '';
    selector.value = String(anio);
    selector.dispatchEvent(new Event('change', { bubbles: true }));
    for (let i = 0; i < 40 && !el('hddFechaProcesamientoSel').value; i++) {
      await new Promise(r => setTimeout(r, 250));
    }

    const cuerpo = new URLSearchParams();
    for (const [k, v] of new FormData(form)) if (typeof v === 'string') cuerpo.append(k, v);
    cuerpo.set(`${P}:hddAnioSel`, String(anio));
    cuerpo.set(`${P}:_idcl`, `${P}:lnkDescargarReporteExogena`);

    const respuesta = await fetch(form.action, { method: 'POST', body: cuerpo, credentials: 'include' });
    const adjunto = respuesta.headers.get('content-disposition') || '';
    if (!/\.xlsx?/i.test(adjunto)) {
      return { __error: 'MUISCA no entregó el archivo. Puede que la sesión se haya vencido: recarga MUISCA e intenta de nuevo.' };
    }

    const blob = await respuesta.blob();
    const base64 = await new Promise((resolve) => {
      const lector = new FileReader();
      lector.onload = () => resolve(String(lector.result).split(',')[1]);
      lector.readAsDataURL(blob);
    });

    return { nombre: (adjunto.match(/filename="?([^";]+)/) || [])[1] || `reporteExogena${anio}.xlsx`, base64, bytes: blob.size };
  }
})();
