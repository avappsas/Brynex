{{-- Alta y edición del mutuante. Los mismos campos en los dos casos: lo que
     falte aquí sale como línea en blanco en el contrato. --}}
<form action="{{ route('finanzas.prestamistas.guardar', $p?->id) }}" method="POST">
    @csrf
    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
        <div class="form-group-bx" style="flex:2; min-width:220px;">
            <label class="form-label-bx">Nombre completo *</label>
            <input type="text" name="nombre" value="{{ $p->nombre ?? '' }}" class="form-input-bx" required>
        </div>
        <div class="form-group-bx" style="flex:1; min-width:140px;">
            <label class="form-label-bx">Cédula</label>
            <input type="text" name="cedula" value="{{ $p->cedula ?? '' }}" class="form-input-bx">
        </div>
        <div class="form-group-bx" style="flex:1; min-width:140px;">
            <label class="form-label-bx">Expedida en</label>
            <input type="text" name="expedida_en" value="{{ $p->expedida_en ?? '' }}" class="form-input-bx">
        </div>
    </div>
    <div style="display:flex; gap:0.75rem; margin-top:0.6rem; flex-wrap:wrap;">
        <div class="form-group-bx" style="flex:2; min-width:220px;">
            <label class="form-label-bx">Dirección</label>
            <input type="text" name="direccion" value="{{ $p->direccion ?? '' }}" class="form-input-bx">
        </div>
        <div class="form-group-bx" style="flex:1; min-width:140px;">
            <label class="form-label-bx">Ciudad</label>
            <input type="text" name="ciudad" value="{{ $p->ciudad ?? 'Cali' }}" class="form-input-bx">
        </div>
        <div class="form-group-bx" style="flex:1; min-width:140px;">
            <label class="form-label-bx">Teléfono</label>
            <input type="text" name="telefono" value="{{ $p->telefono ?? '' }}" class="form-input-bx">
        </div>
        <div class="form-group-bx" style="flex:1; min-width:180px;">
            <label class="form-label-bx">Correo</label>
            <input type="email" name="correo" value="{{ $p->correo ?? '' }}" class="form-input-bx">
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:1.25rem; margin-top:0.7rem; flex-wrap:wrap;">
        <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.78rem; font-weight:600; color:#475569; cursor:pointer;">
            <input type="checkbox" name="por_defecto" value="1" @checked($p->por_defecto ?? true) style="width:15px; height:15px;">
            Usar por defecto en los expedientes nuevos
        </label>
        <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.78rem; font-weight:600; color:#475569; cursor:pointer;">
            <input type="checkbox" name="activo" value="1" @checked($p->activo ?? true) style="width:15px; height:15px;">
            Activo
        </label>
        <button type="submit" class="btn-guardar-bx" style="margin-left:auto;">💾 Guardar</button>
    </div>
</form>
