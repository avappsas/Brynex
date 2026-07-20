@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Proyectos de Negocio')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ openCrear: false, openEditar: false, selectedProyecto: {} }">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <span>Proyectos</span>
        </div>
        
        <div>
            <button @click="openCrear = true" class="btn-fin success" style="background:#166534;">
                ➕ Nuevo Proyecto
            </button>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>🏗️ Proyectos de Negocio</h1>
            <p>Monitorea y calcula la rentabilidad neta de proyectos comerciales individuales (ej: CuentaFacil) ingresando sus propios flujos de caja.</p>
        </div>
    </div>

    {{-- Listado de Proyectos --}}
    <div class="card-tabla-bx" style="margin-top:1rem;">
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th>Nombre del Proyecto</th>
                    <th>Descripción</th>
                    <th style="text-align:right;">Ingresos Totales</th>
                    <th style="text-align:right;">Egresos Totales</th>
                    <th style="text-align:right;">Balance Neto</th>
                    <th style="text-align:center;">Estado</th>
                    <th style="text-align:center; width:15%;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($proyectos as $proy)
                    @php
                        $balance = $proy->ingresos_total - $proy->egresos_total;
                    @endphp
                    <tr>
                        <td><strong>{{ $proy->nombre }}</strong></td>
                        <td style="color:#64748b; font-size:0.75rem;">{{ $proy->descripcion ?: '-' }}</td>
                        <td style="text-align:right; color:#16a34a; font-weight:600;">${{ number_format($proy->ingresos_total, 0, ',', '.') }}</td>
                        <td style="text-align:right; color:#b91c1c;">${{ number_format($proy->egresos_total, 0, ',', '.') }}</td>
                        <td style="text-align:right; font-weight:700; color:{{ $balance >= 0 ? '#16a34a' : '#b91c1c' }};">
                            ${{ number_format($balance, 0, ',', '.') }} COP
                        </td>
                        <td style="text-align:center;">
                            @if($proy->activo)
                                <span class="badge-ok-bx">Activo</span>
                            @else
                                <span class="badge-err-bx" style="background:#f1f5f9; color:#64748b; border-color:#cbd5e1;">Finalizado</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex; justify-content:center; gap:0.4rem;">
                                <a href="{{ route('finanzas.proyectos.show', $proy->id) }}" class="btn-fin-small primary" style="background:#166534; color:#fff; text-decoration:none; padding:0.25rem 0.5rem;">
                                    Ficha
                                </a>
                                <button @click="selectedProyecto = {{ json_encode($proy) }}; openEditar = true" class="btn-icon-bx edit" title="Editar">✏️</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:2rem; color:#64748b;">
                            No tienes proyectos de negocio registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal Crear --}}
    <div x-show="openCrear" class="modal-overlay-bx" @click.self="openCrear = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #14532d, #166534);">
                <h3>🏗️ Nuevo Proyecto de Negocio</h3>
                <button @click="openCrear = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.proyectos.store') }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre del Proyecto</label>
                        <input type="text" name="nombre" placeholder="Ej: CuentaFacil, Landing Page Cliente" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Descripción / Alcance</label>
                        <textarea name="descripcion" placeholder="Detalles del proyecto..." class="form-input-bx" style="height:100px; resize:none;"></textarea>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openCrear = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#166534;">Crear Proyecto</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar --}}
    <div x-show="openEditar" class="modal-overlay-bx" @click.self="openEditar = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #14532d, #166534);">
                <h3>✏️ Editar Proyecto</h3>
                <button @click="openEditar = false" class="modal-close-bx">&times;</button>
            </div>
            <form :action="'{{ route('finanzas.proyectos.index') }}/' + selectedProyecto.id" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre del Proyecto</label>
                        <input type="text" name="nombre" x-model="selectedProyecto.nombre" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Estado</label>
                        <select name="activo" x-model="selectedProyecto.activo" class="form-select-bx" required>
                            <option value="1">Activo</option>
                            <option value="0">Finalizado / Cerrado</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Descripción</label>
                        <textarea name="descripcion" x-model="selectedProyecto.descripcion" class="form-input-bx" style="height:100px; resize:none;"></textarea>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openEditar = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#166534;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

/* Tabla */
.card-tabla-bx { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.04); overflow: hidden; }
.tabla-brynex-bx { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; }
.tabla-brynex-bx th, .tabla-brynex-bx td { border-bottom: 1px solid #e2e8f0; padding: 0.75rem 1rem; }
.tabla-brynex-bx th { background: #f8fafc; font-weight: 700; color: #475569; }

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { border: 1px solid; border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }

.btn-icon-bx { background: none; border: none; font-size: 1rem; cursor: pointer; padding: 0.2rem; border-radius: 4px; transition: background 0.1s; }
.btn-icon-bx:hover { background: #f1f5f9; }

/* Modales */
.modal-overlay-bx { position: fixed; inset: 0; z-index: 9998; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-box-bx { background: #fff; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); width: 100%; max-width: 460px; overflow: hidden; }
.modal-head-bx { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #cbd5e1; color: #fff; }
.modal-head-bx h3 { color:#fff; font-size:1rem; font-weight:600; }
.modal-close-bx { background: none; border: none; font-size: 1.3rem; cursor: pointer; color: rgba(255,255,255,0.7); }
.modal-close-bx:hover { color: #fff; }
.modal-body-bx { padding: 1.25rem; }
.modal-foot-bx { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid #cbd5e1; background: #f8fafc; }
.btn-glass-bx { padding: 0.45rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer; background: #fff; color: #475569; }

.form-group-bx { display: flex; flex-direction: column; gap: 0.25rem; }
.form-label-bx { font-size: 0.78rem; font-weight: 600; color: #334155; }
.form-input-bx { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem; outline: none; }
.form-select-bx { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.82rem; outline: none; background: #fff; cursor: pointer; }
.btn-fin-small { padding: 0.25rem 0.5rem; border: none; border-radius: 6px; font-size: 0.72rem; font-weight: 600; cursor: pointer; }
</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
