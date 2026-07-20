@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Aliados de App Líderes')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ openCrear: false, openEditar: false, selectedAliado: {} }">

    {{-- Breadcrumb --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <a href="{{ route('finanzas.entradas.index') }}">Entradas Mensuales</a>
            <span>›</span>
            <span>App Líderes</span>
        </div>
        <div>
            <button @click="openCrear = true" class="btn-fin success">
                ➕ Registrar Aliado
            </button>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>👥 Aliados de App Líderes</h1>
            <p>Monitorea y gestiona las empresas o aliados de la plataforma de Líderes que generan ingresos mensuales.</p>
        </div>
    </div>

    {{-- Listado de Aliados --}}
    <div class="card-tabla-bx">
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th>Nombre del Aliado</th>
                    <th style="text-align:right;">Valor Mensual</th>
                    <th style="text-align:center;">Fecha Inicio</th>
                    <th style="text-align:center;">Fecha Fin</th>
                    <th style="text-align:center;">Estado</th>
                    <th style="text-align:center; width:15%;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($aliados as $aliado)
                    <tr>
                        <td><strong>{{ $aliado->nombre }}</strong></td>
                        <td style="text-align:right; color:#16a34a; font-weight:700;">
                            ${{ number_format($aliado->valor_mensual, 0, ',', '.') }} COP
                        </td>
                        <td style="text-align:center;">{{ Carbon\Carbon::parse($aliado->fecha_inicio)->format('d/m/Y') }}</td>
                        <td style="text-align:center; color:#64748b;">
                            {{ $aliado->fecha_fin ? Carbon\Carbon::parse($aliado->fecha_fin)->format('d/m/Y') : '-' }}
                        </td>
                        <td style="text-align:center;">
                            @if($aliado->activo)
                                <span class="badge-ok-bx">Activo</span>
                            @else
                                <span class="badge-err-bx">Retirado</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex; justify-content:center; gap:0.4rem;">
                                <button @click="selectedAliado = {{ json_encode($aliado) }}; openEditar = true" class="btn-icon-bx edit" title="Editar">✏️</button>
                                @if($aliado->activo)
                                    <form action="{{ route('finanzas.app-lideres.destroy', $aliado->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas dar de baja este aliado?')" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon-bx delete" title="Dar de baja">❌</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">
                            No tienes aliados de App Líderes registrados.
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
                <h3>👥 Nuevo Aliado de App Líderes</h3>
                <button @click="openCrear = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.app-lideres.store') }}" method="POST">
                @csrf
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre del Aliado</label>
                        <input type="text" name="nombre" placeholder="Ej: Aliado A, Inversiones XYZ" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Valor Mensual ($ COP)</label>
                        <input type="number" name="valor_mensual" placeholder="Ej: 500000" class="form-input-bx" required min="0">
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Fecha de Inicio</label>
                        <input type="date" name="fecha_inicio" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones</label>
                        <textarea name="observaciones" placeholder="Detalles o comentarios..." class="form-input-bx" style="height:80px; resize:none;"></textarea>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openCrear = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success">Registrar Aliado</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar --}}
    <div x-show="openEditar" class="modal-overlay-bx" @click.self="openEditar = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx">
                <h3>✏️ Editar Aliado de App Líderes</h3>
                <button @click="openEditar = false" class="modal-close-bx">&times;</button>
            </div>
            <form :action="'{{ route('finanzas.app-lideres.index') }}/' + selectedAliado.id" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Nombre del Aliado</label>
                        <input type="text" name="nombre" x-model="selectedAliado.nombre" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Valor Mensual ($ COP)</label>
                        <input type="number" name="valor_mensual" x-model="selectedAliado.valor_mensual" class="form-input-bx" required min="0">
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Fecha de Inicio</label>
                        <input type="date" name="fecha_inicio" x-model="selectedAliado.fecha_inicio" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Fecha de Fin (Opcional)</label>
                        <input type="date" name="fecha_fin" x-model="selectedAliado.fecha_fin" class="form-input-bx">
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Estado</label>
                        <select name="activo" x-model="selectedAliado.activo" class="form-select-bx" required>
                            <option value="1">Activo</option>
                            <option value="0">Retirado / Inactivo</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Observaciones</label>
                        <textarea name="observaciones" x-model="selectedAliado.observaciones" class="form-input-bx" style="height:80px; resize:none;"></textarea>
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
.card-tabla-bx { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.04); margin-top: 1rem; overflow: hidden; }
.tabla-brynex-bx { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; }
.tabla-brynex-bx th, .tabla-brynex-bx td { border-bottom: 1px solid #e2e8f0; padding: 0.75rem 1rem; }
.tabla-brynex-bx th { background: #f8fafc; font-weight: 700; color: #475569; }

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
.badge-err-bx { background: rgba(239,68,68,0.1); color: #991b1b; border: 1px solid rgba(239,68,68,0.35); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }

.btn-icon-bx { background: none; border: none; font-size: 1rem; cursor: pointer; padding: 0.2rem; border-radius: 4px; transition: background 0.1s; }
.btn-icon-bx:hover { background: #f1f5f9; }

/* Modales */
.modal-overlay-bx { position: fixed; inset: 0; z-index: 9998; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-box-bx { background: #fff; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); width: 100%; max-width: 460px; overflow: hidden; }
.modal-head-bx { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #cbd5e1; background: linear-gradient(135deg, var(--azul-oscuro), var(--azul-medio)); color: #fff; }
.modal-head-bx h3 { color:#fff; font-size:1rem; font-weight:600; }
.modal-close-bx { background: none; border: none; font-size: 1.3rem; cursor: pointer; color: rgba(255,255,255,0.7); }
.modal-close-bx:hover { color: #fff; }
.modal-body-bx { padding: 1.25rem; }
.modal-foot-bx { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid #cbd5e1; background: #f8fafc; }
.btn-glass-bx { padding: 0.45rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer; background: #fff; color: #475569; }
</style>
@endpush
