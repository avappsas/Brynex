@extends('layouts.app')
@section('modulo', 'Gestión de Módulos')
@section('contenido')

<div style="max-width:1100px;margin:0 auto;">

    {{-- Botón de regreso --}}
    <div style="margin-bottom:1rem;">
        <a href="{{ route('brynex.consumo.index') }}" style="color:#64748b;font-size:0.8rem;text-decoration:none;font-weight:700;">
            ← Volver al Dashboard
        </a>
    </div>

    {{-- Cabecera --}}
    <div style="background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;margin-bottom:1.5rem;">
        <h1 style="font-size:1.4rem;font-weight:800;color:#0d2550;margin:0;">⚙️ Configuración de Módulos y Tarifas</h1>
        <p style="color:#64748b;font-size:0.82rem;margin:0.2rem 0 0 0;">
            Aliado: <strong>{{ $aliado->nombre }}</strong> (NIT: {{ $aliado->nit }})
        </p>
    </div>

    {{-- Formulario General --}}
    <form action="{{ route('brynex.consumo.modulos.update', $aliado->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div style="display:flex;flex-direction:column;gap:1.5rem;margin-bottom:2rem;">
            
            @foreach($modulos as $mod)
                @php
                    $activo = isset($modulosContratados[$mod->id]) && $modulosContratados[$mod->id] == 1;
                    $personalizados = $tramos[$mod->id]['personalizados'];
                    $globales = $tramos[$mod->id]['globales'];
                @endphp

                <div style="background:#fff;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;overflow:hidden;">
                    
                    {{-- Cabecera del Módulo --}}
                    <div style="padding:1.25rem 1.5rem;background:#f8fafc;border-bottom:1px solid #f1f5f9;display:flex;justify-content:between;align-items:center;flex-wrap:wrap;gap:1rem;">
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <span style="font-size:1.3rem;">
                                @if($mod->codigo === 'administracion') 👥
                                @elseif($mod->codigo === 'afiliaciones') 🤝
                                @elseif($mod->codigo === 'wa_plantillas') 💬
                                @elseif($mod->codigo === 'wa_conversaciones') 💬
                                @else ⚙️ @endif
                            </span>
                            <div>
                                <h3 style="font-size:0.9rem;font-weight:800;color:#0d2550;margin:0;">{{ $mod->nombre }}</h3>
                                <p style="color:#64748b;font-size:0.75rem;margin:0.15rem 0 0 0;">{{ $mod->descripcion }}</p>
                            </div>
                        </div>

                        {{-- Switch de Activo --}}
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <label style="font-size:0.78rem;font-weight:700;color:#475569;cursor:pointer;" for="mod_{{ $mod->id }}">Contratado:</label>
                            <input type="checkbox" name="modulos[{{ $mod->id }}]" id="mod_{{ $mod->id }}" value="1" {{ $activo ? 'checked' : '' }} style="width:18px;height:18px;cursor:pointer;" onchange="toggleTramos({{ $mod->id }})">
                        </div>
                    </div>

                    {{-- Cuerpo del Módulo (Tarifas y Tramos) --}}
                    <div id="cuerpo_mod_{{ $mod->id }}" style="padding:1.5rem;display:{{ $activo ? 'block' : 'none' }};">
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;@media(max-width:768px){grid-template-columns:1fr;}">
                            
                            {{-- Tramos Personalizados --}}
                            <div>
                                <h4 style="font-size:0.8rem;font-weight:700;color:#334155;margin:0 0 0.75rem 0;">Tramos de Tarifa Personalizados para este Aliado</h4>
                                
                                <div id="tramos_container_{{ $mod->id }}" style="display:flex;flex-direction:column;gap:0.5rem;">
                                    @php $index = 0; @endphp
                                    @forelse($personalizados as $tramo)
                                        <div style="display:flex;gap:0.35rem;align-items:center;">
                                            <input type="number" name="tramos[{{ $mod->id }}][{{ $index }}][desde]" placeholder="Desde" value="{{ $tramo->desde_cant }}" required style="width:70px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:center;">
                                            <span style="font-size:0.75rem;color:#94a3b8;">-</span>
                                            <input type="number" name="tramos[{{ $mod->id }}][{{ $index }}][hasta]" placeholder="Hasta" value="{{ $tramo->hasta_cant }}" style="width:70px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:center;">
                                            <span style="font-size:0.75rem;color:#94a3b8;">Tarifa:</span>
                                            <input type="number" name="tramos[{{ $mod->id }}][{{ $index }}][tarifa]" placeholder="$. Unitario" value="{{ $tramo->tarifa_unidad }}" step="any" style="width:90px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:right;">
                                            <span style="font-size:0.75rem;color:#94a3b8;">Mín:</span>
                                            <input type="number" name="tramos[{{ $mod->id }}][{{ $index }}][minima]" placeholder="$. Mínimo" value="{{ $tramo->tarifa_minima }}" step="any" style="width:100px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:right;">
                                            
                                            <button type="button" onclick="this.parentNode.remove()" style="background:#fee2e2;color:#ef4444;border:none;width:26px;height:26px;border-radius:6px;font-weight:bold;cursor:pointer;font-size:0.8rem;display:flex;align-items:center;justify-content:center;">
                                                ✕
                                            </button>
                                        </div>
                                        @php $index++; @endphp
                                    @empty
                                        {{-- Fila vacía inicial --}}
                                        <div style="display:flex;gap:0.35rem;align-items:center;">
                                            <input type="number" name="tramos[{{ $mod->id }}][0][desde]" placeholder="Desde" value="0" required style="width:70px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:center;">
                                            <span style="font-size:0.75rem;color:#94a3b8;">-</span>
                                            <input type="number" name="tramos[{{ $mod->id }}][0][hasta]" placeholder="Hasta (vacio=inf)" style="width:70px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:center;">
                                            <span style="font-size:0.75rem;color:#94a3b8;">Tarifa:</span>
                                            <input type="number" name="tramos[{{ $mod->id }}][0][tarifa]" placeholder="$. Unitario" step="any" style="width:90px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:right;">
                                            <span style="font-size:0.75rem;color:#94a3b8;">Mín:</span>
                                            <input type="number" name="tramos[{{ $mod->id }}][0][minima]" placeholder="$. Mínimo" step="any" style="width:100px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:right;">
                                        </div>
                                    @endforelse
                                </div>

                                <button type="button" onclick="agregarTramo({{ $mod->id }})" style="background:#eff6ff;color:#1d4ed8;border:1px dashed #bfdbfe;font-weight:700;font-size:0.75rem;padding:0.4rem 0.8rem;border-radius:8px;cursor:pointer;margin-top:0.75rem;width:100%;transition:background 0.15s;" onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                                    ➕ Agregar Fila de Tramo Tarifa
                                </button>
                            </div>

                            {{-- Tramos Globales de Referencia --}}
                            <div style="background:#f8fafc;border-radius:10px;padding:1rem;border:1px solid #e2e8f0;">
                                <h4 style="font-size:0.75rem;font-weight:700;color:#64748b;margin:0 0 0.5rem 0;text-transform:uppercase;letter-spacing:0.04em;">Tramos Globales de Referencia</h4>
                                <p style="font-size:0.7rem;color:#94a3b8;margin:0 0 0.75rem 0;line-height:1.3;">Si no guardas ningún tramo personalizado, se aplicarán estas tarifas globales por defecto.</p>
                                
                                <div style="display:flex;flex-direction:column;gap:0.4rem;">
                                    @forelse($globales as $tg)
                                        <div style="font-size:0.75rem;color:#475569;display:flex;justify-content:between;border-bottom:1px dashed #e2e8f0;padding-bottom:0.2rem;">
                                            <span>
                                                Tramo {{ $tg->desde_cant }} – {{ $tg->hasta_cant ?? '∞' }}
                                            </span>
                                            <span style="font-weight:700;color:#1e3a8a;">
                                                @if($tg->tarifa_unidad > 0)
                                                    $ {{ number_format($tg->tarifa_unidad, 0, ',', '.') }} c/u
                                                @elseif($tg->tarifa_minima > 0)
                                                    Mínimo: $ {{ number_format($tg->tarifa_minima, 0, ',', '.') }}
                                                @else
                                                    Sin Costo
                                                @endif
                                            </span>
                                        </div>
                                    @empty
                                        <div style="font-size:0.72rem;color:#94a3b8;text-align:center;">No hay tarifas globales configuradas.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach

        </div>

        {{-- Botón Guardar Cambios --}}
        <div style="background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid #e2e8f0;text-align:right;">
            <button type="submit" style="background:#1e3a8a;color:#fff;border:none;padding:0.75rem 2rem;border-radius:8px;font-size:0.85rem;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(30,58,138,0.15);transition:background 0.15s;" onmouseover="this.style.background='#172554'" onmouseout="this.style.background='#1e3a8a'">
                💾 Guardar Configuración de Tarifas
            </button>
        </div>
    </form>

