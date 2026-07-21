@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Entradas Mensuales')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="excelGrid()">

    @component('finanzas.partials._header_banner', [
        'titulo' => '📥 Entradas Mensuales',
        'subtitulo' => 'Haz clic en cualquier celda para ingresar o editar el monto. Los cambios se guardan automáticamente al salir de la celda.',
        'breadcrumb' => [
            'Finanzas Personales' => route('finanzas.dashboard'),
            'Entradas Mensuales' => null
        ]
    ])
        @slot('opciones')
            <a href="{{ route('finanzas.fuentes.index') }}" class="btn-fin-link primary">⚙️ Gestionar Fuentes</a>
            <a href="{{ route('finanzas.app-lideres.index') }}" class="btn-fin-link success">👥 App Líderes</a>
            
            <form method="GET" action="{{ route('finanzas.entradas.index') }}" class="period-selector-bx" style="margin: 0; display: inline-block;">
                <select name="anio" class="select-fin" onchange="this.form.submit()">
                    @foreach(range(2020, now()->year + 1) as $a)
                        <option value="{{ $a }}" @selected($anio == $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </form>
        @endslot
    @endcomponent

    {{-- Cuadrícula Excel --}}
    <div class="card-tabla" style="overflow-x:auto;">
        <table class="tabla-excel">
            <thead>
                <tr>
                    <th class="fuente-col">Fuente de Ingreso / Mes</th>
                    @foreach(range(1,12) as $m)
                        <th class="mes-col">{{ ucfirst(\Carbon\Carbon::create()->month($m)->locale('es')->shortMonthName) }}</th>
                    @endforeach
                    <th class="total-col">Total Anual</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fuentes as $fuente)
                    <tr class="fuente-row">
                        <td class="fuente-name-cell">
                            @php
                                $nombreUpper = strtoupper(trim($fuente->nombre));
                                $esCalculada = in_array($nombreUpper, ['BRYNEX', 'INTERESES PRESTAMOS', 'OTRAS APP', 'PROYECTOS', 'OTRAS ENTRADAS']);
                            @endphp
                            
                            @if($nombreUpper === 'BRYNEX')
                                <a href="{{ route('finanzas.brynex-aliados.index') }}?anio={{ $anio }}" style="color: #047857; text-decoration: underline;" title="Administrar cobros de aliados">
                                    <strong>{{ $fuente->nombre }} ⚙️</strong>
                                </a>
                            @elseif($nombreUpper === 'OTRAS APP')
                                <a href="{{ route('finanzas.app-lideres.index') }}?anio={{ $anio }}" style="color: #1d4ed8; text-decoration: underline;" title="Administrar cobros de Otras App">
                                    <strong>{{ $fuente->nombre }} ⚙️</strong>
                                </a>
                            @else
                                <strong>{{ $fuente->nombre }}</strong>
                            @endif
                        </td>
                        @php $totalFuente = 0; @endphp
                        @foreach(range(1,12) as $mesNum)
                            @php
                                $entradaObj = isset($entradas[$fuente->id][$mesNum]) ? $entradas[$fuente->id][$mesNum]->first() : null;
                                $monto = $entradaObj ? $entradaObj->monto : 0;
                                $isCalculated = $entradaObj ? (bool)($entradaObj->is_calculated ?? false) : false;
                                $totalFuente += $monto;
                            @endphp
                            <td class="excel-cell {{ $isCalculated ? 'calculated-cell' : '' }}" 
                                @if(!$isCalculated)
                                @click="editCell({{ $fuente->id }}, {{ $mesNum }}, $el)" 
                                :class="{'editing': isEditing({{ $fuente->id }}, {{ $mesNum }})}"
                                @elseif(strtoupper(trim($fuente->nombre)) === 'OTRAS ENTRADAS')
                                @click="verDetalleEsporadico({{ $mesNum }})"
                                style="cursor: pointer; background: #e8f5e9;"
                                title="Haga clic para ver el detalle de ingresos extras"
                                @elseif(strtoupper(trim($fuente->nombre)) === 'BRYNEX')
                                @click="window.location.href='{{ route('finanzas.brynex-aliados.index') }}?anio={{ $anio }}'"
                                style="cursor: pointer; background: #e8f5e9;"
                                title="Haga clic para administrar cobros de aliados de Brynex"
                                @elseif(strtoupper(trim($fuente->nombre)) === 'OTRAS APP')
                                @click="window.location.href='{{ route('finanzas.app-lideres.index') }}?anio={{ $anio }}'"
                                style="cursor: pointer; background: #e8f5e9;"
                                title="Haga clic para administrar cobros de Otras App"
                                @endif
                            >
                                <div x-show="!isEditing({{ $fuente->id }}, {{ $mesNum }})" class="cell-val">
                                    {{ $monto > 0 ? '$' . number_format($monto, 0, ',', '.') : '-' }}
                                </div>
                                @if(!$isCalculated)
                                <input x-show="isEditing({{ $fuente->id }}, {{ $mesNum }})" 
                                       x-ref="input_{{ $fuente->id }}_{{ $mesNum }}"
                                       type="text" 
                                       value="{{ $monto > 0 ? '$' . number_format($monto, 0, ',', '.') : '' }}"
                                       class="cell-input"
                                       @input="event => event.target.value = formatMoney(event.target.value)"
                                       @blur="saveCell({{ $fuente->id }}, {{ $mesNum }}, $el.value)"
                                       @keydown.enter="$el.blur()"
                                       @keydown.escape="cancelEdit()"
                                >
                                @endif
                            </td>
                        @endforeach
                        <td class="fuente-total-cell" id="total_fuente_{{ $fuente->id }}">
                            <strong>${{ number_format($totalFuente, 0, ',', '.') }}</strong>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" style="text-align:center; padding:2rem; color:#64748b;">
                            No tienes fuentes de ingresos registradas. Comienza agregando una.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td><strong>Total Mensual</strong></td>
                    @php $granTotal = 0; @endphp
                    @foreach(range(1,12) as $mesNum)
                        @php
                            $totalMes = 0;
                            foreach($fuentes as $fuente) {
                                $totalMes += isset($entradas[$fuente->id][$mesNum]) ? $entradas[$fuente->id][$mesNum]->first()->monto : 0;
                            }
                            $granTotal += $totalMes;
                        @endphp
                        <td id="total_mes_{{ $mesNum }}"><strong>{{ $totalMes > 0 ? number_format($totalMes, 0, ',', '.') : '-' }}</strong></td>
                    @endforeach
                    <td id="gran_total"><strong>${{ number_format($granTotal, 0, ',', '.') }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Helper alert para ingresos esporádicos --}}
    <div style="margin-top: 1.5rem; padding: 1rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
        <div style="display: flex; gap: 0.75rem; align-items: center; min-width: 280px; flex: 1;">
            <span style="font-size: 1.75rem;">💵</span>
            <div>
                <strong style="color: #166534; font-size: 0.85rem;">¿Deseas registrar un Ingreso Extra o Esporádico?</strong>
                <p style="margin: 0; font-size: 0.78rem; color: #1e3a1e;">Registra tus entradas esporádicas en el módulo de <strong>Transacciones Diarias</strong>. Se sumarán de forma automática en la fila <strong>"OTRAS ENTRADAS"</strong> de cada mes.</p>
            </div>
        </div>
        <a href="{{ route('finanzas.gastos.index') }}" class="btn-fin-link success" style="white-space: nowrap; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600; text-align: center; background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #166534;">
            📝 Registrar Ingreso Extra
        </a>
    </div>

    {{-- Modal Detalle Ingresos Esporádicos --}}
    <div x-show="openDetalleModal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="modal-overlay-bx" 
         @click.self="openDetalleModal = false"
         style="position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem;">
        
        <div class="modal-box-bx" style="background:#fff; border-radius:14px; box-shadow:0 20px 40px rgba(0,0,0,0.2); width:100%; max-width:750px; overflow:hidden; white-space: normal;">
            <div class="modal-head-bx" style="background: linear-gradient(135deg, #10b981, #047857); color: #fff; display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #cbd5e1;">
                <h3 style="color:#fff; margin:0;" x-text="'💵 Detalle de Ingresos Extras - ' + detalleMes">💵 Detalle de Ingresos Extras</h3>
                <button @click="openDetalleModal = false" class="modal-close-bx" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:rgba(255,255,255,0.7);">&times;</button>
            </div>
            <div class="modal-body-bx" style="padding:1.25rem;">
                <div x-show="loadingDetalle" style="text-align:center; padding:2rem; color:#64748b;">
                    <div style="border: 3px solid #f3f3f3; border-top: 3px solid #10b981; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; margin: 0 auto 0.5rem auto;"></div>
                    <span>Cargando transacciones...</span>
                </div>
                <div x-show="!loadingDetalle && detalleItems.length === 0" style="text-align:center; padding:2rem; color:#64748b;">
                    No se encontraron ingresos esporádicos para este mes.
                </div>
                <div x-show="!loadingDetalle && detalleItems.length > 0">
                    <div style="max-height: 350px; overflow-y: auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
                            <thead>
                                <tr style="border-bottom:2px solid #e2e8f0; color:#475569; text-align:left;">
                                    <th style="padding:0.5rem; width: 110px;">Fecha</th>
                                    <th style="padding:0.5rem; width: 130px;">Categoría</th>
                                    <th style="padding:0.5rem;">Descripción</th>
                                    <th style="padding:0.5rem; text-align:right; width: 110px;">Monto</th>
                                    <th style="padding:0.5rem; text-align:center; width: 85px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in detalleItems">
                                    <tr style="border-bottom:1px solid #f1f5f9; color:#0f172a; vertical-align: middle;">
                                        <!-- Modo lectura -->
                                        <td x-show="editingItemId !== item.id" style="padding:0.5rem;" x-text="item.fecha"></td>
                                        <td x-show="editingItemId !== item.id" style="padding:0.5rem;" x-text="item.categoria_nombre"></td>
                                        <td x-show="editingItemId !== item.id" style="padding:0.5rem;" x-text="item.descripcion || 'Sin descripción'"></td>
                                        <td x-show="editingItemId !== item.id" style="padding:0.5rem; text-align:right; font-weight:600; color:#10b981;" x-text="'+ $' + parseFloat(item.monto).toLocaleString('es-CO', {maximumFractionDigits:0})"></td>
                                        <td x-show="editingItemId !== item.id" style="padding:0.5rem; text-align:center;">
                                            <div style="display:flex; gap:0.25rem; justify-content:center;">
                                                <button @click="startEditEsporadico(item)" style="padding:0.2rem 0.4rem; font-size:0.7rem; border-radius:4px; border:none; background:#eff6ff; color:#2563eb; cursor:pointer;" title="Editar">✏️</button>
                                                <button @click="deleteEsporadico(item.id, detalleMesNum)" style="padding:0.2rem 0.4rem; font-size:0.7rem; border-radius:4px; border:none; background:#fef2f2; color:#dc2626; cursor:pointer;" title="Eliminar">🗑️</button>
                                            </div>
                                        </td>
                                        
                                        <!-- Modo edición -->
                                        <td x-show="editingItemId === item.id" style="padding:0.25rem;">
                                            <input type="date" x-model="editForm.fecha" style="width:100%; font-size:0.75rem; padding:0.2rem; border:1px solid #cbd5e1; border-radius:4px;">
                                        </td>
                                        <td x-show="editingItemId === item.id" style="padding:0.25rem;">
                                            <select x-model="editForm.categoria_id" style="width:100%; font-size:0.75rem; padding:0.2rem; border:1px solid #cbd5e1; border-radius:4px;">
                                                @foreach($categorias as $cat)
                                                    <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td x-show="editingItemId === item.id" style="padding:0.25rem;">
                                            <input type="text" x-model="editForm.descripcion" placeholder="Descripción" style="width:100%; font-size:0.75rem; padding:0.2rem; border:1px solid #cbd5e1; border-radius:4px;">
                                        </td>
                                        <td x-show="editingItemId === item.id" style="padding:0.25rem;">
                                            <input type="number" x-model.number="editForm.monto" style="width:100%; font-size:0.75rem; padding:0.2rem; border:1px solid #cbd5e1; border-radius:4px; text-align:right;">
                                        </td>
                                        <td x-show="editingItemId === item.id" style="padding:0.25rem; text-align:center;">
                                            <div style="display:flex; gap:0.25rem; justify-content:center;">
                                                <button @click="saveEsporadico(detalleMesNum)" style="padding:0.2rem 0.4rem; font-size:0.7rem; border-radius:4px; border:none; background:#dcfce7; color:#166534; cursor:pointer;" title="Guardar">✔️</button>
                                                <button @click="cancelEditEsporadico()" style="padding:0.2rem 0.4rem; font-size:0.7rem; border-radius:4px; border:none; background:#f1f5f9; color:#475569; cursor:pointer;" title="Cancelar">❌</button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:1rem; border-top:2px solid #e2e8f0; padding-top:0.75rem; display:flex; justify-content:space-between; font-weight:700; font-size:0.9rem; color:#0f172a;">
                        <span>Total Acumulado:</span>
                        <span style="color:#10b981;" x-text="'$' + parseFloat(detalleTotal).toLocaleString('es-CO', {maximumFractionDigits:0}) + ' COP'"></span>
                    </div>
                </div>
            </div>
            <div class="modal-foot-bx" style="display:flex; justify-content:flex-end; padding:1rem; border-top:1px solid #cbd5e1; background:#f8fafc;">
                <button type="button" @click="openDetalleModal = false" class="btn-glass-bx" style="text-decoration:none; padding:0.45rem 1rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.78rem; font-weight:600; cursor:pointer; background:#fff; color:#475569;">Cerrar</button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.finanzas-container { max-width: 1400px; margin: 0 auto; padding: 0.5rem; }

