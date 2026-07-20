@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Fuentes de Ingreso')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ openCrear: false, openEditar: false, selectedFuente: {} }">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.entradas.index') }}">Entradas Mensuales</a>
            <span>›</span>
            <span>Fuentes de Ingreso</span>
        </div>
        <div>
            <button @click="openCrear = true" class="btn-fin success">
                ➕ Nueva Fuente
            </button>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>⚙️ Gestión de Fuentes de Ingreso</h1>
            <p>Define los negocios, salarios o proyectos desde los cuales recibes dinero para estructurar tu contabilidad.</p>
        </div>
    </div>

    {{-- Listado de Fuentes --}}
    <div class="card-tabla-bx">
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th style="width: 5%">Orden</th>
                    <th style="width: 25%">Nombre de la Fuente</th>
                    <th style="width: 15%">Tipo</th>
                    <th style="width: 35%">Descripción</th>
                    <th style="width: 10%">Estado</th>
                    <th style="width: 10%; text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fuentes as $fuente)
                    <tr>
                        <td style="text-align:center;">{{ $fuente->orden }}</td>
                        <td><strong>{{ $fuente->nombre }}</strong></td>
                        <td><span class="tipo-tag-bx {{ $fuente->tipo }}">{{ $fuente->tipo }}</span></td>
                        <td style="color:#64748b; font-size:0.75rem;">{{ $fuente->descripcion ?: '-' }}</td>
                        <td>
                            @if($fuente->activo)
                                <span class="badge-ok-bx">Activo</span>
                            @else
                                <span class="badge-err-bx">Inactivo</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex; justify-content:center; gap:0.4rem;">
                                <button @click="selectedFuente = {{ json_encode($fuente) }}; openEditar = true" class="btn-icon-bx edit" title="Editar">✏️</button>
                                <form action="{{ route('finanzas.fuentes.destroy', $fuente->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas desactivar esta fuente?')" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon-bx delete" title="Desactivar">❌</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">
                            No tienes fuentes de ingresos configuradas. Crea una nueva para comenzar.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal Crear --}}
    <div x-show="openCrear" class="modal-overlay-bx" @click.self="openCrear = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx">
                <h3>➕ Nueva Fuente de Ingreso</h3>
                <button @click="openCrear = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.fuentes.store') }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre</label>
                        <input type="text" name="nombre" placeholder="Ej: Megatransportes, Sueldo, Intereses" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Tipo de Ingreso</label>
                        <select name="tipo" class="form-select-bx" required>
                            <option value="fijo">Fijo / Mensual</option>
                            <option value="proyecto">Proyecto / Variable</option>
                            <option value="esporadico">Esporádico / Ocasional</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Orden visual en la tabla</label>
                        <input type="number" name="orden" value="{{ count($fuentes) + 1 }}" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Descripción</label>
                        <textarea name="descripcion" placeholder="Detalles de la fuente..." class="form-input-bx" style="height:80px; resize:none;"></textarea>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openCrear = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success">Crear Fuente</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar --}}
    <div x-show="openEditar" class="modal-overlay-bx" @click.self="openEditar = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx">
                <h3>✏️ Editar Fuente de Ingreso</h3>
                <button @click="openEditar = false" class="modal-close-bx">&times;</button>
            </div>
            <form :action="'{{ route('finanzas.fuentes.index') }}/' + selectedFuente.id" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre</label>
                        <input type="text" name="nombre" x-model="selectedFuente.nombre" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Tipo de Ingreso</label>
                        <select name="tipo" x-model="selectedFuente.tipo" class="form-select-bx" required>
                            <option value="fijo">Fijo / Mensual</option>
                            <option value="proyecto">Proyecto / Variable</option>
                            <option value="esporadico">Esporádico / Ocasional</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Orden visual en la tabla</label>
                        <input type="number" name="orden" x-model="selectedFuente.orden" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Estado</label>
                        <select name="activo" x-model="selectedFuente.activo" class="form-select-bx" required>
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Descripción</label>
                        <textarea name="descripcion" x-model="selectedFuente.descripcion" class="form-input-bx" style="height:80px; resize:none;"></textarea>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openEditar = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success">Guardar Cambios</button>
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

.tipo-tag-bx { display: inline-block; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; padding: 0.1rem 0.4rem; border-radius: 4px; }
.tipo-tag-bx.fijo { background: #d1fae5; color: #065f46; }
.tipo-tag-bx.proyecto { background: #e0f2fe; color: #0369a1; }
.tipo-tag-bx.esporadico { background: #fef3c7; color: #d97706; }

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.1rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { background: rgba(239,68,68,0.1); color: #991b1b; border: 1px solid rgba(239,68,68,0.35); border-radius: 999px; padding: 0.1rem 0.5rem; font-size: 0.7rem; font-weight: 600; }


/* Modales */
</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
