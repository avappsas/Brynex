{{--
    ╔══════════════════════════════════════════════════════════╗
    ║  PARTIAL: _modal_cobros_adicionales.blade.php  —  BryNex  ║
    ║  Modal para gestionar cobros adicionales de la empresa   ║
    ╚══════════════════════════════════════════════════════════╝
--}}
<style>
/* ═══════════════════════════════════════════════════════════════
   Modal Cobros Adicionales — BryNex Premium Design
   ═══════════════════════════════════════════════════════════════ */
#mca-overlay {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(8px);
    z-index: 2100;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
    animation: mca-backdropFade 0.2s ease-out;
}
@keyframes mca-backdropFade {
    from { background: rgba(15, 23, 42, 0); }
    to { background: rgba(15, 23, 42, 0.45); }
}

#mca-box {
    background: #fff; border-radius: 20px;
    width: min(780px, 98vw); max-height: 90vh;
    overflow: hidden; display: flex; flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25), 0 0 0 1px rgba(15, 23, 42, 0.08);
    animation: mca-modalScale 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes mca-modalScale {
    from { opacity: 0; transform: scale(0.92) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* ── HEADER ── */
#mca-header {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    padding: 1.1rem 1.4rem;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
    position: relative;
}
#mca-header::after {
    content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1px;
    background: rgba(255, 255, 255, 0.1);
}
#mca-header-icon {
    width: 38px; height: 38px; border-radius: 10px;
    background: rgba(255, 255, 255, 0.16);
    display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
    box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.2);
}
#mca-header-text h2 { font-size: 1rem; font-weight: 800; color: #fff; margin: 0; letter-spacing: -0.01em; }
#mca-header-text p  { font-size: .72rem; color: rgba(255, 255, 255, 0.8); margin: 2px 0 0; }
#mca-close-btn {
    width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer;
    background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.9); font-size: 1rem;
    display: flex; align-items: center; justify-content: center; transition: all .2s;
}
#mca-close-btn:hover { background: rgba(255, 255, 255, 0.2); color: #fff; transform: rotate(90deg); }

/* ── BODY ── */
#mca-body {
    padding: 1.4rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1.4rem;
}

/* Sección Formulario */
.mca-sec-title {
    font-size: .78rem; font-weight: 800; color: #0369a1;
    text-transform: uppercase; letter-spacing: .08em;
    margin-bottom: .7rem; padding-bottom: .35rem;
    border-bottom: 2px solid #f0f9ff;
    display: flex; align-items: center; gap: .5rem;
}
.mca-form-container {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.1rem;
    box-shadow: inset 0 1px 2px rgba(248, 250, 252, 0.5);
}
.mca-form-row {
    display: grid; grid-template-columns: 2.2fr 0.6fr 1fr;
    gap: .9rem;
}
@media (max-width: 640px) {
    .mca-form-row { grid-template-columns: 1fr; gap: .75rem; }
}

.mca-field { display: flex; flex-direction: column; gap: .35rem; min-width: 0; }
.mca-label { font-size: .65rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: .04em; }
.mca-input, .mca-select {
    width: 100%;
    box-sizing: border-box;
    padding: .55rem .75rem; border: 1.5px solid #cbd5e1; border-radius: 10px;
    font-size: .82rem; outline: none; transition: border-color 0.15s, box-shadow 0.15s;
    background: #fff; font-family: inherit; color: #1e293b;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.mca-input:focus, .mca-select:focus {
    border-color: #0284c7; box-shadow: 0 0 0 3.5px rgba(2, 132, 199, 0.12);
}
.mca-btn-add {
    padding: .55rem 1.4rem; background: linear-gradient(135deg, #0284c7, #0369a1);
    color: #fff; border: none; border-radius: 10px; font-size: .82rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; height: 38px;
    display: inline-flex; align-items: center; gap: .4rem; box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.2);
}
.mca-btn-add:hover { opacity: 0.95; transform: translateY(-1px); box-shadow: 0 6px 12px -2px rgba(2, 132, 199, 0.3); }
.mca-btn-add:active { transform: translateY(0); }

/* Sección Listado */
#mca-list-wrap {
    min-height: 120px; max-height: 300px; overflow-y: auto;
    border: 1px solid #e2e8f0; border-radius: 12px; background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.mca-table { width: 100%; border-collapse: collapse; text-align: left; }
