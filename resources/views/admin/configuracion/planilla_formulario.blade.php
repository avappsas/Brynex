@extends('layouts.app')
@section('modulo','Planillas de Pago - Editor de Mapeo')

@section('contenido')
<style>
/* ── Layout ──────────────────────────────────────────── */
.fmap-wrap   { display:grid;grid-template-columns:300px 1fr;gap:1rem;height:calc(100vh - 85px); }
.fmap-panel  { background:#0f172a;border-radius:12px;padding:0.9rem;display:flex;flex-direction:column;gap:0.5rem;overflow:hidden; }
.fmap-viewer { background:#334155;border-radius:12px;overflow:hidden;display:flex;flex-direction:column; }

/* ── Panel izquierdo ────────────────────────────────── */
.fmap-title  { color:#fff;font-size:0.9rem;font-weight:800; }
.fmap-sub    { color:#475569;font-size:0.68rem;line-height:1.4; }
.ops-select  { width:100%;background:#1e3a5f;color:#e2e8f0;border:1px solid #334155;border-radius:8px;padding:0.4rem 0.6rem;font-size:0.8rem;font-weight:700; }

.upload-zone { border:1.5px dashed #334155;border-radius:8px;padding:0.5rem;text-align:center;
               font-size:0.7rem;color:#64748b;cursor:pointer;transition:all .15s; }
.upload-zone:hover { border-color:#3b82f6;color:#3b82f6; }
.upload-zone input { display:none; }

.campo-list  { flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:2px;min-height:0; }
.campo-item  { border-radius:6px;padding:0.3rem 0.5rem;font-size:0.7rem;cursor:pointer;
               display:flex;align-items:center;gap:0.4rem;border:1.5px solid transparent;
               transition:all .12s;background:#1e293b;color:#94a3b8;user-select:none; }
.campo-item:hover  { background:#1e3a5f;color:#e2e8f0; }
.campo-item.activo { background:#1e40af;color:#fff;border-color:#3b82f6;font-weight:700; }
.campo-item.mapeado { border-color:#16a34a;color:#86efac; }
.campo-item.mapeado.activo { background:#14532d;border-color:#4ade80; }

.campo-dot   { width:8px;height:8px;border-radius:50%;background:currentColor;flex-shrink:0; }

/* ── Toolbar visor ───────────────────────────────────── */
.fmap-toolbar { background:#1e293b;padding:0.4rem 0.8rem;display:flex;align-items:center;gap:0.5rem;flex-shrink:0; }
.fmap-toolbar button { background:#334155;color:#e2e8f0;border:none;border-radius:6px;padding:0.25rem 0.6rem;font-size:0.72rem;cursor:pointer; }
.fmap-toolbar button:hover { background:#475569; }
.page-info   { font-size:0.72rem;color:#94a3b8; }
.fmap-status { font-size:0.7rem;color:#fbbf24;font-weight:700;padding:0.2rem 0.6rem;background:#422006;border-radius:6px;flex:1;text-align:center;min-width:0; }

/* ── Canvas ────────────────────────────────────────── */
.canvas-wrap { flex:1;overflow:auto;position:relative;cursor:crosshair; }
#pdfCanvas   { display:block; }

/* ── Rectángulos mapeados ────────────────────────────── */
.rect-overlay { position:absolute;border:2px solid;border-radius:2px;pointer-events:auto;
               box-sizing:border-box;overflow:hidden;display:flex;flex-direction:column;
               align-items:center;justify-content:flex-end; }
.rect-overlay:hover { cursor:grab; }
.rect-overlay.moviendo { cursor:grabbing !important; }
.rect-preview-text { font-size:7.5px;color:rgba(255,255,255,0.95);font-weight:700;
                     text-align:center;pointer-events:none;line-height:1;
                     padding:1px 2px 2px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
                     width:100%;text-shadow:0 1px 3px rgba(0,0,0,.8); }
.rect-label   { position:absolute;top:-1.2em;left:0;font-size:0.55rem;font-weight:700;
                 color:#fff;padding:0 3px;border-radius:2px;white-space:nowrap; }

#rectPreview  { position:absolute;border:2px dashed #facc15;pointer-events:none;display:none;box-sizing:border-box; }

/* ── Config del campo ────────────────────────────────── */
.campo-config { background:#1e293b;padding:0.55rem 0.8rem;border-top:1px solid #334155;
                display:none;gap:0.8rem;align-items:center;flex-wrap:wrap;flex-shrink:0; }
.campo-config.visible { display:flex; }
.campo-config label { font-size:0.68rem;color:#94a3b8; }
.campo-config input[type="number"] { width:58px;background:#0f172a;border:1px solid #334155;
                                     color:#e2e8f0;border-radius:5px;padding:0.18rem 0.35rem;font-size:0.7rem; }
.campo-config select { background:#0f172a;border:1px solid #334155;color:#e2e8f0;
                       border-radius:5px;padding:0.18rem 0.35rem;font-size:0.7rem; }
.campo-config input[type="checkbox"] { width:auto;cursor:pointer; }
.btn-del-campo { background:#7f1d1d;color:#fca5a5;border:none;border-radius:6px;
                 padding:0.22rem 0.55rem;font-size:0.68rem;cursor:pointer; }

/* ── Botón guardar ───────────────────────────────────── */
.btn-guardar { width:100%;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;
               border:none;border-radius:8px;padding:0.55rem;font-size:0.8rem;font-weight:700;cursor:pointer; }
.btn-guardar:hover { filter:brightness(1.1); }

/* ── Toast ──────────────────────────────────────────── */
.toast { position:fixed;bottom:1.5rem;right:1.5rem;background:#15803d;color:#fff;
         padding:0.65rem 1rem;border-radius:10px;font-weight:700;font-size:0.82rem;
         box-shadow:0 4px 20px rgba(0,0,0,.25);transform:translateY(80px);opacity:0;
         transition:all .3s;z-index:9999; }
.toast.show { transform:translateY(0);opacity:1; }
</style>

<div class="fmap-wrap">

{{-- ══ PANEL IZQUIERDO ══ --}}
<div class="fmap-panel">
    <div class="fmap-title">📑 Editor de Planillas</div>
    <div class="fmap-sub">Selecciona el operador de planilla, sube el PDF en blanco y arrastra campos en el lienzo para configurar el reporte.</div>

    <select class="ops-select" id="selectorOperador" onchange="cambiarOperador()">
        @foreach($operadores as $o)
        <option value="{{ $o->id }}" {{ $o->id == $operador->id ? 'selected' : '' }}>
            {{ $o->nombre }} {{ $o->activo ? '✅' : '❌' }}
        </option>
        @endforeach
    </select>

    <form id="formPdf" action="{{ route('admin.configuracion.operadores.formulario.pdf', $operador) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="upload-zone" onclick="document.getElementById('inputPdf').click()">
            📁 {{ $template->formulario_pdf ? 'Cambiar PDF Planilla' : 'Subir PDF de Planilla en Blanco' }}
            <input type="file" id="inputPdf" name="pdf" accept=".pdf" onchange="this.form.submit()">
        </div>
        @if(session('success'))
        <div style="font-size:0.68rem;color:#86efac;margin-top:0.25rem;text-align:center;">✅ {{ session('success') }}</div>
        @endif
    </form>

    <hr style="border-color:#1e3a5f;margin:0.1rem 0;">
    <div style="font-size:0.62rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.05em;display:flex;justify-content:between;">
        <span>Campos disponibles</span>
        <span id="contadorCampos" style="margin-left:auto;">0 / {{ count($campos) }}</span>
    </div>

    <div class="campo-list" id="listaCampos">
        @foreach($campos as $clave => $etiqueta)
        @php $mapeado = collect($mapeados)->firstWhere('dato', $clave); @endphp
        <div class="campo-item {{ $mapeado ? 'mapeado' : '' }}" 
             id="ci-{{ str_replace('.', '-', $clave) }}"
             onclick="seleccionarCampo('{{ $clave }}', '{{ $etiqueta }}')">
            <span class="campo-dot"></span>
            <span>{{ $etiqueta }}</span>
        </div>
        @endforeach
    </div>

    <!-- Datos de Previsualización Real -->
    <hr style="border-color:#1e3a5f;margin:0.25rem 0;">
    <div style="font-size:0.62rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.2rem;">
        🔍 Datos de Previsualización Real
    </div>
    <div style="display:flex;flex-direction:column;gap:0.35rem;background:#1e293b;padding:0.45rem;border-radius:8px;margin-bottom:0.5rem;">
        <div style="display:flex;gap:0.3rem;">
            <input type="text" id="ejemploCedula" placeholder="Cédula" value="1058846712" 
                   style="flex:1;background:#0f172a;border:1px solid #334155;color:#e2e8f0;border-radius:5px;padding:0.18rem 0.35rem;font-size:0.7rem;">
            <input type="text" id="ejemploPlanilla" placeholder="Planilla" value="86667957"
                   style="flex:1;background:#0f172a;border:1px solid #334155;color:#e2e8f0;border-radius:5px;padding:0.18rem 0.35rem;font-size:0.7rem;">
        </div>
        <button type="button" onclick="cargarDatosReales()" 
                style="width:100%;background:#059669;color:#fff;border:none;border-radius:5px;padding:0.25rem;font-size:0.7rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.2rem;">
            🔄 Cargar Datos Reales
        </button>
    </div>

    <form id="formMapeo" action="{{ route('admin.configuracion.operadores.formulario.guardar', $operador) }}" method="POST">
        @csrf
        <input type="hidden" name="formulario_campos" id="inputMapeoJson" value="">
        <button type="button" class="btn-guardar" onclick="guardarMapeo()">💾 Guardar Configuración</button>
        <button type="button" class="btn-guardar" onclick="descargarPdfPrueba()" 
                style="background:linear-gradient(135deg,#0369a1,#0284c7);margin-top:0.35rem;display:flex;align-items:center;justify-content:center;gap:0.25rem;">
            📄 Descargar PDF de Prueba
        </button>
    </form>
</div>

{{-- ══ VISOR Y CANVAS ══ --}}
<div class="fmap-viewer">
    <div class="fmap-toolbar">
        <button onclick="zoomOut()">🔎-</button>
        <button onclick="zoomIn()">🔎+</button>
        <span id="zoomText" style="font-size:0.75rem;color:#fff;min-width:38px;">100%</span>
        
        <div style="margin-left:1rem;display:flex;align-items:center;gap:0.4rem;">
            <button onclick="pagAnterior()">◀</button>
            <span class="page-info">Pág. <span id="pageNumSpan">1</span> de <span id="pageCountSpan">1</span></span>
            <button onclick="pagSiguiente()">▶</button>
        </div>

        <div class="fmap-status" id="statusCampo">
            👆 Selecciona un campo y arrastra en el PDF
        </div>
    </div>

    <div class="canvas-wrap" id="canvasWrap">
        <canvas id="pdfCanvas"></canvas>
        <div id="rectPreview"></div>
        <!-- Capa de rectángulos mapeados colocados vía JS -->
        <div id="rectsLayer" style="position:absolute;inset:0;pointer-events:none;"></div>
    </div>

    {{-- Configuración flotante del campo activo --}}
    <div class="campo-config" id="campoConfig">
        <div style="display:flex;align-items:center;gap:0.3rem;">
            <label>Fuente:</label>
            <input type="number" id="cfgFontSize" value="7.5" step="0.5" onchange="actualizarEstiloCampo()">
            <label>pt</label>
        </div>

        <div style="display:flex;align-items:center;gap:0.3rem;">
            <label>Estilo:</label>
            <select id="cfgStyle" onchange="actualizarEstiloCampo()">
                <option value="">Normal</option>
                <option value="B">Negrita</option>
                <option value="I">Cursiva</option>
                <option value="BI">Negrita y Cursiva</option>
            </select>
        </div>

        <div style="display:flex;align-items:center;gap:0.3rem;">
            <label>Alineación:</label>
            <select id="cfgAlign" onchange="actualizarEstiloCampo()">
                <option value="left">Izquierda</option>
                <option value="center">Centro</option>
                <option value="right">Derecha</option>
            </select>
        </div>

        <div style="display:flex;align-items:center;gap:0.3rem;">
            <label>Fondo Limpieza:</label>
            <select id="cfgColorFondo" onchange="actualizarEstiloCampo()">
                <option value="transparente">Transparente (Sin limpieza)</option>
                <option value="blanco">Blanco</option>
                <option value="gris">Gris de pago</option>
            </select>
        </div>

        <div style="display:flex;align-items:center;gap:0.3rem;">
            <label>Espaciado:</label>
            <input type="number" id="cfgLetterSpacing" value="0.0" step="0.1" min="-2.0" max="10.0" onchange="actualizarEstiloCampo()">
            <label>pt</label>
        </div>

        <button class="btn-del-campo" onclick="eliminarCampoActivo()" style="margin-left:auto;">🗑 Eliminar Campo</button>
    </div>
</div>

</div>

<div class="toast" id="toast">✅ Guardado correctamente</div>

{{-- ══ SCRIPTS ══ --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
// Cargar PDF.js worker
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

let pdfDoc = null,
    pageNum = 1,
    zoom = 1.0,
    pdfWidth = 0,
    pdfHeight = 0,
    campoActivo = null,
    mapeo = @json($mapeados);

const canvas = document.getElementById('pdfCanvas');
const ctx = canvas.getContext('2d');
const canvasWrap = document.getElementById('canvasWrap');
const rectsLayer = document.getElementById('rectsLayer');
const preview = document.getElementById('rectPreview');

// Arrastre e interactividad
const drag = { active: false, mode: 'draw', startX: 0, startY: 0, movingDato: null, offX: 0, offY: 0 };

@if($template->formulario_pdf)
    const pdfUrl = "{{ route('admin.configuracion.operadores.formulario.vpdf', $operador) }}";
    pdfjsLib.getDocument(pdfUrl).promise.then(doc => {
        pdfDoc = doc;
        document.getElementById('pageCountSpan').textContent = doc.numPages;
        renderizarPagina();
    }).catch(err => {
        console.error("Error al cargar planilla PDF:", err);
    });
@endif

function renderizarPagina() {
    if (!pdfDoc) return;
    pdfDoc.getPage(pageNum).then(page => {
        const viewport = page.getViewport({ scale: zoom });
        canvas.width  = viewport.width;
        canvas.height = viewport.height;
        
        const renderCtx = { canvasContext: ctx, viewport: viewport };
        page.render(renderCtx).promise.then(() => {
            // Obtener dimensiones físicas exactas del PDF
            const mediaBox = page.view;
            pdfWidth  = mediaBox[2] - mediaBox[0];
            pdfHeight = mediaBox[3] - mediaBox[1];
            
            // Redimensionar las capas
            rectsLayer.style.width  = canvas.width + 'px';
            rectsLayer.style.height = canvas.height + 'px';
            
            dibujarRectangulos();
            actualizarContador();
        });
    });
    document.getElementById('pageNumSpan').textContent = pageNum;
}

function cambiarOperador() {
    const id = document.getElementById('selectorOperador').value;
    window.location.href = `/admin/configuracion/operadores/${id}/formulario`;
}

function zoomIn() { zoom = Math.min(4.5, zoom + 0.15); renderizarPagina(); actualizarZoomLabel(); }
function zoomOut() { zoom = Math.max(0.5, zoom - 0.15); renderizarPagina(); actualizarZoomLabel(); }
function actualizarZoomLabel() { document.getElementById('zoomText').textContent = Math.round(zoom * 100) + '%'; }

function pagAnterior() { if (pageNum > 1) { pageNum--; renderizarPagina(); } }
function pagSiguiente() { if (pdfDoc && pageNum < pdfDoc.numPages) { pageNum++; renderizarPagina(); } }

function rectEnPunto(mx, my) {
    // Buscar si hay un rectángulo mapeado en la posición actual de mouse (mx, my px)
    return mapeo.find(r => {
        if (r.pagina !== pageNum) return false;
        const rx = r.x * zoom, ry = r.y * zoom;
        const rw = r.w * zoom, rh = r.h * zoom;
        return (mx >= rx && mx <= rx + rw && my >= ry && my <= ry + rh);
    });
}

function previewDe(clave) {
    const el = document.getElementById('ci-' + clave.replace(/\./g, '-'));
    return el ? el.querySelector('span:last-child').textContent : clave;
}

// Eventos de ratón
canvasWrap.addEventListener('mousemove', e => {
    const wr = canvasWrap.getBoundingClientRect();
    const mx = e.clientX - wr.left + canvasWrap.scrollLeft;
    const my = e.clientY - wr.top  + canvasWrap.scrollTop;

    if (drag.active) {
        if (drag.mode === 'draw') {
            const x = Math.min(drag.startX, mx), y = Math.min(drag.startY, my);
            preview.style.left   = x + 'px'; preview.style.top    = y + 'px';
            preview.style.width  = Math.abs(mx - drag.startX) + 'px';
            preview.style.height = Math.abs(my - drag.startY) + 'px';
        } else {
            // Mover rectángulo
            const m = mapeo.find(r => r.dato === drag.movingDato);
            if (m) {
                m.x = Math.max(0, Math.round(((mx - drag.offX) / zoom) * 10) / 10);
                m.y = Math.max(0, Math.round(((my - drag.offY) / zoom) * 10) / 10);
                dibujarRectangulos();
            }
        }
        return;
    }
    const bajo = rectEnPunto(mx, my);
    canvasWrap.style.cursor = bajo ? 'grab' : (campoActivo ? 'crosshair' : 'default');
});

canvasWrap.addEventListener('mousedown', e => {
    if (!pdfDoc) return;
    e.preventDefault();
    const wr = canvasWrap.getBoundingClientRect();
    const mx = e.clientX - wr.left + canvasWrap.scrollLeft;
    const my = e.clientY - wr.top  + canvasWrap.scrollTop;

    const bajo = rectEnPunto(mx, my);
    if (bajo) {
        drag.active      = true;
        drag.mode        = 'move';
        drag.movingDato  = bajo.dato;
        drag.offX        = mx - bajo.x * zoom;
        drag.offY        = my - bajo.y * zoom;
        canvasWrap.style.cursor = 'grabbing';
        seleccionarCampo(bajo.dato, previewDe(bajo.dato));
        return;
    }
    if (!campoActivo) return;
    drag.active = true;
    drag.mode   = 'draw';
    drag.startX = mx; drag.startY = my;
    preview.style.left = mx + 'px'; preview.style.top = my + 'px';
    preview.style.width = '0'; preview.style.height = '0';
    preview.style.display = 'block';
});

canvasWrap.addEventListener('mouseup', e => {
    if (!drag.active) return;
    drag.active = false;
    canvasWrap.style.cursor = campoActivo ? 'crosshair' : 'default';

    if (drag.mode === 'move') return;

    preview.style.display = 'none';
    const wr   = canvasWrap.getBoundingClientRect();
    const curX = e.clientX - wr.left + canvasWrap.scrollLeft;
    const curY = e.clientY - wr.top  + canvasWrap.scrollTop;
    const pxX  = Math.min(drag.startX, curX);
    const pxY  = Math.min(drag.startY, curY);
    const pxW  = Math.abs(curX - drag.startX);
    const pxH  = Math.abs(curY - drag.startY);
    if (pxW < 5 || pxH < 5) return;

    const ptX = Math.round((pxX / zoom) * 10) / 10;
    const ptY = Math.round((pxY / zoom) * 10) / 10;
    const ptW = Math.round((pxW / zoom) * 10) / 10;
    const ptH = Math.round((pxH / zoom) * 10) / 10;

    const fondoVal = document.getElementById('cfgColorFondo').value;
    const obj = {
        dato        : campoActivo,
        pagina      : pageNum,
        x           : ptX, 
        y           : ptY, 
        w           : ptW, 
        h           : ptH,
        font_size   : parseFloat(document.getElementById('cfgFontSize').value) || 7.5,
        bold        : document.getElementById('cfgStyle').value.includes('B'),
        italic      : document.getElementById('cfgStyle').value.includes('I'),
        align       : document.getElementById('cfgAlign').value,
        limpiar     : (fondoVal !== 'transparente'),
        color_fondo : fondoVal,
        letter_spacing: parseFloat(document.getElementById('cfgLetterSpacing').value) || 0,
    };

    const idx = mapeo.findIndex(m => m.dato === campoActivo);
    if (idx >= 0) mapeo[idx] = obj;
    else          mapeo.push(obj);

    marcarMapeado(campoActivo);
    dibujarRectangulos();
    actualizarContador();
});

function seleccionarCampo(clave, etiqueta) {
    document.querySelectorAll('.campo-item').forEach(el => el.classList.remove('activo'));
    if (campoActivo === clave) {
        campoActivo = null;
        document.getElementById('statusCampo').textContent = '👆 Selecciona un campo y arrastra en el PDF';
        document.getElementById('campoConfig').classList.remove('visible');
        return;
    }
    campoActivo = clave;
    const elId = 'ci-' + clave.replace(/\./g, '-');
    const elActivo = document.getElementById(elId);
    if (elActivo) elActivo.classList.add('activo');

    document.getElementById('statusCampo').textContent = `✏️ Mapeando campo: ${etiqueta}`;
    document.getElementById('campoConfig').classList.add('visible');

    const existente = mapeo.find(m => m.dato === clave);
    if (existente) {
        document.getElementById('cfgFontSize').value = existente.font_size ?? 7.5;
        let estiloVal = '';
        if (existente.bold) estiloVal += 'B';
        if (existente.italic) estiloVal += 'I';
        document.getElementById('cfgStyle').value = estiloVal;
        document.getElementById('cfgAlign').value = existente.align ?? 'left';
        
        // Sincronizar el selector unificado
        if (existente.limpiar === false) {
            document.getElementById('cfgColorFondo').value = 'transparente';
        } else {
            document.getElementById('cfgColorFondo').value = existente.color_fondo ?? 'blanco';
        }
        
        document.getElementById('cfgLetterSpacing').value = existente.letter_spacing ?? 0.0;
        if (existente.pagina !== pageNum) { pageNum = existente.pagina; renderizarPagina(); }
    } else {
        // Inicializar a valores por defecto si el campo es nuevo
        document.getElementById('cfgColorFondo').value = 'transparente';
        document.getElementById('cfgLetterSpacing').value = 0.0;
    }
}

function actualizarEstiloCampo() {
    if (!campoActivo) return;
    const m = mapeo.find(r => r.dato === campoActivo);
    if (m) {
        m.font_size   = parseFloat(document.getElementById('cfgFontSize').value) || 7.5;
        const style = document.getElementById('cfgStyle').value;
        m.bold        = style.includes('B');
        m.italic      = style.includes('I');
        m.align       = document.getElementById('cfgAlign').value;
        
        const fondoVal = document.getElementById('cfgColorFondo').value;
        m.limpiar     = (fondoVal !== 'transparente');
        m.color_fondo = fondoVal;
        
        m.letter_spacing = parseFloat(document.getElementById('cfgLetterSpacing').value) || 0;
        dibujarRectangulos();
    }
}

function eliminarCampoActivo() {
    if (!campoActivo) return;
    mapeo = mapeo.filter(m => m.dato !== campoActivo);
    const el = document.getElementById('ci-' + campoActivo.replace(/\./g, '-'));
    if (el) el.classList.remove('mapeado');
    campoActivo = null;
    document.getElementById('campoConfig').classList.remove('visible');
    document.getElementById('statusCampo').textContent = '👆 Selecciona un campo y arrastra en el PDF';
    dibujarRectangulos();
    actualizarContador();
}

function marcarMapeado(clave) {
    const el = document.getElementById('ci-' + clave.replace(/\./g, '-'));
    if (el) el.classList.add('mapeado');
}

let datosReales = null;

function cargarDatosReales() {
    const ced = document.getElementById('ejemploCedula').value.trim();
    const pla = document.getElementById('ejemploPlanilla').value.trim();
    if (!ced || !pla) {
        mostrarToast("Ingresa Cédula y Planilla", "#7f1d1d");
        return;
    }

    const status = document.getElementById('statusCampo');
    status.textContent = "⏳ Cargando datos reales de la planilla...";

    fetch(`/admin/configuracion/operadores/datos-ejemplo?cedula=${ced}&numero_planilla=${pla}`)
        .then(res => {
            if (!res.ok) throw new Error("No se encontraron registros.");
            return res.json();
        })
        .then(json => {
            datosReales = json;
            dibujarRectangulos();
            mostrarToast("Datos reales cargados correctamente");
            status.textContent = "✅ Datos reales aplicados sobre el visor";
        })
        .catch(err => {
            console.error(err);
            mostrarToast("Error al cargar datos del plano", "#7f1d1d");
            status.textContent = "❌ Error: no se encontró el plano";
        });
}

function mostrarToast(msg, bg = "#15803d") {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.style.background = bg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2500);
}

function dibujarRectangulos() {
    rectsLayer.innerHTML = '';
    mapeo.forEach(r => {
        if (r.pagina !== pageNum) return;
        
        const el = document.createElement('div');
        el.className = 'rect-overlay';
        if (campoActivo === r.dato) el.classList.add('activo');
        
        // Estilos e interactividad del contenedor
        el.style.left   = (r.x * zoom) + 'px';
        el.style.top    = (r.y * zoom) + 'px';
        el.style.width  = (r.w * zoom) + 'px';
        el.style.height = (r.h * zoom) + 'px';
        el.style.borderColor = (campoActivo === r.dato) ? '#3b82f6' : '#16a34a';
        el.style.backgroundColor = (campoActivo === r.dato) ? 'rgba(59, 130, 246, 0.15)' : 'rgba(22, 163, 74, 0.1)';
        
        // Label flotante
        const label = document.createElement('span');
        label.className = 'rect-label';
        label.style.backgroundColor = (campoActivo === r.dato) ? '#2563eb' : '#15803d';
        label.textContent = previewDe(r.dato);
        el.appendChild(label);
        
        // Texto interior simulado
        const pr = document.createElement('span');
        pr.className = 'rect-preview-text';
        
        // Cargar dato real si está disponible
        const valorReal = datosReales ? (datosReales[r.dato] ?? '') : null;
        pr.textContent = (valorReal !== null) ? valorReal : r.dato;

        // Estilos de previsualización de texto real exactos
        pr.style.fontSize = ((r.font_size ?? 7.5) * zoom) + 'px';
        pr.style.fontWeight = r.bold ? 'bold' : 'normal';
        pr.style.fontStyle = r.italic ? 'italic' : 'normal';
        pr.style.letterSpacing = ((r.letter_spacing ?? 0) * zoom) + 'px';
        
        const align = r.align ?? 'left';
        pr.style.textAlign = align;
        el.style.justifyContent = 'center'; // Alinear verticalmente al centro
        
        // Si hay datos reales cargados, usar color distintivo (verde brillante si es normal, blanco si está activo)
        if (datosReales) {
            pr.style.color = (campoActivo === r.dato) ? '#fff' : '#4ade80';
        } else {
            pr.style.color = (campoActivo === r.dato) ? '#fff' : 'rgba(255,255,255,0.7)';
        }

        el.appendChild(pr);
        rectsLayer.appendChild(el);
    });
}

function actualizarContador() {
    const total = document.querySelectorAll('.campo-item').length;
    document.getElementById('contadorCampos').textContent = `${mapeo.length} / ${total}`;
}

function guardarMapeo() {
    document.getElementById('inputMapeoJson').value = JSON.stringify(mapeo);
    document.getElementById('formMapeo').submit();
}

function descargarPdfPrueba() {
    const ced = document.getElementById('ejemploCedula').value.trim();
    const pla = document.getElementById('ejemploPlanilla').value.trim();
    if (!ced || !pla) {
        mostrarToast("Ingresa Cédula y Planilla", "#7f1d1d");
        return;
    }
    
    // Primero, guardar mapeo para asegurar que la descarga use la configuración que el usuario tiene en pantalla
    document.getElementById('inputMapeoJson').value = JSON.stringify(mapeo);
    
    // Abrir la descarga en una pestaña nueva con parámetro anticaché dinámico
    const url = `/admin/planos/certificado-pdf?cedula=${ced}&numero_planilla=${pla}&t=${Date.now()}`;
    window.open(url, '_blank');
}
</script>
@endsection
