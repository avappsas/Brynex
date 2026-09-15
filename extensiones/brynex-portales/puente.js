/**
 * Puente entre la página de BryNex y la extensión.
 *
 * La página no puede hablar con la extensión directamente sin conocer su id,
 * así que manda `window.postMessage({canal: 'brynex-portales', tipo: 'pedido', …})`
 * y este script (que corre dentro de BryNex) lo reenvía al service worker y
 * devuelve la respuesta por el mismo canal. También marca el documento para
 * que la página sepa que la extensión está instalada.
 */
document.documentElement.dataset.brynexPortales = chrome.runtime.getManifest().version;

window.addEventListener('message', (ev) => {
  if (ev.source !== window || ev.data?.canal !== 'brynex-portales' || ev.data.tipo !== 'pedido') return;

  const { id, portal, accion, datos } = ev.data;
  const responder = (respuesta) => window.postMessage(
    { canal: 'brynex-portales', tipo: 'respuesta', id, respuesta },
    window.location.origin
  );

  try {
    chrome.runtime.sendMessage({ canal: 'brynex-portales', portal, accion, datos }, (respuesta) => {
      const error = chrome.runtime.lastError?.message;
      responder(respuesta || { ok: false, error: error || 'La extensión no respondió.' });
    });
  } catch (e) {
    // La extensión se actualizó con la página abierta: hay que recargar.
    responder({ ok: false, error: 'La extensión BryNex Portales se actualizó: recarga la página.' });
  }
});
