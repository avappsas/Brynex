/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  modal_facturar.js — BryNex                                     ║
 * ║                                                                  ║
 * ║  Lógica UNIFICADA del Modal de Facturación.                     ║
 * ║  Funciona en dos contextos:                                      ║
 * ║    • modo:'individual' → desde admin/contratos/{id}/edit        ║
 * ║    • modo:'masivo'     → desde admin/facturacion/empresa/{id}   ║
 * ╚══════════════════════════════════════════════════════════════════╝
 *
 * USO desde Blade:
 *   MF.init({
 *     modo: 'individual',          // 'individual' | 'masivo'
 *     urlFacturar: '...',          // route('admin.facturacion.facturar')
 *     urlMesPagado: '...',         // base url para api/mes-pagado/{contratoId}
 *     csrf: '...',                 // meta[name=csrf-token]
 *     // -- Solo modo individual --
 *     contratoId: 3,
 *     fechaIngresoMes: 4,          // mes de ingreso del contrato (0 = sin fecha)
 *     fechaIngresoAnio: 2026,
 *     esIndependiente: false,
 *     costoAfiliacion: 120000,
 *     arlNivel: 1,
 *     distDefaults: { asesor: 0, retiro: 0, encargado: 0 },
 *     getAlpineResult: () => ({})  // función que devuelve result del cotizador Alpine
 *   });
 */

