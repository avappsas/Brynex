@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Nuevo Préstamo')

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
            <span>Nuevo</span>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>🤝 Registrar Nuevo Préstamo</h1>
            <p>Ingresa los datos para abrir una ficha de préstamo con intereses dinámicos.</p>
        </div>
    </div>

    {{-- Formulario --}}
    <div class="card-formulario-bx">
        <form action="{{ route('finanzas.prestamos.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div class="form-body-bx">
                
                {{-- Nombre deudor --}}
                <div class="form-group-bx">
                    <label class="form-label-bx">Nombre del Deudor</label>
                    <input type="text" name="nombre_deudor" placeholder="Ej: Juan Pérez" class="form-input-bx" required>
                </div>

                {{-- Cédula deudor --}}
                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Cédula (Opcional)</label>
                        <input type="text" name="cedula_deudor" placeholder="Ej: 1143..." class="form-input-bx">
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Teléfono Celular (WhatsApp)</label>
                        <input type="text" name="telefono_deudor" placeholder="Ej: 3001234567" class="form-input-bx">
                    </div>
                </div>

                {{-- Monto original --}}
                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Monto Prestado ($ COP)</label>
                        <input type="number" name="monto_original" placeholder="Ej: 1000000" class="form-input-bx" required min="1">
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Tasa Interés Mensual (%)</label>
                        <input type="number" step="0.001" name="tasa_interes_mensual" placeholder="Ej: 3.5 o 1.85" class="form-input-bx" required min="0">
                    </div>
                </div>

                {{-- Fecha desembolso y días mora --}}
                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Fecha de Desembolso</label>
                        <input type="date" name="fecha_desembolso" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="flex:1;">
                        <label class="form-label-bx">Días límite de pago (Mora)</label>
                        <input type="number" name="dias_mora_alerta" value="30" class="form-input-bx" required min="1">
                    </div>
                </div>

                {{-- Cuenta corriente --}}
                <div x-data="{ esCC: false }" style="margin-top:1rem; background:#f8fafc; padding:0.75rem; border-radius:9px; border:1px dashed #cbd5e1;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <input type="checkbox" name="es_cuenta_corriente" value="1" x-model="esCC" id="cc_check" style="cursor:pointer; width:16px; height:16px;">
                        <label for="cc_check" style="font-size:0.8rem; font-weight:700; color:#334155; cursor:pointer;">
                            ¿Es Cuenta Corriente? (Trabajos acumulados)
                        </label>
                    </div>
                    <div x-show="esCC" x-cloak style="margin-top:0.5rem; padding-left:1.25rem;">
                        <label class="form-label-bx">Nombre del Grupo / Mes de Trabajo</label>
                        <input type="text" name="cuenta_corriente_grupo" placeholder="Ej: Trabajos Junio 2026, Proyecto X" class="form-input-bx">
                    </div>
                </div>

                {{-- Alertas --}}
                <div style="display:flex; align-items:center; gap:0.5rem; margin-top:1rem;">
                    <input type="checkbox" name="alertas_activas" value="1" checked id="alertas_check" style="cursor:pointer; width:16px; height:16px;">
                    <label for="alertas_check" style="font-size:0.8rem; font-weight:600; color:#475569; cursor:pointer;">
                        Activar alertas automáticas de mora
                    </label>
                </div>

                {{-- Documento Soporte --}}
                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Soporte Escaneado (PDF / Imagen)</label>
                    <input type="file" name="soporte" class="form-input-bx" style="padding:0.35rem 0.5rem;">
                </div>

                {{-- Descripción --}}
                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Descripción Corta / Referencia</label>
                    <input type="text" name="descripcion" placeholder="Ej: Préstamo para compra de moto" class="form-input-bx">
                </div>

                {{-- Observaciones --}}
                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">Observaciones</label>
                    <textarea name="observaciones" placeholder="Anotaciones extra..." class="form-input-bx" style="height:80px; resize:none;"></textarea>
                </div>

            </div>

            <div class="form-foot-bx" style="margin-top:1.5rem; display:flex; justify-content:flex-end; gap:0.5rem; border-top:1px solid #e2e8f0; padding-top:1rem;">
                <a href="{{ route('finanzas.prestamos.index') }}" class="btn-glass-bx" style="text-decoration:none; padding:0.5rem 1rem;">Cancelar</a>
                <button type="submit" class="btn-fin success" style="background:#f59e0b;">💾 Guardar Préstamo</button>
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
.btn-glass-bx { padding: 0.45rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer; background: #fff; color: #475569; }
</style>
@endpush
