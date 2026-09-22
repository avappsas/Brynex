/**
 * Puente entre la página de BryNex Renta y el módulo Renta de la extensión (renta.js).
 *
 * La página manda `window.postMessage({canal: 'brynex-renta', tipo: 'pedido', id, accion, datos})`;
 * este script lo reenvía al service worker y devuelve la respuesta por el mismo canal.
 * También marca el documento para que la página sepa que la extensión está instalada.
 */
document.documentElement.dataset.brynexRenta = chrome.runtime.getManifest().version;

window.addEventListener('message', (ev) => {
  if (ev.source !== window || ev.data?.canal !== 'brynex-renta' || ev.data.tipo !== 'pedido') return;

  const { id, accion, datos } = ev.data;
  const responder = (respuesta) => window.postMessage(
    { canal: 'brynex-renta', tipo: 'respuesta', id, respuesta },
    window.location.origin
  );

  try {
    chrome.runtime.sendMessage({ canal: 'brynex-renta', accion, datos }, (respuesta) => {
      const error = chrome.runtime.lastError?.message;
      responder(respuesta || { ok: false, error: error || 'La extensión no respondió.' });
    });
  } catch (e) {
    responder({ ok: false, error: 'La extensión BryNex Renta se actualizó: recarga la página.' });
  }
});
