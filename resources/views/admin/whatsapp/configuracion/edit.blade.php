@extends('layouts.app')
@section('titulo', 'WhatsApp')
@section('modulo', 'Configurar WhatsApp — {{ $aliado->nombre }}')

@push('styles')
<style>
.form-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.75rem; max-width:680px; }
.form-group { margin-bottom:1.1rem; }
.form-label { display:block; font-size:.82rem; font-weight:600; color:#374151; margin-bottom:.35rem; }
.form-control { width:100%; padding:.5rem .75rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.85rem; color:#0f172a; background:#fff; transition:border-color .15s; }
.form-control:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.form-hint { font-size:.73rem; color:#94a3b8; margin-top:.25rem; }
.toggle-group { display:flex; gap:1rem; margin-bottom:1.5rem; }
.toggle-option { flex:1; border:2px solid #e2e8f0; border-radius:10px; padding:1rem; cursor:pointer; transition:border-color .15s, background .15s; text-align:center; }
.toggle-option.active { border-color:#2563eb; background:rgba(37,99,235,.05); }
.toggle-option .icon { font-size:1.8rem; margin-bottom:.4rem; }
.toggle-option .label { font-weight:600; font-size:.85rem; color:#0f172a; }
.toggle-option .desc { font-size:.72rem; color:#64748b; margin-top:.2rem; }
.btn-row { display:flex; gap:.75rem; margin-top:1.5rem; }
.btn { padding:.5rem 1.25rem; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:opacity .15s; }
.btn:hover { opacity:.87; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-outline { background:transparent; border:1px solid #cbd5e1; color:#475569; }
.section-title { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; margin-bottom:1rem; padding-bottom:.4rem; border-bottom:1px solid #f1f5f9; }
</style>
@endpush

@section('contenido')
<div class="contenido">
    @if($errors->any())
        <div class="flash" style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.83rem;">
            @foreach($errors->all() as $e) <div>❌ {{ $e }}</div> @endforeach
        </div>
    @endif

    <div style="margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem;">
        <a href="{{ route('admin.whatsapp.config.index') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">← Volver a configuraciones</a>
        <div style="display:inline-flex; gap:.5rem;">
            <a href="{{ route('admin.whatsapp.config.switch_and_go', ['id' => $aliado->id, 'to' => 'plantillas.index']) }}" class="btn btn-outline" style="padding:.35rem .75rem; font-size:.75rem; border-radius:6px; display:inline-flex; align-items:center; gap:.25rem; margin:0;">
                📋 Plantillas del Aliado
            </a>
            <a href="{{ route('admin.whatsapp.config.switch_and_go', ['id' => $aliado->id, 'to' => 'plantillas.importar']) }}" class="btn btn-outline" style="padding:.35rem .75rem; font-size:.75rem; border-radius:6px; background:#f8fafc; display:inline-flex; align-items:center; gap:.25rem; margin:0;">
                📥 Importar desde Meta
            </a>
            <a href="{{ route('admin.whatsapp.config.switch_and_go', ['id' => $aliado->id, 'to' => 'plantillas.create']) }}" class="btn" style="padding:.35rem .75rem; font-size:.75rem; border-radius:6px; background:#10b981; color:#fff; display:inline-flex; align-items:center; gap:.25rem; margin:0;">
                ➕ Crear Plantilla
            </a>
        </div>
    </div>

    <div class="form-card" x-data="{ usaBrynex: {{ $config->usa_cuenta_brynex ? 'true' : 'false' }} }">
        <h2 style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:1.5rem">
            ⚙️ WhatsApp — {{ $aliado->nombre }}
        </h2>

        <form method="POST" action="{{ route('admin.whatsapp.config.update', $aliado->id) }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            {{-- Toggle cuenta Brynex / propia --}}
            <div class="section-title">Modo de cuenta</div>
            <div class="toggle-group">
                <div class="toggle-option" :class="usaBrynex ? 'active' : ''" @click="usaBrynex = true; $nextTick(() => toggleImageUpload())" style="cursor:pointer">
                    <div class="icon">🔵</div>
                    <div class="label">Usar cuenta Brynex</div>
                    <div class="desc">El aliado usa el número y WABA de Brynex</div>
                </div>
                <div class="toggle-option" :class="!usaBrynex ? 'active' : ''" @click="usaBrynex = false; $nextTick(() => toggleImageUpload())" style="cursor:pointer">
                    <div class="icon">🏢</div>
                    <div class="label">Cuenta propia del aliado</div>
                    <div class="desc">El aliado tiene su propio WABA y número</div>
                </div>
            </div>

            <input type="hidden" name="usa_cuenta_brynex" :value="usaBrynex ? '1' : '0'">

            {{-- Campos propios solo si usa cuenta propia --}}
            <div x-show="!usaBrynex" x-cloak>
                <div class="section-title">Credenciales de Meta Cloud API</div>

                <div class="form-group">
                    <label class="form-label">WABA ID (WhatsApp Business Account ID)</label>
                    <input type="text" name="waba_id" class="form-control" value="{{ old('waba_id', $config->waba_id) }}"
                           placeholder="Ej: 123456789012345">
                    <div class="form-hint">Encontrado en Meta Business Suite → Configuración de WhatsApp</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number ID</label>
                    <input type="text" name="phone_number_id" class="form-control" value="{{ old('phone_number_id', $config->phone_number_id) }}"
                           placeholder="Ej: 987654321098765">
                    <div class="form-hint">ID del número de teléfono registrado en Meta</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Access Token</label>
                    <input type="password" name="access_token" class="form-control"
                           placeholder="{{ $config->access_token ? '••••••••••••••••••••' : 'Token de Meta (permanente)' }}">
                    @if($config->access_token)
                        <div class="form-hint">⚠️ Dejar vacío para mantener el token actual</div>
                    @else
                        <div class="form-hint">Token permanente de Meta Cloud API</div>
                    @endif
                </div>

                <div class="form-group">
                    <label class="form-label">Nombre de la cuenta</label>
                    <input type="text" name="nombre_cuenta" class="form-control" value="{{ old('nombre_cuenta', $config->nombre_cuenta) }}"
                           placeholder="Nombre del perfil WhatsApp Business">
                    <div class="form-hint">Nombre que aparece como remitente en los mensajes.</div>
                </div>
            </div>

            {{-- Mensaje cuando usa Brynex --}}
            <div x-show="usaBrynex" x-cloak style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:1rem;font-size:.83rem;color:#1e40af;">
                ℹ️ Este aliado usará las credenciales globales de Brynex. Los mensajes saldrán del número oficial de Brynex.
                Para usar un número propio, selecciona <strong>"Cuenta propia del aliado"</strong>.
            </div>

            {{-- Sección de Plantilla de Cobros de Brynex e Imagen --}}
            <div class="section-title" style="margin-top:1.5rem">Configuración de Cobros por WhatsApp</div>
            
            {{-- Selector de plantillas para Brynex Global --}}
            <div class="form-group" x-show="usaBrynex" x-cloak>
                <label class="form-label">Plantilla de WhatsApp para Cobros (Brynex Global)</label>
                <select name="cobro_plantilla_id" class="form-control" :disabled="!usaBrynex" onchange="toggleImageUpload()">
                    <option value="">-- Seleccionar Plantilla Global --</option>
                    @foreach($plantillasGlobales as $plantilla)
                        <option value="{{ $plantilla->id }}" {{ old('cobro_plantilla_id', $config->cobro_plantilla_id) == $plantilla->id ? 'selected' : '' }} data-header-tipo="{{ $plantilla->header_tipo }}">
                            {{ $plantilla->nombre_display }} ({{ $plantilla->nombre }})
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Selecciona la plantilla global de Brynex que se enviará automáticamente.</div>
            </div>

            {{-- Selector de plantillas propias del Aliado --}}
            <div class="form-group" x-show="!usaBrynex" x-cloak>
                <label class="form-label">Plantilla de WhatsApp para Cobros (Propia del Aliado)</label>
                <select name="cobro_plantilla_id" class="form-control" :disabled="usaBrynex" onchange="toggleImageUpload()">
                    <option value="">-- Seleccionar Plantilla del Aliado --</option>
                    @foreach($plantillasBrynex->where('aliado_id', $aliado->id) as $plantilla)
                        <option value="{{ $plantilla->id }}" {{ old('cobro_plantilla_id', $config->cobro_plantilla_id) == $plantilla->id ? 'selected' : '' }} data-header-tipo="{{ $plantilla->header_tipo }}">
                            {{ $plantilla->nombre_display }} ({{ $plantilla->nombre }})
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Selecciona una de las plantillas creadas o importadas en la cuenta propia de Meta de este aliado.</div>
            </div>

            <div class="form-group" id="imageUploadSection" style="display:none">
                <label class="form-label">Imagen para Encabezado de la Plantilla</label>
                @if($config->cobro_header_imagen)
                    <div style="margin-bottom:.5rem">
                        <img src="{{ asset('storage/' . $config->cobro_header_imagen) }}" alt="Encabezado actual" style="max-height:100px; border-radius:6px; border:1px solid #cbd5e1; display:block">
                        <small style="color:#64748b">Imagen actual. Sube otra si deseas reemplazarla.</small>
                    </div>
                @endif
                <input type="file" name="cobro_header_imagen" class="form-control" accept="image/*" onchange="validarTamanoImagen(this)">
                <div class="form-hint">La plantilla seleccionada requiere una imagen como encabezado (HEADER). Sube una imagen PNG o JPG de máximo 2MB.</div>
            </div>

            {{-- WhatsApp de Contacto / Variable {{5}} — siempre visible --}}
            <div class="form-group" style="margin-top:1rem;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:1rem;">
                <label class="form-label" style="color:#92400e;">
                    WhatsApp de Contacto del Aliado
                    <span style="background:#fef3c7;color:#92400e;border-radius:4px;padding:.1rem .4rem;font-size:.68rem;font-weight:700;margin-left:.4rem;">Variable {{5}}</span>
                </label>
                <input type="text" name="numero_telefono" class="form-control"
                       value="{{ old('numero_telefono', $config->numero_telefono) }}"
                       placeholder="Ej: 3001234567 ó 573001234567"
                       style="background:#fff;">
                <div class="form-hint" style="color:#b45309;">
                    Número que el cliente verá en el mensaje de cobro para contactar al aliado (variable <strong>{{5}}</strong> de la plantilla).
                </div>
            </div>

            {{-- Checkbox de Activo y Botones de Guardar --}}
            <div class="form-group" style="margin-top:1.5rem;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.83rem;font-weight:500">
                    <input type="checkbox" name="activo" value="1" {{ $config->activo ? 'checked' : '' }} style="width:16px;height:16px;">
                    Módulo WhatsApp activo para este aliado
                </label>
            </div>

            <div class="btn-row" style="margin-bottom: 2rem;">
                <button type="submit" class="btn btn-primary">💾 Guardar configuración</button>
                <a href="{{ route('admin.whatsapp.config.index') }}" class="btn btn-outline">Cancelar</a>
            </div>

        </form>


    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleImageUpload() {
    // Buscar el select que esté actualmente habilitado (no disabled)
    const select = document.querySelector('select[name="cobro_plantilla_id"]:not([disabled])');
    const imageSection = document.getElementById('imageUploadSection');
    
    if (!select || !imageSection) return;
    
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption) {
        imageSection.style.display = 'none';
        return;
    }
    
    const headerTipo = selectedOption.getAttribute('data-header-tipo');
    if (headerTipo === 'IMAGE') {
        imageSection.style.display = 'block';
    } else {
        imageSection.style.display = 'none';
    }
}

document.addEventListener("DOMContentLoaded", function() {
    toggleImageUpload();
});

function validarTamanoImagen(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 2 * 1024 * 1024; // 2MB en bytes
        
        if (file.size > maxSize) {
            alert("⚠️ La imagen seleccionada pesa " + (file.size / (1024 * 1024)).toFixed(2) + "MB, lo cual supera el límite máximo de 2MB soportado por el servidor.\n\nPor favor, selecciona una imagen de máximo 2MB.");
            input.value = ""; // Limpiar el input para evitar enviar el archivo
        }
    }
}
</script>
@endpush