/* Tabla Excel */
.tabla-excel { width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left; table-layout: fixed; }
.tabla-excel th, .tabla-excel td { border: 1px solid #e2e8f0; padding: 0.6rem 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tabla-excel th { background: #f8fafc; font-weight: 700; color: #475569; text-align: center; }
.tabla-excel th.fuente-col { width: 170px; text-align: left; }
.tabla-excel th.mes-col { width: 80px; }
.tabla-excel th.total-col { width: 110px; background: #f1f5f9; color: #1e293b; }

.tabla-excel td.fuente-name-cell {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
    line-height: 1.25;
    padding: 0.6rem 0.5rem;
}
.fuente-name-cell strong {
    word-break: break-word;
    display: inline-block;
}
.tipo-tag { display: inline-block; font-size: 0.62rem; font-weight: 700; text-transform: uppercase; padding: 0.05rem 0.3rem; border-radius: 4px; width: fit-content; }

.excel-cell { text-align: right; cursor: pointer; transition: background 0.1s; position: relative; padding: 0 !important; }
.excel-cell:hover { background: #f0fdf4; }
.excel-cell.editing { background: #fff; cursor: default; }
.excel-cell.calculated-cell { background: #f8fafc; color: #64748b; cursor: not-allowed; }
.excel-cell.calculated-cell:hover { background: #f1f5f9; }
.excel-cell.calculated-cell .cell-val { font-weight: 500; color: #64748b; }

.cell-val { padding: 0.6rem 0.5rem; width: 100%; height: 100%; min-height: 28px; color: #0f172a; }
.cell-input { width: 100%; height: 100%; padding: 0.6rem 0.5rem; border: 2px solid var(--acento); border-radius: 0; outline: none; font-size: 0.8rem; text-align: right; background: #fff; }

.fuente-total-cell { text-align: right; background: #f8fafc; color: #1e293b; }
.total-row { background: #f1f5f9; color: #0f172a; text-align: right; }
.total-row td { border-top: 2px solid #cbd5e1; padding: 0.6rem 0.2rem !important; font-size: 0.73rem !important; font-weight: bold; }
.total-row td:first-child { text-align: left; font-size: 0.78rem !important; }
</style>
@endpush

@push('scripts')
<script>
function excelGrid() {
    return {
        editingCell: { fuente_id: null, mes: null },
        openDetalleModal: false,
        detalleMes: '',
        detalleMesNum: null,
        detalleItems: [],
        detalleTotal: 0,
        loadingDetalle: false,
        
        editingItemId: null,
        editForm: { id: null, fecha: '', descripcion: '', categoria_id: '', monto: 0 },
        
        init() {
            const activeModalMes = sessionStorage.getItem('active_modal_mes');
            if (activeModalMes) {
                sessionStorage.removeItem('active_modal_mes');
                this.verDetalleEsporadico(parseInt(activeModalMes));
            }
        },
        
        isEditing(fuenteId, mes) {
            return this.editingCell.fuente_id === fuenteId && this.editingCell.mes === mes;
        },
        
        editCell(fuenteId, mes, el) {
            this.editingCell = { fuente_id: fuenteId, mes: mes };
            this.$nextTick(() => {
                const input = this.$refs[`input_${fuenteId}_${mes}`];
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },
        
        cancelEdit() {
            this.editingCell = { fuente_id: null, mes: null };
        },

        async verDetalleEsporadico(mesNum) {
            const mesesNombres = [
                'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
            ];
            this.detalleMes = mesesNombres[mesNum - 1];
            this.detalleMesNum = mesNum;
            this.openDetalleModal = true;
            this.loadingDetalle = true;
            this.detalleItems = [];
            this.detalleTotal = 0;
            this.cancelEditEsporadico();
            
            try {
                const response = await fetch(`{{ route('finanzas.entradas.detalle-esporadico') }}?mes=${mesNum}&anio={{ $anio }}`, {
                    headers: {
                        "Accept": "application/json",
                        "X-Requested-With": "XMLHttpRequest"
                    }
                });
                if (response.ok) {
                    const data = await response.json();
                    this.detalleItems = data;
                    this.detalleTotal = data.reduce((acc, item) => acc + parseFloat(item.monto), 0);
                } else {
                    console.error("Error al obtener detalle.");
                }
            } catch (error) {
                console.error(error);
            } finally {
                this.loadingDetalle = false;
            }
        },
        
        formatMoney(value) {
            let num = value.toString().replace(/\D/g, '');
            if (num === '') return '';
            return '$' + parseInt(num).toLocaleString('es-CO', { maximumFractionDigits: 0 });
        },

        async saveCell(fuenteId, mes, val) {
            const cleanVal = val.toString().replace(/\D/g, '');
            const monto = parseFloat(cleanVal) || 0;
            
            // Cerrar modo edición inmediatamente para respuesta fluida
            this.cancelEdit();

            try {
                const response = await fetch("{{ route('finanzas.entradas.store') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: JSON.stringify({
                        fuente_id: fuenteId,
                        anio: {{ $anio }},
                        mes: mes,
                        monto: monto
                    })
                });

                if (response.ok) {
                    // Recargar la página silenciosamente o recargar los valores en caliente
                    // Para mayor consistencia de totales (suma mensual, anual, total), refrescamos la vista
                    window.location.reload();
                } else {
                    alert("Error al guardar el monto. Verifica la conexión.");
                }
            } catch (error) {
                console.error(error);
                alert("Error de red al intentar guardar.");
            }
        },
        
        startEditEsporadico(item) {
            this.editingItemId = item.id;
            this.editForm = {
                id: item.id,
                fecha: item.fecha_raw,
                descripcion: item.descripcion || '',
                categoria_id: item.categoria_id,
                monto: item.monto
            };
        },
        
        cancelEditEsporadico() {
            this.editingItemId = null;
            this.editForm = { id: null, fecha: '', descripcion: '', categoria_id: '', monto: 0 };
        },
        
        async saveEsporadico(mesNum) {
            if (!this.editForm.fecha || !this.editForm.monto || this.editForm.monto <= 0) {
                alert("La fecha y el monto son obligatorios.");
                return;
            }
            try {
                const url = `/finanzas/entradas/esporadico/${this.editForm.id}`;
                const response = await fetch(url, {
                    method: "PUT",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: JSON.stringify({
                        categoria_id: this.editForm.categoria_id,
                        fecha: this.editForm.fecha,
                        monto: this.editForm.monto,
                        descripcion: this.editForm.descripcion
                    })
                });

                if (response.ok) {
                    sessionStorage.setItem('active_modal_mes', mesNum);
                    window.location.reload();
                } else {
                    const data = await response.json();
                    alert("Error al actualizar: " + (data.message || "Verifique los datos."));
                }
            } catch (error) {
                console.error(error);
                alert("Error de red al intentar actualizar.");
            }
        },
        
        async deleteEsporadico(id, mesNum) {
            if (!confirm("¿Está seguro de eliminar este ingreso extra?")) {
                return;
            }
            try {
                const url = `/finanzas/entradas/esporadico/${id}`;
                const response = await fetch(url, {
                    method: "DELETE",
                    headers: {
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                        "X-Requested-With": "XMLHttpRequest"
                    }
                });

                if (response.ok) {
                    sessionStorage.setItem('active_modal_mes', mesNum);
                    window.location.reload();
                } else {
                    alert("Error al eliminar el ingreso.");
                }
            } catch (error) {
                console.error(error);
                alert("Error de red al intentar eliminar.");
            }
        }
    }
}
</script>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
