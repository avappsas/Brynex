@extends('layouts.app')
@section('modulo', $rs ? 'Editar Razón Social' : 'Nueva Razón Social')

@section('contenido')
<style>
.rs-wrap{max-width:820px;margin:0 auto}
.rs-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1.2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
.card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1.3rem;margin-bottom:1rem}
.card-title{font-size:.82rem;font-weight:800;color:#0f172a;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid #e2e8f0;text-transform:uppercase;letter-spacing:.04em}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.7rem}
.form-full{grid-column:1/-1}
.flb{display:block;font-size:.67rem;font-weight:700;color:#475569;margin-bottom:.18rem;text-transform:uppercase;letter-spacing:.02em}
.flb span{color:#ef4444;margin-left:.15rem}
.finp{width:100%;padding:.42rem .6rem;border:1.5px solid #cbd5e1;border-radius:7px;font-size:.84rem;box-sizing:border-box;transition:border-color .15s;background:#fff}
.finp:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.finp-disabled{background:#f8fafc;color:#94a3b8;cursor:not-allowed}
.toggle-wrap{display:flex;align-items:center;gap:.6rem;padding:.6rem .8rem;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;user-select:none;transition:all .15s}
.toggle-wrap:has(input:checked){border-color:#7c3aed;background:#faf5ff}
.toggle-lbl{font-size:.82rem;font-weight:600;color:#334155}
.toggle-sub{font-size:.65rem;color:#94a3b8;margin-top:.1rem}
.badge-helper{font-size:.62rem;background:#ede9fe;color:#7c3aed;padding:.1rem .4rem;border-radius:12px;font-weight:700;margin-left:.4rem}
.btn-save{padding:.6rem 1.8rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;border-radius:9px;color:#fff;font-size:.9rem;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(37,99,235,.3)}
.btn-save:hover{opacity:.9}
.btn-cancel{padding:.6rem 1.2rem;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:8px;font-size:.88rem;font-weight:600;cursor:pointer;text-decoration:none}
.hint{font-size:.62rem;color:#94a3b8;margin:.18rem 0 0}
</style>

<div class="rs-wrap">

{{-- Header --}}
<div class="rs-header">
    <div>
        <a href="{{ route('admin.configuracion.razones.index') }}" style="color:#94a3b8;font-size:.75rem;text-decoration:none">← Razones Sociales</a>
        <div style="font-size:1.1rem;font-weight:800;margin-top:.2rem">
            {{ $rs ? '✏️ Editar: '.Str::limit($rs->razon_social, 40) : '🏭 Nueva Razón Social' }}
        </div>
    </div>
    @if($rs)
    <div style="font-size:.75rem;color:#94a3b8">
        ID: {{ $rs->id }} &nbsp;|&nbsp; NIT: {{ $rs->nit ?? $rs->id }}
    </div>
    @endif
</div>

@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.65rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#dc2626">
    @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
</div>
@endif

<form method="POST"
      action="{{ $rs ? route('admin.configuracion.razones.update', $rs->id) : route('admin.configuracion.razones.store') }}">
    @csrf
    @if($rs) @method('PUT') @endif

    {{-- ── Identificación ── --}}
    <div class="card">
        <div class="card-title">🔢 Identificación</div>
        <div class="form-grid">
            {{-- ID interno (PK) --}}
            <div>
                <label class="flb">ID interno (PK) <span>*</span></label>
                <input class="finp {{ $rs ? 'finp-disabled' : '' }}" type="number" name="id"
                       value="{{ old('id', $rs?->id) }}"
                       {{ $rs ? 'readonly' : 'required' }}
                       placeholder="Ej: 37">
                @if($rs)<p class="hint">No se puede cambiar</p>@endif
            </div>
            {{-- NIT real --}}
            <div>
                <label class="flb">NIT de la empresa</label>
                <input class="finp" type="number" name="nit"
                       value="{{ old('nit', $rs?->nit) }}"
                       placeholder="Ej: 900123456">
                <p class="hint">NIT que aparece en los formularios y documentos</p>
            </div>
            {{-- DV --}}
            <div>
                <label class="flb">Dígito de verificación (DV)</label>
                <input class="finp" type="number" name="dv" min="0" max="9"
                       value="{{ old('dv', $rs?->dv) }}"
                       placeholder="0-9">
            </div>
            {{-- Estado --}}
            <div>
                <label class="flb">Estado</label>
                <select class="finp" name="estado">
                    <option value="Activa"   {{ old('estado', $rs?->estado ?? 'Activa') === 'Activa'   ? 'selected' : '' }}>Activa</option>
                    <option value="Inactiva" {{ old('estado', $rs?->estado)              === 'Inactiva' ? 'selected' : '' }}>Inactiva</option>
                </select>
            </div>
            {{-- Razón Social --}}
            <div class="form-full">
                <label class="flb">Razón Social (nombre) <span>*</span></label>
                <input class="finp" type="text" name="razon_social"
                       value="{{ old('razon_social', $rs?->razon_social) }}"
                       required placeholder="Nombre completo de la empresa" style="text-transform:uppercase"
                       oninput="this.value=this.value.toUpperCase()">
            </div>
        </div>
    </div>

    {{-- ── Tipo de Afiliación ── --}}
    <div class="card">
        <div class="card-title">⚙️ Tipo de Afiliación</div>
        <label class="toggle-wrap">
            <input type="hidden"   name="es_independiente" value="0">
            <input type="checkbox" name="es_independiente" value="1"
                   style="width:16px;height:16px;accent-color:#7c3aed"
                   {{ old('es_independiente', $rs?->es_independiente ?? false) ? 'checked' : '' }}>
            <div>
                <div class="toggle-lbl">Razón Social Independiente <span class="badge-helper">Indep.</span></div>
                <div class="toggle-sub">Los contratos solo usarán modalidades independientes (I Act, I Venc, En el Exterior)</div>
            </div>
        </label>
    </div>

    {{-- ── Contacto y Ubicación ── --}}
    <div class="card">
        <div class="card-title">📍 Contacto y Ubicación</div>
        <div class="form-grid">
            <div class="form-full">
                <label class="flb">Dirección</label>
                <input class="finp" type="text" name="direccion"
                       value="{{ old('direccion', $rs?->direccion) }}"
                       placeholder="Ej: Calle 15 # 5-20, Cali">
            </div>
            <div>
                <label class="flb">Teléfonos</label>
                <input class="finp" type="text" name="telefonos"
                       value="{{ old('telefonos', $rs?->telefonos) }}"
                       placeholder="Ej: 6024567890, 3001234567">
            </div>
            <div>
                <label class="flb">Correos electrónicos</label>
                <input class="finp" type="text" name="correos"
                       value="{{ old('correos', $rs?->correos) }}"
                       placeholder="Ej: empresa@mail.com">
            </div>
            {{-- Teléfono y correo para formularios (PILA) --}}
            <div>
                <label class="flb">Teléfono (formularios PILA)</label>
                <input class="finp" type="text" name="tel_formulario"
                       value="{{ old('tel_formulario', $rs?->tel_formulario) }}"
                       placeholder="Teléfono impreso en formularios">
            </div>
            <div>
                <label class="flb">Correo (formularios PILA)</label>
                <input class="finp" type="email" name="correo_formulario"
                       value="{{ old('correo_formulario', $rs?->correo_formulario) }}"
                       placeholder="Correo impreso en formularios">
            </div>
        </div>
    </div>

    {{-- ── Representante Legal ── --}}
    <div class="card">
        <div class="card-title">👤 Representante Legal</div>
        <div class="form-grid">
            <div>
                <label class="flb">Cédula del representante</label>
                <input class="finp" type="text" name="cedula_rep"
                       value="{{ old('cedula_rep', $rs?->cedula_rep) }}"
                       placeholder="Número de cédula">
            </div>
            <div>
                <label class="flb">Nombre del representante</label>
                <input class="finp" type="text" name="nombre_rep"
                       value="{{ old('nombre_rep', $rs?->nombre_rep) }}"
                       placeholder="Nombre completo" style="text-transform:uppercase"
                       oninput="this.value=this.value.toUpperCase()">
            </div>
        </div>
    </div>

    {{-- ── Entidades por Defecto ── --}}
    <div class="card">
        <div class="card-title">🔗 Entidades por Defecto</div>
        <div style="font-size:.7rem;color:#64748b;margin-bottom:.75rem">
            Se pre-cargan automáticamente al crear un contrato con esta razón social.
        </div>
        <div class="form-grid">
            <div>
                <label class="flb">ARL Predeterminada</label>
                <select class="finp" name="arl_nit">
                    <option value="">— Sin ARL por defecto —</option>
                    @foreach($arls as $arl)
                    <option value="{{ $arl->nit }}"
                        {{ old('arl_nit', $rs?->arl_nit) == $arl->nit ? 'selected' : '' }}>
                        {{ $arl->nombre_arl }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flb">Caja de Compensación Predeterminada</label>
                <select class="finp" name="caja_nit">
                    <option value="">— Sin Caja por defecto —</option>
                    @foreach($cajas as $caja)
                    <option value="{{ $caja->nit }}"
                        {{ old('caja_nit', $rs?->caja_nit) == $caja->nit ? 'selected' : '' }}>
                        {{ $caja->nombre }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- ── Datos Comerciales ── --}}
    <div class="card">
        <div class="card-title">🏢 Datos Comerciales</div>
        <div class="form-grid">
            <div>
                <label class="flb">Actividad Económica</label>
                <input class="finp" type="text" name="actividad_economica"
                       value="{{ old('actividad_economica', $rs?->actividad_economica) }}"
                       placeholder="Ej: Servicios de salud">
            </div>

            <div class="form-full">
                <label class="flb">Objeto Social</label>
                <textarea class="finp" name="objeto_social" rows="2" style="resize:vertical"
                          placeholder="Descripción del objeto social de la empresa">{{ old('objeto_social', $rs?->objeto_social) }}</textarea>
            </div>

            <div>
                <label class="flb">Fecha de Constitución</label>
                <input class="finp" type="date" name="fecha_constitucion"
                       value="{{ old('fecha_constitucion', $rs?->fecha_constitucion ? \Carbon\Carbon::parse($rs->fecha_constitucion)->format('Y-m-d') : '') }}">
            </div>
        </div>
    </div>

    {{-- ── Configuración de Pagos ── --}}
    <div class="card">
        <div class="card-title">💳 Configuración de Pagos</div>
        <div class="form-grid-3">
            <div>
                <label class="flb">Día límite de pago</label>
                <input class="finp" type="number" name="fecha_limite_pago" min="1" max="31"
                       value="{{ old('fecha_limite_pago', $rs?->fecha_limite_pago) }}"
                       placeholder="Ej: 10">
            </div>
            <div>
                <label class="flb">¿Día Hábil?</label>
                <select class="finp" name="dia_habil">
                    <option value="">— No definido —</option>
                    <option value="1" {{ old('dia_habil', $rs?->dia_habil) == 1 ? 'selected' : '' }}>Sí — día hábil</option>
                    <option value="0" {{ old('dia_habil', $rs?->dia_habil) === '0' || old('dia_habil', $rs?->dia_habil) === 0 ? 'selected' : '' }}>No — día calendario</option>
                </select>
            </div>
            <div>
                <label class="flb">Forma de Presentación</label>
                <input class="finp" type="text" name="forma_presentacion"
                       value="{{ old('forma_presentacion', $rs?->forma_presentacion) }}"
                       placeholder="Ej: Electrónica, Física">
            </div>
        </div>
    </div>

    {{-- ── Sucursal ── --}}
    <div class="card">
        <div class="card-title">🏪 Sucursal PILA</div>
        <div class="form-grid">
            <div>
                <label class="flb">Código Sucursal</label>
                <input class="finp" type="text" name="codigo_sucursal"
                       value="{{ old('codigo_sucursal', $rs?->codigo_sucursal) }}"
                       placeholder="Código para planilla PILA">
            </div>
            <div>
                <label class="flb">Nombre Sucursal</label>
                <input class="finp" type="text" name="nombre_sucursal"
                       value="{{ old('nombre_sucursal', $rs?->nombre_sucursal) }}"
                       placeholder="Nombre de la sucursal">
            </div>
        </div>
    </div>

    {{-- ── Observaciones ── --}}
    <div class="card">
        <div class="card-title">📝 Observaciones</div>
        <textarea class="finp" name="observacion" rows="3" style="resize:vertical"
                  placeholder="Información adicional sobre esta razón social...">{{ old('observacion', $rs?->observacion) }}</textarea>
    </div>

    {{-- ── Sello / Firma de la empresa ── (solo al editar) --}}
    @if($rs)
    @php
        $nitSello   = $rs->nit ?? $rs->id;
        $rutaSello  = storage_path('app/sellos/' . $nitSello . '.png');
        $tieneSello = file_exists($rutaSello);
    @endphp
    <div class="card" id="cardSello">
        <div class="card-title">🖼️ Sello / Firma de la empresa</div>


        {{-- Preview actual --}}
        @if($tieneSello)
        @php $selloSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($rutaSello)); @endphp
        <div id="selloPreviewWrap" style="margin-bottom:.75rem;text-align:center">
            <p style="font-size:.65rem;color:#64748b;margin:0 0 .35rem">Sello actual:</p>
            <img id="selloImgActual"
                 src="{{ $selloSrc }}"
                 style="max-height:80px;max-width:220px;border:1px solid #e2e8f0;border-radius:8px;padding:4px;background:#f8fafc;object-fit:contain">
        </div>
        @endif

        {{-- Zona de drop --}}
        <div id="selloDropZone"
             style="border:2px dashed #cbd5e1;border-radius:10px;padding:1.2rem;text-align:center;cursor:pointer;transition:all .15s;background:#f8fafc"
             onclick="document.getElementById('inputSello').click()"
             ondragover="event.preventDefault();this.style.borderColor='#3b82f6';this.style.background='#eff6ff'"
             ondragleave="this.style.borderColor='#cbd5e1';this.style.background='#f8fafc'"
             ondrop="handleSelloDrop(event)">
            <div style="font-size:2rem">🖼️</div>
            <div style="font-size:.8rem;font-weight:700;color:#475569;margin:.3rem 0 .1rem">
                Arrastra la imagen aquí o haz clic para seleccionar
            </div>
            <div style="font-size:.65rem;color:#94a3b8">PNG, JPG o WEBP — máx. 5 MB</div>
        </div>

        {{-- Preview nuevo antes de subir --}}
        <div id="selloNuevoWrap" style="display:none;margin:.6rem 0;text-align:center">
            <p style="font-size:.65rem;color:#64748b;margin:0 0 .3rem">Vista previa del nuevo sello:</p>
            <img id="selloImgNuevo" src="" style="max-height:80px;max-width:220px;border:1px solid #3b82f6;border-radius:8px;padding:4px;object-fit:contain">
        </div>

        {{-- Formulario de subida separado --}}
        <form id="formSello" method="POST"
              action="{{ route('admin.configuracion.razones.sello', $rs->id) }}"
              enctype="multipart/form-data" style="margin-top:.6rem">
            @csrf
            <input type="file" id="inputSello" name="sello" accept=".png,.jpg,.jpeg,.webp"
                   style="display:none" onchange="previewSello(this)">
            <button type="submit" id="btnSubirSello"
                    style="display:none;width:100%;padding:.5rem;background:linear-gradient(135deg,#0369a1,#0284c7);
                           color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer">
                💾 Guardar sello
            </button>
        </form>

        @if(session('success') && str_contains(session('success'), 'Sello'))
        <div style="margin-top:.5rem;font-size:.72rem;color:#15803d;font-weight:700;text-align:center">
            ✅ {{ session('success') }}
        </div>
        @endif

        @error('sello')
        <div style="margin-top:.4rem;font-size:.72rem;color:#dc2626">⚠️ {{ $message }}</div>
        @enderror
    </div>

    <script>
    function previewSello(input) {
        if (!input.files || !input.files[0]) return;
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('selloImgNuevo').src = e.target.result;
            document.getElementById('selloNuevoWrap').style.display = 'block';
            document.getElementById('btnSubirSello').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
    function handleSelloDrop(e) {
        e.preventDefault();
        document.getElementById('selloDropZone').style.borderColor = '#cbd5e1';
        document.getElementById('selloDropZone').style.background  = '#f8fafc';
        const file = e.dataTransfer.files[0];
        if (!file) return;
        const input = document.getElementById('inputSello');
        const dt    = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        previewSello(input);
    }
    </script>
    @endif

    {{-- Botones --}}

    <div style="display:flex;justify-content:flex-end;gap:.7rem;margin-bottom:2rem">
        <a href="{{ route('admin.configuracion.razones.index') }}" class="btn-cancel">Cancelar</a>
        <button type="submit" class="btn-save">
            {{ $rs ? '💾 Guardar cambios' : '✅ Crear Razón Social' }}
        </button>
    </div>

</form>
</div>
@endsection
