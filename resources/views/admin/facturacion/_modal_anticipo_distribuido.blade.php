{{--
    ╔══════════════════════════════════════════════════════════╗
    ║  PARTIAL: _modal_anticipo_distribuido.blade.php  — BryNex ║
    ║  Modal multi-paso para registrar anticipos distribuidos ║
    ║  de empresas a clientes individuales.                   ║
    ╚══════════════════════════════════════════════════════════╝
--}}
@php
    $adBancos = $bancos ?? collect();
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
@endphp

<style>
/* ═══════════════════════════════════════════════════════════════
   Modal Anticipo Distribuido — BryNex
   Diseño Premium en tonos Ámbar / Dorado
   ═══════════════════════════════════════════════════════════════ */
#ad-overlay {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
    z-index: 2100;
    display: flex; align-items: center; justify-content: center;
    padding: .75rem;
}
#ad-box {
    background: #fff; border-radius: 18px;
    width: min(780px, 98vw); max-height: 96vh;
    overflow: hidden; display: flex; flex-direction: column;
    box-shadow: 0 32px 100px rgba(0,0,0,.35), 0 0 0 1px rgba(255,255,255,.05);
    animation: adScaleUp 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes adScaleUp {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

/* ── HEADER ── */
#ad-header {
    background: linear-gradient(135deg, #b45309 0%, #d97706 100%);
    padding: .9rem 1.3rem .8rem;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
#ad-header-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: rgba(255, 255, 255, 0.15);
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
}
#ad-header-text { margin-left: .8rem; flex: 1; }
#ad-header-text h2 { font-size: .95rem; font-weight: 800; color: #fff; margin: 0; }
#ad-header-text p { font-size: .68rem; color: rgba(255, 255, 255, 0.75); margin: 0; }
#ad-close-btn {
    width: 28px; height: 28px; border-radius: 7px; border: none; cursor: pointer;
    background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.85); font-size: .95rem;
    display: flex; align-items: center; justify-content: center; transition: background .15s;
}
#ad-close-btn:hover { background: rgba(255, 255, 255, 0.2); color: #fff; }

