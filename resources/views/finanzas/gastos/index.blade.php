@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Gestión de Gastos')

@section('contenido')
<div class="finanzas-container" x-data="{ openCrear: false, openEditar: false, selectedGasto: {} }">

    {{-- Breadcrumb & Period Selector --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <a href="{{ route('finanzas.dashboard') }}">Finanzas Personales</a>
            <span>›</span>
            <span>Transacciones Diarias</span>
        </div>
        
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <a href="{{ route('finanzas.gastos.informe') }}" class="btn-fin-link primary">📊 Informe Anual</a>
            <a href="{{ route('finanzas.categorias.index') }}" class="btn-fin-link success">⚙️ Categorías</a>
            
            <form method="GET" action="{{ route('finanzas.gastos.index') }}" class="period-selector-bx">
                <select name="mes" class="select-fin" onchange="this.form.submit()">
                    @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" @selected($mes == $m)>
                            {{ ucfirst(\Carbon\Carbon::create()->month($m)->locale('es')->monthName) }}
                        </option>
                    @endforeach
                </select>
                <select name="anio" class="select-fin" onchange="this.form.submit()">
                    @foreach(range(2020, now()->year + 1) as $a)
                        <option value="{{ $a }}" @selected($anio == $a)>{{ $a }}</option>
                    @endforeach
                </select>
                @if($categoriaId)
                    <input type="hidden" name="categoria_id" value="{{ $categoriaId }}">
                @endif
            </form>
        </div>
    </div>

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>📤 Transacciones Diarias</h1>
            <p>Monitorea tus gastos e ingresos esporádicos. 
                Egresos: <strong style="color:#ef4444; font-size:1.15rem;">${{ number_format($totalGastos, 0, ',', '.') }} COP</strong> | 
                Entradas: <strong style="color:#10b981; font-size:1.15rem;">${{ number_format($totalIngresos ?? 0, 0, ',', '.') }} COP</strong>
            </p>
        </div>
        <div>
            <button @click="openCrear = true" class="btn-fin success" style="background:linear-gradient(135deg, #4f46e5, #4338ca);">
                ➕ Registrar Transacción
            </button>
        </div>
    </div>

    {{-- Categoría Filtro Rápido --}}
    <div class="filtros-categoria-bx">
        <a href="{{ route('finanzas.gastos.index', ['anio' => $anio, 'mes' => $mes]) }}" class="cat-filter-btn {{ !$categoriaId ? 'activo' : '' }}">
            📂 Todos
        </a>
        @foreach($categorias as $cat)
            <a href="{{ route('finanzas.gastos.index', ['anio' => $anio, 'mes' => $mes, 'categoria_id' => $cat->id]) }}" class="cat-filter-btn {{ $categoriaId == $cat->id ? 'activo' : '' }}" style="border-left:3px solid {{ $cat->color }};">
                {{ $cat->icono }} {{ $cat->nombre }}
            </a>
        @endforeach
    </div>

    {{-- Listado de Gastos --}}
    <div class="card-tabla-bx" style="margin-top:1rem;">
        <table class="tabla-brynex-bx">
            <thead>
                <tr>
                    <th style="width: 10%">Fecha</th>
                    <th style="width: 20%">Categoría</th>
                    <th style="width: 35%">Descripción</th>
                    <th style="width: 10%">Tipo Mov.</th>
                    <th style="width: 15%; text-align:right;">Monto</th>
                    <th style="width: 10%; text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($gastos as $gasto)
                    <tr>
                        <td>{{ Carbon\Carbon::parse($gasto->fecha)->format('d/m/Y') }}</td>
                        <td>
                            <span style="display:flex; align-items:center; gap:0.4rem;">
                                <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:{{ $gasto->categoria->color ?? '#64748b' }}"></span>
                                <strong>{{ $gasto->categoria->icono ?? '📂' }} {{ $gasto->categoria->nombre ?? 'Sin Categoria' }}</strong>
                            </span>
                        </td>
                        <td>
                            {{ $gasto->descripcion ?: '-' }}
                            @if($gasto->es_patrimonio && $gasto->patrimonio)
                                <a href="{{ route('finanzas.patrimonio.show', $gasto->patrimonio_id) }}" class="badge-patrimonio-link">
                                    🏠 {{ $gasto->patrimonio->nombre }}
                                </a>
                            @endif
                        </td>
                        <td>
                            <span class="tipo-tag-bx {{ $gasto->tipo_movimiento }}" style="{{ $gasto->tipo_movimiento === 'ingreso_esporadico' ? 'background:#d1fae5; color:#065f46;' : '' }}">
                                {{ $gasto->tipo_movimiento === 'ingreso_esporadico' ? 'Entrada' : $gasto->tipo_movimiento }}
                            </span>
                        </td>
                        <td style="text-align:right; font-weight:700; color:{{ $gasto->tipo_movimiento === 'ingreso_esporadico' ? '#10b981' : '#ef4444' }};">
                            {{ $gasto->tipo_movimiento === 'ingreso_esporadico' ? '+' : '-' }} ${{ number_format($gasto->monto, 0, ',', '.') }}
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex; justify-content:center; gap:0.4rem;">
                                <button @click="selectedGasto = {{ json_encode($gasto) }}; openEditar = true" class="btn-icon-bx edit" title="Editar">✏️</button>
                                <form action="{{ route('finanzas.gastos.destroy', $gasto->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este gasto?')" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon-bx delete" title="Eliminar">❌</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">
                            No hay gastos registrados en este período.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal Crear --}}
    <div x-show="openCrear" class="modal-overlay-bx" @click.self="openCrear = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #3b82f6, #1d4ed8);" :style="tipo === 'ingreso_esporadico' ? 'background:linear-gradient(135deg, #10b981, #047857);' : (tipo === 'gasto' ? 'background:linear-gradient(135deg, #ef4444, #b91c1c);' : 'background:linear-gradient(135deg, #4f46e5, #4338ca);')">
                <h3 style="color:#fff;" x-text="tipo === 'ingreso_esporadico' ? '📥 Registrar Entrada' : '📤 Registrar Nuevo Gasto'">📥 Registrar Transacción</h3>
                <button @click="openCrear = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.gastos.store') }}" method="POST">
                @csrf
                <div class="modal-body-bx" x-data="{ tipo: 'gasto' }">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha</label>
                        <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto ($ COP)</label>
                        <input type="number" name="monto" placeholder="Ej: 50000" class="form-input-bx" required min="1">
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Tipo de Movimiento</label>
                        <select name="tipo_movimiento" x-model="tipo" class="form-select-bx" required>
                            <option value="gasto">Gasto Habitual</option>
                            <option value="ingreso_esporadico">Entrada</option>
                            <option value="prestamo">Desembolso Préstamo</option>
                            <option value="inversion">Inversión (Cripto/USDT)</option>
                        </select>
                        <div x-show="tipo === 'ingreso_esporadico'" x-cloak style="margin-top:0.4rem; font-size:0.75rem; color:#10b981; font-weight:500;">
                            💡 Este ingreso se sumará automáticamente bajo la fuente "OTRAS ENTRADAS" de este mes.
                        </div>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Categoría</label>
                        <select name="categoria_id" class="form-select-bx" required>
                            @foreach($categorias as $cat)
                                <option value="{{ $cat->id }}" :selected="tipo === 'ingreso_esporadico' && '{{ strtoupper($cat->nombre) }}' === 'ENTRADA'">{{ $cat->icono }} {{ $cat->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Descripción</label>
                        <input type="text" name="descripcion" placeholder="Ej: almuerzo, venta de equipo, etc." class="form-input-bx">
                    </div>
                    <div x-data="{ esPatrimonio: false }" x-show="tipo !== 'ingreso_esporadico'" style="margin-top:1rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <input type="checkbox" name="es_patrimonio" value="1" x-model="esPatrimonio" id="es_patrimonio_check" style="cursor:pointer; width:16px; height:16px;">
                            <label for="es_patrimonio_check" style="font-size:0.8rem; color:#475569; cursor:pointer; font-weight:500;">
                                ¿Asociar a un bien de Patrimonio?
                            </label>
                        </div>
                        <div x-show="esPatrimonio" x-cloak style="margin-top:0.75rem; padding-left:1.5rem; border-left:2px solid #a855f7;">
                            <label class="form-label-bx" style="color:#7e22ce;">Seleccionar Bien</label>
                            <select name="patrimonio_id" class="form-select-bx">
                                <option value="">-- Seleccionar Bien --</option>
                                @foreach($patrimonios as $pat)
                                    <option value="{{ $pat->id }}">{{ $pat->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openCrear = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" :style="tipo === 'ingreso_esporadico' ? 'background:#10b981;' : (tipo === 'gasto' ? 'background:#ef4444;' : 'background:#4f46e5;')">Registrar Transacción</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar --}}
    <div x-show="openEditar" class="modal-overlay-bx" @click.self="openEditar = false" x-cloak>
        <div class="modal-box-bx">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #3b82f6, #1d4ed8);" :style="selectedGasto.tipo_movimiento === 'ingreso_esporadico' ? 'background:linear-gradient(135deg, #10b981, #047857);' : (selectedGasto.tipo_movimiento === 'gasto' ? 'background:linear-gradient(135deg, #ef4444, #b91c1c);' : 'background:linear-gradient(135deg, #4f46e5, #4338ca);')">
                <h3 style="color:#fff;" x-text="selectedGasto.tipo_movimiento === 'ingreso_esporadico' ? '✏️ Editar Entrada' : '✏️ Editar Transacción'">✏️ Editar Transacción</h3>
                <button @click="openEditar = false" class="modal-close-bx">&times;</button>
            </div>
            <form :action="'{{ route('finanzas.gastos.index') }}/' + selectedGasto.id" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body-bx">
                    <div class="form-group-bx">
                        <label class="form-label-bx">Fecha</label>
                        <input type="date" name="fecha" x-model="selectedGasto.fecha" class="form-input-bx" required>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Monto ($ COP)</label>
                        <input type="number" name="monto" x-model="selectedGasto.monto" class="form-input-bx" required min="1">
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Tipo de Movimiento</label>
                        <select name="tipo_movimiento" x-model="selectedGasto.tipo_movimiento" class="form-select-bx" required>
                            <option value="gasto">Gasto Habitual</option>
                            <option value="ingreso_esporadico">Entrada</option>
                            <option value="prestamo">Desembolso Préstamo</option>
                            <option value="inversion">Inversión (Cripto/USDT)</option>
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Categoría</label>
                        <select name="categoria_id" x-model="selectedGasto.categoria_id" class="form-select-bx" required>
                            @foreach($categorias as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->icono }} {{ $cat->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Descripción</label>
                        <input type="text" name="descripcion" x-model="selectedGasto.descripcion" class="form-input-bx">
                    </div>
                    <div x-data="{ esPatrimonio: false }" x-show="selectedGasto.tipo_movimiento !== 'ingreso_esporadico'" style="margin-top:1rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <input type="checkbox" name="es_patrimonio" value="1" x-model="esPatrimonio" :checked="selectedGasto.es_patrimonio == 1" id="es_patrimonio_check_edit" style="cursor:pointer; width:16px; height:16px;">
                            <label for="es_patrimonio_check_edit" style="font-size:0.8rem; color:#475569; cursor:pointer; font-weight:500;">
                                ¿Asociar a un bien de Patrimonio?
                            </label>
                        </div>
                        <div x-show="esPatrimonio" x-cloak style="margin-top:0.75rem; padding-left:1.5rem; border-left:2px solid #a855f7;">
                            <label class="form-label-bx" style="color:#7e22ce;">Seleccionar Bien</label>
                            <select name="patrimonio_id" x-model="selectedGasto.patrimonio_id" class="form-select-bx">
                                <option value="">-- Seleccionar Bien --</option>
                                @foreach($patrimonios as $pat)
                                    <option value="{{ $pat->id }}">{{ $pat->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-foot-bx">
                    <button type="button" @click="openEditar = false" class="btn-glass-bx">Cancelar</button>
                    <button type="submit" class="btn-fin success" :style="selectedGasto.tipo_movimiento === 'ingreso_esporadico' ? 'background:#10b981;' : (selectedGasto.tipo_movimiento === 'gasto' ? 'background:#ef4444;' : 'background:#4f46e5;')">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }
.btn-fin-link { text-decoration: none; padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600; text-align: center; }
.btn-fin-link.primary { background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); color: var(--azul-btn); }
.btn-fin-link.success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #166534; }

/* Filtro Rápido Categorías */
.filtros-categoria-bx { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
.cat-filter-btn { display: flex; align-items: center; gap: 0.35rem; text-decoration: none; padding: 0.4rem 0.75rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.78rem; color: #475569; font-weight: 500; transition: all 0.15s; }
.cat-filter-btn:hover { background: #f8fafc; border-color: #94a3b8; }
.cat-filter-btn.activo { background: var(--azul-btn); color: #fff; border-color: var(--azul-btn); }

.badge-patrimonio-link { display: inline-block; background: #f3e8ff; color: #6b21a8; font-size: 0.68rem; font-weight: 600; text-decoration: none; padding: 0.1rem 0.4rem; border-radius: 4px; border: 1px solid #d8b4fe; margin-left: 5px; }

/* Tabla */
.card-tabla-bx { background: #fff; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.04); overflow: hidden; }
.tabla-brynex-bx { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; }
.tabla-brynex-bx th, .tabla-brynex-bx td { border-bottom: 1px solid #e2e8f0; padding: 0.75rem 1rem; }
.tabla-brynex-bx th { background: #f8fafc; font-weight: 700; color: #475569; }

.tipo-tag-bx { display: inline-block; font-size: 0.62rem; font-weight: 700; text-transform: uppercase; padding: 0.05rem 0.3rem; border-radius: 4px; }
.tipo-tag-bx.gasto { background: #fee2e2; color: #991b1b; }
.tipo-tag-bx.prestamo { background: #fef3c7; color: #92400e; }
.tipo-tag-bx.inversion { background: #e0f2fe; color: #0369a1; }

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }
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
