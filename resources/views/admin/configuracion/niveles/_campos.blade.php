{{-- Campos del nivel. Se usa tanto en el alta como en la edición en línea. --}}
<div style="display:grid;grid-template-columns:2fr 1fr 1fr 1.2fr 0.7fr;gap:0.7rem;align-items:end;">

  <div>
    <label style="display:block;font-size:0.65rem;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:0.25rem;">Nombre *</label>
    <input type="text" name="nombre" required maxlength="100"
        value="{{ old('nombre', $nivel?->nombre) }}"
        placeholder="Ej: Nivel 1"
        style="width:100%;padding:0.45rem 0.6rem;border:1px solid #cbd5e1;border-radius:7px;font-size:0.84rem;">
  </div>

  <div>
    <label style="display:block;font-size:0.65rem;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:0.25rem;">Desde *</label>
    <input type="number" name="contratos_min" required min="0"
        value="{{ old('contratos_min', $nivel?->contratos_min ?? 0) }}"
        style="width:100%;padding:0.45rem 0.6rem;border:1px solid #cbd5e1;border-radius:7px;font-size:0.84rem;font-family:monospace;text-align:right;">
  </div>

  <div>
    <label style="display:block;font-size:0.65rem;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:0.25rem;" title="Vacío = sin tope (último nivel)">Hasta</label>
    <input type="number" name="contratos_max" min="0"
        value="{{ old('contratos_max', $nivel?->contratos_max) }}"
        placeholder="sin tope"
        style="width:100%;padding:0.45rem 0.6rem;border:1px solid #cbd5e1;border-radius:7px;font-size:0.84rem;font-family:monospace;text-align:right;">
  </div>

  <div>
    <label style="display:block;font-size:0.65rem;font-weight:700;color:#0891b2;text-transform:uppercase;margin-bottom:0.25rem;" title="Igual para todos los planes">Admon mensual asesor *</label>
    <div style="display:flex;align-items:center;gap:0.2rem;">
      <span style="color:#0891b2;font-size:0.8rem;">$</span>
      <input type="number" name="admon_asesor" required min="0" step="500"
          value="{{ old('admon_asesor', $nivel ? intval($nivel->admon_asesor) : '') }}"
          placeholder="6000"
          style="width:100%;padding:0.45rem 0.6rem;border:1.5px solid #a5f3fc;border-radius:7px;font-size:0.84rem;font-family:monospace;text-align:right;font-weight:700;color:#0891b2;">
    </div>
  </div>

  <div>
    <label style="display:block;font-size:0.65rem;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:0.25rem;">Orden</label>
    <input type="number" name="orden" min="0" max="999"
        value="{{ old('orden', $nivel?->orden ?? 0) }}"
        style="width:100%;padding:0.45rem 0.6rem;border:1px solid #cbd5e1;border-radius:7px;font-size:0.84rem;font-family:monospace;text-align:right;">
  </div>
</div>

<div style="display:grid;grid-template-columns:3fr 1fr;gap:0.7rem;margin-top:0.6rem;align-items:end;">
  <div>
    <label style="display:block;font-size:0.65rem;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:0.25rem;">Descripción</label>
    <input type="text" name="descripcion" maxlength="255"
        value="{{ old('descripcion', $nivel?->descripcion) }}"
        placeholder="Opcional — para qué perfil de asesor es este nivel"
        style="width:100%;padding:0.45rem 0.6rem;border:1px solid #cbd5e1;border-radius:7px;font-size:0.84rem;">
  </div>
  <div>
    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;padding-bottom:0.5rem;">
      <input type="hidden" name="activo" value="0">
      <input type="checkbox" name="activo" value="1" {{ old('activo', $nivel?->activo ?? true) ? 'checked' : '' }}
          style="width:17px;height:17px;cursor:pointer;accent-color:#0369a1;">
      <span style="font-size:0.8rem;font-weight:600;color:#0f172a;">Activo</span>
    </label>
  </div>
</div>