.mca-table th {
    background: #f8fafc; padding: .65rem .9rem; font-size: .68rem;
    font-weight: 800; color: #475569; text-transform: uppercase;
    border-bottom: 1.5px solid #e2e8f0; letter-spacing: .05em;
}
.mca-table td {
    padding: .75rem .9rem; border-bottom: 1px solid #f1f5f9; font-size: .82rem; vertical-align: middle;
}
.mca-table tr:hover td { background-color: #f8fafc; }
.mca-table tr:last-child td { border-bottom: none; }
.mca-desc-text { font-weight: 600; color: #1e293b; line-height: 1.4; }
.mca-type-badge {
    display: inline-flex; align-items: center; padding: .15rem .6rem; border-radius: 9999px;
    font-size: .68rem; font-weight: 700; border: 1px solid transparent;
}
.mca-badge-recurrente { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }
.mca-badge-unica { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.mca-val-text { font-family: monospace; font-weight: 700; color: #0f172a; font-size: .88rem; }

.mca-btn-del {
    background: none; border: none; cursor: pointer; width: 28px; height: 28px;
    color: #ef4444; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
    transition: all 0.2s; border: 1px solid transparent;
}
.mca-btn-del:hover { background: #fee2e2; border-color: #fca5a5; transform: scale(1.05); }

/* Empty state style */
.mca-empty-container {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 2.2rem 1rem; color: #94a3b8; text-align: center; gap: .6rem;
}
.mca-empty-icon { font-size: 2rem; filter: grayscale(0.2); opacity: 0.8; }
.mca-empty-text { font-size: .82rem; font-weight: 500; color: #64748b; margin: 0; }

/* ── FOOTER ── */
#mca-footer {
    border-top: 1px solid #cbd5e1; padding: 1rem 1.4rem;
    display: flex; justify-content: flex-end; gap: .5rem; background: #f8fafc;
    flex-shrink: 0;
}
.mca-btn-close {
    padding: .5rem 1.4rem; background: #fff; color: #334155;
    border: 1.5px solid #cbd5e1; border-radius: 10px; cursor: pointer;
    font-size: .82rem; font-weight: 600; transition: all .15s;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.mca-btn-close:hover { background: #f1f5f9; border-color: #94a3b8; }
</style>

{{-- ════════════════════ OVERLAY ════════════════════ --}}
<div id="mca-overlay" style="display:none;" onclick="if(event.target===this)MCA.cerrar()">
<div id="mca-box" onclick="event.stopPropagation()">

    {{-- HEADER --}}
    <div id="mca-header">
        <div style="display:flex;align-items:center;gap:.75rem;">
            <div id="mca-header-icon">⚙️</div>
            <div id="mca-header-text">
                <h2>Cobros Adicionales de la Empresa</h2>
                <p id="mca-subtitle">Administrar conceptos adicionales facturados a la empresa</p>
            </div>
        </div>
        <button id="mca-close-btn" onclick="MCA.cerrar()" title="Cerrar">✕</button>
    </div>

    {{-- BODY --}}
    <div id="mca-body">

        {{-- Formulario para Agregar --}}
        <div>
            <div class="mca-sec-title">➕ Agregar Cobro Adicional</div>
            <div class="mca-form-container">
                <div class="mca-form-row">
                    <div class="mca-field">
                        <span class="mca-label">Descripción / Concepto *</span>
                        <input type="text" id="mca-desc-inp" class="mca-input" placeholder="Ej: Soporte técnico, papelería...">
                    </div>
                    <div class="mca-field">
                        <span class="mca-label">Valor ($) *</span>
                        <input type="number" id="mca-val-inp" class="mca-input" placeholder="0" min="0" style="text-align:right;font-family:monospace;font-weight:700;">
                    </div>
                    <div class="mca-field">
                        <span class="mca-label">Periodicidad / Destino *</span>
                        <select id="mca-tipo-inp" class="mca-select">
                            <option value="unica_vez" selected>Solo este mes (Única vez)</option>
                            <option value="recurrente">Guardar para futuros cobros (Recurrente)</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:.9rem;">
                    <button type="button" class="mca-btn-add" onclick="MCA.agregar()">
                        ➕ Agregar Cobro
                    </button>
                </div>
            </div>
        </div>

        {{-- Listado de Cobros --}}
        <div>
            <div class="mca-sec-title" style="margin-bottom:.5rem;">📋 Conceptos Activos para este Periodo</div>
            <div id="mca-list-wrap">
                <table class="mca-table">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th style="width:140px;text-align:center;">Tipo</th>
                            <th style="width:120px;text-align:right;">Valor</th>
                            <th style="width:70px;text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="mca-list-body">
                        <!-- Carga dinámica -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- FOOTER --}}
    <div id="mca-footer">
        <button class="mca-btn-close" onclick="MCA.cerrar()">Cerrar</button>
    </div>

</div>
</div>

<script>
const MCA = (() => {
    let _empresaId = null;
    let _hasChanges = false;
    let _csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    function _el(id) { return document.getElementById(id); }

    // Abrir Modal
    function abrir(empresaId) {
        _empresaId = empresaId;
        _hasChanges = false;

        // Reset inputs
        _el('mca-desc-inp').value = '';
        _el('mca-val-inp').value = '';
        _el('mca-tipo-inp').value = 'unica_vez';

        cargarCobros();
        _el('mca-overlay').style.display = 'flex';
    }

    // Cerrar Modal
    function cerrar() {
        _el('mca-overlay').style.display = 'none';
        if (_hasChanges) {
            // Si hubo cambios, recargar la pantalla padre para reflejar los totales
            location.reload();
        }
    }

    // Cargar Listado de Cobros
    async function cargarCobros() {
        const tbody = _el('mca-list-body');
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#64748b;font-style:italic;padding:2rem;">Cargando cobros adicionales...</td></tr>`;

        try {
            const res = await fetch(`/admin/facturacion/empresa/${_empresaId}/cobros-adicionales`);
            const data = await res.json();

            if (data.ok && data.cobros.length > 0) {
                tbody.innerHTML = data.cobros.map(c => {
                    const tipoText = c.tipo === 'recurrente' ? 'Recurrente' : 'Única vez';
                    const badgeClass = c.tipo === 'recurrente' ? 'mca-badge-recurrente' : 'mca-badge-unica';
                    const formattedVal = '$' + Math.round(c.valor).toLocaleString('es-CO');

                    return `
                        <tr>
                            <td class="mca-desc-text">${c.descripcion}</td>
                            <td style="text-align:center;">
                                <span class="mca-type-badge ${badgeClass}">${tipoText}</span>
                            </td>
                            <td class="mca-val-text" style="text-align:right;">${formattedVal}</td>
                            <td style="text-align:center;">
                                <button type="button" class="mca-btn-del" onclick="MCA.eliminar(${c.id})" title="Eliminar cobro">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4">
                            <div class="mca-empty-container">
                                <div class="mca-empty-icon">🍃</div>
                                <p class="mca-empty-text">No hay cobros adicionales registrados para esta empresa.</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
        } catch (e) {
            console.error(e);
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#ef4444;font-weight:600;padding:2rem;">Error al cargar los cobros.</td></tr>`;
        }
    }

    // Agregar Cobro Adicional
    async function agregar() {
        const desc = _el('mca-desc-inp').value.trim();
        const valor = parseFloat(_el('mca-val-inp').value);
        const tipo = _el('mca-tipo-inp').value;

        if (!desc) { alert('Por favor ingresa una descripción para el cobro.'); _el('mca-desc-inp').focus(); return; }
        if (isNaN(valor) || valor <= 0) { alert('Por favor ingresa un valor mayor a 0.'); _el('mca-val-inp').focus(); return; }

        try {
            const res = await fetch(`/admin/facturacion/empresa/${_empresaId}/cobros-adicionales`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': _csrf
                },
                body: JSON.stringify({ descripcion: desc, valor: valor, tipo: tipo })
            });
            const data = await res.json();

            if (data.ok) {
                _el('mca-desc-inp').value = '';
                _el('mca-val-inp').value = '';
                _el('mca-tipo-inp').value = 'unica_vez';
                _hasChanges = true;
                cargarCobros();
            } else {
                alert(data.mensaje || 'Error al guardar el cobro adicional.');
            }
        } catch (e) {
            console.error(e);
            alert('Error de red al intentar registrar el cobro.');
        }
    }

    // Eliminar Cobro Adicional
    async function eliminar(id) {
        if (!confirm('¿Seguro que deseas eliminar este cobro adicional?')) return;

        try {
            const res = await fetch(`/admin/facturacion/cobros-adicionales/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': _csrf
                }
            });
            const data = await res.json();

            if (data.ok) {
                _hasChanges = true;
                cargarCobros();
            } else {
                alert('Error al intentar eliminar el cobro.');
            }
        } catch (e) {
            console.error(e);
            alert('Error de red al intentar eliminar el cobro.');
        }
    }

    return { abrir, cerrar, agregar, eliminar };
})();
</script>
