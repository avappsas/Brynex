{{-- ════════════════════════════════════════════════════════════════
     MODAL PANEL DOCUMENTOS — Lista + Upload dentro de modal grande
════════════════════════════════════════════════════════════════ --}}

<div id="modalListaDocumentos" class="dmodal-backdrop" onclick="cerrarPanelDoc(event)">
    <div class="dlist-panel">

        {{-- Header --}}
        <div class="dlist-header">
            <div style="display:flex;align-items:center;gap:.85rem;">
                <div style="width:44px;height:44px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                </div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:700;color:#fff;">Documentos Digitales</h3>
                    <p style="margin:0;font-size:.78rem;color:rgba(255,255,255,.7);">Titular: {{ $cliente->primer_nombre }} {{ $cliente->primer_apellido }} · CC {{ $cliente->cedula }}</p>
                </div>
            </div>
            <div style="display:flex;gap:.6rem;align-items:center;">
                <button type="button" onclick="docModalAbrir()" style="display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.2);border:1.5px solid rgba(255,255,255,.35);color:#fff;border-radius:10px;padding:.5rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3"/></svg>
                    Subir Doc.
                </button>
                <button type="button" onclick="cerrarPanelDoc()" style="background:rgba(255,255,255,.15);border:none;border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Cuerpo: lista de documentos --}}
        <div class="dlist-body">
            @php
                $docs = $cliente->documentos;
                $tipoLabels = [
                    'cedula'=>'Cédula','carta_laboral'=>'Carta Laboral',
                    'registro_civil'=>'Registro Civil','tarjeta_identidad'=>'Tarjeta Identidad',
                    'decl_juramentada'=>'Decl. Juramentada','acta_matrimonio'=>'Acta de Matrimonio','otro'=>'Otro'
                ];
            @endphp

            @if($docs->isEmpty())
            <div style="text-align:center;padding:3rem 2rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:16px;color:#94a3b8;">
                <div style="margin-bottom:1rem;opacity:.5;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#94a3b8" stroke-width="1.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.75m0 0l-1.5-1.5m1.5 1.5l1.5-1.5"/>
                    </svg>
                </div>
                <p style="font-size:1.05rem;font-weight:600;color:#475569;margin:0;">No hay documentos adjuntos</p>
                <p style="font-size:.82rem;margin:.4rem 0 0;">Suba la cédula, carta laboral, registros civiles u otros archivos de la afiliación.</p>
                <button type="button" onclick="docModalAbrir()" style="margin-top:.75rem;display:inline-flex;align-items:center;gap:.4rem;background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;border:none;border-radius:10px;padding:.5rem 1.1rem;font-size:.82rem;font-weight:600;cursor:pointer;box-shadow:0 3px 10px rgba(124,58,237,.35);">
                    ⬆️ Subir primer documento
                </button>
            </div>
            @else
            <div style="display:flex;flex-direction:column;gap:.65rem;">
                @foreach($docs as $doc)
                @php
                    $ext = strtolower(pathinfo($doc->nombre_archivo, PATHINFO_EXTENSION));
                    $esImg = in_array($ext,['jpg','jpeg','png','webp','gif']);
                    $iconInfo = $ext === 'pdf'
                        ? ['bg'=>'#fee2e2','color'=>'#dc2626','ico'=>'📄']
                        : ($esImg ? ['bg'=>'#dbeafe','color'=>'#2563eb','ico'=>'🖼️'] : ['bg'=>'#f1f5f9','color'=>'#475569','ico'=>'📎']);
                @endphp
                <div class="doc-card">
                    <div style="width:46px;height:46px;border-radius:12px;background:{{ $iconInfo['bg'] }};display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">{{ $iconInfo['ico'] }}</div>
                    <div style="flex:1;min-width:0;">
                        <p style="margin:0;font-size:.87rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ \Illuminate\Support\Str::limit($doc->nombre_archivo, 45) }}</p>
                        <div style="display:flex;gap:.4rem;margin:.2rem 0;flex-wrap:wrap;">
                            <span class="doc-badge">{{ $tipoLabels[$doc->tipo_documento] ?? $doc->tipo_documento }}</span>
                            @if($doc->doc_beneficiario)
                                <span class="doc-badge" style="background:#f0fdf4;color:#16a34a;">Benef. {{ $doc->doc_beneficiario }}</span>
                            @else
                                <span class="doc-badge" style="background:#eff6ff;color:#2563eb;">Titular</span>
                            @endif
                        </div>
                        <p style="margin:0;font-size:.72rem;color:#94a3b8;">⬆️ {{ $doc->subidor?->nombre ?? 'Sistema' }} · {{ $doc->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div style="display:flex;gap:.4rem;flex-shrink:0;">
                        <a href="{{ route('admin.documentos.download', $doc->id) }}" target="_blank" class="doc-btn-ico doc-btn-download" title="Descargar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        </a>
                        <form action="{{ route('admin.documentos.destroy', $doc->id) }}" method="POST" onsubmit="return confirm('¿Eliminar permanentemente?');" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" class="doc-btn-ico doc-btn-del" title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     MODAL UPLOAD DOCUMENTO
══════════════════════════════════════════════════════ --}}
<div id="docModal" class="dmodal-backdrop" style="z-index:1080;" onclick="docModalCerrar(event)">
    <div class="dmodal-panel">
        <div class="dmodal-header">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="width:38px;height:38px;background:rgba(255,255,255,.18);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.338-2.32 5.75 5.75 0 011.88 11.095H6.75z"/></svg>
                </div>
                <div>
                    <h4 style="margin:0;font-size:1rem;font-weight:700;color:#fff;">Subir Documento</h4>
                    <p style="margin:.1rem 0 0;font-size:.72rem;color:rgba(255,255,255,.75);">PDF, JPG, PNG · Máx. 10 MB</p>
                </div>
            </div>
            <button type="button" onclick="docModalCerrar()" style="background:rgba(255,255,255,.15);border:none;border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="docForm" method="POST" action="{{ route('admin.clientes.documentos.store', $cliente->cedula) }}" enctype="multipart/form-data">
            @csrf
            <div style="padding:1.5rem;display:flex;flex-direction:column;gap:1rem;">

                <div class="dropzone" id="docDropzone" onclick="document.getElementById('docFileInput').click()">
                    <div class="dropzone-content" id="docDropContent">
                        <div style="margin-bottom:.5rem;display:flex;justify-content:center;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#94a3b8" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.338-2.32 5.75 5.75 0 011.88 11.095H6.75z"/></svg>
                        </div>
                        <p style="margin:0;font-size:.9rem;font-weight:600;color:#334155;">Haga clic o arrastre el archivo aquí</p>
                        <p style="margin:.2rem 0 0;font-size:.75rem;color:#94a3b8;">PDF, JPG, PNG, WEBP — hasta 10 MB</p>
                    </div>
                    <input type="file" id="docFileInput" name="archivo" accept=".pdf,image/*" required style="display:none;" onchange="docMostrarArchivo(this)">
                </div>

                <div id="docFilePreview" style="display:none;" class="doc-preview-bar">
                    <div id="docPreviewIco" style="font-size:1.5rem;flex-shrink:0;">📄</div>
                    <div style="flex:1;min-width:0;">
                        <p id="docPreviewNombre" style="margin:0;font-size:.85rem;font-weight:600;color:#15803d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></p>
                        <p id="docPreviewTam" style="margin:0;font-size:.72rem;color:#16a34a;"></p>
                    </div>
                    <button type="button" onclick="docQuitarArchivo()" style="background:transparent;border:none;font-size:1rem;cursor:pointer;color:#64748b;padding:.2rem;border-radius:4px;">✖</button>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div style="display:flex;flex-direction:column;gap:.3rem;">
                        <label style="font-size:.68rem;font-weight:700;color:#475569;letter-spacing:.05em;">TIPO DE DOCUMENTO *</label>
                        <select name="tipo_documento" class="bmodal-inp" required>
                            <option value="">Seleccione…</option>
                            <optgroup label="Cliente Titular">
                                <option value="cedula">🪪 Cédula de Ciudadanía</option>
                                <option value="carta_laboral">📋 Carta Laboral</option>
                            </optgroup>
                            <optgroup label="Beneficiarios">
                                <option value="registro_civil">📜 Registro Civil</option>
                                <option value="tarjeta_identidad">🪪 Tarjeta de Identidad</option>
                                <option value="acta_matrimonio">💍 Acta de Matrimonio</option>
                                <option value="decl_juramentada">✍️ Declaración Juramentada</option>
                            </optgroup>
                            <option value="otro">📎 Otro</option>
                        </select>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.3rem;">
                        <label style="font-size:.68rem;font-weight:700;color:#475569;letter-spacing:.05em;">PERTENECE A</label>
                        <select name="doc_beneficiario" class="bmodal-inp">
                            <option value="">🧑 Titular (CC: {{ $cliente->cedula }})</option>
                            @foreach($cliente->beneficiarios as $ben)
                                @if($ben->n_documento)
                                <option value="{{ $ben->n_documento }}">👤 {{ $ben->nombres }} ({{ $ben->tipo_doc }} {{ $ben->n_documento }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;align-items:center;gap:.75rem;padding:1rem 1.5rem;background:#f8fafc;border-top:1px solid #e2e8f0;">
                <button type="button" class="bmodal-btn-cancel" onclick="docModalCerrar()">Cancelar</button>
                <button type="submit" id="docBtnSubir" disabled style="display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1.25rem;background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;border:none;border-radius:9px;font-size:.83rem;font-weight:600;cursor:pointer;box-shadow:0 3px 10px rgba(124,58,237,.35);opacity:.5;transition:all .2s;" id="docBtnSubir">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3"/></svg>
                    Subir Archivo
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ESTILOS --}}
<style>
.dmodal-backdrop { display:none;position:fixed;inset:0;z-index:1060;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;animation:bmodal-fade-in .2s ease; }
.dlist-panel { background:#fff;border-radius:20px;width:90%;max-width:760px;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:hidden;animation:bmodal-slide-up .25s cubic-bezier(.16,1,.3,1); }
.dlist-header { display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.5rem;flex-shrink:0;background:linear-gradient(135deg,#5b21b6,#7c3aed); }
.dlist-body { overflow-y:auto;padding:1.5rem;flex:1; }

.dmodal-panel { background:#fff;border-radius:20px;width:90%;max-width:560px;box-shadow:0 25px 50px rgba(0,0,0,.25);overflow:hidden;animation:bmodal-slide-up .25s cubic-bezier(.16,1,.3,1); }
.dmodal-header { display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;background:linear-gradient(135deg,#5b21b6,#7c3aed); }

.doc-card { display:flex;align-items:center;gap:1rem;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:.85rem 1.1rem;transition:box-shadow .2s,transform .2s;box-shadow:0 1px 4px rgba(0,0,0,.04); }
.doc-card:hover { transform:translateX(3px);box-shadow:0 4px 14px rgba(0,0,0,.08); }
.doc-badge { padding:.12rem .5rem;border-radius:99px;font-size:.67rem;font-weight:700;background:#f1f5f9;color:#475569; }
.doc-btn-ico { width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#64748b;transition:all .15s;text-decoration:none; }
.doc-btn-download:hover { background:#eff6ff;border-color:#bfdbfe;color:#2563eb; }
.doc-btn-del:hover { background:#fee2e2;border-color:#fca5a5;color:#dc2626; }

.dropzone { border:2px dashed #cbd5e1;border-radius:12px;padding:2rem;text-align:center;cursor:pointer;background:#fafbff;transition:all .2s; }
.dropzone:hover,.dropzone.dragover { border-color:#7c3aed;background:#f5f3ff; }
.doc-preview-bar { display:flex;align-items:center;gap:.75rem;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:.65rem 1rem; }

/* Reutiliza bmodal-inp y bmodal-btn-cancel de beneficiarios */
</style>

<script>
function abrirPanelDocumentos(ben) {
    document.getElementById('modalListaDocumentos').style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Si viene un beneficiario, abrir directamente el modal de subida pre-cargado
    if (ben) {
        // Pequeño delay para que el panel principal sea visible primero
        setTimeout(function() {
            docModalAbrir(ben);
        }, 80);
    }
}
function cerrarPanelDoc(event) {
    if (event && event.target !== document.getElementById('modalListaDocumentos')) return;
    document.getElementById('modalListaDocumentos').style.display = 'none';
    document.body.style.overflow = '';
}
function docModalAbrir(ben) {
    // Resetear formulario
    document.getElementById('docForm').reset();
    docQuitarArchivo();

    // Pre-seleccionar beneficiario si viene
    if (ben && ben.n_documento) {
        const sel = document.querySelector('#docModal select[name="doc_beneficiario"]');
        if (sel) sel.value = ben.n_documento;

        // Sugerir tipo de documento según el tipo_doc del beneficiario
        const tipoMap = { 'TI': 'tarjeta_identidad', 'RC': 'registro_civil', 'CC': 'cedula', 'CE': 'cedula', 'PA': 'otro' };
        const tipoSugerido = tipoMap[ben.tipo_doc] || '';
        const tipoSel = document.querySelector('#docModal select[name="tipo_documento"]');
        if (tipoSel && tipoSugerido) tipoSel.value = tipoSugerido;

        // Mostrar título contextual
        const encab = document.querySelector('.dmodal-header h4');
        if (encab) encab.textContent = '📎 Adjuntar a: ' + (ben.nombres || '');
    } else {
        const encab = document.querySelector('.dmodal-header h4');
        if (encab) encab.textContent = 'Subir Documento';
    }

    document.getElementById('docModal').style.display = 'flex';
}
function docModalCerrar(event) {
    if (event && event.target !== document.getElementById('docModal')) return;
    document.getElementById('docModal').style.display = 'none';
}
function docMostrarArchivo(input) {
    const file = input.files[0];
    if (!file) return;
    const ext = file.name.split('.').pop().toLowerCase();
    const ico = ext === 'pdf' ? '📄' : (['jpg','jpeg','png','webp'].includes(ext) ? '🖼️' : '📎');
    document.getElementById('docPreviewIco').textContent = ico;
    document.getElementById('docPreviewNombre').textContent = file.name;
    document.getElementById('docPreviewTam').textContent = (file.size/1024/1024).toFixed(2) + ' MB';
    document.getElementById('docFilePreview').style.display = 'flex';
    document.getElementById('docDropContent').style.display = 'none';
    const btn = document.getElementById('docBtnSubir');
    btn.disabled = false; btn.style.opacity = '1';
}
function docQuitarArchivo() {
    document.getElementById('docFileInput').value = '';
    document.getElementById('docFilePreview').style.display = 'none';
    document.getElementById('docDropContent').style.display = 'block';
    const btn = document.getElementById('docBtnSubir');
    btn.disabled = true; btn.style.opacity = '.5';
}
const dz = document.getElementById('docDropzone');
if (dz) {
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragover'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
    dz.addEventListener('drop', e => {
        e.preventDefault(); dz.classList.remove('dragover');
        const f = e.dataTransfer.files[0];
        if (f) { const dt = new DataTransfer(); dt.items.add(f); document.getElementById('docFileInput').files = dt.files; docMostrarArchivo(document.getElementById('docFileInput')); }
    });
}
</script>
