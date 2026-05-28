@extends('layouts.app')
@section('titulo', 'WhatsApp')
@section('modulo', $plantilla ? 'Editar Plantilla' : 'Nueva Plantilla')

@push('styles')
<style>
.form-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.75rem; max-width:760px; }
.form-group { margin-bottom:1rem; }
.form-label { display:block; font-size:.82rem; font-weight:600; color:#374151; margin-bottom:.35rem; }
.form-label .req { color:#ef4444; }
.form-control { width:100%; padding:.5rem .75rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.85rem; color:#0f172a; background:#fff; transition:border-color .15s; }
.form-control:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.form-hint { font-size:.73rem; color:#94a3b8; margin-top:.25rem; }
.section-title { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; margin:1.5rem 0 .75rem; padding-bottom:.4rem; border-bottom:1px solid #f1f5f9; }
.row-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.btn { padding:.45rem 1rem; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:opacity .15s; }
.btn:hover { opacity:.87; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-outline { background:transparent; border:1px solid #cbd5e1; color:#475569; }
.btn-danger { background:#ef4444; color:#fff; padding:.3rem .65rem; font-size:.76rem; border-radius:6px; }
.boton-item { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.75rem; margin-bottom:.5rem; }
.preview-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:1rem; font-size:.85rem; color:#1e40af; min-height:80px; white-space:pre-wrap; }
.preview-label { font-size:.72rem; font-weight:700; color:#3b82f6; margin-bottom:.4rem; }
</style>
@endpush

@section('content')
<div class="contenido">
    @if($errors->any())
        <div class="flash" style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.83rem;">
            @foreach($errors->all() as $e) <div>❌ {{ $e }}</div> @endforeach
        </div>
    @endif

    <div style="margin-bottom:1rem">
        <a href="{{ route('admin.whatsapp.plantillas.index') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">← Volver a plantillas</a>
    </div>

    <div class="form-card" x-data="plantillaForm()" x-init="init()">
        <h2 style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:1.5rem">
            {{ $plantilla ? '✏️ Editar Plantilla' : '+ Nueva Plantilla de WhatsApp' }}
        </h2>

        <form method="POST" action="{{ $plantilla ? route('admin.whatsapp.plantillas.update', $plantilla->id) : route('admin.whatsapp.plantillas.store') }}">
            @csrf
            @if($plantilla) @method('PUT') @endif

            <div class="section-title">Información básica</div>
            <div class="row-2">
                <div class="form-group">
                    <label class="form-label">Nombre en Meta <span class="req">*</span></label>
                    <input type="text" name="nombre" class="form-control" required
                           pattern="[a-z0-9_]+" title="Solo letras minúsculas, números y guión bajo"
                           value="{{ old('nombre', $plantilla?->nombre) }}"
                           placeholder="cobro_mensual_v1" @input="actualizarPreview()">
                    <div class="form-hint">Solo minúsculas, números y _ (sin espacios)</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nombre visible <span class="req">*</span></label>
                    <input type="text" name="nombre_display" class="form-control" required
                           value="{{ old('nombre_display', $plantilla?->nombre_display) }}"
                           placeholder="Cobro mensual de planilla">
                </div>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label class="form-label">Categoría <span class="req">*</span></label>
                    <select name="categoria" class="form-control" required>
                        @foreach(['UTILITY' => 'Utilidad', 'MARKETING' => 'Marketing', 'AUTHENTICATION' => 'Autenticación'] as $val => $label)
                            <option value="{{ $val }}" {{ old('categoria', $plantilla?->categoria ?? 'UTILITY') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Idioma</label>
                    <select name="idioma" class="form-control">
                        <option value="es_CO" {{ old('idioma', $plantilla?->idioma ?? 'es_CO') === 'es_CO' ? 'selected' : '' }}>Español Colombia (es_CO)</option>
                        <option value="es" {{ old('idioma', $plantilla?->idioma) === 'es' ? 'selected' : '' }}>Español (es)</option>
                        <option value="en_US" {{ old('idioma', $plantilla?->idioma) === 'en_US' ? 'selected' : '' }}>Inglés US (en_US)</option>
                    </select>
                </div>
            </div>

            <div class="section-title">Contenido del mensaje</div>

            {{-- Header --}}
            <div class="row-2">
                <div class="form-group">
                    <label class="form-label">Tipo de Header</label>
                    <select name="header_tipo" class="form-control" x-model="headerTipo">
                        <option value="">Sin header</option>
                        <option value="TEXT">Texto</option>
                        <option value="IMAGE">Imagen (URL)</option>
                        <option value="DOCUMENT">Documento (URL)</option>
                    </select>
                </div>
                <div class="form-group" x-show="headerTipo">
                    <label class="form-label">Valor del Header</label>
                    <input type="text" name="header_valor" class="form-control"
                           value="{{ old('header_valor', $plantilla?->header_valor) }}"
                           :placeholder="headerTipo === 'TEXT' ? 'Texto del encabezado' : 'URL de la imagen o documento'">
                </div>
            </div>

            {{-- Cuerpo --}}
            <div class="form-group">
                <label class="form-label">Cuerpo del mensaje <span class="req">*</span></label>
                <textarea name="cuerpo" class="form-control" rows="5" required
                          placeholder="Hola {{1}}, te informamos que tienes un pago pendiente de ${{2}} con vencimiento el {{3}}."
                          @input="actualizarPreview()">{{ old('cuerpo', $plantilla?->cuerpo) }}</textarea>
                <div class="form-hint">Usa {{1}}, {{2}}, {{3}}... para insertar variables dinámicas. Ej: {{1}} = nombre del cliente</div>
            </div>

            {{-- Footer --}}
            <div class="form-group">
                <label class="form-label">Footer</label>
                <input type="text" name="footer" class="form-control" maxlength="60"
                       value="{{ old('footer', $plantilla?->footer) }}"
                       placeholder="Brynex Soluciones S.A.S">
                <div class="form-hint">Máximo 60 caracteres</div>
            </div>

            {{-- Preview --}}
            <div style="margin-bottom:1.25rem">
                <div class="preview-label">👁 Vista previa</div>
                <div class="preview-box" x-text="preview || 'El preview aparecerá aquí mientras escribes...'"></div>
            </div>

            <div class="section-title">Botones (opcional, máximo 3)</div>

            <template x-for="(boton, idx) in botones" :key="idx">
                <div class="boton-item">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                        <strong style="font-size:.78rem;color:#374151" x-text="'Botón ' + (idx+1)"></strong>
                        <button type="button" class="btn-danger" @click="quitarBoton(idx)">🗑 Quitar</button>
                    </div>
                    <div class="row-2">
                        <div>
                            <label class="form-label">Tipo</label>
                            <select :name="'botones[' + idx + '][tipo]'" class="form-control" x-model="boton.tipo">
                                <option value="QUICK_REPLY">Respuesta rápida</option>
                                <option value="URL">Abrir URL</option>
                                <option value="PHONE_NUMBER">Llamar teléfono</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Texto del botón</label>
                            <input type="text" :name="'botones[' + idx + '][texto]'" class="form-control" x-model="boton.texto"
                                   maxlength="25" placeholder="Ej: Ya pagué">
                        </div>
                    </div>
                    <div x-show="boton.tipo === 'URL'" style="margin-top:.5rem">
                        <label class="form-label">URL</label>
                        <input type="url" :name="'botones[' + idx + '][url]'" class="form-control" x-model="boton.url"
                               placeholder="https://brynex.co/pago">
                    </div>
                    <div x-show="boton.tipo === 'PHONE_NUMBER'" style="margin-top:.5rem">
                        <label class="form-label">Número de teléfono</label>
                        <input type="text" :name="'botones[' + idx + '][telefono]'" class="form-control" x-model="boton.telefono"
                               placeholder="+573001234567">
                    </div>
                </div>
            </template>

            <button type="button" class="btn btn-outline" @click="agregarBoton()" x-show="botones.length < 3"
                    style="margin-bottom:1.25rem">+ Agregar botón</button>

            <div class="section-title">Opciones de publicación</div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.83rem">
                    <input type="checkbox" name="crear_en_meta" value="1" style="width:16px;height:16px;"
                           {{ old('crear_en_meta') ? 'checked' : '' }}>
                    Crear también en Meta ahora (la aprobación puede tardar 24-72h)
                </label>
                <div class="form-hint">Si no marcas esta opción, la plantilla se guarda en el sistema pero debes crearla manualmente en Meta Business Suite.</div>
            </div>

            <div style="display:flex;gap:.75rem;margin-top:1.5rem">
                <button type="submit" class="btn btn-primary">💾 {{ $plantilla ? 'Actualizar' : 'Crear' }} plantilla</button>
                <a href="{{ route('admin.whatsapp.plantillas.index') }}" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function plantillaForm() {
    return {
        headerTipo: '{{ old('header_tipo', $plantilla?->header_tipo ?? '') }}',
        preview: '',
        botones: @json(old('botones', $plantilla?->botones ?? [])),

        init() {
            this.actualizarPreview();
        },

        actualizarPreview() {
            const cuerpo = document.querySelector('[name="cuerpo"]')?.value || '';
            this.preview = cuerpo
                .replace(/\{\{1\}\}/g, '[Nombre cliente]')
                .replace(/\{\{2\}\}/g, '[Valor deuda]')
                .replace(/\{\{3\}\}/g, '[Fecha vencimiento]')
                .replace(/\{\{(\d+)\}\}/g, '[Variable $1]');
        },

        agregarBoton() {
            if (this.botones.length >= 3) return;
            this.botones.push({ tipo: 'QUICK_REPLY', texto: '', url: '', telefono: '' });
        },

        quitarBoton(idx) {
            this.botones.splice(idx, 1);
        }
    };
}
</script>
@endpush
