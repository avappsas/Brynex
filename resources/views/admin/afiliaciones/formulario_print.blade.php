<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Formulario {{ $eps->nombre }} — {{ $nombreCompleto }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #0f172a; height: 100vh; display: flex; flex-direction: column; }

        /* ── Barra superior ─────── */
        .top-bar {
            background: linear-gradient(135deg, #0f172a, #1e3a5f 60%, #1e40af);
            padding: 0.65rem 1.25rem;
            display: flex; align-items: center; gap: 0.6rem; flex-shrink: 0;
            box-shadow: 0 2px 12px rgba(0,0,0,0.4);
            flex-wrap: wrap;
        }
        .top-bar .info { flex: 1; min-width: 0; }
        .top-bar h1 { font-size: 0.92rem; font-weight: 700; color: #e2e8f0; }
        .top-bar p  { font-size: 0.7rem; color: #64748b; margin-top: 1px; }

        .btn { display:inline-flex; align-items:center; gap:0.35rem;
            border:none; border-radius:8px; padding:0.48rem 1rem;
            font-size:0.8rem; font-weight:700; cursor:pointer; white-space:nowrap;
            transition:filter 0.15s; text-decoration:none; }
        .btn:hover { filter:brightness(1.12); }
        .btn-primary   { background:#2563eb; color:#fff; }
        .btn-success   { background:#16a34a; color:#fff; }
        .btn-secondary { background:rgba(255,255,255,0.08); color:#94a3b8;
            border:1px solid rgba(255,255,255,0.12); }
        .btn-secondary:hover { color:#e2e8f0; }
        .btn-warning   { background:#d97706; color:#fff; }

        /* ── Iframe PDF ─────────── */
        #pdfFrame { flex:1; width:100%; border:none; background:#1e293b; }

        /* ── Modal firma ─────────── */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75);
            z-index:1000; align-items:center; justify-content:center;
        }
        .modal-overlay.open { display:flex; }
        .modal {
            background:#0f172a; border:1px solid #1e3a5f; border-radius:16px;
            padding:1.5rem; width:min(520px, 96vw); display:flex; flex-direction:column; gap:1rem;
            box-shadow:0 20px 60px rgba(0,0,0,0.5);
        }
        .modal h2 { color:#e2e8f0; font-size:1rem; font-weight:700; }

        /* Tabs */
        .tabs { display:flex; gap:0.4rem; border-bottom:1px solid #1e3a5f; padding-bottom:0.5rem; }
        .tab-btn { background:none; border:none; color:#64748b; font-size:0.82rem; font-weight:600;
            padding:0.3rem 0.8rem; border-radius:6px; cursor:pointer; transition:all .15s; }
        .tab-btn.activo { background:#1e3a5f; color:#93c5fd; }

        /* Panel texto-firma */
        .tab-panel { display:none; }
        .tab-panel.activo { display:block; }

        .firma-preview {
            background:#fff; border-radius:8px; min-height:120px;
            display:flex; align-items:center; justify-content:center;
            font-family:'Dancing Script', cursive; font-size:2.8rem; color:#1e293b;
            padding:1rem; text-align:center; line-height:1.1; word-break:break-word;
        }
        .size-slider { width:100%; accent-color:#3b82f6; }
        label.sl { font-size:0.72rem; color:#64748b; display:block; margin-bottom:0.3rem; }

        /* Panel canvas */
        #canvasFirma {
            width:100%; height:160px; border-radius:8px; cursor:crosshair;
            background:#fff; touch-action:none;
        }
        .canvas-tools { display:flex; gap:0.5rem; align-items:center; margin-top:0.4rem; }
        .canvas-tools label { font-size:0.72rem; color:#64748b; }
        #colorFirma { width:32px; height:28px; padding:0; border:none; border-radius:4px; cursor:pointer; }
        #grosorFirma { width:80px; accent-color:#3b82f6; }

        /* Botones modal */
        .modal-footer { display:flex; gap:0.5rem; justify-content:flex-end; margin-top:0.5rem; }

        /* Indicador de firma guardada */
        .firma-guardada { font-size:0.72rem; color:#86efac; display:none; }
        .firma-guardada.ok { display:inline; }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="info">
        <h1>📋 {{ $eps->nombre }} — {{ $nombreCompleto }}</h1>
        <p>{{ $empresa }} &nbsp;·&nbsp; Ingreso: {{ $fechaIngreso }} &nbsp;·&nbsp; Salario: {{ $salario }}</p>
    </div>

    @if($conBeneficiarios)
    <a href="{{ route('admin.afiliaciones.formulario.eps', ['contrato' => $contrato->id]) }}"
       class="btn btn-secondary" target="_blank">👤 Sin beneficiarios</a>
    @else
    <a href="{{ route('admin.afiliaciones.formulario.eps', ['contrato' => $contrato->id, 'beneficiarios' => 1]) }}"
       class="btn btn-secondary" target="_blank">👨‍👩‍👧 Con beneficiarios</a>
    @endif

    {{-- Botón firmar --}}
    <button class="btn btn-warning" onclick="abrirModalFirma()">
        ✍️ Firmar
        <span class="firma-guardada" id="firmaOk">✅</span>
    </button>

    <button class="btn btn-primary" onclick="document.getElementById('pdfFrame').contentWindow.print()">
        🖨️ Imprimir / Guardar PDF
    </button>
</div>

<iframe id="pdfFrame"
    src="{{ route('admin.afiliaciones.formulario.eps.raw', ['contrato' => $contrato->id, 'beneficiarios' => $conBeneficiarios ? 1 : 0]) }}"
    title="Formulario {{ $eps->nombre }}">
</iframe>

{{-- ══ MODAL FIRMA ══ --}}
<div class="modal-overlay" id="modalFirma">
<div class="modal">

    <h2>✍️ Agregar firma del cliente</h2>

    <div class="tabs">
        <button class="tab-btn activo" onclick="switchTab('texto')">🔤 Nombre como firma</button>
        <button class="tab-btn"        onclick="switchTab('canvas')">✏️ Dibujar firma</button>
    </div>

    {{-- Tab: texto estilo firma --}}
    <div class="tab-panel activo" id="tabTexto">
        <div class="firma-preview" id="firmaTextoPreview">{{ $nombreCompleto }}</div>
        <div style="margin-top:0.75rem;">
            <label class="sl">Tamaño de la firma</label>
            <input type="range" class="size-slider" id="firmaSize" min="1.5" max="5" step="0.1" value="2.8"
                oninput="document.getElementById('firmaTextoPreview').style.fontSize = this.value + 'rem'">
        </div>
    </div>

    {{-- Tab: canvas dibujo --}}
    <div class="tab-panel" id="tabCanvas">
        <canvas id="canvasFirma" width="300" height="100"></canvas>
        <div class="canvas-tools">
            <label>Color:</label>
            <input type="color" id="colorFirma" value="#1e293b">
            <label>Grosor:</label>
            <input type="range" id="grosorFirma" min="1" max="8" value="2.5" step="0.5">
            <button class="btn btn-secondary" style="padding:0.25rem 0.65rem;font-size:0.72rem;" onclick="limpiarCanvas()">🗑 Limpiar</button>
        </div>
    </div>

    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="cerrarModalFirma()">Cancelar</button>
        <button class="btn btn-success"   onclick="guardarFirma()">💾 Guardar y aplicar</button>
    </div>

</div>
</div>

<script>
const FIRMA_URL = "{{ route('admin.afiliaciones.formulario.eps.firma', $contrato->id) }}";
const PDF_RAW   = "{{ route('admin.afiliaciones.formulario.eps.raw', ['contrato' => $contrato->id, 'beneficiarios' => $conBeneficiarios ? 1 : 0]) }}";
const CSRF      = document.querySelector('meta[name="csrf-token"]').content;
let tabActivo   = 'texto';

// ── Modal ────────────────────────────────────────────
function abrirModalFirma() {
    document.getElementById('modalFirma').classList.add('open');
    initCanvas();
}
function cerrarModalFirma() {
    document.getElementById('modalFirma').classList.remove('open');
}

// ── Tabs ─────────────────────────────────────────────
function switchTab(tab) {
    tabActivo = tab;
    document.querySelectorAll('.tab-btn').forEach((b,i) =>
        b.classList.toggle('activo', (i === 0 && tab==='texto') || (i===1 && tab==='canvas')));
    document.getElementById('tabTexto').classList.toggle('activo', tab === 'texto');
    document.getElementById('tabCanvas').classList.toggle('activo', tab === 'canvas');
}

// ── Guardar firma ─────────────────────────────────────
async function guardarFirma() {
    const dataUrl = tabActivo === 'texto' ? capturarTexto() : capturarCanvas();
    if (!dataUrl) return;

    // Enviar como FormData para evitar límites de JSON
    const form = new FormData();
    form.append('_token', CSRF);
    form.append('firma', dataUrl);

    const res = await fetch(FIRMA_URL, { method: 'POST', body: form });
    const txt = await res.text();

    if (res.ok) {
        try {
            const j = JSON.parse(txt);
            if (j.ok) {
                document.getElementById('firmaOk').classList.add('ok');
                cerrarModalFirma();
                document.getElementById('pdfFrame').src = PDF_RAW + (PDF_RAW.includes('?') ? '&' : '?') + '_t=' + Date.now();
                return;
            }
        } catch(e) {}
    }
    console.error('Firma error:', txt);
    alert('Error al guardar firma:\n' + txt.slice(0, 300));
}

// ── Texto como firma ──────────────────────────────────
function capturarTexto() {
    const preview = document.getElementById('firmaTextoPreview');
    const size    = parseFloat(document.getElementById('firmaSize').value);
    // Canvas pequeño = PNG ligero, fondo transparente
    const canvas  = document.createElement('canvas');
    canvas.width  = 300;
    canvas.height = 90;
    const ctx = canvas.getContext('2d');
    // Sin fillRect = fondo transparente
    ctx.fillStyle    = '#1e293b';
    ctx.font         = `bold ${Math.round(size * 12)}px 'Dancing Script', cursive`;
    ctx.textAlign    = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(preview.textContent.trim(), 150, 45);
    return canvas.toDataURL('image/png');
}

// ── Canvas dibujo ─────────────────────────────────────
let canvasInicializado = false;
let dibujando = false;
let lastX = 0, lastY = 0;

function initCanvas() {
    if (canvasInicializado) return;
    canvasInicializado = true;
    const c   = document.getElementById('canvasFirma');
    const ctx = c.getContext('2d');
    // Sin relleno blanco = fondo transparente
    ctx.lineJoin = ctx.lineCap = 'round';

    const pos = (e) => {
        const r = c.getBoundingClientRect();
        const t = e.touches?.[0] ?? e;
        return [(t.clientX - r.left) * (c.width / r.width),
                (t.clientY - r.top)  * (c.height / r.height)];
    };

    const start = (e) => {
        dibujando = true;
        [lastX, lastY] = pos(e);
    };
    const draw = (e) => {
        if (!dibujando) return;
        e.preventDefault();
        const [x, y] = pos(e);
        ctx.strokeStyle = document.getElementById('colorFirma').value;
        ctx.lineWidth   = parseFloat(document.getElementById('grosorFirma').value);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(x, y);
        ctx.stroke();
        [lastX, lastY] = [x, y];
    };
    const stop = () => dibujando = false;

    c.addEventListener('mousedown', start);
    c.addEventListener('mousemove', draw);
    c.addEventListener('mouseup',   stop);
    c.addEventListener('mouseleave',stop);
    c.addEventListener('touchstart', start, { passive: false });
    c.addEventListener('touchmove',  draw,  { passive: false });
    c.addEventListener('touchend',   stop);
}

function limpiarCanvas() {
    const c = document.getElementById('canvasFirma');
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, c.width, c.height); // clearRect = transparente
}

function capturarCanvas() {
    const c = document.getElementById('canvasFirma');
    // Verificar si está vacío (compare con canvas blanco)
    return c.toDataURL('image/png');
}
</script>
</body>
</html>
