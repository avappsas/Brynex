@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Editar Préstamo')

@section('contenido')
<div class="finanzas-container" style="max-width: 600px;">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.prestamos.index') }}">Préstamos</a>
            <span>›</span>
            <a href="{{ route('finanzas.prestamos.show', $prestamo->id) }}">{{ $prestamo->nombre_deudor }}</a>
            <span>›</span>
            <span>Editar</span>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>✏️ Editar Préstamo</h1>
            <p>Modifica los datos del préstamo o cambia su estado actual.</p>
        </div>
    </div>

    {{-- Formulario --}}
    <div class="card-formulario-bx">
        <form action="{{ route('finanzas.prestamos.update', $prestamo->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            
            <div class="form-body-bx">
                
                {{-- Nombre deudor --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Nombre del Deudor</label>
                    <input type="text" name="nombre_deudor" value="{{ $prestamo->nombre_deudor }}" class="form-input-bx" required>
                </div>

                {{-- Cédula deudor --}}
                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Cédula (Opcional)</label>
                        <input type="text" name="cedula_deudor" value="{{ $prestamo->cedula_deudor }}" class="form-input-bx">
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Teléfono Celular (WhatsApp)</label>
                        <input type="text" name="telefono_deudor" value="{{ $prestamo->telefono_deudor }}" class="form-input-bx">
                    </div>
                </div>

                {{-- Tasa y estado --}}
                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Tasa Interés Mensual (%)</label>
                        <input type="number" step="0.001" name="tasa_interes_mensual" value="{{ $prestamo->tasa_interes_mensual }}" class="form-input-bx" required min="0">
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Estado del Préstamo</label>
                        <select name="estado" class="form-select-bx" required>
                            <option value="activo" @selected($prestamo->estado === 'activo')>Activo / Al día</option>
                            <option value="mora" @selected($prestamo->estado === 'mora')>En Mora</option>
                            <option value="pagado" @selected($prestamo->estado === 'pagado')>Totalmente Pagado</option>
                            <option value="castigado" @selected($prestamo->estado === 'castigado')>Castigado / Pérdida</option>
                        </select>
                    </div>
                </div>

                {{-- Días límite de pago --}}
                <div class="form-group-bx" style="margin-top:1rem; max-width: 50%;">
                    <label class="form-label-bx">Días límite de pago (Mora)</label>
                    <input type="number" name="dias_mora_alerta" value="{{ $prestamo->dias_mora_alerta }}" class="form-input-bx" required min="1">
                </div>

                {{-- Alertas --}}
                <div style="display:flex; align-items:center; gap:0.5rem; margin-top:1rem;">
                    <input type="checkbox" name="alertas_activas" value="1" @checked($prestamo->alertas_activas) id="alertas_check" style="cursor:pointer; width:16px; height:16px;">
                    <label for="alertas_check" style="font-size:0.8rem; font-weight:600; color:#475569; cursor:pointer;">
                        Activar alertas automáticas de mora
                    </label>
                </div>

                {{-- Documento Soporte --}}
                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Actualizar Soporte Escaneado (PDF / Imagen)</label>
                    <input type="file" name="soporte" class="form-input-bx" style="padding:0.35rem 0.5rem;">
                    @if($prestamo->soporte_path)
                        <small style="color:#10b981; font-weight:600; margin-top:0.25rem;">
                            Ya cuenta con un archivo soporte cargado.
                        </small>
                    @endif
                </div>

                {{-- Observaciones --}}
                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Observaciones</label>
                    <textarea name="observaciones" placeholder="Anotaciones extra..." class="form-input-bx" style="height:100px; resize:none;">{{ $prestamo->observaciones }}</textarea>
                </div>

            </div>

            <div class="form-foot-bx" style="margin-top:1.5rem; display:flex; justify-content:flex-end; gap:0.5rem; border-top:1px solid #e2e8f0; padding-top:1rem;">
                <a href="{{ route('finanzas.prestamos.show', $prestamo->id) }}" class="btn-glass-bx" style="text-decoration:none; padding:0.5rem 1rem;">Cancelar</a>
                <button type="submit" class="btn-fin success" style="background:#f59e0b;">💾 Guardar Cambios</button>
            </div>
        </form>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { margin: 0 auto; padding: 0.5rem; }
.card-formulario-bx { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04); margin-top: 1rem; }

.form-group-bx { display: flex; flex-direction: column; gap: 0.25rem; }
.form-label-bx { font-size: 0.78rem; font-weight: 600; color: #334155; }
.form-input-bx { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem; outline: none; }
.form-input-bx:focus { border-color: var(--acento); }
.form-select-bx { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem; outline: none; background: #fff; cursor: pointer; }
.btn-glass-bx { padding: 0.45rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer; background: #fff; color: #475569; }
</style>
@endpush
