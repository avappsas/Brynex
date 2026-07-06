@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Categorías de Gasto')

@section('contenido')
<div class="finanzas-container" x-data="{ openCrear: false, openEditar: false, selectedCategoria: {} }">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.gastos.index') }}">Egresos / Gastos</a>
            <span>›</span>
            <span>Categorías</span>
        </div>
        <div>
            <button @click="openCrear = true" class="btn-fin success" style="background:#ef4444;">
                ➕ Nueva Categoría
            </button>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>⚙️ Categorías de Gasto</h1>
            <p>Clasifica tus gastos habituales y define cuáles de ellos son recurrentes para alertas automáticas del sistema.</p>
        </div>
    </div>

    {{-- Listado de Categorías --}}
    <div class="card-tabla-bx">
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th style="width: 5%; text-align:center;">Orden</th>
                    <th style="width: 10%; text-align:center;">Icono</th>
                    <th style="width: 25%">Nombre de la Categoría</th>
                    <th style="width: 15%; text-align:center;">Color</th>
                    <th style="width: 20%; text-align:center;">Recurrente (Mensual)</th>
                    <th style="width: 15%">Estado</th>
                    <th style="width: 10%; text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categorias as $cat)
                    <tr>
                        <td style="text-align:center;">{{ $cat->orden }}</td>
                        <td style="text-align:center; font-size:1.3rem;">{{ $cat->icono }}</td>
                        <td><strong>{{ $cat->nombre }}</strong></td>
                        <td style="text-align:center;">
                            <span style="display:inline-flex; align-items:center; gap:0.4rem;">
                                <span style="display:inline-block; width:14px; height:14px; border-radius:50%; background:{{ $cat->color }}; border:1px solid #cbd5e1;"></span>
                                <code style="font-size:0.7rem; color:#64748b;">{{ $cat->color }}</code>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            @if($cat->es_recurrente)
                                <span class="badge-recurrente-bx">🔁 Recurrente</span>
                            @else
                                <span class="badge-esporadico-bx">Ocasional</span>
                            @endif
                        </td>
                        <td>
                            @if($cat->activo)
                                <span class="badge-ok-bx">Activo</span>
                            @else
                                <span class="badge-err-bx">Inactivo</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex; justify-content:center; gap:0.4rem;">
                                <button @click="selectedCategoria = {{ json_encode($cat) }}; openEditar = true" class="btn-icon-bx edit" title="Editar">✏️</button>
                                <form action="{{ route('finanzas.categorias.destroy', $cat->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas desactivar esta categoría?')" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon-bx delete" title="Desactivar">❌</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:2rem; color:#64748b;">
                            No tienes categorías configuradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal Crear --}}
    <div x-show="openCrear" class="modal-overlay-bx" @click.self="openCrear = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #7f1d1d, #991b1b);">
                <h3>➕ Nueva Categoría</h3>
                <button @click="openCrear = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.categorias.store') }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre</label>
                        <input type="text" name="nombre" placeholder="Ej: Servicios, Alimentación, Gasolina" class="form-input-bx" required>
                    </div>
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Icono (Emoji)</label>
                            <input type="text" name="icono" placeholder="Ej: 🏠, 🍔, 🚗" class="form-input-bx" required max="10">
                        </div>
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Color (Hex)</label>
                            <input type="color" name="color" value="#3b82f6" class="form-input-bx" style="padding:0.25rem 0.5rem; height:38px; cursor:pointer;" required>
                        </div>
                    </div>
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Orden visual</label>
                            <input type="number" name="orden" value="{{ count($categorias) + 1 }}" class="form-input-bx" required>
                        </div>
                        <div class="form-group-bx" style="flex:1; justify-content:center; padding-top:1.25rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <input type="checkbox" name="es_recurrente" value="1" id="es_recurrente_check" style="width:16px; height:16px; cursor:pointer;">
                                <label for="es_recurrente_check" style="font-size:0.78rem; font-weight:600; color:#334155; cursor:pointer;">¿Es Recurrente?</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openCrear = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#ef4444;">Crear Categoría</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar --}}
    <div x-show="openEditar" class="modal-overlay-bx" @click.self="openEditar = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #7f1d1d, #991b1b);">
                <h3>✏️ Editar Categoría</h3>
                <button @click="openEditar = false" class="modal-close-bx">&times;</button>
            </div>
            <form :action="'{{ route('finanzas.categorias.index') }}/' + selectedCategoria.id" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre</label>
                        <input type="text" name="nombre" x-model="selectedCategoria.nombre" class="form-input-bx" required>
                    </div>
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Icono (Emoji)</label>
                            <input type="text" name="icono" x-model="selectedCategoria.icono" class="form-input-bx" required max="10">
                        </div>
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Color (Hex)</label>
                            <input type="color" name="color" x-model="selectedCategoria.color" class="form-input-bx" style="padding:0.25rem 0.5rem; height:38px; cursor:pointer;" required>
                        </div>
                    </div>
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Orden visual</label>
                            <input type="number" name="orden" x-model="selectedCategoria.orden" class="form-input-bx" required>
                        </div>
                        <div class="form-group-bx" style="flex:1;">
                            <label class="form-label-bx">Estado</label>
                            <select name="activo" x-model="selectedCategoria.activo" class="form-select-bx" required>
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <input type="checkbox" name="es_recurrente" value="1" x-model="selectedCategoria.es_recurrente" :checked="selectedCategoria.es_recurrente == 1" id="es_recurrente_check_edit" style="width:16px; height:16px; cursor:pointer;">
                            <label for="es_recurrente_check_edit" style="font-size:0.78rem; font-weight:600; color:#334155; cursor:pointer;">¿Es Recurrente?</label>
                        </div>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openEditar = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" style="background:#ef4444;">Guardar Cambios</button>
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
.card-tabla-bx { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.04); margin-top: 1rem; overflow: hidden; }
.tabla-brynex-bx { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; }
.tabla-brynex-bx th, .tabla-brynex-bx td { border-bottom: 1px solid #e2e8f0; padding: 0.75rem 1rem; }
.tabla-brynex-bx th { background: #f8fafc; font-weight: 700; color: #475569; }

.badge-recurrente-bx { background: rgba(168,85,247,0.12); color: #6b21a8; border: 1px solid rgba(168,85,247,0.3); border-radius: 999px; padding: 0.1rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-esporadico-bx { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 999px; padding: 0.1rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { background: rgba(239,68,68,0.1); color: #991b1b; border: 1px solid rgba(239,68,68,0.35); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }

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
</style>
@endpush
