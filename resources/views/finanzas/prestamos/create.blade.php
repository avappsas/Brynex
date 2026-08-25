@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Nuevo Préstamo')

@section('contenido')
@include('finanzas.partials._responsive_fin')
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
                        <input type="number" name="monto_original" placeholder="Ej: 1000000" class="form-input-bx" required min="1" autocomplete="off">
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

                {{-- Cuenta de origen del desembolso --}}
                @if(isset($cuentas) && $cuentas->isNotEmpty())
                <div class="form-group-bx" style="margin-top:1rem;">
                    <label class="form-label-bx">¿De qué cuenta salió el dinero?</label>
                    <select name="cuenta_id" class="form-select-bx" required>
                        @foreach($cuentas as $cta)
                            <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Cuenta corriente: los trabajos a clientes recurrentes se registran
                     en su propio módulo, con desglose por ítem y pago por trabajo. --}}
                <div style="margin-top:1rem; background:#faf5ff; padding:0.75rem; border-radius:9px; border:1px dashed #d8b4fe; font-size:0.78rem; color:#581c87;">
                    ¿Es un <strong>trabajo</strong> para un cliente recurrente y no un préstamo en efectivo?
                    Regístralo en
                    <a href="{{ route('finanzas.cuenta-corriente.index') }}" style="color:#7e22ce; font-weight:700;">Cuenta Corriente de Servicios</a>,
                    donde puedes discriminar materiales y mano de obra y cobrar trabajo por trabajo.
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

            <div class="form-foot-bx" style="margin-top:2rem; display:flex; justify-content:flex-end; gap:0.75rem; border-top:1px solid #e2e8f0; padding-top:1.25rem;">
                <a href="{{ route('finanzas.prestamos.index') }}" class="btn-cancelar-bx">Cancelar</a>
                <button type="submit" class="btn-guardar-bx">💾 Guardar Préstamo</button>
            </div>
        </form>
    </div>

</div>
@endsection

@push('styles')
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

.finanzas-container { 
    font-family: 'Outfit', sans-serif;
    max-width: 600px;
    margin: 0 auto;
    padding: 1rem 0.5rem;
}

/* Breadcrumb */
.fin-top-bar { 
    margin-bottom: 1.25rem; 
}
.breadcrumb-bx { 
    display: flex; 
    align-items: center; 
    gap: 0.4rem; 
    font-size: 0.8rem; 
    color: #64748b; 
}
.breadcrumb-bx a { 
    color: #3b82f6; 
    text-decoration: none; 
    font-weight: 500; 
    transition: color 0.2s ease; 
}
.breadcrumb-bx a:hover { 
    color: #1d4ed8; 
}

/* Header */
.fin-header-section {
    margin-bottom: 1.5rem;
}
.fin-header-section h1 {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.025em;
    margin: 0;
}
.fin-header-section p {
    font-size: 0.85rem;
    color: #64748b;
    margin-top: 0.25rem;
    margin-bottom: 0;
}

/* Card Formulario */
.card-formulario-bx { 
    background: #ffffff; 
    border-radius: 16px; 
    border: 1px solid #e2e8f0; 
    padding: 1.75rem; 
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
    margin-top: 1rem; 
}

.form-group-bx { 
    display: flex; 
    flex-direction: column; 
    gap: 0.35rem; 
}
.form-label-bx { 
    font-size: 0.8rem; 
    font-weight: 600; 
    color: #334155; 
}
.form-input-bx { 
    height: 40px;
    padding: 0 0.85rem; 
    border: 1px solid #cbd5e1; 
    border-radius: 10px; 
    font-size: 0.85rem; 
    outline: none; 
    transition: all 0.2s ease;
    color: #1f2937;
    background: #f8fafc;
}
.form-input-bx::placeholder {
    color: #94a3b8;
}
.form-input-bx:focus { 
    border-color: #f59e0b; 
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.12);
}
textarea.form-input-bx {
    height: auto;
    padding: 0.75rem 0.85rem;
}

/* Botones */
.btn-cancelar-bx {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 42px;
    padding: 0 1.25rem;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    color: #475569;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-cancelar-bx:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #1e293b;
}

.btn-guardar-bx {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 42px;
    padding: 0 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #ffffff;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    cursor: pointer;
    box-shadow: 0 4px 6px -1px rgba(217, 119, 6, 0.2), 0 2px 4px -1px rgba(217, 119, 6, 0.1);
    transition: all 0.2s ease;
}
.btn-guardar-bx:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 12px -1px rgba(217, 119, 6, 0.25), 0 4px 6px -1px rgba(217, 119, 6, 0.15);
    background: linear-gradient(135deg, #fbbf24, #ea580c);
}
.btn-guardar-bx:active {
    transform: translateY(0);
}
</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
