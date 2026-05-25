{{-- ═══ MODAL CENTRALIZADO: Llavero de Claves y Accesos (Iframe) ═══ --}}
<div id="modalClavesGlobalOverlay"
     style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);z-index:99999;
            align-items:center;justify-content:center;backdrop-filter:blur(4px);padding:1.5rem;"
     onclick="if(event.target===this) cerrarModalClavesGlobal()">
    <div style="background:#fff;border-radius:20px;width:95%;max-width:1300px;height:88vh;
                box-shadow:0 25px 60px rgba(15,23,42,0.3);display:flex;flex-direction:column;overflow:hidden;
                border: 1px solid rgba(255,255,255,0.18);animation: clavesModalIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);">
        
        {{-- Cabecera del Llavero --}}
        <div style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8f 100%);
                    padding:0.95rem 1.4rem;display:flex;align-items:center;justify-content:space-between;
                    border-bottom:1px solid #e2e8f0;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="background:rgba(245,158,11,0.15);padding:0.4rem;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(245,158,11,0.25);">
                    <span style="font-size:1.35rem;line-height:1;filter:drop-shadow(0 2px 4px rgba(245,158,11,0.25));">🔑</span>
                </div>
                <div>
                    <h3 style="margin:0;font-size:1.05rem;font-weight:800;color:#fff;letter-spacing:-0.02em;line-height:1.2;">Llavero de Credenciales y Accesos</h3>
                    <p style="margin:2px 0 0 0;font-size:0.72rem;color:#94a3b8;font-weight:500;">Buscador global y administración de claves del Aliado</p>
                </div>
            </div>
            <button onclick="cerrarModalClavesGlobal()"
                    style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.18);border-radius:8px;
                           width:32px;height:32px;cursor:pointer;font-size:0.95rem;font-weight:700;color:#fff;
                           display:inline-flex;align-items:center;justify-content:center;transition:all 0.15s;outline:none;"
                    onmouseover="this.style.background='rgba(255,255,255,0.25)';this.style.borderColor='rgba(255,255,255,0.35)';"
                    onmouseout="this.style.background='rgba(255,255,255,0.12)';this.style.borderColor='rgba(255,255,255,0.18)';">✕</button>
        </div>
        
        {{-- Cuerpo del Llavero con Iframe y Spinner --}}
        <div style="flex:1;position:relative;background:#f8fafc;display:flex;flex-direction:column;min-height:0;">
            {{-- Spinner de carga suave --}}
            <div id="spinIframeClaves"
                 style="position:absolute;inset:0;background:rgba(248,250,252,0.92);display:flex;
                        flex-direction:column;align-items:center;justify-content:center;z-index:10;gap:0.85rem;">
                <div class="claves-loader-ring"></div>
                <span style="font-size:0.82rem;font-weight:800;color:#1e293b;letter-spacing:0.02em;text-transform:uppercase;">Cargando llavero de seguridad...</span>
                <span style="font-size:0.72rem;color:#64748b;font-weight:500;">Espere un momento por favor</span>
            </div>
            
            <iframe id="iframeClavesGlobal" src="about:blank" 
                    style="width:100%;height:100%;border:none;display:block;background:transparent;flex:1;" 
                    onload="document.getElementById('spinIframeClaves').style.display='none';"></iframe>
        </div>
    </div>
</div>

<style>
@keyframes clavesModalIn {
    from { opacity: 0; transform: scale(0.96) translateY(12px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.claves-loader-ring {
    width: 44px;
    height: 44px;
    border: 4px solid #e2e8f0;
    border-top: 4px solid #f59e0b;
    border-right: 4px solid #1e3a8f;
    border-radius: 50%;
    animation: clavesSpin 0.75s linear infinite;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

@keyframes clavesSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
function abrirModalClavesGlobal() {
    var modal = document.getElementById('modalClavesGlobalOverlay');
    var iframe = document.getElementById('iframeClavesGlobal');
    var spinner = document.getElementById('spinIframeClaves');
    
    if (!modal || !iframe) return;
    
    // Activar spinner
    spinner.style.display = 'flex';
    
    // Fijar la URL del buscador global con el parámetro iframe=1
    iframe.src = "{{ route('admin.clave_accesos.global') }}?iframe=1";
    
    // Abrir modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Evita scroll de la pagina de fondo
}

function cerrarModalClavesGlobal() {
    var modal = document.getElementById('modalClavesGlobalOverlay');
    var iframe = document.getElementById('iframeClavesGlobal');
    
    if (!modal || !iframe) return;
    
    // Ocultar modal
    modal.style.display = 'none';
    document.body.style.overflow = ''; // Habilitar scroll
    
    // Limpiar src para liberar memoria del iframe de forma segura
    iframe.src = "about:blank";
}
</script>
