@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Gestión de Gastos')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ openCrear: false, openEditar: false, selectedGasto: {} }">
    @component('finanzas.partials._header_banner', [
        'titulo' => request('tipo') === 'prestamo' ? '🤝 Transacciones Diarias (Préstamos)' : '📤 Transacciones Diarias',
        'subtitulo' => request('tipo') === 'prestamo' 
            ? 'Monitorea tus préstamos del mes seleccionado. Total Préstamos: $' . number_format($totalPrestamos, 0, ',', '.') . ' COP'
            : 'Monitorea tus gastos e ingresos esporádicos. Egresos: $' . number_format($totalGastos, 0, ',', '.') . ' COP | Entradas: $' . number_format($totalIngresos ?? 0, 0, ',', '.') . ' COP',
        'breadcrumb' => [
            'Finanzas Personales' => route('finanzas.dashboard'),
            'Transacciones Diarias' => null
        ]
    ])
        @slot('opciones')
            <a href="{{ route('finanzas.gastos.informe') }}" class="btn-fin-link primary">📊 Informe Anual</a>
            <a href="{{ route('finanzas.categorias.index') }}" class="btn-fin-link success">⚙️ Categorías</a>
            
            <form method="GET" action="{{ route('finanzas.gastos.index') }}" class="period-selector-bx" style="margin: 0; display: inline-block;">
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
                @if(request('tipo'))
                    <input type="hidden" name="tipo" value="{{ request('tipo') }}">
                @endif
            </form>
            
            <button @click="openCrear = true" class="btn-fin success" style="background:linear-gradient(135deg, #4f46e5, #4338ca); margin-left: 0.5rem;">
                ➕ Registrar Transacción
            </button>
        @endslot
    @endcomponent

    {{-- Filtro de Categoría Simple con Select + Filtros de Tipo --}}
    <div style="margin-top:1rem; margin-bottom:1.25rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:center; background:#fff; padding:0.75rem 1rem; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="font-size:0.8rem; font-weight:700; color:#475569;">Categoría:</label>
            <select onchange="window.location.href=this.value" class="select-fin" style="padding:0.4rem 0.75rem; border-radius:8px; border:1px solid #cbd5e1; font-size:0.8rem; outline:none; background:#fff; font-weight:700; cursor:pointer;">
                <option value="{{ route('finanzas.gastos.index', ['anio' => $anio, 'mes' => $mes, 'tipo' => request('tipo')]) }}" @selected(!$categoriaId)>📂 Todas las categorías</option>
                @foreach($categorias as $cat)
                    <option value="{{ route('finanzas.gastos.index', ['anio' => $anio, 'mes' => $mes, 'categoria_id' => $cat->id, 'tipo' => request('tipo')]) }}" @selected($categoriaId == $cat->id)>
                        {{ $cat->icono }} {{ $cat->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="display:flex; gap:0.4rem; align-items:center;">
            <span style="font-size:0.8rem; font-weight:700; color:#475569; margin-right:0.25rem;">Vista:</span>
            <a href="{{ route('finanzas.gastos.index', ['anio' => $anio, 'mes' => $mes, 'categoria_id' => $categoriaId]) }}"
               style="padding:0.4rem 0.85rem; border-radius:8px; font-size:0.78rem; text-decoration:none; font-weight:700; border:1px solid #cbd5e1; transition:all 0.15s; display:flex; align-items:center; gap:0.25rem; {{ !request('tipo') ? 'background:#4f46e5; color:#fff; border-color:#4f46e5; box-shadow:0 2px 4px rgba(79,70,229,0.2);' : 'background:#fff; color:#475569;' }}">
                💼 General (Excluir Préstamos)
            </a>
            <a href="{{ route('finanzas.gastos.index', ['anio' => $anio, 'mes' => $mes, 'categoria_id' => $categoriaId, 'tipo' => 'prestamo']) }}"
               style="padding:0.4rem 0.85rem; border-radius:8px; font-size:0.78rem; text-decoration:none; font-weight:700; border:1px solid #cbd5e1; transition:all 0.15s; display:flex; align-items:center; gap:0.25rem; {{ request('tipo') === 'prestamo' ? 'background:#f59e0b; color:#fff; border-color:#f59e0b; box-shadow:0 2px 4px rgba(245,158,11,0.2);' : 'background:#fff; color:#475569;' }}">
                🤝 Préstamos <span style="font-size:0.72rem; padding:0.1rem 0.35rem; border-radius:6px; background:{{ request('tipo') === 'prestamo' ? 'rgba(255,255,255,0.2)' : '#f1f5f9' }}">${{ number_format($totalPrestamos, 0, ',', '.') }}</span>
            </a>
        </div>
    </div>

    {{-- Listado de Gastos (Escritorio) --}}
    <div class="card-tabla-bx desktop-only" style="margin-top:1rem;">
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
                            @if($gasto->soporte_path)
                                <a href="{{ route('finanzas.gastos.descargar-soporte', $gasto->id) }}" class="badge-soporte-link" title="Descargar soporte de pago">
                                    📎 Soporte
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
                                @if($gasto->cc_trabajo_id)
                                    {{-- El monto lo gobierna el desglose del trabajo: se edita allá o queda descuadrado --}}
                                    <a href="{{ route('finanzas.cuenta-corriente.index') }}" class="btn-icon-bx"
                                       title="Costo de un trabajo de Cuenta Corriente. Se edita desde el trabajo, no desde aquí.">🛠️</a>
                                @else
                                    <button @click="selectedGasto = {{ json_encode($gasto) }}; openEditar = true" class="btn-icon-bx edit" title="Editar">✏️</button>
                                    <form action="{{ route('finanzas.gastos.destroy', $gasto->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este gasto?')" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon-bx delete" title="Eliminar">❌</button>
                                    </form>
                                @endif
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

    {{-- Listado de Gastos (Móvil) --}}
    <div class="mobile-only" style="display:none; margin-top:1rem;">
        <div style="display:flex; flex-direction:column; gap:0.75rem;">
            @forelse($gastos as $gasto)
                <div style="background:#fff; border:1px solid #cbd5e1; border-radius:12px; padding:0.85rem; box-shadow:0 2px 8px rgba(0,0,0,0.03); display:flex; flex-direction:column; gap:0.5rem;">
                    
                    {{-- Fila Superior: Categoria y Monto --}}
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="display:flex; align-items:center; gap:0.4rem;">
                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:{{ $gasto->categoria->color ?? '#64748b' }}"></span>
                            <strong style="font-size:0.85rem; color:#1e293b;">{{ $gasto->categoria->icono ?? '📂' }} {{ $gasto->categoria->nombre ?? 'Sin Categoria' }}</strong>
                        </span>
                        <strong style="font-size:0.95rem; color:{{ $gasto->tipo_movimiento === 'ingreso_esporadico' ? '#10b981' : '#ef4444' }};">
                            {{ $gasto->tipo_movimiento === 'ingreso_esporadico' ? '+' : '-' }} ${{ number_format($gasto->monto, 0, ',', '.') }}
                        </strong>
                    </div>

                    {{-- Fila Media: Descripcion y Badges --}}
                    @if($gasto->descripcion || $gasto->es_patrimonio || $gasto->soporte_path)
                        <div style="font-size:0.78rem; color:#475569;">
                            {{ $gasto->descripcion ?: '-' }}
                            
                            @if($gasto->es_patrimonio && $gasto->patrimonio)
                                <a href="{{ route('finanzas.patrimonio.show', $gasto->patrimonio_id) }}" class="badge-patrimonio-link" style="margin-left: 5px;">
                                    🏠 {{ $gasto->patrimonio->nombre }}
                                </a>
                            @endif
                            
                            @if($gasto->soporte_path)
                                <a href="{{ route('finanzas.gastos.descargar-soporte', $gasto->id) }}" class="badge-soporte-link" style="margin-left: 5px;">
                                    📎 Soporte
                                </a>
                            @endif
                        </div>
                    @endif

                    {{-- Fila Inferior: Info de fecha/tipo y Acciones --}}
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:0.5rem; margin-top:0.25rem;">
                        <div style="display:flex; align-items:center; gap:0.4rem; font-size:0.7rem; color:#94a3b8; font-weight:600;">
                            <span>{{ Carbon\Carbon::parse($gasto->fecha)->format('d/m/Y') }}</span>
                            <span>•</span>
                            <span class="tipo-tag-bx {{ $gasto->tipo_movimiento }}" style="font-size:0.6rem; padding:0.1rem 0.3rem; {{ $gasto->tipo_movimiento === 'ingreso_esporadico' ? 'background:#d1fae5; color:#065f46;' : '' }}">
                                {{ $gasto->tipo_movimiento === 'ingreso_esporadico' ? 'Entrada' : $gasto->tipo_movimiento }}
                            </span>
                        </div>
                        
                        <div style="display:flex; gap:0.4rem;">
                            @if($gasto->cc_trabajo_id)
                                <a href="{{ route('finanzas.cuenta-corriente.index') }}" class="btn-glass-bx"
                                   style="padding: 0.3rem 0.6rem; font-size: 0.72rem; background:#fffbeb; color:#b45309; border-color:#fcd34d; font-weight:600; text-decoration:none;">
                                    🛠️ Editar en el trabajo
                                </a>
                            @else
                                <button @click="selectedGasto = {{ json_encode($gasto) }}; openEditar = true" class="btn-glass-bx" style="padding: 0.3rem 0.6rem; font-size: 0.72rem; border-color:#cbd5e1; color:#334155; font-weight:600;" title="Editar">✏️ Editar</button>
                                <form action="{{ route('finanzas.gastos.destroy', $gasto->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este gasto?')" style="display:inline; margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-glass-bx" style="padding: 0.3rem 0.6rem; font-size: 0.72rem; background:#fef2f2; color:#ef4444; border-color:#fee2e2; font-weight:600;" title="Eliminar">🗑️ Borrar</button>
                                </form>
                            @endif
                        </div>
                    </div>

                </div>
            @empty
                <div style="text-align:center; padding:2rem; color:#64748b; font-size:0.8rem; background:#fff; border:1px solid #cbd5e1; border-radius:12px;">
                    No se encontraron transacciones registradas.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Modal Crear --}}
    <div x-show="openCrear" class="modal-overlay-bx" @click.self="openCrear = false" x-cloak>
        <div class="modal-box-bx" style="max-width: 480px;">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #3b82f6, #1d4ed8);" :style="tipo === 'ingreso_esporadico' ? 'background:linear-gradient(135deg, #10b981, #047857);' : (tipo === 'gasto' ? 'background:linear-gradient(135deg, #ef4444, #b91c1c);' : 'background:linear-gradient(135deg, #4f46e5, #4338ca);')">
                <h3 style="color:#fff;" x-text="tipo === 'ingreso_esporadico' ? '📥 Registrar Entrada' : '📤 Registrar Nuevo Gasto'">📥 Registrar Transacción</h3>
                <button @click="openCrear = false" class="modal-close-bx">&times;</button>
            </div>
            <form action="{{ route('finanzas.gastos.store') }}" method="POST" enctype="multipart/form-data" 
                  x-data="{
                      categoriaOpen: false,
                      categoriaSearch: '',
                      categoriaIdSelected: '',
                      categoriaIconSelected: '',
                      categorias: {{ json_encode($categorias->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'icono' => $c->icono, 'color' => $c->color]) ?? []) }},
                      soportePreview: null,
                      soporteName: '',
                      cargando: false,
                      tipo: 'gasto',
                      montoLimpio: '',
                      montoFormateado: '',
                      formatearMonto() {
                          let valor = this.montoFormateado.replace(/\D/g, '');
                          this.montoLimpio = valor;
                          if (valor) {
                              this.montoFormateado = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(valor);
                          } else {
                              this.montoFormateado = '';
                          }
                      },
                      
                      get filtradas() {
                          if (!this.categoriaSearch) return this.categorias;
                          return this.categorias.filter(c => c.nombre.toLowerCase().includes(this.categoriaSearch.toLowerCase()));
                      },
                      select(cat) {
                          this.categoriaIdSelected = cat.id;
                          this.categoriaIconSelected = cat.icono;
                          this.categoriaSearch = cat.nombre;
                          this.categoriaOpen = false;
                      },
                      clearCategoria() {
                          this.categoriaIdSelected = '';
                          this.categoriaIconSelected = '';
                          this.categoriaSearch = '';
                      },
                      async pegarSoporte() {
                          try {
                              const clipboardItems = await navigator.clipboard.read();
                              for (const item of clipboardItems) {
                                  for (const type of item.types) {
                                      if (type.startsWith('image/')) {
                                          const blob = await item.getType(type);
                                          const file = new File([blob], 'soporte_crear_' + Date.now() + '.png', { type: type });
                                          const dt = new DataTransfer();
                                          dt.items.add(file);
                                          this.$refs.soporteInputCrear.files = dt.files;
                                          this.soporteName = file.name;
                                          this.soportePreview = URL.createObjectURL(blob);
                                          return;
                                      }
                                  }
                              }
                              alert('No se encontró ninguna imagen en el portapapeles. Copia una imagen primero.');
                          } catch (err) {
                              alert('No se pudo acceder al portapapeles. Intenta subir el archivo seleccionándolo.');
                          }
                      },
                      handleFileChange(e) {
                          const file = e.target.files[0];
                          if (file) {
                              this.soporteName = file.name;
                              this.soportePreview = URL.createObjectURL(file);
                          }
                      },
                      limpiarSoporte() {
                          this.$refs.soporteInputCrear.value = '';
                          this.soporteName = '';
                          this.soportePreview = null;
                      },
                      handlePaste(e) {
                          if (!openCrear) return;
                          const items = (e.clipboardData || e.originalEvent.clipboardData).items;
                          for (const item of items) {
                              if (item.kind === 'file' && item.type.startsWith('image/')) {
                                  const blob = item.getAsFile();
                                  const file = new File([blob], 'soporte_crear_' + Date.now() + '.png', { type: item.type });
                                  const dt = new DataTransfer();
                                  dt.items.add(file);
                                  this.$refs.soporteInputCrear.files = dt.files;
                                  this.soporteName = file.name;
                                  this.soportePreview = URL.createObjectURL(blob);
                              }
                          }
                      }
                  }"
                  @paste.window="handlePaste($event)"
                  @submit="cargando = true"
            >
                @csrf
                <div class="modal-body-bx">
                    
                    {{-- Fecha y Monto en la misma fila --}}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group-bx">
                            <label class="form-label-bx">Fecha</label>
                            <input type="date" name="fecha" value="{{ now()->toDateString() }}" class="form-input-bx" style="font-size: 1.15rem; font-weight: 700; color: #1e293b;" required>
                        </div>
                        <div class="form-group-bx">
                            <label class="form-label-bx">Monto ($ COP)</label>
                            <input type="text" 
                                   x-model="montoFormateado" 
                                   @input="formatearMonto()" 
                                   placeholder="Ej: 50.000" 
                                   class="form-input-bx" 
                                   style="font-size: 1.15rem; font-weight: 700; color: #1e293b;"
                                   autocomplete="off"
                                   required>
                            <input type="hidden" name="monto" :value="montoLimpio">
                        </div>
                    </div>

                    {{-- Tipo de Movimiento --}}
                    {{-- Tipo de Movimiento y Categoría en la misma fila --}}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                        {{-- Tipo de Movimiento --}}
                        <div class="form-group-bx">
                            <label class="form-label-bx">Tipo de Movimiento</label>
                            <select name="tipo_movimiento" x-model="tipo" class="form-select-bx" required style="height: 100%;">
                                <option value="gasto">Gasto Habitual</option>
                                <option value="ingreso_esporadico">Entrada</option>
                                <option value="prestamo">Desembolso Préstamo</option>
                                <option value="inversion">Inversión (Cripto/USDT)</option>
                            </select>
                        </div>

                        {{-- Categoría (Combobox buscable y editable) --}}
                        <div class="form-group-bx" style="position: relative;">
                            <label class="form-label-bx">Categoría</label>
                            <div class="combobox-container-bx" @click.away="categoriaOpen = false" style="width: 100%;">
                                <div class="combobox-input-wrapper-bx" style="position: relative; display: flex; align-items: center; width: 100%;">
                                    <span x-show="categoriaIconSelected" class="combobox-icon-bx" x-text="categoriaIconSelected" style="position: absolute; left: 0.75rem; font-size: 0.95rem; z-index: 5;"></span>
                                    <input type="text" 
                                           x-model="categoriaSearch" 
                                           @focus="categoriaOpen = true"
                                           @input="categoriaOpen = true; categoriaIdSelected = ''; categoriaIconSelected = ''"
                                           placeholder="Seleccione o escriba..."
                                           class="form-input-bx" 
                                           :style="categoriaIconSelected ? 'padding-left: 2.25rem; width: 100%; flex: 1;' : 'padding-left: 0.75rem; width: 100%; flex: 1;'"
                                           autocomplete="off">
                                    <button type="button" x-show="categoriaSearch" @click="clearCategoria()" class="combobox-clear-btn-bx" style="position: absolute; right: 0.75rem; background: none; border: none; font-size: 1.1rem; color: #94a3b8; cursor: pointer;">&times;</button>
                                </div>

                                {{-- Inputs ocultos --}}
                                <input type="hidden" name="categoria_id" :value="categoriaIdSelected">
                                <input type="hidden" name="nueva_categoria" :value="!categoriaIdSelected && categoriaSearch ? categoriaSearch : ''">

                                {{-- Lista desplegable --}}
                                <div x-show="categoriaOpen" 
                                     x-cloak 
                                     class="combobox-dropdown-bx">
                                    <template x-for="cat in filtradas" :key="cat.id">
                                        <div @click="select(cat)" class="combobox-item-bx">
                                            <span x-text="cat.icono" style="margin-right: 0.5rem;"></span>
                                            <span x-text="cat.nombre" style="font-weight: 500;"></span>
                                        </div>
                                    </template>
                                    <div x-show="categoriaSearch && filtradas.length === 0" 
                                         @click="categoriaOpen = false"
                                         class="combobox-item-bx" 
                                         style="color: var(--azul-btn); font-weight: 600;">
                                        <span>➕ Crear: "</span><span x-text="categoriaSearch"></span><span>"</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div x-show="tipo === 'ingreso_esporadico'" x-cloak style="margin-top:0.4rem; font-size:0.75rem; color:#10b981; font-weight:500;">
                        💡 Este ingreso se sumará automáticamente bajo la fuente "OTRAS ENTRADAS" de este mes.
                    </div>

                    {{-- Cuenta / Bolsillo --}}
                    @if(isset($cuentas) && $cuentas->isNotEmpty())
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx" x-text="tipo === 'ingreso_esporadico' ? '¿A qué cuenta entró el dinero?' : '¿De qué cuenta salió el dinero?'"></label>
                        <select name="cuenta_id" class="form-select-bx" required>
                            @foreach($cuentas as $cta)
                                <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    {{-- Descripción --}}
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Descripción</label>
                        <input type="text" name="descripcion" placeholder="Ej: almuerzo, venta de equipo, etc." class="form-input-bx">
                    </div>

                    {{-- Soporte de Pago --}}
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Soporte de Pago (Opcional)</label>
                        <input type="file" name="soporte" x-ref="soporteInputCrear" accept="image/*" style="display: none;" @change="handleFileChange($event)">
                        
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.25rem;">
                            <button type="button" @click="pegarSoporte()" class="btn-glass-bx" style="display: flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.75rem; font-size: 0.75rem; background: #e2e8f0;">
                                📋 Pegar Soporte
                            </button>
                            <button type="button" @click="$refs.soporteInputCrear.click()" class="btn-glass-bx" style="display: flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.75rem; font-size: 0.75rem; background: #e2e8f0;">
                                📸 Tomar Foto / Subir
                            </button>
                        </div>

                        {{-- Previsualización de Soporte --}}
                        <div x-show="soportePreview" x-cloak style="margin-top: 0.75rem; position: relative; display: inline-block;">
                            <img :src="soportePreview" style="max-height: 100px; border-radius: 8px; border: 1px solid #cbd5e1;">
                            <button type="button" @click="limpiarSoporte()" class="btn-icon-bx" style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">
                                &times;
                            </button>
                            <div style="font-size: 0.65rem; color: #64748b; margin-top: 0.25rem;" x-text="soporteName"></div>
                        </div>
                    </div>

                    {{-- Patrimonio --}}
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
                    <button type="submit" :disabled="cargando" class="btn-accion-premium" :style="tipo === 'ingreso_esporadico' ? 'background:linear-gradient(135deg, #10b981, #047857);' : (tipo === 'gasto' ? 'background:linear-gradient(135deg, #ef4444, #b91c1c);' : 'background:linear-gradient(135deg, #4f46e5, #4338ca);')" style="color: white; display: flex; align-items: center; gap: 0.5rem;">
                        <span x-show="!cargando">Registrar Transacción</span>
                        <span x-show="cargando" x-cloak><i class="fas fa-spinner fa-spin"></i> Registrando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Editar --}}
    <div x-show="openEditar" class="modal-overlay-bx" @click.self="openEditar = false" x-cloak>
        <div class="modal-box-bx" style="max-width: 480px;">
            <div class="modal-head-bx" style="background:linear-gradient(135deg, #3b82f6, #1d4ed8);" :style="selectedGasto.tipo_movimiento === 'ingreso_esporadico' ? 'background:linear-gradient(135deg, #10b981, #047857);' : (selectedGasto.tipo_movimiento === 'gasto' ? 'background:linear-gradient(135deg, #ef4444, #b91c1c);' : 'background:linear-gradient(135deg, #4f46e5, #4338ca);')">
                <h3 style="color:#fff;" x-text="selectedGasto.tipo_movimiento === 'ingreso_esporadico' ? '✏️ Editar Entrada' : '✏️ Editar Transacción'">✏️ Editar Transacción</h3>
                <button @click="openEditar = false" class="modal-close-bx">&times;</button>
            </div>
            <form :action="'{{ route('finanzas.gastos.index') }}/' + selectedGasto.id" method="POST" enctype="multipart/form-data"
                  x-data="{
                      categoriaOpen: false,
                      categoriaSearch: '',
                      categoriaIdSelected: '',
                      categoriaIconSelected: '',
                      categorias: {{ json_encode($categorias->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'icono' => $c->icono, 'color' => $c->color]) ?? []) }},
                      soportePreview: null,
                      soporteName: '',
                      gastoSoporteActual: null,
                      eliminarSoporteAnterior: false,
                      cargando: false,
                      montoLimpio: '',
                      montoFormateado: '',
                      formatearMonto() {
                          let valor = this.montoFormateado.replace(/\D/g, '');
                          this.montoLimpio = valor;
                          if (valor) {
                              this.montoFormateado = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(valor);
                          } else {
                              this.montoFormateado = '';
                          }
                      },
                      
                      init() {
                          this.$watch('selectedGasto', (value) => {
                              if (value && value.id) {
                                  this.categoriaIdSelected = value.categoria_id || '';
                                  this.eliminarSoporteAnterior = false;
                                  this.soportePreview = null;
                                  this.soporteName = '';
                                  
                                  let found = this.categorias.find(c => c.id == this.categoriaIdSelected);
                                  if (found) {
                                      this.categoriaIconSelected = found.icono;
                                      this.categoriaSearch = found.nombre;
                                  } else {
                                      this.categoriaIconSelected = '';
                                      this.categoriaSearch = '';
                                  }
                                  
                                  this.gastoSoporteActual = value.soporte_path || null;
                                  
                                  // Formatear monto inicial
                                  this.montoLimpio = value.monto ? Math.round(value.monto).toString() : '';
                                  this.montoFormateado = this.montoLimpio ? new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(this.montoLimpio) : '';
                              }
                          });
                      },
                      get filtradas() {
                          if (!this.categoriaSearch) return this.categorias;
                          return this.categorias.filter(c => c.nombre.toLowerCase().includes(this.categoriaSearch.toLowerCase()));
                      },
                      select(cat) {
                          this.categoriaIdSelected = cat.id;
                          this.categoriaIconSelected = cat.icono;
                          this.categoriaSearch = cat.nombre;
                          this.categoriaOpen = false;
                      },
                      clearCategoria() {
                          this.categoriaIdSelected = '';
                          this.categoriaIconSelected = '';
                          this.categoriaSearch = '';
                      },
                      async pegarSoporte() {
                          try {
                              const clipboardItems = await navigator.clipboard.read();
                              for (const item of clipboardItems) {
                                  for (const type of item.types) {
                                      if (type.startsWith('image/')) {
                                          const blob = await item.getType(type);
                                          const file = new File([blob], 'soporte_editar_' + Date.now() + '.png', { type: type });
                                          const dt = new DataTransfer();
                                          dt.items.add(file);
                                          this.$refs.soporteInputEditar.files = dt.files;
                                          this.soporteName = file.name;
                                          this.soportePreview = URL.createObjectURL(blob);
                                          this.eliminarSoporteAnterior = true;
                                          return;
                                      }
                                  }
                              }
                              alert('No se encontró ninguna imagen en el portapapeles. Copia una imagen primero.');
                          } catch (err) {
                              alert('No se pudo acceder al portapapeles. Intenta subir el archivo seleccionándolo.');
                          }
                      },
                      handleFileChange(e) {
                          const file = e.target.files[0];
                          if (file) {
                              this.soporteName = file.name;
                              this.soportePreview = URL.createObjectURL(file);
                              this.eliminarSoporteAnterior = true;
                          }
                      },
                      limpiarSoporte() {
                          this.$refs.soporteInputEditar.value = '';
                          this.soporteName = '';
                          this.soportePreview = null;
                          if (this.gastoSoporteActual) {
                              this.eliminarSoporteAnterior = true;
                          }
                      },
                      handlePaste(e) {
                          if (!openEditar) return;
                          const items = (e.clipboardData || e.originalEvent.clipboardData).items;
                          for (const item of items) {
                              if (item.kind === 'file' && item.type.startsWith('image/')) {
                                  const blob = item.getAsFile();
                                  const file = new File([blob], 'soporte_editar_' + Date.now() + '.png', { type: item.type });
                                  const dt = new DataTransfer();
                                  dt.items.add(file);
                                  this.$refs.soporteInputEditar.files = dt.files;
                                  this.soporteName = file.name;
                                  this.soportePreview = URL.createObjectURL(blob);
                                  this.eliminarSoporteAnterior = true;
                              }
                          }
                      }
                  }"
                  @paste.window="handlePaste($event)"
                  @submit="cargando = true"
            >
                @csrf
                @method('PUT')
                <div class="modal-body-bx">
                    
                    {{-- Fecha y Monto en la misma fila --}}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group-bx">
                            <label class="form-label-bx">Fecha</label>
                            <input type="date" name="fecha" x-model="selectedGasto.fecha" class="form-input-bx" style="font-size: 1.15rem; font-weight: 700; color: #1e293b;" required>
                        </div>
                        <div class="form-group-bx">
                            <label class="form-label-bx">Monto ($ COP)</label>
                            <input type="text" 
                                   x-model="montoFormateado" 
                                   @input="formatearMonto()" 
                                   placeholder="Ej: 50.000" 
                                   class="form-input-bx" 
                                   style="font-size: 1.15rem; font-weight: 700; color: #1e293b;"
                                   autocomplete="off"
                                   required>
                            <input type="hidden" name="monto" :value="montoLimpio">
                        </div>
                    </div>

                    {{-- Tipo de Movimiento y Categoría en la misma fila --}}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                        {{-- Tipo de Movimiento --}}
                        <div class="form-group-bx">
                            <label class="form-label-bx">Tipo de Movimiento</label>
                            <select name="tipo_movimiento" x-model="selectedGasto.tipo_movimiento" class="form-select-bx" required style="height: 100%;">
                                <option value="gasto">Gasto Habitual</option>
                                <option value="ingreso_esporadico">Entrada</option>
                                <option value="prestamo">Desembolso Préstamo</option>
                                <option value="inversion">Inversión (Cripto/USDT)</option>
                            </select>
                        </div>

                        {{-- Categoría (Combobox buscable y editable) --}}
                        <div class="form-group-bx" style="position: relative;">
                            <label class="form-label-bx">Categoría</label>
                            <div class="combobox-container-bx" @click.away="categoriaOpen = false" style="width: 100%;">
                                <div class="combobox-input-wrapper-bx" style="position: relative; display: flex; align-items: center; width: 100%;">
                                    <span x-show="categoriaIconSelected" class="combobox-icon-bx" x-text="categoriaIconSelected" style="position: absolute; left: 0.75rem; font-size: 0.95rem; z-index: 5;"></span>
                                    <input type="text" 
                                           x-model="categoriaSearch" 
                                           @focus="categoriaOpen = true"
                                           @input="categoriaOpen = true; categoriaIdSelected = ''; categoriaIconSelected = ''"
                                           placeholder="Seleccione o escriba..."
                                           class="form-input-bx" 
                                           :style="categoriaIconSelected ? 'padding-left: 2.25rem; width: 100%; flex: 1;' : 'padding-left: 0.75rem; width: 100%; flex: 1;'"
                                           autocomplete="off">
                                    <button type="button" x-show="categoriaSearch" @click="clearCategoria()" class="combobox-clear-btn-bx" style="position: absolute; right: 0.75rem; background: none; border: none; font-size: 1.1rem; color: #94a3b8; cursor: pointer;">&times;</button>
                                </div>

                                {{-- Inputs ocultos --}}
                                <input type="hidden" name="categoria_id" :value="categoriaIdSelected">
                                <input type="hidden" name="nueva_categoria" :value="!categoriaIdSelected && categoriaSearch ? categoriaSearch : ''">

                                {{-- Lista desplegable --}}
                                <div x-show="categoriaOpen" 
                                     x-cloak 
                                     class="combobox-dropdown-bx">
                                    <template x-for="cat in filtradas" :key="cat.id">
                                        <div @click="select(cat)" class="combobox-item-bx">
                                            <span x-text="cat.icono" style="margin-right: 0.5rem;"></span>
                                            <span x-text="cat.nombre" style="font-weight: 500;"></span>
                                        </div>
                                    </template>
                                    <div x-show="categoriaSearch && filtradas.length === 0" 
                                         @click="categoriaOpen = false"
                                         class="combobox-item-bx" 
                                         style="color: var(--azul-btn); font-weight: 600;">
                                        <span>➕ Crear: "</span><span x-text="categoriaSearch"></span><span>"</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Cuenta / Bolsillo --}}
                    @if(isset($cuentas) && $cuentas->isNotEmpty())
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Cuenta / Bolsillo</label>
                        <select name="cuenta_id" x-model="selectedGasto.cuenta_id" class="form-select-bx">
                            @foreach($cuentas as $cta)
                                <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    {{-- Descripción --}}
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Descripción</label>
                        <input type="text" name="descripcion" x-model="selectedGasto.descripcion" class="form-input-bx">
                    </div>

                    {{-- Soporte de Pago --}}
                    <div class="form-group-bx" style="margin-top:1rem;">
                        <label class="form-label-bx">Soporte de Pago</label>
                        <input type="file" name="soporte" x-ref="soporteInputEditar" accept="image/*" style="display: none;" @change="handleFileChange($event)">
                        
                        {{-- Visualización de Soporte Anterior --}}
                        <div x-show="gastoSoporteActual && !eliminarSoporteAnterior" x-cloak style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <a :href="'{{ route('finanzas.gastos.index') }}/' + selectedGasto.id + '/soporte'" target="_blank" class="badge-soporte-link" style="margin-left: 0;">
                                📎 Descargar Soporte Actual
                            </a>
                            <button type="button" @click="eliminarSoporteAnterior = true" class="btn-icon-bx" style="color: #ef4444; font-size: 0.75rem; padding: 0.1rem 0.3rem;" title="Eliminar soporte actual">
                                ❌ Eliminar actual
                            </button>
                        </div>
                        <input type="hidden" name="eliminar_soporte" :value="eliminarSoporteAnterior ? 1 : 0">

                        <div style="display: flex; gap: 0.5rem; margin-top: 0.25rem;">
                            <button type="button" @click="pegarSoporte()" class="btn-glass-bx" style="display: flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.75rem; font-size: 0.75rem; background: #e2e8f0;">
                                📋 Pegar Nuevo Soporte
                            </button>
                            <button type="button" @click="$refs.soporteInputEditar.click()" class="btn-glass-bx" style="display: flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.75rem; font-size: 0.75rem; background: #e2e8f0;">
                                📸 Tomar Foto / Subir Nuevo
                            </button>
                        </div>

                        {{-- Previsualización de Nuevo Soporte --}}
                        <div x-show="soportePreview" x-cloak style="margin-top: 0.75rem; position: relative; display: inline-block;">
                            <img :src="soportePreview" style="max-height: 100px; border-radius: 8px; border: 1px solid #cbd5e1;">
                            <button type="button" @click="limpiarSoporte()" class="btn-icon-bx" style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">
                                &times;
                            </button>
                            <div style="font-size: 0.65rem; color: #64748b; margin-top: 0.25rem;" x-text="soporteName"></div>
                        </div>
                    </div>

                    {{-- Patrimonio --}}
                    <div x-data="{ esPatrimonio: false }" x-init="$watch('selectedGasto', val => esPatrimonio = val.es_patrimonio == 1)" x-show="selectedGasto.tipo_movimiento !== 'ingreso_esporadico'" style="margin-top:1rem;">
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
                    <button type="submit" :disabled="cargando" class="btn-accion-premium" :style="selectedGasto.tipo_movimiento === 'ingreso_esporadico' ? 'background:linear-gradient(135deg, #10b981, #047857);' : (selectedGasto.tipo_movimiento === 'gasto' ? 'background:linear-gradient(135deg, #ef4444, #b91c1c);' : 'background:linear-gradient(135deg, #4f46e5, #4338ca);')" style="color: white; display: flex; align-items: center; gap: 0.5rem;">
                        <span x-show="!cargando">Guardar Cambios</span>
                        <span x-show="cargando" x-cloak><i class="fas fa-spinner fa-spin"></i> Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
