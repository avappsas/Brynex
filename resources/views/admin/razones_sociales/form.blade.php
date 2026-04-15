@extends('layouts.app')
@section('modulo', $rs ? 'Editar Razón Social' : 'Nueva Razón Social')

@section('contenido')
<style>
.rs-wrap{max-width:760px;margin:0 auto}
.rs-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1.2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
.card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1.3rem;margin-bottom:1rem}
.card-title{font-size:.82rem;font-weight:800;color:#0f172a;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid #e2e8f0;text-transform:uppercase;letter-spacing:.04em}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
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
    <div style="font-size:.75rem;color:#94a3b8">NIT: {{ number_format($rs->id, 0, ',', '.') }}</div>
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

    {{-- Identificación --}}
    <div class="card">
        <div class="card-title">🔢 Identificación</div>
        <div class="form-grid">
            <div>
                <label class="flb">NIT / Número de Identificación <span>*</span></label>
                <input class="finp {{ $rs ? 'finp-disabled' : '' }}" type="number" name="id"
                       value="{{ old('id', $rs?->id) }}"
                       {{ $rs ? 'readonly' : 'required' }}
                       placeholder="Ej: 900123456">
                @if($rs)<p style="font-size:.62rem;color:#94a3b8;margin:.2rem 0 0">El NIT no se puede cambiar</p>@endif
            </div>
            <div>
                <label class="flb">Estado</label>
                <select class="finp" name="estado">
                    <option value="Activa"   {{ old('estado', $rs?->estado ?? 'Activa') === 'Activa'   ? 'selected' : '' }}>Activa</option>
                    <option value="Inactiva" {{ old('estado', $rs?->estado)              === 'Inactiva' ? 'selected' : '' }}>Inactiva</option>
                </select>
            </div>
            <div class="form-full">
                <label class="flb">Razón Social (nombre) <span>*</span></label>
                <input class="finp" type="text" name="razon_social"
                       value="{{ old('razon_social', $rs?->razon_social) }}"
                       required placeholder="Nombre completo de la empresa" style="text-transform:uppercase"
                       oninput="this.value=this.value.toUpperCase()">
            </div>
        </div>
    </div>

    {{-- Tipo --}}
    <div class="card">
        <div class="card-title">⚙️ Tipo de Afiliación</div>
        <label class="toggle-wrap">
            <input type="hidden"   name="es_independiente" value="0">
            <input type="checkbox" name="es_independiente" value="1"
                   style="width:16px;height:16px;accent-color:#7c3aed"
                   {{ old('es_independiente', $rs?->es_independiente ?? false) ? 'checked' : '' }}>
            <div>
                <div class="toggle-lbl">
                    Razón Social Independiente
                    <span class="badge-helper">Indep.</span>
                </div>
                <div class="toggle-sub">
                    Los contratos de esta RS solo podrán usar modalidades independientes
                    (I Act, I Venc, En el Exterior)
                </div>
            </div>
        </label>
    </div>

    {{-- Entidades por defecto --}}
    <div class="card">
        <div class="card-title">🔗 Entidades por Defecto</div>
        <div style="font-size:.7rem;color:#64748b;margin-bottom:.75rem">
            Estas entidades se pre-cargan automáticamente al crear un contrato con esta razón social.
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

    {{-- Observación --}}
    <div class="card">
        <div class="card-title">📝 Observaciones</div>
        <textarea class="finp" name="observacion" rows="3" style="resize:vertical"
                  placeholder="Información adicional sobre esta razón social...">{{ old('observacion', $rs?->observacion) }}</textarea>
    </div>

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
