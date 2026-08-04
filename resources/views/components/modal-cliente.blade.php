{{--
    Modal reutilizable: ficha del cliente en iframe (sin layout).

    Uso:
        @include('components.modal-cliente')

    Y en cualquier botón de la vista:
        <button type="button" class="btn-ficha-cliente"
                data-cedula="{{ $x->cedula }}"
                data-nombre="{{ $x->nombre_cliente }}">👤</button>

    También puede abrirse por JS:
        abrirModalCliente('1094123456', 'Juan Pérez');

    La ficha se carga desde admin.clientes.ficha_cedula con ?iframe=1, que
    resuelve el cliente dentro del aliado activo y redirige a
    /admin/clientes/{id}/edit?iframe=1 (el layout oculta header y menú cuando
    existe el parámetro iframe).
--}}

<style>
@keyframes spinFichaCliente { to { transform: rotate(360deg); } }
.btn-ficha-cliente {
    background:none; border:none; cursor:pointer; padding:0 .1rem;
    color:#3b82f6; font-size:.9rem; line-height:1; opacity:.75;
    transition:opacity .15s, transform .15s;
}
.btn-ficha-cliente:hover { opacity:1; transform:scale(1.15); }
</style>

<div id="modalClienteOverlay" style="
    display:none; position:fixed; inset:0; z-index:4000;
    background:rgba(10,10,20,.7); backdrop-filter:blur(4px);
    align-items:center; justify-content:center; padding:.75rem;
" onclick="if(event.target===this)cerrarModalCliente()">
    <div style="
        background:#fff; border-radius:16px; width:min(1360px,90vw);
        height:88vh; display:flex; flex-direction:column;
        box-shadow:0 32px 100px rgba(0,0,0,.5); overflow:hidden;
    ">
        {{-- Header --}}
        <div style="
            background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);
            padding:.65rem 1.2rem; display:flex; align-items:center;
            justify-content:space-between; flex-shrink:0;
        ">
            <div style="display:flex;align-items:center;gap:.6rem;">
                <span style="font-size:1.1rem;">👤</span>
                <div>
                    <div style="font-size:.9rem;font-weight:800;color:#fff;" id="modalClienteTitulo">Cliente</div>
                    <div style="font-size:.62rem;color:rgba(255,255,255,.5);" id="modalClienteSubtitulo">Ficha del cliente</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;">
                <a id="modalClienteLink" href="#" target="_blank"
                   style="font-size:.72rem;font-weight:600;color:rgba(255,255,255,.6);text-decoration:none;padding:.3rem .7rem;border:1px solid rgba(255,255,255,.2);border-radius:6px;transition:color .15s;"
                   onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.6)'">
                   &#x2197; Abrir pestaña
                </a>
                <button type="button" onclick="cerrarModalCliente()" style="
                    width:30px;height:30px;border-radius:7px;border:none;cursor:pointer;
                    background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);
                    font-size:1rem;display:flex;align-items:center;justify-content:center;
                    transition:background .15s;
                " onmouseover="this.style.background='rgba(255,255,255,.22)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">
                    ✕
                </button>
            </div>
        </div>
        {{-- iframe + spinner --}}
        <div style="position:relative;flex:1;overflow:hidden;">
            <div id="modalClienteLoading" style="
                position:absolute;inset:0;background:#f8fafc;
                display:flex;flex-direction:column;align-items:center;justify-content:center;
                gap:1rem;z-index:10;
            ">
                <div style="
                    width:44px;height:44px;border-radius:50%;
                    border:4px solid #e2e8f0;border-top-color:#3b82f6;
                    animation:spinFichaCliente .7s linear infinite;
                "></div>
                <div style="font-size:.82rem;color:#64748b;font-weight:600;">Cargando cliente...</div>
            </div>
            <iframe id="modalClienteFrame" src=""
                style="width:100%;height:100%;border:none;display:block;"
                onload="document.getElementById('modalClienteLoading').style.display='none'"></iframe>
        </div>
    </div>
</div>

<script>
(function () {
    // Guardia: la vista puede incluir el componente más de una vez.
    if (window.abrirModalCliente) return;

    const BASE_FICHA_CLIENTE = '{{ url('admin/clientes/ficha') }}';

    window.abrirModalCliente = function (cedula, nombre) {
        if (!cedula) return;
        const fullUrl = `${BASE_FICHA_CLIENTE}/${encodeURIComponent(cedula)}`;

        document.getElementById('modalClienteTitulo').textContent    = nombre || cedula;
        document.getElementById('modalClienteSubtitulo').textContent = nombre ? `C.C. ${cedula}` : 'Ficha del cliente';
        document.getElementById('modalClienteLink').href             = fullUrl;
        document.getElementById('modalClienteLoading').style.display = 'flex';
        document.getElementById('modalClienteFrame').src             = `${fullUrl}?iframe=1`;
        document.getElementById('modalClienteOverlay').style.display = 'flex';
    };

    window.cerrarModalCliente = function () {
        document.getElementById('modalClienteOverlay').style.display = 'none';
        // Limpiar el src evita que el iframe siga cargado en segundo plano
        document.getElementById('modalClienteFrame').src = '';
    };

    // Delegación: cualquier .btn-ficha-cliente de la página abre el modal.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-ficha-cliente');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        abrirModalCliente(btn.dataset.cedula, btn.dataset.nombre);
    });

    // Escape cierra SOLO este modal si está abierto; en fase de captura y
    // cortando el evento para no cerrar también el modal que quedó debajo.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        const ov = document.getElementById('modalClienteOverlay');
        if (!ov || ov.style.display !== 'flex') return;
        e.stopImmediatePropagation();
        cerrarModalCliente();
    }, true);
})();
</script>