/* Responsive Desktop vs Móvil */
@media (max-width: 768px) {
    .desktop-only { display: none !important; }
    .mobile-only { display: block !important; }
}
@media (min-width: 769px) {
    .desktop-only { display: block !important; }
    .mobile-only { display: none !important; }
}

.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

/* Top Bar & Breadcrumb */

/* Header Section */



.badge-patrimonio-link { display: inline-block; background: #f3e8ff; color: #6b21a8; font-size: 0.68rem; font-weight: 600; text-decoration: none; padding: 0.1rem 0.4rem; border-radius: 4px; border: 1px solid #d8b4fe; margin-left: 5px; }

/* Tabla */

.tipo-tag-bx { display: inline-block; font-size: 0.62rem; font-weight: 700; text-transform: uppercase; padding: 0.05rem 0.3rem; border-radius: 4px; }
.tipo-tag-bx.gasto { background: #fee2e2; color: #991b1b; }
.tipo-tag-bx.prestamo { background: #fef3c7; color: #92400e; }
.tipo-tag-bx.inversion { background: #e0f2fe; color: #0369a1; }

.badge-ok-bx { background: rgba(34,197,94,0.12); color: #166534; border: 1px solid rgba(34,197,94,0.3); border-radius: 999px; padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600; }

/* Modales */


/* Combobox */
.combobox-container-bx { position: relative; width: 100%; }
.combobox-dropdown-bx {
    position: absolute;
    z-index: 1000;
    width: 100%;
    max-height: 200px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    margin-top: 0.25rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.combobox-item-bx {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    font-size: 0.82rem;
    color: #334155;
    transition: background 0.1s;
}
.combobox-item-bx:hover {
    background: #f1f5f9;
}

/* Botón Premium */
.btn-accion-premium {
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.1s, opacity 0.15s;
}
.btn-accion-premium:hover:not(:disabled) {
    transform: translateY(-1px);
    opacity: 0.95;
}
.btn-accion-premium:active:not(:disabled) {
    transform: translateY(0);
}
.btn-accion-premium:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.badge-soporte-link {
    display: inline-block;
    background: #e0f2fe;
    color: #0369a1;
    font-size: 0.68rem;
    font-weight: 600;
    text-decoration: none;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    border: 1px solid #bae6fd;
    margin-left: 5px;
    transition: background 0.15s;
}
.badge-soporte-link:hover {
    background: #bae6fd;
}
</style>
@endpush

@push('styles')
@include('finanzas.partials._responsive_movil')
@endpush