/* ── PROGRESS BAR ── */
#ad-progress-container {
    background: #fffbeb; border-bottom: 1px solid #fde68a;
    padding: .6rem 1.5rem; display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.ad-step-indicator {
    display: flex; align-items: center; gap: .45rem; font-size: .72rem; font-weight: 700; color: #92400e; opacity: 0.5;
    transition: opacity 0.2s, transform 0.2s;
}
.ad-step-indicator.active { opacity: 1; transform: scale(1.02); }
.ad-step-indicator.completed { opacity: 0.95; color: #16a34a; }
.ad-step-num {
    width: 20px; height: 20px; border-radius: 50%; background: #f59e0b; color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: .68rem;
}
.ad-step-indicator.completed .ad-step-num { background: #16a34a; }
.ad-step-line { flex: 1; height: 2px; background: #fcd34d; margin: 0 1rem; opacity: 0.4; }

/* ── BODY & STEPS ── */
#ad-body {
    flex: 1; overflow-y: auto; padding: 1.2rem 1.4rem; background: #fafafa; min-height: 0;
}
.ad-step-content { display: none; }
.ad-step-content.active { display: block; animation: adFadeIn 0.2s ease-out; }
@keyframes adFadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── FORM GROUPS ── */
.ad-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.9rem; }
.ad-form-group { display: flex; flex-direction: column; gap: .3rem; }
.ad-form-group.full { grid-column: 1 / span 2; }
.ad-label { font-size: .65rem; font-weight: 800; color: #451a03; text-transform: uppercase; letter-spacing: .04em; }
.ad-input, .ad-select, .ad-textarea {
    padding: .48rem .7rem; border: 1.5px solid #fcd34d; border-radius: 8px;
    font-size: .82rem; background: #fff; color: #0f172a; outline: none;
    transition: border-color 0.15s, box-shadow 0.15s; font-family: inherit;
}
.ad-input:focus, .ad-select:focus, .ad-textarea:focus {
    border-color: #d97706; box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
}

/* Paso 2: Selección de Clientes */
#ad-clients-search {
    padding: .45rem .7rem; border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: .8rem; width: 100%; box-sizing: border-box; margin-bottom: .8rem; outline: none;
}
#ad-clients-list-container {
    border: 1.5px solid #fde68a; border-radius: 10px; background: #fff;
    max-height: 220px; overflow-y: auto; padding: .4rem;
}
.ad-client-item {
    display: flex; align-items: center; gap: .6rem; padding: .42rem .6rem;
    border-radius: 7px; transition: background .12s; cursor: pointer;
    border-bottom: 1px solid #fef3c7;
}
.ad-client-item:last-child { border-bottom: none; }
.ad-client-item:hover { background: #fffbeb; }
.ad-client-item.selected { background: #fef3c7; }
.ad-client-checkbox {
    width: 15px; height: 15px; border-radius: 4px; border: 1.5px solid #d97706;
    cursor: pointer; accent-color: #d97706;
}
.ad-client-details { display: flex; flex-direction: column; flex: 1; }
.ad-client-name { font-size: .78rem; font-weight: 700; color: #1e293b; }
.ad-client-sub { font-size: .65rem; color: #64748b; display: flex; gap: .6rem; }
.ad-client-plan { color: #b45309; font-weight: 600; }

/* Paso 3: Distribución */
#ad-dist-table-container {
    border: 1.5px solid #fde68a; border-radius: 10px; background: #fff;
    max-height: 240px; overflow-y: auto; margin-bottom: .9rem;
}
.ad-dist-table {
    width: 100%; border-collapse: collapse; text-align: left; font-size: .78rem;
}
.ad-dist-table th {
    background: #fffbeb; color: #92400e; padding: .55rem .7rem;
    font-weight: 800; font-size: .62rem; text-transform: uppercase;
    border-bottom: 1.5px solid #fde68a; position: sticky; top: 0; z-index: 10;
}
.ad-dist-table td {
    padding: .4rem .7rem; border-bottom: 1px solid #fef3c7; vertical-align: middle;
}
.ad-dist-table tr:last-child td { border-bottom: none; }
.ad-dist-val-input {
    padding: .28rem .5rem; border: 1.5px solid #fcd34d; border-radius: 6px;
    font-size: .78rem; font-weight: 700; color: #0f172a; outline: none; width: 90px; text-align: right;
}
.ad-dist-val-input:focus { border-color: #d97706; }
.ad-dist-period-select {
    padding: .25rem .4rem; border: 1.5px solid #e2e8f0; border-radius: 6px;
    font-size: .72rem; outline: none; background: #fff;
}
.ad-dist-period-select:focus { border-color: #f59e0b; }

/* Indicadores de balance */
#ad-dist-balance {
    background: #fffbeb; border: 1.5px dashed #fde68a; border-radius: 10px;
    padding: .6rem .9rem; display: flex; align-items: center; justify-content: space-between;
}
.ad-balance-item { display: flex; flex-direction: column; gap: .1rem; }
.ad-balance-label { font-size: .58rem; font-weight: 800; color: #92400e; text-transform: uppercase; }
.ad-balance-val { font-size: .9rem; font-weight: 900; color: #1e293b; }
.ad-balance-val.match { color: #16a34a; }
.ad-balance-val.diff { color: #dc2626; }

/* ── FOOTER ── */
#ad-footer {
    background: #f8fafc; border-top: 1px solid #e2e8f0;
    padding: .75rem 1.3rem; display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0; border-radius: 0 0 18px 18px;
}
.ad-btn {
    padding: .42rem 1.1rem; border-radius: 8px; font-size: .78rem; font-weight: 700;
    cursor: pointer; border: none; display: flex; align-items: center; gap: .4rem;
    transition: background .15s, opacity .15s; font-family: inherit;
}
.ad-btn-secondary { background: #e2e8f0; color: #334155; }
.ad-btn-secondary:hover { background: #cbd5e1; }
.ad-btn-primary { background: #d97706; color: #fff; }
.ad-btn-primary:hover { background: #b45309; }
.ad-btn-primary:disabled { background: #fcd34d; color: rgba(255,255,255,0.7); cursor: not-allowed; }
</style>

<div id="ad-overlay" style="display: none;" onclick="MAD.cerrarSi(event)">
    <div id="ad-box">
        <!-- HEADER -->
        <div id="ad-header">
            <div style="display: flex; align-items: center;">
                <div id="ad-header-icon">💼</div>
                <div id="ad-header-text">
                    <h2 id="ad-title-label">Registrar Anticipo Empresa</h2>
                    <p id="ad-subtitle-label">Distribución entre empleados de la empresa</p>
                </div>
            </div>
            <button id="ad-close-btn" onclick="MAD.cerrar()">&times;</button>
        </div>

        <!-- PROGRESS BAR -->
        <div id="ad-progress-container">
            <div class="ad-step-indicator active" id="ad-ind-1">
                <span class="ad-step-num">1</span>
                <span>Datos del Pago</span>
            </div>
            <div class="ad-step-line"></div>
            <div class="ad-step-indicator" id="ad-ind-2">
                <span class="ad-step-num">2</span>
                <span>Seleccionar Clientes</span>
            </div>
            <div class="ad-step-line"></div>
            <div class="ad-step-indicator" id="ad-ind-3">
                <span class="ad-step-num">3</span>
                <span>Distribuir Montos</span>
            </div>
        </div>

        <!-- BODY -->
        <div id="ad-body">
            <form id="ad-form" onsubmit="event.preventDefault();" enctype="multipart/form-data">
                <input type="hidden" id="ad-empresa-id" name="empresa_id">

                <!-- PASO 1: Datos de pago -->
                <div class="ad-step-content active" id="ad-step-1">
                    <div class="ad-form-grid">
                        <div class="ad-form-group">
                            <label class="ad-label">Fecha de Pago</label>
                            <input type="date" id="ad-fecha" name="fecha_pago" class="ad-input" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="ad-form-group">
                            <label class="ad-label">Valor del Anticipo</label>
                            <input type="number" id="ad-valor" name="valor" class="ad-input" placeholder="Monto total pagado" required min="1000">
                        </div>
                    </div>

                    <div class="ad-form-grid">
                        <div class="ad-form-group">
                            <label class="ad-label">Forma de Pago</label>
                            <select id="ad-forma-pago" name="forma_pago" class="ad-select" onchange="MAD.toggleBanco()">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia / Banco</option>
                            </select>
                        </div>
                        <div class="ad-form-group" id="ad-banco-group" style="display: none;">
                            <label class="ad-label">Cuenta de Destino</label>
                            <select id="ad-banco" name="banco_cuenta_id" class="ad-select">
                                <option value="">Seleccione Cuenta...</option>
                                @foreach($adBancos as $b)
                                    <option value="{{ $b->id }}">{{ $b->descripcion }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="ad-form-grid">
                        <div class="ad-form-group">
                            <label class="ad-label">Referencia / Comprobante</label>
                            <input type="text" id="ad-referencia" name="referencia" class="ad-input" placeholder="N° de transferencia o nequi">
                        </div>
                        <div class="ad-form-group">
                            <label class="ad-label">Soporte de Pago (Imagen/PDF)</label>
                            <input type="file" id="ad-imagen" name="imagen" class="ad-input" accept="image/*,application/pdf">
                        </div>
                    </div>

                    <div class="ad-form-group full" style="margin-top: .3rem;">
                        <label class="ad-label">Observación</label>
                        <textarea id="ad-observacion" name="observacion" rows="2" class="ad-textarea" placeholder="Notas adicionales del registro..."></textarea>
                    </div>
                </div>

                <!-- PASO 2: Selección de clientes -->
                <div class="ad-step-content" id="ad-step-2">
                    <div style="display: flex; gap: .7rem; align-items: center; margin-bottom: .6rem;">
                        <div style="flex: 1;">
                            <input type="text" id="ad-search" placeholder="🔍 Buscar por nombre o cédula..." class="ad-clients-search" onkeyup="MAD.filtrarClientes()">
                        </div>
                        <div>
                            <button type="button" class="ad-btn ad-btn-secondary" style="padding: .35rem .7rem; font-size: .72rem;" onclick="MAD.seleccionarTodos(true)">
                                Seleccionar Todos
                            </button>
                        </div>
                        <div>
                            <button type="button" class="ad-btn ad-btn-secondary" style="padding: .35rem .7rem; font-size: .72rem;" onclick="MAD.seleccionarTodos(false)">
                                Limpiar
                            </button>
                        </div>
                    </div>

                    <div id="ad-clients-list-container">
                        <!-- Clientes cargados dinámicamente -->
                        <div style="text-align: center; padding: 2rem; color: #64748b; font-size: .8rem;">
                            Cargando contratos vigentes...
                        </div>
                    </div>
                    
                    <div style="margin-top: .5rem; font-size: .7rem; font-weight: 700; color: #475569;" id="ad-selected-count">
                        Clientes seleccionados: 0
                    </div>
                </div>

                <!-- PASO 3: Distribución y validación -->
                <div class="ad-step-content" id="ad-step-3">
                    <div style="margin-bottom: .5rem; font-size: .72rem; color: #475569;">
                        Distribuye el valor total del anticipo entre los clientes seleccionados. Por defecto se dividirá equitativamente.
                    </div>

                    <div id="ad-dist-table-container">
                        <table class="ad-dist-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Cédula</th>
                                    <th>Plan Vigente</th>
                                    <th style="width: 140px;">Mes Destino Info</th>
                                    <th style="text-align: right; width: 120px;">Monto Anticipo</th>
                                </tr>
                            </thead>
                            <tbody id="ad-dist-tbody">
                                <!-- Filas de distribución dinámicas -->
                            </tbody>
                        </table>
                    </div>

                    <!-- BALANCE -->
                    <div id="ad-dist-balance">
                        <div class="ad-balance-item">
                            <span class="ad-balance-label">Valor Total</span>
                            <span class="ad-balance-val" id="ad-bal-total">$0</span>
                        </div>
                        <div class="ad-balance-item">
                            <span class="ad-balance-label">Suma Distribuida</span>
                            <span class="ad-balance-val" id="ad-bal-suma">$0</span>
                        </div>
                        <div class="ad-balance-item" style="text-align: right;">
                            <span class="ad-balance-label">Diferencia / Restante</span>
                            <span class="ad-balance-val match" id="ad-bal-restante">$0</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- FOOTER -->
        <div id="ad-footer">
            <div>
                <button type="button" id="ad-btn-atras" class="ad-btn ad-btn-secondary" style="display: none;" onclick="MAD.atras()">
                    ← Atrás
                </button>
            </div>
            <div style="display: flex; gap: .5rem;">
                <button type="button" class="ad-btn ad-btn-secondary" onclick="MAD.cerrar()">
                    Cancelar
                </button>
                <button type="button" id="ad-btn-sig" class="ad-btn ad-btn-primary" onclick="MAD.siguiente()">
                    Siguiente →
                </button>
                <button type="button" id="ad-btn-registrar" class="ad-btn ad-btn-primary" style="display: none;" onclick="MAD.registrar()" disabled>
                    ✓ Registrar Anticipo
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Namespace para evitar colisión de variables en JS global
const MAD = {
    empresaId: null,
    contratos: [], // lista completa de contratos desde la API
    pasoActivo: 1,
    meses: @json($meses),

    abrir: function(empresaId, empresaNombre) {
        this.empresaId = empresaId;
        this.pasoActivo = 1;
        this.contratos = [];
        
        // Reset campos del form
        document.getElementById('ad-form').reset();
        document.getElementById('ad-empresa-id').value = empresaId;
        document.getElementById('ad-title-label').innerText = `Registrar Anticipo: ${empresaNombre}`;
        document.getElementById('ad-imagen').value = '';
        
        // Ocultar grupo banco
        document.getElementById('ad-banco-group').style.display = 'none';
        
        // Cargar contratos de la empresa
        const listContainer = document.getElementById('ad-clients-list-container');
        listContainer.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #64748b; font-size: .8rem;">
                <i class="fa fa-spinner fa-spin" style="margin-right: .3rem;"></i> Cargando contratos vigentes...
            </div>
        `;
        document.getElementById('ad-selected-count').innerText = 'Clientes seleccionados: 0';
        
        document.getElementById('ad-overlay').style.display = 'flex';
        this.irAPaso(1);

        fetch(`/admin/anticipos/api/contratos-empresa/${empresaId}`)
            .then(res => res.json())
            .then(data => {
                if (data.ok && data.contratos) {
                    this.contratos = data.contratos;
                    this.renderizarClientes();
                } else {
                    listContainer.innerHTML = `
                        <div style="text-align: center; padding: 2rem; color: #dc2626; font-size: .8rem;">
                            Error al cargar contratos: ${data.mensaje || 'Respuesta no válida'}
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error(err);
                listContainer.innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: #dc2626; font-size: .8rem;">
                        Error de red al cargar contratos.
                    </div>
                `;
            });
    },

    cerrar: function() {
        document.getElementById('ad-overlay').style.display = 'none';
    },

    cerrarSi: function(e) {
        if (e.target.id === 'ad-overlay') {
            this.cerrar();
        }
    },

    toggleBanco: function() {
        const forma = document.getElementById('ad-forma-pago').value;
        const bGroup = document.getElementById('ad-banco-group');
        const bancoInput = document.getElementById('ad-banco');
        
        if (forma === 'transferencia') {
            bGroup.style.display = 'flex';
            bancoInput.setAttribute('required', 'required');
        } else {
            bGroup.style.display = 'none';
            bancoInput.removeAttribute('required');
            bancoInput.value = '';
        }
    },

    renderizarClientes: function() {
        const listContainer = document.getElementById('ad-clients-list-container');
        if (this.contratos.length === 0) {
            listContainer.innerHTML = `
                <div style="text-align: center; padding: 2.5rem; color: #64748b; font-size: .8rem;">
                     No se encontraron contratos vigentes o activos para esta empresa.
                </div>
            `;
            return;
        }

        let html = '';
        this.contratos.forEach(c => {
            html += `
                <div class="ad-client-item" id="ad-item-${c.id}" onclick="MAD.toggleSeleccionCliente(${c.id})">
                    <input type="checkbox" class="ad-client-checkbox" id="ad-chk-${c.id}" value="${c.id}" onclick="event.stopPropagation(); MAD.actualizarConteoSeleccionados();">
                    <div class="ad-client-details">
                        <div class="ad-client-name">${c.cliente_nombre}</div>
                        <div class="ad-client-sub">
                            <span>CC: <b>${c.cedula}</b></span>
                            <span>Contrato N°: <b>${c.id}</b></span>
                            <span class="ad-client-plan">${c.plan_nombre}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        listContainer.innerHTML = html;
        this.actualizarConteoSeleccionados();
    },

    toggleSeleccionCliente: function(contratoId) {
        const chk = document.getElementById(`ad-chk-${contratoId}`);
        if (chk) {
            chk.checked = !chk.checked;
            const item = document.getElementById(`ad-item-${contratoId}`);
            if (chk.checked) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
            this.actualizarConteoSeleccionados();
        }
    },

    seleccionarTodos: function(select) {
        this.contratos.forEach(c => {
            const chk = document.getElementById(`ad-chk-${c.id}`);
            const item = document.getElementById(`ad-item-${c.id}`);
            if (chk && item) {
                chk.checked = select;
                if (select) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            }
        });
        this.actualizarConteoSeleccionados();
    },

    filtrarClientes: function() {
        const term = document.getElementById('ad-search').value.toLowerCase().trim();
        this.contratos.forEach(c => {
            const item = document.getElementById(`ad-item-${c.id}`);
            if (item) {
                const text = (c.cliente_nombre + ' ' + c.cedula).toLowerCase();
                if (text.includes(term)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            }
        });
    },

    actualizarConteoSeleccionados: function() {
        let count = 0;
        this.contratos.forEach(c => {
            const chk = document.getElementById(`ad-chk-${c.id}`);
            const item = document.getElementById(`ad-item-${c.id}`);
            if (chk) {
                if (chk.checked) {
                    count++;
                    if (item) item.classList.add('selected');
                } else {
                    if (item) item.classList.remove('selected');
                }
            }
        });
        document.getElementById('ad-selected-count').innerText = `Clientes seleccionados: ${count}`;
    },

    obtenerSeleccionados: function() {
        const seleccionados = [];
        this.contratos.forEach(c => {
            const chk = document.getElementById(`ad-chk-${c.id}`);
            if (chk && chk.checked) {
                seleccionados.push(c);
            }
        });
        return seleccionados;
    },

    irAPaso: function(paso) {
        // Ocultar todos
        document.getElementById('ad-step-1').classList.remove('active');
        document.getElementById('ad-step-2').classList.remove('active');
        document.getElementById('ad-step-3').classList.remove('active');
        
        // Desactivar indicadores
        document.getElementById('ad-ind-1').classList.remove('active', 'completed');
        document.getElementById('ad-ind-2').classList.remove('active', 'completed');
        document.getElementById('ad-ind-3').classList.remove('active', 'completed');
        
        // Mostrar paso activo
        document.getElementById(`ad-step-${paso}`).classList.add('active');
        this.pasoActivo = paso;

        // Configurar botones
        const btnAtras = document.getElementById('ad-btn-atras');
        const btnSig = document.getElementById('ad-btn-sig');
        const btnRegistrar = document.getElementById('ad-btn-registrar');

        if (paso === 1) {
            btnAtras.style.display = 'none';
            btnSig.style.display = 'block';
            btnRegistrar.style.display = 'none';
            document.getElementById('ad-ind-1').classList.add('active');
        } else if (paso === 2) {
            btnAtras.style.display = 'block';
            btnSig.style.display = 'block';
            btnRegistrar.style.display = 'none';
            document.getElementById('ad-ind-1').classList.add('completed');
            document.getElementById('ad-ind-2').classList.add('active');
        } else if (paso === 3) {
            btnAtras.style.display = 'block';
            btnSig.style.display = 'none';
            btnRegistrar.style.display = 'block';
            document.getElementById('ad-ind-1').classList.add('completed');
            document.getElementById('ad-ind-2').classList.add('completed');
            document.getElementById('ad-ind-3').classList.add('active');
            
            // Generar tabla de distribución y balance
            this.generarDistribucion();
        }
    },

    siguiente: function() {
        if (this.pasoActivo === 1) {
            // Validar campos de Paso 1
            const valorInput = document.getElementById('ad-valor');
            const fechaInput = document.getElementById('ad-fecha');
            const formaInput = document.getElementById('ad-forma-pago');
            const bancoInput = document.getElementById('ad-banco');

            if (!fechaInput.value) {
                alert('Debe ingresar la fecha de pago.');
                return;
            }
            if (!valorInput.value || parseInt(valorInput.value) <= 0) {
                alert('Debe ingresar un valor de anticipo válido mayor a 0.');
                return;
            }
            if (formaInput.value === 'transferencia' && !bancoInput.value) {
                alert('Debe seleccionar la cuenta bancaria de destino.');
                return;
            }
            
            this.irAPaso(2);
        } else if (this.pasoActivo === 2) {
            // Validar que al menos un cliente esté seleccionado
            const seleccionados = this.obtenerSeleccionados();
            if (seleccionados.length === 0) {
                alert('Debe seleccionar al menos un cliente para distribuir el anticipo.');
                return;
            }
            this.irAPaso(3);
        }
    },

    atras: function() {
        if (this.pasoActivo > 1) {
            this.irAPaso(this.pasoActivo - 1);
        }
    },

    generarDistribucion: function() {
        const seleccionados = this.obtenerSeleccionados();
        const totalValor = parseInt(document.getElementById('ad-valor').value || 0);
        const tbody = document.getElementById('ad-dist-tbody');
        tbody.innerHTML = '';

        if (seleccionados.length === 0) return;

        // Distribución equitativa por defecto
        const baseMonto = Math.floor(totalValor / seleccionados.length);
        let residuo = totalValor - (baseMonto * seleccionados.length);

        // Generar filas
        seleccionados.forEach((c, index) => {
            // El último elemento recibe el residuo del redondeo
            const monto = baseMonto + (index === seleccionados.length - 1 ? residuo : 0);

            // Generar opciones de meses (año actual y año siguiente)
            const anioActual = new Date().getFullYear();
            let selectMesesHtml = `<select class="ad-dist-period-select" data-contrato="${c.id}" id="ad-periodo-mes-${c.id}" onchange="MAD.recalcularSuma()">`;
            
            for (let m = 1; m <= 12; m++) {
                const selected = (m === c.periodo_mes) ? 'selected' : '';
                selectMesesHtml += `<option value="${m}" ${selected}>${this.meses[m]}</option>`;
            }
            selectMesesHtml += `</select>`;

            let selectAniosHtml = `<select class="ad-dist-period-select" data-contrato="${c.id}" id="ad-periodo-anio-${c.id}" onchange="MAD.recalcularSuma()">`;
            for (let a = anioActual - 1; a <= anioActual + 2; a++) {
                const selected = (a === c.periodo_anio) ? 'selected' : '';
                selectAniosHtml += `<option value="${a}" ${selected}>${a}</option>`;
            }
            selectAniosHtml += `</select>`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight: 700; color: #1e293b;">${c.cliente_nombre}</td>
                <td style="color: #64748b;">${c.cedula}</td>
                <td style="color: #475569; font-weight: 600;">${c.plan_nombre}</td>
                <td>
                    <div style="display: flex; gap: .3rem;">
                        ${selectMesesHtml}
                        ${selectAniosHtml}
                    </div>
                </td>
                <td style="text-align: right;">
                    <span style="font-size: .8rem; font-weight: 700; color: #92400e; margin-right: .3rem;">$</span>
                    <input type="number" class="ad-dist-val-input" id="ad-dist-val-${c.id}" data-contrato="${c.id}" value="${monto}" min="0" oninput="MAD.recalcularSuma()">
                </td>
            `;
            tbody.appendChild(tr);
        });

        this.recalcularSuma();
    },

    recalcularSuma: function() {
        const totalValor = parseInt(document.getElementById('ad-valor').value || 0);
        const inputs = document.querySelectorAll('.ad-dist-val-input');
        let suma = 0;

        inputs.forEach(input => {
            suma += parseInt(input.value || 0);
        });

        const diferencia = totalValor - suma;

        // Formatear pesos
        const formatPesos = (val) => '$' + new Intl.NumberFormat('es-CO').format(val);

        document.getElementById('ad-bal-total').innerText = formatPesos(totalValor);
        document.getElementById('ad-bal-suma').innerText = formatPesos(suma);
        
        const restanteEl = document.getElementById('ad-bal-restante');
        
        if (diferencia === 0) {
            restanteEl.innerText = formatPesos(0);
            restanteEl.className = 'ad-balance-val match';
            document.getElementById('ad-btn-registrar').removeAttribute('disabled');
        } else {
            restanteEl.innerText = (diferencia > 0 ? '+' : '') + formatPesos(diferencia);
            restanteEl.className = 'ad-balance-val diff';
            document.getElementById('ad-btn-registrar').setAttribute('disabled', 'disabled');
        }
    },

    registrar: function() {
        const totalValor = parseInt(document.getElementById('ad-valor').value || 0);
        const inputs = document.querySelectorAll('.ad-dist-val-input');
        let suma = 0;
        const distribucion = [];

        inputs.forEach(input => {
            const contratoId = input.dataset.contrato;
            const valor = parseInt(input.value || 0);
            const mes = document.getElementById(`ad-periodo-mes-${contratoId}`).value;
            const anio = document.getElementById(`ad-periodo-anio-${contratoId}`).value;
            
            suma += valor;
            distribucion.push({
                contrato_id: parseInt(contratoId),
                valor: valor,
                periodo_mes: parseInt(mes),
                periodo_anio: parseInt(anio)
            });
        });

        if (suma !== totalValor) {
            alert('La distribución no está balanceada. La diferencia debe ser cero.');
            return;
        }

        // Crear FormData para enviar imagen, inputs y arreglo
        const formEl = document.getElementById('ad-form');
        const formData = new FormData(formEl);
        
        // Laravel no procesa arrays complejos en FormData de forma directa sin estructurarlos:
        distribucion.forEach((item, index) => {
            formData.append(`distribucion[${index}][contrato_id]`, item.contrato_id);
            formData.append(`distribucion[${index}][valor]`, item.valor);
            formData.append(`distribucion[${index}][periodo_mes]`, item.periodo_mes);
            formData.append(`distribucion[${index}][periodo_anio]`, item.periodo_anio);
        });

        // Deshabilitar botón
        const btnReg = document.getElementById('ad-btn-registrar');
        btnReg.setAttribute('disabled', 'disabled');
        btnReg.innerText = 'Guardando...';

        fetch('/admin/anticipos/distribuir', {
            method: 'POST',
            body: formData,
            headers: {
                // Laravel CSRF token - lo obtenemos del meta tag de la página
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.ok && data.anticipo_id) {
                this.cerrar();
                
                // Mostrar alerta de éxito
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Anticipo Guardado',
                        text: 'El anticipo distribuido ha sido guardado exitosamente.',
                        confirmButtonText: 'Ver Recibo',
                        confirmButtonColor: '#d97706'
                    }).then((result) => {
                        // Abrir el recibo de anticipo
                        abrirRecibo(`/admin/anticipos/${data.anticipo_id}/recibo?modal=1`);
                    });
                } else {
                    alert('Anticipo distribuido registrado con éxito.');
                    abrirRecibo(`/admin/anticipos/${data.anticipo_id}/recibo?modal=1`);
                }
            } else {
                alert('Error: ' + (data.mensaje || 'No se pudo guardar el anticipo.'));
                btnReg.removeAttribute('disabled');
                btnReg.innerText = '✓ Registrar Anticipo';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de red al intentar registrar el anticipo distribuido.');
            btnReg.removeAttribute('disabled');
            btnReg.innerText = '✓ Registrar Anticipo';
        });
    }
};
</script>