const MF = (function () {

    // ── Estado interno ────────────────────────────────────────────
    let _cfg = {};
    let _selContratos = [];        // array de {id, data-* del <tr>} seleccionados
    let _total = 0;                // total calculado (planilla o afiliación)
    let _totalAfil = 0;            // costo afiliación
    let _saldoFavor = 0;
    let _saldoPendiente = 0;
    let _modo = 'individual';      // 'individual' | 'masivo'

    // ── Helpers ───────────────────────────────────────────────────
    const fmt = v => '$' + Math.ceil(v || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const parse = s => parseInt(('' + (s || 0)).replace(/[^0-9]/g, '')) || 0;
    const ceil = v => Math.ceil((v || 0) / 100) * 100; // redondeo al centena superior
    const el = id => document.getElementById(id);
    const setText = (id, v) => { const e = el(id); if (e) e.textContent = v; };
    const setVal = (id, v) => { const e = el(id); if (e) e.value = v; };

    function bancoOptions(selId = '') {
        const bancos = window._MF_BANCOS || [];
        return bancos.map(b => `<option value="${b.id}" ${b.id == selId ? 'selected' : ''}>${b.label}</option>`).join('');
    }

    // ── Init ──────────────────────────────────────────────────────
    function init(cfg) {
        _cfg = cfg;
        _modo = cfg.modo || 'individual';
        _totalAfil = cfg.costoAfiliacion || 0;
    }

    // ── Abrir modal ───────────────────────────────────────────────
    /**
     * @param {Array} contratos  - en modo masivo: array de objetos {id, eps, arl, afp, caja, admon, seg, iva, tot, arl_nivel, dias, nombre, costoAfil}
     *                             en modo individual: array de un solo elemento o vacío (se toma cfg.contratoId)
     * @param {string} subtitulo - texto opcional para el subtítulo del modal
     */
    function abrir(contratos, subtitulo) {
        _selContratos = contratos || [];

        // Subtítulo
        setText('mf-subtitle', subtitulo || '');

        // Reset campos
        el('mf-consig-list').innerHTML = '';
        setVal('mf-efectivo', '0');
        setVal('mf-prestamo', '0');
        setVal('mf-obs', '');
        setVal('mf-otros', '0');
        setVal('mf-otros-admon', '0');
        setVal('mf-estado', 'pagada');
        setVal('mf-nplano', '');
        document.querySelectorAll('input[name="mf_indep_modo"]').forEach(r => { if (r.value === 'afiliacion') r.checked = true; });
        _saldoFavor = 0; _saldoPendiente = 0;

        // N° Plano visible solo en masivo
        const npWrap = el('mf-nplano-wrap');
        if (npWrap) npWrap.style.display = _modo === 'masivo' ? 'block' : 'none';

        // Calcular resumen de los contratos seleccionados
        _calcularResumenInicial();

        // Mostrar overlay
        el('mf-overlay').style.display = 'flex';

        onEstado(true);

        // Según modo: detectar tipo o verificar mes pagado
        if (_modo === 'individual') {
            _verificarMesPagado().then(() => { detectarTipo(); recalc(); });
        } else {
            // Masivo: siempre planilla por defecto (el backend auto-detecta por contrato)
            _setTipo('planilla');
            // Mostrar aviso si hay contratos I ACT primer mes (afiliación + planilla juntas)
            _mostrarAvisoIndActMasivo();
            // Consultar saldos a favor/pendientes de los contratos seleccionados
            _fetchSaldosMasivo().then(() => recalc());
        }
    }

    // ── Cálculo de resumen según modo ────────────────────────────
    function _calcularResumenInicial() {
        if (_modo === 'masivo') {
            // Sumar todos los contratos seleccionados
            let eps = 0, arl = 0, afp = 0, caja = 0, admon = 0, seg = 0, iva = 0, afil = 0;
            let maxArlNivel = 0;
            _selContratos.forEach(c => {
                eps += c.eps || 0;
                arl += c.arl || 0;
                afp += c.afp || 0;
                caja += c.caja || 0;
                admon += c.admon || 0;
                seg += c.seg || 0;
                iva += c.iva || 0;
                afil += c.afiliacion || 0;   // afiliación (I ACT primer mes + I VENC afil pura)
                if ((c.arl_nivel || 0) > maxArlNivel) maxArlNivel = c.arl_nivel;
            });
            const ss = eps + arl + afp + caja;

            setText('mf-v-eps', fmt(ceil(eps)));
            setText('mf-v-arl', fmt(ceil(arl)));
            setText('mf-v-afp', fmt(ceil(afp)));
            setText('mf-v-caja', fmt(ceil(caja)));
            setText('mf-v-ss', fmt(ceil(ss)));
            setText('mf-v-admon', fmt(ceil(admon)));
            setText('mf-v-seg', fmt(ceil(seg)));
            setText('mf-v-iva', fmt(ceil(iva)));

            // Afiliación: mostrar fila siempre que haya valor (I ACT o I VENC afil)
            const rowAfil = el('mf-row-afil');
            if (afil > 0) {
                setText('mf-v-afil', fmt(afil));
                if (rowAfil) rowAfil.style.display = '';
            } else {
                if (rowAfil) rowAfil.style.display = 'none';
            }

            const arlBadge = el('mf-arl-badge');
            if (arlBadge) arlBadge.textContent = maxArlNivel ? 'N' + maxArlNivel : '';

            const dias = _selContratos.length === 1 ? (_selContratos[0].dias || 30) : 30;
            if (_selContratos.length === 1) {
                setText('mf-badge-dias', '📅 ' + (_selContratos[0].nombre || '1 trab.') + ' · ' + dias + ' días');
            } else {
                setText('mf-badge-dias', '📅 ' + _selContratos.length + ' trab. · ' + dias + ' días');
            }

            _total = ceil(ss) + ceil(admon) + ceil(seg) + ceil(iva) + afil;

        } else {
            // Modo individual: leer del cotizador Alpine
            const r = (_cfg.getAlpineResult && _cfg.getAlpineResult()) || {};
            setText('mf-v-eps', fmt(ceil(r.eps || 0)));
            setText('mf-v-arl', fmt(ceil(r.arl || 0)));
            setText('mf-v-afp', fmt(ceil(r.pen || 0)));
            setText('mf-v-caja', fmt(ceil(r.caja || 0)));
            setText('mf-v-ss', fmt(ceil((r.eps || 0) + (r.arl || 0) + (r.pen || 0) + (r.caja || 0))));
            setText('mf-v-admon', fmt(ceil(r.admon || 0)));
            setText('mf-v-seg', fmt(ceil(r.seguro || 0)));
            setText('mf-v-iva', fmt(ceil(r.iva || 0)));

            const arlBadge = el('mf-arl-badge');
            if (arlBadge) arlBadge.textContent = _cfg.arlNivel ? 'Nivel ' + _cfg.arlNivel : '';

            // Días del cotizador
            const diasEl = document.getElementById('sel_dias_cotizar');
            const dias = parseInt(diasEl?.value || 30);
            setText('mf-badge-dias', '📅 ' + dias + ' días');

            _total = Math.ceil((r.eps || 0) + (r.arl || 0) + (r.pen || 0) + (r.caja || 0))
                + ceil(r.admon || 0) + ceil(r.seguro || 0) + ceil(r.iva || 0);
            _totalAfil = _cfg.costoAfiliacion || 0;
        }

        recalc();
    }

    // ── Verificar mes pagado (individual) ─────────────────────────
    async function _verificarMesPagado() {
        if (_modo !== 'individual' || !_cfg.contratoId) return;
        const mes = parseInt(el('mf-mes')?.value);
        const anio = parseInt(el('mf-anio')?.value);
        try {
            const url = _cfg.urlMesPagado + '/' + _cfg.contratoId + '?mes=' + mes + '&anio=' + anio;
            const data = await fetch(url, { headers: { 'X-CSRF-TOKEN': _cfg.csrf } }).then(r => r.json());
            const avisoMes = el('mf-aviso-mes');
            const saldoPanel = el('mf-saldos-panel');
            const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

            if (data.pagado) {
                setVal('mf-mes', data.mes);
                setVal('mf-anio', data.anio);
                if (avisoMes) {
                    avisoMes.style.display = 'block';
                    avisoMes.style.background = '#fef3c7';
                    avisoMes.style.borderColor = '#f59e0b';
                    avisoMes.style.color = '#78350f';
                    avisoMes.textContent = 'El mes ' + meses[mes - 1] + ' ya está facturado. Facturando ' + meses[data.mes - 1] + ' ' + data.anio;
                }
            } else {
                if (avisoMes) avisoMes.style.display = 'none';
            }

            // ── Aviso de gap (mes sin facturar antes del periodo seleccionado)
            let gapPanel = el('mf-aviso-gap');
            if (!gapPanel) {
                // Crear elemento si no existe
                gapPanel = document.createElement('div');
                gapPanel.id = 'mf-aviso-gap';
                gapPanel.style.cssText = 'display:none;margin:.4rem 0;padding:.45rem .7rem;border-radius:8px;font-size:.78rem;font-weight:600;border:1.5px solid #ef4444;background:#fef2f2;color:#991b1b;';
                // Insertarlo despues del aviso de mes
                if (avisoMes) avisoMes.parentNode.insertBefore(gapPanel, avisoMes.nextSibling);
            }
            if (data.tiene_gap && data.gap_mensaje) {
                gapPanel.style.display = 'block';
                gapPanel.innerHTML = '🚫 ' + data.gap_mensaje;
                // Deshabilitar botón de submit
                const btn = el('mf-btn-submit');
                if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }
            } else {
                gapPanel.style.display = 'none';
                const btn = el('mf-btn-submit');
                if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
            }

            _saldoFavor = parseInt(data.saldo_a_favor || 0);
            _saldoPendiente = parseInt(data.saldo_pendiente || 0);

            if (saldoPanel) {
                if (_saldoFavor > 0 || _saldoPendiente > 0) {
                    saldoPanel.style.display = 'flex';
                    saldoPanel.style.flexDirection = 'column';
                    let html = '';
                    if (_saldoFavor > 0) html += '<span class="mf-badge-favor">✅ Saldo a favor: ' + fmt(_saldoFavor) + ' (se descuenta del total)</span>';
                    if (_saldoPendiente > 0) html += '<span class="mf-badge-pendiente">⚠️ Saldo pendiente: ' + fmt(_saldoPendiente) + ' (se suma al total)</span>';
                    saldoPanel.innerHTML = html;
                } else {
                    saldoPanel.style.display = 'none';
                }
            }

            recalc();
        } catch (e) {
            console.warn('MF.verificarMesPagado error:', e);
        }
    }

    // ── Fetch saldos masivo (empresa) ─────────────────────────────
    async function _fetchSaldosMasivo() {
        if (_modo !== 'masivo' || !_cfg.urlSaldosContratos) return;
        const mes = parseInt(el('mf-mes')?.value || 0);
        const anio = parseInt(el('mf-anio')?.value || 0);
        if (!mes || !anio) return;

        const ids = _selContratos.map(c => c.id).filter(Boolean);
        if (!ids.length) return;

        try {
            const params = ids.map(id => 'contratos[]=' + id).join('&');
            const url = _cfg.urlSaldosContratos + '?' + params + '&mes=' + mes + '&anio=' + anio;
            const data = await fetch(url, { headers: { 'X-CSRF-TOKEN': _cfg.csrf } }).then(r => r.json());

            _saldoFavor = parseInt(data.total_a_favor || 0);
            _saldoPendiente = parseInt(data.total_pendiente || 0);

            const saldoPanel = el('mf-saldos-panel');
            if (saldoPanel) {
                if (_saldoFavor > 0 || _saldoPendiente > 0) {
                    saldoPanel.style.display = 'flex';
                    saldoPanel.style.flexDirection = 'column';
                    saldoPanel.style.gap = '.3rem';
                    let html = '';
                    // Anticipo arriba — solo el total, sin desglose por persona
                    if (_saldoFavor > 0) html += '<span class="mf-badge-favor">✅ Anticipo a favor: ' + fmt(_saldoFavor) + ' (se descuenta del pendiente)</span>';
                    if (_saldoPendiente > 0) html += '<span class="mf-badge-pendiente">⚠️ Cartera pendiente: ' + fmt(_saldoPendiente) + ' (se suma al total)</span>';
                    saldoPanel.innerHTML = html;
                } else {
                    saldoPanel.style.display = 'none';
                }
            }

            // ── Autocompletar efectivo sugerido con descuento de anticipo ────
            // Si hay anticipo a favor, el efectivo a ingresar = totalBruto - saldoFavor.
            // Esto evita que el usuario ingrese el total bruto completo sin
            // considerar que el anticipo ya cubre parte del cobro.
            if (_saldoFavor > 0) {
                const efInp = el('mf-efectivo');
                if (efInp && parse(efInp.value) === 0) {
                    // Solo si el campo aún está en 0 (sin edición manual)
                    const neto = Math.max(0, _total - _saldoFavor + _saldoPendiente);
                    setVal('mf-efectivo', neto);
                    recalc(); // actualizar el saldo a pagar con el nuevo valor
                }
            }
        } catch (e) {
            console.warn('MF._fetchSaldosMasivo error:', e);
        }
    }

    // ── Detectar tipo (individual) ────────────────────────────────
    function detectarTipo() {
        if (_modo !== 'individual') return;
        const mes = parseInt(el('mf-mes')?.value);
        const anio = parseInt(el('mf-anio')?.value);
        const esPrimMes = _cfg.fechaIngresoMes > 0
            && mes === _cfg.fechaIngresoMes
            && anio === _cfg.fechaIngresoAnio;

        const avisoEl = el('mf-aviso-tipo');
        const indepOpts = el('mf-indep-opts');

        if (!esPrimMes) {
            if (avisoEl) avisoEl.style.display = 'none';
            if (indepOpts) indepOpts.style.display = 'none';
            _setTipo('planilla');
            return;
        }

        if (!_cfg.esIndependiente) {
            if (avisoEl) {
                avisoEl.style.display = 'block';
                avisoEl.innerHTML = 'Mes de afiliación — Se factura como <strong>AFILIACIÓN</strong> (primer mes del contrato)';
            }
            if (indepOpts) indepOpts.style.display = 'none';
            _setTipo('afiliacion');
        } else {
            if (avisoEl) avisoEl.style.display = 'none';
            if (indepOpts) indepOpts.style.display = 'block';
            actualizarTipo();
        }
    }

    function actualizarTipo() {
        const modo = document.querySelector('input[name="mf_indep_modo"]:checked')?.value || 'afiliacion';

        if (modo === 'ambos') {
            // Planilla + Afiliación: mostrar SS normal Y agregar fila afiliación
            _setTipo('planilla');

            // Agregar la afiliación como ítem adicional en el desglose
            if (_totalAfil > 0) {
                const rowAfil = el('mf-row-afil');
                if (rowAfil) {
                    rowAfil.style.display = '';
                    setText('mf-v-afil', fmt(_totalAfil));
                }
            }
            recalc(); // recalc() ya suma mf-v-afil al total
        } else {
            _setTipo('afiliacion');
        }
    }

    function _setTipo(tipo) {
        setVal('mf-tipo', tipo);
        const detallesSS = el('mf-detalle-ss');
        const detallesAfil = el('mf-detalle-afil');
        const distSec = el('mf-dist-sec');

        if (tipo === 'afiliacion') {
            _total = _totalAfil;
            if (detallesSS) detallesSS.style.display = 'none';
            if (detallesAfil) {
                detallesAfil.style.display = 'block';
                detallesAfil.innerHTML = 'Costo afiliación: <strong>' + fmt(_totalAfil) + '</strong>';
            }
            if (distSec) {
                distSec.style.display = 'block';
                _distInicial();
            }
        } else {
            // Planilla: restaurar SS desde Alpine (individual) o de los datos (masivo)
            if (_modo === 'individual') {
                const r = (_cfg.getAlpineResult && _cfg.getAlpineResult()) || {};
                setText('mf-v-eps', fmt(r.eps || 0));
                setText('mf-v-arl', fmt(r.arl || 0));
                setText('mf-v-afp', fmt(r.pen || 0));
                setText('mf-v-caja', fmt(r.caja || 0));
                setText('mf-v-ss', fmt(r.ss || 0));
                setText('mf-v-admon', fmt(r.admon || 0));
                setText('mf-v-seg', fmt(r.seguro || 0));
                setText('mf-v-iva', fmt(r.iva || 0));
                _total = Math.round(r.total || 0);
            }
            if (detallesSS) detallesSS.style.display = '';
            if (detallesAfil) detallesAfil.style.display = 'none';
            if (distSec) distSec.style.display = 'none';
            // En modo masivo: mantener fila afiliación visible si hay I ACT primer mes
            if (_modo === 'masivo') {
                _calcularResumenInicial(); // reconstruye con afiliación si aplica
                return; // _calcularResumenInicial ya llama recalc()
            }
        }

        setText('mf-total', fmt(_total));
        recalc();
    }

    // ── Aviso I ACT primer mes en modo masivo ─────────────────────
    function _mostrarAvisoIndActMasivo() {
        const hayIndAct = _selContratos.some(c => c.esindact);
        const avisoEl = el('mf-aviso-tipo');
        if (!avisoEl) return;
        if (hayIndAct) {
            avisoEl.style.display = 'block';
            avisoEl.className = 'mf-alert mf-alert-purple';
            const cuantos = _selContratos.filter(c => c.esindact).length;
            avisoEl.innerHTML = '⚡ <strong>' + cuantos + ' independiente(s) I ACT</strong> — pagan Afiliación + Planilla juntas este mes. El pago parcial deja la factura en estado <strong>Abono</strong>.';
        } else {
            avisoEl.style.display = 'none';
        }
    }

    // ── Cambio de período (llama a re-detectar tipo en individual) ─
    function cambiarPeriodo() {
        if (_modo === 'individual') {
            _verificarMesPagado().then(() => detectarTipo());
        }
    }

    // ── Estado (pagada/pre_factura/prestamo) ──────────────────────
    function onEstado(skip) {
        const estado = el('mf-estado')?.value;
        const w = el('mf-prest-wrap');
        if (w) w.style.display = estado === 'prestamo' ? 'flex' : 'none';
        if (!skip) recalc();
    }

    // ── Recalcular total y pendiente ──────────────────────────────
    function recalc() {
        const tipo = el('mf-tipo')?.value;
        let totalBruto = _total;

        if (tipo !== 'afiliacion') {
            const eps  = parse(el('mf-v-eps')?.textContent);
            const arl  = parse(el('mf-v-arl')?.textContent);
            const afp  = parse(el('mf-v-afp')?.textContent);
            const caja = parse(el('mf-v-caja')?.textContent);
            const admon  = parse(el('mf-v-admon')?.textContent);
            const seg    = parse(el('mf-v-seg')?.textContent);
            const iva    = parse(el('mf-v-iva')?.textContent);
            const otros  = parse(el('mf-otros')?.value);
            const otrosA = parse(el('mf-otros-admon')?.value);
            const afilVal = parse(el('mf-v-afil')?.textContent);
            const ss = eps + arl + afp + caja;

            setText('mf-v-ss', fmt(ss));

            // ── Total BRUTO: lo que se cobra sin aplicar ningún saldo ──────
            // Se muestra en la columna izquierda para que el usuario vea
            // cuánto vale la planilla completa antes de cualquier descuento.
            totalBruto = ss + admon + seg + iva + otros + otrosA + afilVal + _saldoPendiente;
        } else {
            totalBruto = _totalAfil;
        }

        // ── Columa izquierda: TOTAL BRUTO (sin descontar saldo a favor) ──
        setText('mf-total', fmt(totalBruto));

        // ── Columna derecha: SALDO PENDIENTE ─────────────────────────────
        // Pendiente = totalBruto - saldoFavor - consignaciones - efectivo - prestamo
        // El saldo a favor se descuenta AQUÍ (como anticipó ya registrado).
        const consigs   = [...document.querySelectorAll('.mf-consig-monto')].reduce((s, e) => s + parse(e.value), 0);
        const efect     = parse(el('mf-efectivo')?.value);
        const prest     = parse(el('mf-prestamo')?.value);
        const pendiente = Math.max(0, totalBruto - _saldoFavor - consigs - efect - prest);

        const pEl = el('mf-pendiente');
        if (pEl) {
            pEl.textContent = fmt(pendiente);
            pEl.style.color      = pendiente === 0 ? '#15803d' : '#dc2626';
            pEl.style.fontWeight = pendiente === 0 ? '700' : '900';
        }
        return pendiente;
    }

    // ── Distribución de afiliación ────────────────────────────────
    function _distInicial() {
        const d = _cfg.distDefaults || {};
        setVal('mf-dist-asesor', d.asesor || 0);
        setVal('mf-dist-retiro', d.retiro || 0);
        setVal('mf-dist-encargado', d.encargado || 0);
        distRecalc();
    }

    function distRecalc() {
        const total = _totalAfil;
        const asesor = parse(el('mf-dist-asesor')?.value);
        const retiro = parse(el('mf-dist-retiro')?.value);
        const encargado = parse(el('mf-dist-encargado')?.value);
        const admon = total - asesor - retiro - encargado;

        const elAdmon = el('mf-dist-admon');
        const elAviso = el('mf-dist-aviso');

        if (elAdmon) {
            elAdmon.textContent = fmt(Math.max(0, admon));
            elAdmon.style.color = admon < 0 ? '#dc2626' : '#1d4ed8';
        }
        if (elAviso) {
            if (admon < 0) {
                elAviso.style.display = 'block';
                elAviso.textContent = '⚠️ La suma distribuida supera el costo de afiliación en ' + fmt(-admon);
            } else {
                elAviso.style.display = 'none';
            }
        }
    }

    // ── Consignaciones dinámicas ────────────────────────────
    function addConsig(banco, monto, fecha, referencia) {
        const today = new Date().toISOString().split('T')[0];
        const row = document.createElement('div');
        row.className = 'mf-consig-row';
        // Grid: banco | monto | fecha | referencia | imagen | del
        row.style.gridTemplateColumns = '2fr 90px 100px 100px 34px 22px';
        row.innerHTML = `
            <select class="mf-consig-sel mf-consig-banco">${bancoOptions(banco || '')}</select>
            <input type="text" class="mf-consig-monto-inp mf-consig-monto" value="${monto || '0'}" placeholder="$0" oninput="MF.recalc()">
            <input type="date" class="mf-consig-fecha-inp mf-consig-fecha" value="${fecha || today}">
            <input type="text" class="mf-consig-fecha-inp mf-consig-ref" placeholder="Referencia" value="${referencia || ''}" style="font-size:.67rem">
            <label class="mf-consig-img-lbl" title="Adjuntar soporte (imagen/PDF)">
                <span class="mf-consig-img-icon">📎</span>
                <input type="file" class="mf-consig-img-inp" accept="image/jpeg,image/png,image/webp,application/pdf" style="display:none">
            </label>
            <button type="button" class="mf-consig-del" onclick="this.closest('.mf-consig-row').remove();MF.recalc();">\xD7</button>
        `;
        // Actualizar icono al seleccionar archivo
        row.querySelector('.mf-consig-img-inp').addEventListener('change', function () {
            const icon = this.closest('.mf-consig-img-lbl').querySelector('.mf-consig-img-icon');
            if (this.files && this.files[0]) {
                icon.textContent = '\uD83D\uDDBC\uFE0F';
                icon.style.color = '#22c55e';
                this.closest('.mf-consig-img-lbl').title = this.files[0].name;
            } else {
                icon.textContent = '\uD83D\uDCCE';
                icon.style.color = '';
                this.closest('.mf-consig-img-lbl').title = 'Adjuntar soporte';
            }
        });
        el('mf-consig-list').appendChild(row);
        // Al agregar consignación, limpiar efectivo para que el saldo pendiente
        // muestre cuánto queda por cubrir con la(s) consignación(es)
        setVal('mf-efectivo', '0');
        recalc();
    }

    // ── Guardar factura ───────────────────────────────────────────
    async function guardar() {
        const pendiente = recalc();
        if (pendiente > 0) {
            // Calcular cuánto se ha ingresado
            const consigs  = [...document.querySelectorAll('.mf-consig-monto')].reduce((s, e) => s + parse(e.value), 0);
            const efectivo = parse(el('mf-efectivo')?.value);
            const prestamo = parse(el('mf-prestamo')?.value);
            const cubierto = consigs + efectivo + prestamo;
            const totalBruto = parse(el('mf-total')?.textContent);

            const lineas = [
                '⚠️  PAGO INCOMPLETO — No se puede facturar',
                '─'.repeat(44),
                '📋  Total bruto:         ' + fmt(totalBruto),
                ...(_saldoFavor > 0 ? ['✅  Anticipo a favor:    -' + fmt(_saldoFavor)] : []),
                '💰  Neto a cubrir:       ' + fmt(Math.max(0, totalBruto - _saldoFavor)),
                '📩  Ya cubierto:         ' + fmt(cubierto) +
                (consigs  > 0 ? '  (consig: ' + fmt(consigs)  + ')' : '') +
                (efectivo > 0 ? '  (efect: '  + fmt(efectivo) + ')' : '') +
                (prestamo > 0 ? '  (prest: '  + fmt(prestamo) + ')' : ''),
                '🔴  Falta por cubrir:    ' + fmt(pendiente),
                '',
                '📋  Para completar el pago puede:',
                '   • Agregar una o más consignaciones bancarias',
                '   • Ingresar el monto en "Valor en efectivo"',
                '   • Activar "Préstamo" y registrar el valor',
                '   • Combinar cualquiera de las opciones anteriores',
            ];
            alert(lineas.join('\n'));
            return;
        }

        const tipoActual = el('mf-tipo')?.value;

        // Validar distribución si es afiliación
        let distAsesor = 0, distRetiro = 0, distEncargado = 0, distAdmon = 0;
        if (tipoActual === 'afiliacion') {
            distAsesor = parse(el('mf-dist-asesor')?.value);
            distRetiro = parse(el('mf-dist-retiro')?.value);
            distEncargado = parse(el('mf-dist-encargado')?.value);
            distAdmon = Math.max(0, _totalAfil - distAsesor - distRetiro - distEncargado);
            if ((distAsesor + distRetiro + distEncargado) > _totalAfil) {
                alert('La distribución supera el costo de afiliación. Corrija los valores.');
                return;
            }
        }

        // IDs de contratos
        const ids = _selContratos.map(c => String(c.id || c));

        // Armar array dinámico de consignaciones
        const consigRows = [...document.querySelectorAll('.mf-consig-row')];
        const consignaciones = consigRows.map(r => ({
            banco_cuenta_id: r.querySelector('.mf-consig-banco')?.value || null,
            valor: parse(r.querySelector('.mf-consig-monto')?.value),
            fecha: r.querySelector('.mf-consig-fecha')?.value || null,
            referencia: r.querySelector('.mf-consig-ref')?.value || null,
        })).filter(c => c.banco_cuenta_id && c.valor > 0);

        const totalConsig = consignaciones.reduce((s, c) => s + c.valor, 0);
        const efect = parse(el('mf-efectivo')?.value);
        const prest = parse(el('mf-prestamo')?.value);

        // Construir descripción para obs_factura
        const detalles = consignaciones.map((c, i) => {
            const opt = document.querySelectorAll('.mf-consig-banco')[i];
            const label = opt?.options[opt.selectedIndex]?.text || 'Banco';
            return 'C' + (i + 1) + ': ' + label + ' ' + fmt(c.valor) + ' ' + (c.fecha || '');
        }).join(' | ');
        const obs = [detalles, el('mf-obs')?.value].filter(Boolean).join(' — ');

        // forma_pago simplificada
        let formaPago = 'efectivo';
        if (totalConsig > 0 && efect > 0) formaPago = 'mixto';
        else if (totalConsig > 0) formaPago = 'consignacion';
        else if (prest > 0) formaPago = 'prestamo';

        const btn = el('mf-btn-guardar');
        if (btn) { btn.disabled = true; btn.textContent = '⏳ Guardando...'; }

        try {
            // Leer días proporcionales del cotizador
            const diasFacturar = parseInt(document.getElementById('sel_dias_cotizar')?.value || 30);

            const body = {
                contratos: ids,
                tipo: tipoActual,
                mes: el('mf-mes')?.value,
                anio: el('mf-anio')?.value,
                dias: diasFacturar,
                forma_pago: formaPago,
                estado: el('mf-estado')?.value,
                consignaciones: consignaciones,   // ← array dinámico
                valor_efectivo: efect,
                valor_prestamo: prest,
                otros: parse(el('mf-otros')?.value),
                otros_admon: parse(el('mf-otros-admon')?.value),
                mensajeria: 0,
                observacion: obs,
                np: parse(el('mf-nplano')?.value) || null,
                empresa_id: _cfg.empresaId || null,
                // SS manual — SOLO en modo individual (1 contrato).
                // En masivo, el modal muestra TOTALES del batch, no valores individuales.
                // El servidor calcula SS por contrato individualmente con los días reales.
                ...(ids.length === 1 ? {
                    v_eps_manual: parse(el('mf-v-eps')?.textContent),
                    v_arl_manual: parse(el('mf-v-arl')?.textContent),
                    v_afp_manual: parse(el('mf-v-afp')?.textContent),
                    v_caja_manual: parse(el('mf-v-caja')?.textContent),
                } : {}),
                // Distribución afiliación
                dist_asesor: distAsesor,
                dist_retiro: distRetiro,
                dist_encargado: distEncargado,
                dist_admon: distAdmon,
            };


            const res = await fetch(_cfg.urlFacturar, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _cfg.csrf },
                body: JSON.stringify(body),
            });

            // ── Detectar 419 (CSRF expirado) o 401 (sesión caducada) ────────
            // Ocurre cuando la sesión de BD tarda y el token se invalida.
            // En lugar de crashear con JSON.parse(), recargamos el token y avisamos.
            if (res.status === 419 || res.status === 401) {
                // Intentar refrescar el token CSRF sin recargar la página completa
                try {
                    const csrfRes = await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
                    // Actualizar token en el meta tag y en _cfg
                    const newToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    if (newToken) _cfg.csrf = newToken;
                } catch (_) { /* ignorar */ }
                if (btn) { btn.disabled = false; btn.textContent = '💾 Guardar Factura'; }
                alert('⚠️ La sesión de seguridad expiró durante el proceso.\n\nPor favor:\n1. Presiona F5 para refrescar\n2. Vuelve a abrir el modal y guarda de nuevo.\n\nTus datos NO se perdieron — la base de datos está intacta.');
                return;
            }

            const data = await res.json();

            if (data.ok) {
                // Subir imágenes de soporte si el usuario las seleccionó
                const rows = [...document.querySelectorAll('.mf-consig-row')];
                const uploads = [];
                rows.forEach((row, idx) => {
                    const inp = row.querySelector('.mf-consig-img-inp');
                    const consigId = data.consignacion_ids && data.consignacion_ids[idx];
                    if (inp && inp.files && inp.files[0] && consigId) {
                        const fd = new FormData();
                        fd.append('imagen', inp.files[0]);
                        fd.append('_token', _cfg.csrf);
                        const url = (_cfg.urlConsignacionImagen || '').replace('__ID__', consigId);
                        uploads.push(fetch(url, { method: 'POST', body: fd }));
                    }
                });
                if (uploads.length) await Promise.all(uploads);

                cerrar();
                if (data.recibo_url) window.open(data.recibo_url, '_blank');
                // Notificar al contexto padre
                if (typeof _cfg.onExito === 'function') {
                    _cfg.onExito(data);
                } else {
                    location.reload();
                }
            } else {
                // Mostrar mensaje diferenciado según tipo de error
                let msg = data.message || data.mensaje || 'Error al facturar.';
                // Gap de facturación: mes sin facturar previo
                if (data.error && data.mensaje && data.mes_gap) {
                    msg = '🚫 ' + data.mensaje;
                    // Mostrar en el panel de gap del modal (si sigue abierto)
                    let gapPanel = el('mf-aviso-gap');
                    if (!gapPanel) {
                        gapPanel = document.createElement('div');
                        gapPanel.id = 'mf-aviso-gap';
                        gapPanel.style.cssText = 'margin:.4rem 0;padding:.45rem .7rem;border-radius:8px;font-size:.78rem;font-weight:600;border:1.5px solid #ef4444;background:#fef2f2;color:#991b1b;';
                        const avisoMes = el('mf-aviso-mes');
                        if (avisoMes) avisoMes.parentNode.insertBefore(gapPanel, avisoMes.nextSibling);
                    }
                    gapPanel.style.display = 'block';
                    gapPanel.innerHTML = msg;
                } else {
                    if (data.omitidos && data.omitidos.length > 0) {
                        const lista = data.omitidos.map(o => '• ' + o.nombre + ' (' + o.motivo + ')').join('\n');
                        msg += '\n\n🚫 Ya facturados para este período:\n' + lista;
                    }
                    alert(msg);
                }
            }
        } catch (e) {
            console.error('MF.guardar error:', e);
            // Si el error es JSON parse → casi siempre es 419/sesión expirada
            // (Laravel devuelve HTML de login en vez de JSON)
            let msg;
            if (e && (e.message || '').toLowerCase().includes('json')) {
                msg = '⚠️ Sesión expirada durante el guardado.\n\nPor favor recarga la página (F5) e intenta de nuevo.\n\nTus datos están seguros — la factura puede o no haberse guardado. Verifica en la lista antes de volver a facturar.';
            } else {
                msg = 'Error de conexión: ' + (e.message || 'desconocido') + '\nRecargue la página e intente de nuevo.';
            }
            alert(msg);
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = '🧾 Facturar'; }
        }
    }

    // ── Cerrar modal ──────────────────────────────────────────────
    function cerrar() {
        const ov = el('mf-overlay');
        if (ov) ov.style.display = 'none';
    }

    // ── API pública ───────────────────────────────────────────────
    return { init, abrir, cerrar, detectarTipo, actualizarTipo, cambiarPeriodo, onEstado, recalc, distRecalc, addConsig, guardar };

})();


