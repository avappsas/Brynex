@extends('layouts.app')
@section('modulo', 'Editar Empresa')

@section('contenido')
<style>
.edit-wrap{max-width:680px;margin:0 auto}
.edit-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1.2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
.card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1.4rem;margin-bottom:1rem}
.card-title{font-size:.85rem;font-weight:800;color:#0f172a;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid #e2e8f0;text-transform:uppercase;letter-spacing:.04em}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin-bottom:.7rem}
.form-full{grid-column:1/-1}
.flb{display:block;font-size:.67rem;font-weight:700;color:#475569;margin-bottom:.18rem;text-transform:uppercase}
.finp{width:100%;padding:.4rem .55rem;border:1.5px solid #cbd5e1;border-radius:7px;font-size:.84rem;box-sizing:border-box;transition:border-color .15s}
.finp:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.btn-save{padding:.6rem 1.5rem;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;transition:background .15s}
.btn-save:hover{background:#1d4ed8}
.btn-cancel{padding:.6rem 1.2rem;background:#f1f5f9;color:#475569;border:none;border-radius:8px;font-size:.88rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
.btn-cancel:hover{background:#e2e8f0}
.iva-group{display:flex;gap:1rem;margin-top:.2rem}
.iva-group label{display:flex;align-items:center;gap:.4rem;font-size:.85rem;cursor:pointer}
</style>

<div class="edit-wrap">

{{-- Header --}}
<div class="edit-header">
    <div>
        <a href="{{ route('admin.facturacion.empresa', $empresa->id) }}"
           style="color:#94a3b8;font-size:.78rem;text-decoration:none">← Volver a facturación</a>
        <div style="font-size:1.15rem;font-weight:800;margin-top:.2rem">✏️ Editar Empresa</div>
    </div>
    <div style="font-size:.78rem;color:#94a3b8">ID: {{ $empresa->id }}</div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:.65rem 1rem;margin-bottom:1rem;font-size:.84rem;color:#15803d">
    ✅ {{ session('success') }}
</div>
@endif

@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.65rem 1rem;margin-bottom:1rem;font-size:.84rem;color:#dc2626">
    @foreach($errors->all() as $e) <div>• {{ $e }}</div> @endforeach
</div>
@endif

<form method="POST" action="{{ route('admin.facturacion.empresa.update', $empresa->id) }}">
    @csrf
    @method('PUT')

    {{-- Datos básicos --}}
    <div class="card">
        <div class="card-title">🏢 Datos Básicos</div>
        <div class="form-row">
            <div class="form-full">
                <label class="flb">Nombre empresa *</label>
                <input class="finp" type="text" name="empresa" value="{{ old('empresa', $empresa->empresa) }}" required>
            </div>
            <div>
                <label class="flb">NIT</label>
                <input class="finp" type="number" name="nit" value="{{ old('nit', $empresa->nit) }}">
            </div>
            <div>
                <label class="flb">Correo</label>
                <input class="finp" type="email" name="correo" value="{{ old('correo', $empresa->correo) }}">
            </div>
            <div>
                <label class="flb">Teléfono</label>
                <input class="finp" type="text" name="telefono" value="{{ old('telefono', $empresa->telefono) }}">
            </div>
            <div>
                <label class="flb">Celular</label>
                <input class="finp" type="text" name="celular" value="{{ old('celular', $empresa->celular) }}">
            </div>
            <div class="form-full">
                <label class="flb">Dirección</label>
                <input class="finp" type="text" name="direccion" value="{{ old('direccion', $empresa->direccion) }}">
            </div>
            <div>
                <label class="flb">Contacto</label>
                <input class="finp" type="text" name="contacto" value="{{ old('contacto', $empresa->contacto) }}">
            </div>
            <div>
                <label class="flb">IVA</label>
                <div class="iva-group">
                    <label>
                        <input type="radio" name="iva" value="SI" {{ strtoupper(old('iva', $empresa->iva)) === 'SI' ? 'checked' : '' }}>
                        <span>Sí</span>
                    </label>
                    <label>
                        <input type="radio" name="iva" value="NO" {{ strtoupper(old('iva', $empresa->iva)) !== 'SI' ? 'checked' : '' }}>
                        <span>No</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- Asesor --}}
    <div class="card">
        <div class="card-title">👤 Asesor Asignado</div>
        <label class="flb">Asesor (para comisiones en Otros Ingresos)</label>
        <select class="finp" name="asesor_id">
            <option value="">— Sin asesor —</option>
            @foreach($asesores as $asesor)
            <option value="{{ $asesor->id }}" {{ (int)old('asesor_id', $empresa->asesor_id) === (int)$asesor->id ? 'selected' : '' }}>
                {{ $asesor->nombre }}
            </option>
            @endforeach
        </select>
        <div style="font-size:.72rem;color:#64748b;margin-top:.35rem">
            Este asesor se pre-carga al registrar un Otro Ingreso desde esta empresa.
        </div>
    </div>

    {{-- Observación --}}
    <div class="card">
        <div class="card-title">📝 Observaciones</div>
        <textarea class="finp" name="observacion" rows="3"
            style="resize:vertical">{{ old('observacion', $empresa->observacion) }}</textarea>
    </div>

    {{-- Botones --}}
    <div style="display:flex;gap:.7rem;justify-content:flex-end;margin-bottom:2rem">
        <a href="{{ route('admin.facturacion.empresa', $empresa->id) }}" class="btn-cancel">Cancelar</a>
        <button type="submit" class="btn-save">💾 Guardar cambios</button>
    </div>
</form>

</div>
@endsection
