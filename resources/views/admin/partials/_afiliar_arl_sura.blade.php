{{--
    Modal de afiliación a ARL Sura, autocontenido.

    Se incluye en cualquier listado de contratos: trae su propio CSS y su JS, y
    solo necesita que algo llame a `abrirAfiliarSura(contratoId)`. Los endpoints
    viven en admin.gestion-arl.precheck / .afiliar y sirven para cualquier
    contrato del aliado activo, no solo los de modalidad 15.
--}}
<style>
.asu-bg { display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:10000;align-items:center;justify-content:center;padding:1rem }
.asu-bg.open { display:flex }
.asu-box { background:#fff;border-radius:14px;max-width:520px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.3);overflow:hidden }
.asu-head { background:linear-gradient(135deg,#1e40af,#2563eb);padding:.85rem 1.1rem;display:flex;justify-content:space-between;align-items:center }
.asu-head h3 { color:#fff;font-size:.92rem;font-weight:800;margin:0 }
.asu-x { background:none;border:none;color:#bfdbfe;font-size:1.15rem;cursor:pointer;line-height:1 }
.asu-body { padding:1.1rem }
.asu-resumen { background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:.65rem .8rem;font-size:.76rem;line-height:1.65;margin-bottom:.8rem }
.asu-resumen span { color:#64748b }
.asu-prob { background:#fef2f2;border:1px solid #fecaca;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#991b1b }
.asu-prob ul { margin:.35rem 0 0 1rem;padding:0 }
.asu-aviso { background:#fffbeb;border:1px solid #fcd34d;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#92400e }
.asu-ok { background:#f0fdf4;border:1px solid #86efac;border-radius:9px;padding:.8rem .9rem;font-size:.8rem;color:#166534;line-height:1.6 }
.asu-label { display:block;font-size:.7rem;font-weight:700;color:#475569;margin-bottom:.25rem }
.asu-input { width:100%;padding:.5rem .65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;font-family:inherit }
.asu-btn { width:100%;margin-top:.9rem;background:linear-gradient(135deg,#1e40af,#2563eb);color:#fff;border:none;border-radius:10px;padding:.6rem 1.2rem;font-size:.86rem;font-weight:700;cursor:pointer }
.asu-btn:disabled { opacity:.5;cursor:not-allowed }
</style>

<div class="asu-bg" id="asuModal">
  <div class="asu-box">
    <div class="asu-head">
      <h3>🚀 Afiliar en ARL Sura</h3>
      <button class="asu-x" onclick="cerrarAfiliarSura()">✕</button>
    </div>
    <div class="asu-body">
      <div id="asuCargando" style="text-align:center;color:#64748b;font-size:.82rem;padding:1.2rem">
        ⏳ Revisando los datos del contrato...
      </div>

      <div id="asuContenido" style="display:none">
        <div class="asu-resumen" id="asuResumen"></div>
        <div class="asu-prob" id="asuProblemas" style="display:none">
          <strong>Faltan datos para poder afiliar:</strong>
          <ul id="asuProblemasLista"></ul>
        </div>
        <div class="asu-aviso" id="asuYaAfiliado" style="display:none"></div>

        {{-- Cuando la empresa aún no tiene credenciales del portal: se piden
             aquí mismo y quedan guardadas para todos los aliados que compartan
             ese NIT. --}}
        <div id="asuCredencial" style="display:none">
          <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:9px;padding:.65rem .8rem;margin-bottom:.8rem;font-size:.75rem;color:#1e40af;line-height:1.5">
            <strong id="asuCredEmpresa"></strong> todavía no tiene credenciales del portal de ARL Sura.
            Ingrésalas una vez: quedan guardadas y sirven para esta empresa en todos los aliados.
          </div>
          <div style="display:grid;grid-template-columns:110px 1fr;gap:.6rem;margin-bottom:.6rem">
            <div>
              <label class="asu-label">Tipo</label>
              <select class="asu-input" id="asuCredTipo">
                <option value="C">Cédula</option>
                <option value="N">NIT</option>
                <option value="E">C. extranjería</option>
              </select>
            </div>
            <div>
              <label class="asu-label">Número de identificación</label>
              <input type="text" class="asu-input" id="asuCredUsuario" autocomplete="off">
            </div>
          </div>
          <label class="asu-label">Contraseña del portal</label>
          <input type="password" class="asu-input" id="asuCredClave" autocomplete="new-password">
          <button class="asu-btn" id="asuCredBtn" onclick="guardarCredencialSura()">🔐 Guardar y buscar la póliza</button>
          <div id="asuCredNota" style="font-size:.68rem;color:#b45309;margin-top:.4rem;line-height:1.35"></div>
        </div>

        <div id="asuFechaGrupo">
          <label class="asu-label">Fecha de inicio de cobertura *</label>
          <input type="date" class="asu-input" id="asuFecha">
          <span style="font-size:.68rem;color:#94a3b8">Sura no cubre el mismo día en que se afilia: por eso se sugiere mañana.</span>
        </div>

        <button class="asu-btn" id="asuBtn" onclick="confirmarAfiliarSura()">🚀 Afiliar en Sura</button>
      </div>

      <div id="asuResultado" class="asu-ok" style="display:none"></div>
    </div>
  </div>
</div>

<script>
let asuContratoId = null;
const ASU_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

function cerrarAfiliarSura() { document.getElementById('asuModal').classList.remove('open'); }

// Estas dos operaciones abren un navegador dentro del servidor y tardan cerca
// de minuto y medio. Sin un contador a la vista el botón parece congelado.
function asuEsperar(btn, texto) {
    const desde = Date.now();
    const pintar = () => {
        const seg = Math.round((Date.now() - desde) / 1000);
        btn.textContent = `⏳ ${texto} ${seg}s`;
    };
    btn.disabled = true; pintar();
    const reloj = setInterval(pintar, 1000);
    return () => clearInterval(reloj);
}

async function asuPedir(url, opciones, limiteSeg) {
    const corte = new AbortController();
    const alarma = setTimeout(() => corte.abort(), limiteSeg * 1000);
    try {
        const res = await fetch(url, { ...opciones, signal: corte.signal });
        return await res.json();
    } finally {
        clearTimeout(alarma);
    }
}

// Si la conexión se corta, el trabajo puede haber terminado igual en el
// servidor: se relee el contrato para ver si la póliza ya quedó guardada.
async function asuPolizaYaQuedo() {
    try {
        const r = await fetch(`/admin/gestion-arl/${asuContratoId}/precheck`, { headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        return !!(d.resumen && d.resumen.poliza);
    } catch (e) {
        return false;
    }
}

async function abrirAfiliarSura(contratoId) {
    asuContratoId = contratoId;
    document.getElementById('asuCargando').style.display  = 'block';
    document.getElementById('asuContenido').style.display = 'none';
    document.getElementById('asuResultado').style.display = 'none';
    document.getElementById('asuModal').classList.add('open');

    let data;
    try {
        const r = await fetch(`/admin/gestion-arl/${contratoId}/precheck`, { headers: { 'Accept': 'application/json' } });
        data = await r.json();
    } catch (e) {
        document.getElementById('asuCargando').textContent = '⚠️ No se pudo revisar el contrato.';
        return;
    }

    document.getElementById('asuCargando').style.display  = 'none';
    document.getElementById('asuContenido').style.display = 'block';

    const r = data.resumen || {};
    const li = (k, v) => v ? `<div><span>${k}:</span> <strong>${v}</strong></div>` : '';
    document.getElementById('asuResumen').innerHTML =
        li('Trabajador', `${r.trabajador} — ${r.documento}`) +
        li('Empresa', `${r.razon_social} (póliza ${r.poliza ?? '—'})`) +
        li('Tipo', `${r.tipo}${r.modalidad ? ' · ' + r.modalidad : ''}`) +
        li('Seguridad social', `${r.eps ?? '—'} / ${r.afp ?? '—'}`) +
        li('IBC', r.ibc ? '$' + Number(r.ibc).toLocaleString('es-CO') : null) +
        li('Cargo', r.cargo) +
        li('Riesgo', r.nivel_riesgo ? `${r.nivel_riesgo} · ${r.centro ?? 'sin centro'}${r.tasa ? ' · tasa ' + r.tasa : ''}` : null);

    // Datos que el sistema consiguió solo (RUAF, o el propio Sura)
    if (data.completado && Object.keys(data.completado).length) {
        const detalle = Object.entries(data.completado).map(([k, v]) => `${k} (${v})`).join(', ');
        document.getElementById('asuResumen').innerHTML +=
            `<div style="margin-top:.35rem;color:#15803d;">✓ Se completó automáticamente: ${detalle}</div>`;
    }

    // Falta la credencial del portal: se pide antes que cualquier otra cosa.
    const cred = data.requiere_credencial;
    document.getElementById('asuCredencial').style.display = cred ? 'block' : 'none';
    if (cred) {
        document.getElementById('asuCredEmpresa').textContent = cred.razon_social || 'Esta empresa';
        document.getElementById('asuFechaGrupo').style.display = 'none';
        document.getElementById('asuBtn').style.display = 'none';
        document.getElementById('asuProblemas').style.display = 'none';
        return;
    }
    document.getElementById('asuBtn').style.display = 'block';

    const problemas = data.problemas || [];
    document.getElementById('asuProblemas').style.display = problemas.length ? 'block' : 'none';
    document.getElementById('asuProblemasLista').innerHTML = problemas.map(p => `<li>${p}</li>`).join('');

    const ya = data.ya_afiliado;
    const aviso = document.getElementById('asuYaAfiliado');
    if (ya) {
        aviso.innerHTML = `⚠️ Ya tiene una afiliación registrada desde <strong>${ya.desde}</strong>` +
            (ya.codigo_transaccion ? ` (transacción ${ya.codigo_transaccion})` : '') + '.' +
            (ya.se_puede_anular ? ' Aún está dentro de los 30 días para anularla si fue un error.'
                               : ' Ya pasaron los 30 días para anularla: para cerrarla hay que retirar.');
        aviso.style.display = 'block';
    } else {
        aviso.style.display = 'none';
    }

    document.getElementById('asuFecha').value = data.fecha_sugerida || '';
    const btn = document.getElementById('asuBtn');
    btn.disabled = problemas.length > 0;
    btn.textContent = problemas.length ? '🚫 Completa los datos primero' : '🚀 Afiliar en Sura';
    document.getElementById('asuFechaGrupo').style.display = problemas.length ? 'none' : 'block';
}

/**
 * Guarda las credenciales de la empresa y descubre su póliza en el portal.
 * Tarda: abre un navegador, entra y lee el número de contrato.
 */
async function guardarCredencialSura() {
    const usuario = document.getElementById('asuCredUsuario').value.trim();
    const clave   = document.getElementById('asuCredClave').value;
    if (!usuario || !clave) { alert('Ingresa el usuario y la contraseña del portal.'); return; }

    const btn = document.getElementById('asuCredBtn');
    const parar = asuEsperar(btn, 'Entrando al portal de Sura...');
    const nota = document.getElementById('asuCredNota');
    if (nota) nota.textContent = 'Abriendo el portal y leyendo la póliza. Suele tardar entre 1 y 2 minutos: no cierres esta ventana.';

    let data;
    try {
        data = await asuPedir(`/admin/gestion-arl/${asuContratoId}/credencial`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ASU_CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({
                tipo_documento: document.getElementById('asuCredTipo').value,
                usuario, contrasena: clave,
            }),
        }, 240);
    } catch (e) {
        // Consultar es inofensivo: solo lee lo que ya está guardado.
        data = await asuPolizaYaQuedo()
            ? { ok: true, mensaje: 'La póliza quedó guardada. La conexión se cortó, pero el proceso sí terminó.' }
            : { ok: false, mensaje: 'Se perdió la conexión antes de terminar. Vuelve a intentarlo.' };
    }

    parar();
    btn.disabled = false; btn.textContent = '🔐 Guardar y buscar la póliza';
    if (nota) nota.textContent = '';

    if (data.ok) {
        alert(data.mensaje);
        abrirAfiliarSura(asuContratoId); // vuelve a revisar, ya con póliza
    } else {
        alert(data.mensaje || 'No se pudieron guardar las credenciales.');
    }
}

async function confirmarAfiliarSura() {
    const fecha = document.getElementById('asuFecha').value;
    if (!fecha) { alert('Selecciona la fecha de inicio de cobertura.'); return; }

    const btn = document.getElementById('asuBtn');
    const parar = asuEsperar(btn, 'Afiliando en Sura...');

    let data;
    try {
        data = await asuPedir(`/admin/gestion-arl/${asuContratoId}/afiliar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ASU_CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ fecha_inicio_cobertura: fecha }),
        }, 280);
    } catch (e) {
        // Aquí no se reintenta solo: la afiliación pudo haber quedado hecha y
        // repetirla crearía un duplicado en Sura.
        data = { ok: false, mensaje: 'Se perdió la conexión con el servidor. Antes de reintentar, revisa en el portal de Sura si la afiliación quedó hecha.' };
    }

    parar();

    if (data.ok) {
        document.getElementById('asuContenido').style.display = 'none';
        const caja = document.getElementById('asuResultado');
        caja.innerHTML = `✅ <strong>${data.mensaje}</strong><br>` +
            `Transacción <strong>${data.codigo_transaccion ?? '—'}</strong> · cobertura desde <strong>${data.fecha_display}</strong><br>` +
            `<span style="color:#475569">El soporte y el carné quedaron en los documentos del cliente.</span>` +
            (data.aviso ? `<br><span style="color:#b45309">⚠️ ${data.aviso}</span>` : '');
        caja.style.display = 'block';
        setTimeout(() => location.reload(), 3500);
    } else {
        btn.disabled = false; btn.textContent = '🚀 Reintentar';
        alert(data.mensaje || 'No se pudo afiliar.');
    }
}
</script>