</div>

<script>
    // Muestra u oculta el contenedor de tramos según si el módulo está contratado
    function toggleTramos(moduloId) {
        const check = document.getElementById('mod_' + moduloId);
        const cuerpo = document.getElementById('cuerpo_mod_' + moduloId);
        if (check.checked) {
            cuerpo.style.display = 'block';
        } else {
            cuerpo.style.display = 'none';
        }
    }

    // Agrega dinámicamente una fila para ingresar un tramo de tarifa
    function agregarTramo(moduloId) {
        const container = document.getElementById('tramos_container_' + moduloId);
        const index = container.children.length;

        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.gap = '0.35rem';
        row.style.alignItems = 'center';

        row.innerHTML = `
            <input type="number" name="tramos[${moduloId}][${index}][desde]" placeholder="Desde" required style="width:70px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:center;">
            <span style="font-size:0.75rem;color:#94a3b8;">-</span>
            <input type="number" name="tramos[${moduloId}][${index}][hasta]" placeholder="Hasta" style="width:70px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:center;">
            <span style="font-size:0.75rem;color:#94a3b8;">Tarifa:</span>
            <input type="number" name="tramos[${moduloId}][${index}][tarifa]" placeholder="$. Unitario" step="any" style="width:90px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:right;">
            <span style="font-size:0.75rem;color:#94a3b8;">Mín:</span>
            <input type="number" name="tramos[${moduloId}][${index}][minima]" placeholder="$. Mínimo" step="any" style="width:100px;font-size:0.78rem;padding:0.35rem;border-radius:6px;border:1px solid #cbd5e1;outline:none;text-align:right;">
            <button type="button" onclick="this.parentNode.remove()" style="background:#fee2e2;color:#ef4444;border:none;width:26px;height:26px;border-radius:6px;font-weight:bold;cursor:pointer;font-size:0.8rem;display:flex;align-items:center;justify-content:center;">
                ✕
            </button>
        `;
        container.appendChild(row);
    }
</script>

@endsection
