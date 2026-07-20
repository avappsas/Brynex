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

    {{-- Header Banner con Gradiente --}}
    <div style="background: linear-gradient(135deg, #0a1628 0%, #0d2550 60%, #1e40af 100%); border-radius: 14px; padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.15); margin-bottom: 1.5rem; color: #fff;">
        <div>
            <h1 style="font-size: 1.4rem; font-weight: 700; color: #fff; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                🏗️ Proyectos de Negocio
            </h1>
            <p style="font-size: 0.8rem; color: rgba(255,255,255,0.75); margin: 0.25rem 0 0 0; font-weight: 400;">
                Monitorea y calcula la rentabilidad neta de proyectos comerciales individuales ingresando sus propios flujos de caja.
            </p>
        </div>
        
        {{-- Selector de Año --}}
        <div style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 0.6rem 1.25rem; min-width: 180px;">
            <form method="GET" action="{{ route('finanzas.proyectos.index') }}" id="filterForm">
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <label style="font-size: 0.62rem; color: rgba(255,255,255,0.7); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Año de Consulta</label>
                    <select name="anio" onchange="document.getElementById('filterForm').submit()" style="padding: 0.35rem; font-size: 0.8rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(10, 22, 40, 0.85); color: #fff; width: 100%; cursor: pointer;">
                        <option value="todos" @selected($anio === 'todos')>Todos los Años</option>
                        @foreach($aniosDisponibles as $a)
                            <option value="{{ $a }}" @selected($anio !== 'todos' && (int)$anio === (int)$a)>Año {{ $a }}</option>
                        @endforeach
                        @if(!in_array(date('Y'), $aniosDisponibles))
                            <option value="{{ date('Y') }}" @selected($anio !== 'todos' && (int)$anio === (int)date('Y'))>Año {{ date('Y') }}</option>
                        @endif
                    </select>
                </div>
            </form>
        </div>
    </div>

    {{-- Listado de Proyectos --}}
    <div class="card-tabla-bx" style="margin-top:1rem;">
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th>Nombre del Proyecto</th>
                    <th>Descripción</th>
                    <th style="text-align:right;">
                        @if($anio === 'todos') Ingresos (Histórico) @else Ingresos ({{ $anio }}) @endif
                    </th>
                    <th style="text-align:right;">
                        @if($anio === 'todos') Egresos (Histórico) @else Egresos ({{ $anio }}) @endif
                    </th>
                    <th style="text-align:right;">
                        @if($anio === 'todos') Balance Neto (Histórico) @else Balance Neto ({{ $anio }}) @endif
                    </th>
                    <th style="text-align:center;">Estado</th>
                    <th style="text-align:center; width:15%;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($proyectos as $proy)
                    <tr>
                        <td><strong>{{ $proy->nombre }}</strong></td>
                        <td style="color:#64748b; font-size:0.75rem;">{{ $proy->descripcion ?: '-' }}</td>
                        <td style="text-align:right; color:#16a34a; font-weight:600;">${{ number_format($proy->periodo_entradas, 0, ',', '.') }}</td>
                        <td style="text-align:right; color:#b91c1c;">${{ number_format($proy->periodo_salidas, 0, ',', '.') }}</td>
                        <td style="text-align:right; font-weight:700; color:{{ $proy->periodo_balance >= 0 ? '#16a34a' : '#b91c1c' }};">
                            ${{ number_format($proy->periodo_balance, 0, ',', '.') }} COP
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
                                <a href="{{ route('finanzas.proyectos.show', $proy->id) }}?anio={{ $anio }}" class="btn-fin-small primary" style="background:#166534; color:#fff; text-decoration:none; padding:0.25rem 0.5rem;">
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

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { border: 1px solid; border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }


/* Modales */

.btn-fin-small { padding: 0.25rem 0.5rem; border: none; border-radius: 6px; font-size: 0.72rem; font-weight: 600; cursor: pointer; }
</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
