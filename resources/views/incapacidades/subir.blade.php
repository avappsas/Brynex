<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subir Documentos de Incapacidad</title>
<meta name="description" content="Portal para cargar los documentos de su incapacidad médica de forma segura.">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:linear-gradient(135deg,#1e40af 0%,#1d4ed8 40%,#2563eb 100%);min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:2rem 1rem}
.wrap{width:100%;max-width:580px;margin-top:1rem}
.logo-bar{text-align:center;margin-bottom:1.5rem}
.logo-bar h1{color:#fff;font-size:1.4rem;font-weight:700}
.logo-bar p{color:rgba(255,255,255,.75);font-size:.85rem;margin-top:.3rem}
.card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden}
.card-header{background:linear-gradient(135deg,#1e40af,#3b82f6);padding:1.5rem 1.75rem;color:#fff}
.card-header h2{font-size:1.1rem;font-weight:700}
.card-header .sub{font-size:.82rem;opacity:.85;margin-top:.3rem}
.card-body{padding:1.75rem}
.info-row{display:flex;gap:.5rem;align-items:flex-start;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.82rem;color:#1e40af}
.info-row strong{display:block;font-size:.85rem}
.step-badge{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#2563eb;color:#fff;font-size:.75rem;font-weight:700;flex-shrink:0;margin-right:.4rem}
.form-group{margin-bottom:1.1rem}
.form-group label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.35rem}
.form-group input,.form-group select,.form-group textarea{width:100%;border:1.5px solid #d1d5db;border-radius:10px;padding:.6rem .9rem;font-size:.88rem;font-family:inherit;transition:border-color .2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.form-group textarea{resize:vertical;min-height:90px}
.btn{display:flex;align-items:center;justify-content:center;gap:.4rem;width:100%;padding:.75rem;border-radius:12px;font-size:.9rem;font-weight:700;border:none;cursor:pointer;transition:all .2s}
.btn-primary{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;box-shadow:0 4px 12px rgba(37,99,235,.35)}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(37,99,235,.45)}
.btn-outline{background:#f8fafc;color:#374151;border:1.5px solid #e2e8f0;margin-top:.75rem}
.btn-outline:hover{background:#f1f5f9}
.alert{border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;font-size:.83rem;display:flex;gap:.5rem;align-items:flex-start}
.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}
.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
.alert-info{background:#dbeafe;border:1px solid #93c5fd;color:#1e40af}
.docs-list{margin-top:1rem}
.doc-item{display:flex;align-items:center;gap:.6rem;padding:.6rem .9rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:.5rem;font-size:.82rem}
.doc-item .doc-icon{font-size:1.1rem}
.doc-item .doc-name{flex:1;font-weight:600;color:#1e293b}
.doc-item .doc-date{font-size:.72rem;color:#94a3b8}
.divider{border:none;border-top:1px solid #f1f5f9;margin:1.25rem 0}
.section-label{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:.75rem}
.file-zone{border:2px dashed #93c5fd;border-radius:12px;padding:1.5rem;text-align:center;cursor:pointer;transition:all .2s;background:#f0f9ff}
.file-zone:hover{background:#dbeafe;border-color:#60a5fa}
.file-zone input{display:none}
.file-zone .icon{font-size:2rem;margin-bottom:.4rem}
.file-zone p{font-size:.8rem;color:#64748b}
.file-zone strong{font-size:.85rem;color:#2563eb}
#file-name{font-size:.78rem;color:#059669;font-weight:600;margin-top:.5rem}
footer{text-align:center;color:rgba(255,255,255,.6);font-size:.75rem;margin-top:1.5rem;padding-bottom:1.5rem}
</style>
</head>
<body>

<div class="wrap">
    <div class="logo-bar">
        <h1>📄 Portal de Documentos</h1>
        <p>{{ $aliado->nombre ?? 'Sistema BryNex' }}</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>🏥 Subida de Documentos — Incapacidad</h2>
            <div class="sub">
                Tipo: {{ $inc->tipoIncapacidadLabel() }} · {{ $inc->dias_incapacidad }} días ·
                {{ $inc->fecha_inicio?->format('d/m/Y') }}
            </div>
        </div>
        <div class="card-body">

            {{-- Alertas de sesión --}}
            @if(session('success'))
            <div class="alert alert-success">✅ {{ session('success') }}</div>
            @endif
            @if(session('success_verificacion'))
            <div class="alert alert-success">{{ session('success_verificacion') }}</div>
            @endif
            @if($errors->any())
            <div class="alert alert-error">❌ {{ $errors->first() }}</div>
            @endif

            @if(!$verificado)
            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- PASO 1: Verificar identidad con cédula --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <div class="alert alert-info">
                <span>🔒</span>
                <div>Para proteger su información, primero debe verificar su identidad ingresando su número de cédula.</div>
            </div>

            <form method="POST" action="{{ route('incapacidades.subir.post', $token) }}">
                @csrf
                <div class="form-group">
                    <label><span class="step-badge">1</span> Número de Cédula</label>
                    <input type="text" name="cedula_verificacion"
                           placeholder="Ej: 1234567890"
                           inputmode="numeric" pattern="[0-9]*"
                           value="{{ old('cedula_verificacion') }}"
                           required autofocus>
                </div>
                <button type="submit" class="btn btn-primary">🔓 Verificar Identidad →</button>
            </form>

            @else
            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- PASO 2: Subir documentos --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}

            {{-- Documentos ya subidos --}}
            @if($docsYaSubidos->count() > 0)
            <div class="section-label">📂 Documentos ya recibidos</div>
            <div class="docs-list">
                @foreach($docsYaSubidos as $doc)
                <div class="doc-item">
                    <span class="doc-icon">📄</span>
                    <div class="doc-name">
                        {{ \App\Http\Controllers\IncapacidadUploadController::TIPOS_DOC[$doc->tipo_documento] ?? $doc->tipo_documento }}
                    </div>
                    <div class="doc-date">{{ \Carbon\Carbon::parse($doc->created_at)->format('d/m/Y H:i') }}</div>
                </div>
                @endforeach
            </div>
            <hr class="divider">
            @endif

            {{-- Formulario de subida --}}
            <form method="POST" action="{{ route('incapacidades.subir.post', $token) }}"
                  enctype="multipart/form-data" id="uploadForm">
                @csrf

                <div class="section-label">📤 Subir nuevo documento</div>

                <div class="form-group">
                    <label>Tipo de Documento *</label>
                    <select name="tipo_documento" required>
                        <option value="">Seleccione...</option>
                        @foreach(\App\Http\Controllers\IncapacidadUploadController::TIPOS_DOC as $k=>$v)
                        <option value="{{ $k }}" @selected(old('tipo_documento')==$k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Archivo (PDF, JPG, PNG · máx. 20 MB) *</label>
                    <div class="file-zone" onclick="document.getElementById('archivoInput').click()">
                        <input type="file" id="archivoInput" name="archivo"
                               accept=".pdf,.jpg,.jpeg,.png,.webp"
                               onchange="mostrarNombre(this)" required>
                        <div class="icon">📎</div>
                        <strong>Toque aquí para seleccionar el archivo</strong>
                        <p>o arrastre y suelte</p>
                        <div id="file-name"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Observación (opcional)</label>
                    <input type="text" name="observacion" placeholder="Ej: Incapacidad del 5 de mayo" value="{{ old('observacion') }}">
                </div>

                {{-- Descripción del cliente (solo se muestra si aún no hay una) --}}
                @if(!$inc->descripcion_cliente)
                <div class="form-group">
                    <label>📝 Cuéntenos qué le pasó (opcional)</label>
                    <textarea name="descripcion_cliente" placeholder="Describa brevemente el motivo de su incapacidad, cómo ocurrió, síntomas principales...">{{ old('descripcion_cliente') }}</textarea>
                </div>
                @else
                <div class="alert alert-info" style="font-size:.8rem">
                    <span>📝</span>
                    <div><strong>Su descripción ya fue recibida:</strong> "{{ Str::limit($inc->descripcion_cliente, 120) }}"</div>
                </div>
                @endif

                <button type="submit" class="btn btn-primary">📤 Enviar Documento</button>
            </form>

            <form method="POST" action="{{ route('incapacidades.subir.post', $token) }}" style="margin-top:.6rem">
                @csrf
                <input type="hidden" name="_logout_token" value="1">
                <button type="button" class="btn btn-outline" onclick="window.location.reload()">🔄 Actualizar</button>
            </form>

            @endif

        </div>{{-- card-body --}}
    </div>{{-- card --}}

    <footer>
        🔒 Enlace seguro · Sus documentos se protegen y procesan de forma confidencial.<br>
        Si tiene dudas comuníquese directamente con su asesor.
    </footer>
</div>

<script>
function mostrarNombre(input){
    const el = document.getElementById('file-name');
    if(input.files && input.files[0]){
        const f = input.files[0];
        const mb = (f.size / 1024 / 1024).toFixed(2);
        el.textContent = '✅ ' + f.name + ' (' + mb + ' MB)';
    } else {
        el.textContent = '';
    }
}

// Drag and drop
const zone = document.querySelector('.file-zone');
if(zone){
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.background='#dbeafe'; });
    zone.addEventListener('dragleave', () => { zone.style.background='#f0f9ff'; });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.background='#f0f9ff';
        const inp = document.getElementById('archivoInput');
        if(inp && e.dataTransfer.files.length){
            inp.files = e.dataTransfer.files;
            mostrarNombre(inp);
        }
    });
}
</script>
</body>
</html>
