@extends('layouts.app')
@section('modulo', 'Configuración')

@section('contenido')
<div style="max-width:1100px;margin:0 auto;">

{{-- Encabezado --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem;">
  <div>
    <a href="{{ route('admin.configuracion.hub') }}"
       style="font-size:.73rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.35rem">
        ← Volver a Configuración
    </a>
    <h1 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0;">⚙️ Configuración del Aliado</h1>
    <p style="font-size:0.78rem;color:#64748b;margin:0;">Parámetros de tarifas, administración y ARL</p>
  </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">✅ {{ session('success') }}</div>
@endif
@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.83rem;">
  <strong>Errores:</strong> @foreach($errors->all() as $e) · {{ $e }} @endforeach
</div>
@endif

<form method="POST" action="{{ route('admin.configuracion.store') }}" enctype="multipart/form-data">
@csrf

{{-- ══ SECCIÓN 1: Porcentajes Seguridad Social — Solo Superadmin BryNex ══ --}}
@if(Auth::user()->hasRole('superadmin') && Auth::user()->es_brynex)
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:1rem;">
  @php $esSuperadmin = true; @endphp
  <div style="font-size:0.72rem;font-weight:700;color:#0891b2;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.85rem;">
    🔒 Parámetros Globales BryNex
    <span style="background:#dcfce7;color:#166534;font-size:0.65rem;font-weight:600;padding:0.1rem 0.5rem;border-radius:999px;text-transform:none;margin-left:0.5rem;">✏️ Editables como Superadmin BryNex</span>
  </div>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0.75rem;">
    @foreach([
      'salario_minimo'                => ['label'=>'Salario Mínimo',   'prefix'=>'$',  'suffix'=>'',  'step'=>'1',    'decimals'=>0],
      'pct_salud_dependiente'         => ['label'=>'EPS Dependiente',  'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_salud_independiente'       => ['label'=>'EPS Independiente','prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_pension_dependiente'       => ['label'=>'Pensión Dep.',     'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_pension_independiente'     => ['label'=>'Pensión Indep.',   'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_caja_dependiente'          => ['label'=>'Caja Dep.',        'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_caja_independiente_alto'   => ['label'=>'Caja Indep. Alt.', 'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_caja_independiente_bajo'   => ['label'=>'Caja Indep. Baj.', 'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'pct_ibc_independiente_sugerido'=> ['label'=>'% IBC Sugerido',   'prefix'=>'',   'suffix'=>'%', 'step'=>'1',    'decimals'=>2],
      'porcentaje_iva'                => ['label'=>'IVA Admin',         'prefix'=>'',   'suffix'=>'%', 'step'=>'0.01', 'decimals'=>2],
      'tasa_mora_pila'                => ['label'=>'Tasa Mora PILA (Art.635 ET)', 'prefix'=>'', 'suffix'=>'% E.A.', 'step'=>'0.01', 'decimals'=>2],
    ] as $clave => $cfg)
    @php $valor = $configBrynex[$clave]->valor ?? null; @endphp
    <div style="background:#f8fafc;border-radius:8px;padding:0.65rem 0.75rem;border:1px solid #bfdbfe;overflow:hidden;">
      <div style="font-size:0.6rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:0.3rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $cfg['label'] }}</div>
      <div style="display:flex;align-items:center;gap:0.2rem;">
        @if($cfg['prefix']) <span style="color:#64748b;font-size:0.72rem;flex-shrink:0;">{{ $cfg['prefix'] }}</span> @endif
        <input type="number" step="{{ $cfg['step'] }}" min="0"
            name="brynex[{{ $clave }}]"
            value="{{ $valor !== null ? $valor : '' }}"
            style="width:100%;padding:0.28rem 0.35rem;border:1px solid #93c5fd;border-radius:5px;font-size:0.82rem;font-family:monospace;font-weight:700;background:#fff;min-width:0;color:#0f172a;box-sizing:border-box;">
        @if($cfg['suffix']) <span style="color:#64748b;font-size:0.72rem;flex-shrink:0;">{{ $cfg['suffix'] }}</span> @endif
      </div>
    </div>
    @endforeach
  </div>
</div>
@endif

{{-- ══ SECCIÓN 2: Tarifas ARL — Solo Superadmin BryNex ══ --}}
@if(Auth::user()->hasRole('superadmin') && Auth::user()->es_brynex)
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:1rem;">
  <div style="font-size:0.72rem;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.85rem;">
    🦺 Tarifas ARL por Nivel de Riesgo
    <span style="font-size:0.65rem;color:#94a3b8;text-transform:none;font-weight:400;margin-left:0.5rem;">Dejar vacío para usar valores globales del sistema</span>
  </div>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0.75rem;">
    @foreach($arlGlobal as $nivel => $global)
    @php $personalizada = $arlAliado[$nivel] ?? null; @endphp
    <div style="border:1px solid #e2e8f0;border-radius:10px;padding:0.85rem;background:{{ $personalizada ? '#f0fdf4' : '#fafafa' }};">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
        <span style="font-size:0.8rem;font-weight:700;color:#0f172a;">Nivel {{ $nivel }}</span>
        @if($personalizada)
        <span style="background:#dcfce7;color:#16a34a;font-size:0.63rem;font-weight:700;padding:0.1rem 0.4rem;border-radius:999px;">Personalizado</span>
        @else
        <span style="background:#f1f5f9;color:#64748b;font-size:0.63rem;padding:0.1rem 0.4rem;border-radius:999px;">Global</span>
        @endif
      </div>
      <div style="font-size:0.68rem;color:#64748b;margin-bottom:0.5rem;line-height:1.3;">
        {{ $global->descripcion ?? '' }}
        <br>Global: <strong>{{ $global->porcentaje }}%</strong>
      </div>
      <label style="display:block;font-size:0.65rem;font-weight:700;color:#475569;margin-bottom:0.2rem;">% PERSONALIZADO</label>
      <div style="display:flex;align-items:center;gap:0.35rem;">
        <input type="number" step="0.0001" min="0" max="100"
            name="arl[{{ $nivel }}][porcentaje]"
            value="{{ $personalizada ? $personalizada->porcentaje : '' }}"
            placeholder="{{ $global->porcentaje }}"
            style="flex:1;padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem;font-family:monospace;">
        <span style="color:#64748b;font-size:0.75rem;">%</span>
      </div>
      <input type="hidden" name="arl[{{ $nivel }}][descripcion]" value="{{ $personalizada?->descripcion ?? $global->descripcion }}">
    </div>
    @endforeach
  </div>
</div>
@endif

{{-- ══ SECCIÓN 2.5: Configuración de Mora al Cliente ══ --}}
<div style="background:#fffbeb;border-radius:12px;border:2px solid #fde68a;padding:1rem 1.25rem;margin-bottom:1rem;">
  <div style="font-size:0.72rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.35rem;">
    ⚠️ Configuración de Mora al Cliente
  </div>
  <div style="font-size:0.72rem;color:#78350f;margin-bottom:0.85rem;line-height:1.5;">
    Define cuándo y cuánto se cobra de mora a los clientes por pago tardío de su factura SS.
    La <strong>tasa de cálculo</strong> (Art. 635 ET) la configura BryNex globalmente.
    Aquí configuras el <strong>día de inicio</strong> y los <strong>montos mínimos</strong> de tu operación.
  </div>
  @php
    $globalMoraCfg = $configs['global'] ?? null;
    $globalCfg = $configs['global'] ?? null;
  @endphp
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;">
    {{-- Campo 1: Día hábil de inicio --}}
    <div style="background:#fff;border-radius:9px;padding:0.85rem;border:1.5px solid #fde68a;">
      <div style="font-size:0.62rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.55rem;">🗓 Día Hábil de Inicio</div>
      <div style="font-size:0.72rem;color:#78350f;margin-bottom:0.5rem;line-height:1.4;">
        A partir de qué día hábil del mes se cobra mora a <strong>TODOS</strong> los clientes.<br>
        <em>Si lo dejas vacío, cada cliente usa el día según los 2 últimos dígitos de su RS (Decreto 1990/2016).</em>
      </div>
      <div style="display:flex;align-items:center;gap:0.35rem;">
        <input type="number" step="1" min="2" max="16"
            name="configs[global][mora_dia_habil_inicio]"
            value="{{ $globalMoraCfg?->mora_dia_habil_inicio ?? '' }}"
            placeholder="Ej: 5 (día hábil 5)"
            style="flex:1;padding:0.45rem 0.6rem;border:2px solid #fde68a;border-radius:7px;font-size:0.9rem;font-family:monospace;font-weight:700;color:#92400e;background:#fffbeb;">
        <span style="color:#92400e;font-size:0.75rem;white-space:nowrap;">d. hábil</span>
      </div>
    </div>
    {{-- Campo 2: Mora mínima --}}
    <div style="background:#fff;border-radius:9px;padding:0.85rem;border:1.5px solid #fde68a;">
      <div style="font-size:0.62rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.55rem;">💰 Mora Mínima (Tramo 1)</div>
      <div style="font-size:0.72rem;color:#78350f;margin-bottom:0.5rem;line-height:1.4;">
        Si la mora real calculada es <strong>menor</strong> a este valor, se cobra este monto fijo.
        <br><em>Ejemplo: mora real = $1.200 → se cobra $2.000</em>
      </div>
      <div style="display:flex;align-items:center;gap:0.25rem;">
        <span style="color:#92400e;font-size:0.82rem;">$</span>
        <input type="number" step="500" min="0"
            name="configs[global][mora_minimo]"
            value="{{ $globalMoraCfg?->mora_minimo ?? 2000 }}"
            style="flex:1;padding:0.45rem 0.6rem;border:2px solid #fde68a;border-radius:7px;font-size:0.9rem;font-family:monospace;font-weight:700;color:#92400e;background:#fffbeb;text-align:right;">
      </div>
    </div>
    {{-- Campo 3: Mora segundo tramo --}}
    <div style="background:#fff;border-radius:9px;padding:0.85rem;border:1.5px solid #fde68a;">
      <div style="font-size:0.62rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.55rem;">💰 Mora Segundo Tramo</div>
      <div style="font-size:0.72rem;color:#78350f;margin-bottom:0.5rem;line-height:1.4;">
        Si mora real &ge; mínima pero es <strong>menor</strong> a este valor, se cobra este monto fijo.
        <br><em>Si mora real &ge; este valor, se cobra la mora real.</em>
      </div>
      <div style="display:flex;align-items:center;gap:0.25rem;">
        <span style="color:#92400e;font-size:0.82rem;">$</span>
        <input type="number" step="500" min="0"
            name="configs[global][mora_segundo]"
            value="{{ $globalMoraCfg?->mora_segundo ?? 5000 }}"
            style="flex:1;padding:0.45rem 0.6rem;border:2px solid #fde68a;border-radius:7px;font-size:0.9rem;font-family:monospace;font-weight:700;color:#92400e;background:#fffbeb;text-align:right;">
      </div>
    </div>
  </div>
  <div style="font-size:0.7rem;color:#92400e;margin-top:0.65rem;background:#fef3c7;border-radius:6px;padding:0.4rem 0.75rem;">
    💡 <strong>Lógica de tramos:</strong>
    mora_real &lt; mínima (${{ number_format($globalMoraCfg?->mora_minimo ?? 2000, 0, ',', '.') }}) → cobrar mínima ·
    mora_real &lt; segundo (${{ number_format($globalMoraCfg?->mora_segundo ?? 5000, 0, ',', '.') }}) → cobrar segundo ·
    mora_real &ge; segundo → cobrar mora real
  </div>
</div>

{{-- ══ SECCIÓN 2.6: Parámetros Especiales ══ --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:1rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.85rem;">
    <div style="font-size:0.72rem;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:0.06em;">
      ⚙️ Parámetros Especiales
      <span style="font-size:0.65rem;color:#94a3b8;text-transform:none;font-weight:400;margin-left:0.5rem;">Configuración por aliado</span>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;">

    {{-- Campo: Día de ingreso Ingreso-Retiro --}}
    <div style="background:#f0f9ff;border-radius:9px;padding:0.85rem;border:1.5px solid #bae6fd;">
      <div style="font-size:0.62rem;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.55rem;">📅 Día de Ingreso (Plan Ingreso-Retiro)</div>
      <div style="font-size:0.72rem;color:#0c4a6e;margin-bottom:0.5rem;line-height:1.4;">
        Día del mes en que se afiliará el contrato al duplicar en el flujo <strong>Ingreso-Retiro</strong>.
        <br><em>Valor por defecto: 26. Rango: 1 al 28.</em>
      </div>
      <div style="display:flex;align-items:center;gap:0.35rem;">
        <input type="number" step="1" min="1" max="28"
            name="configs[global][dia_ingreso_ir]"
            value="{{ $globalMoraCfg?->dia_ingreso_ir ?? 26 }}"
            placeholder="Ej: 26"
            style="flex:1;padding:0.45rem 0.6rem;border:2px solid #bae6fd;border-radius:7px;font-size:0.9rem;font-family:monospace;font-weight:700;color:#0369a1;background:#fff;text-align:center;">
        <span style="color:#0369a1;font-size:0.75rem;white-space:nowrap;">día del mes</span>
      </div>
    </div>

    {{-- Card combinado: Seguro Único + Logo Aseguradora --}}
    <div x-data="{ preview: '{{ $globalCfg?->seguro_logo ? Storage::url($globalCfg->seguro_logo) : '' }}' }"
         style="background:#fdf4ff;border-radius:9px;padding:0.85rem;border:1.5px solid #e9d5ff;grid-column:span 2;">
      <div style="font-size:0.62rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.65rem;">
        🛡️ Seguro — Valor y Aseguradora
        <span style="font-size:0.63rem;color:#9f67f5;text-transform:none;font-weight:400;margin-left:0.4rem;">Aplica igual a todos los planes</span>
      </div>
      <div style="display:flex;gap:1rem;align-items:flex-start;">

        {{-- Valor del seguro --}}
        <div style="flex:0 0 auto;min-width:180px;">
          <label style="display:block;font-size:0.62rem;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.35rem;">Valor</label>
          <div style="display:flex;align-items:center;gap:0.35rem;">
            <span style="color:#7c3aed;font-size:0.95rem;font-weight:700;">$</span>
            <input type="text"
                name="configs[global][seguro_valor]"
                value="{{ $globalCfg ? number_format($globalCfg->seguro_valor, 0, ',', '.') : '0' }}"
                class="input-miles"
                style="flex:1;padding:0.45rem 0.6rem;border:2px solid #e9d5ff;border-radius:7px;font-size:0.95rem;font-family:monospace;font-weight:700;color:#7c3aed;background:#fff;text-align:right;"
                onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e9d5ff'">
          </div>
          <div style="font-size:0.65rem;color:#9f67f5;margin-top:0.35rem;">Déjalo en 0 si no aplica seguro.</div>
        </div>

        {{-- Separador vertical --}}
        <div style="width:1px;background:#e9d5ff;align-self:stretch;"></div>

        {{-- Logo de la aseguradora --}}
        <div style="flex:1;">
          <label style="display:block;font-size:0.62rem;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.35rem;">🏢 Logo de la Aseguradora</label>
          <div style="display:flex;align-items:center;gap:1rem;">
            {{-- Preview --}}
            <div style="flex:0 0 auto;">
              <template x-if="preview">
                <img :src="preview" alt="Logo aseguradora"
                     style="height:52px;max-width:140px;object-fit:contain;border-radius:7px;border:1px solid #e9d5ff;background:#fff;padding:5px;">
              </template>
              <template x-if="!preview">
                <div style="height:52px;width:120px;display:flex;align-items:center;justify-content:center;background:#f5f0ff;border-radius:7px;border:1.5px dashed #c4b5fd;color:#a78bfa;font-size:0.7rem;text-align:center;padding:0.3rem;">
                  Sin logo de aseguradora
                </div>
              </template>
            </div>
            {{-- Input file --}}
            <div style="flex:1;">
              <input type="file" name="seguro_logo" accept="image/png,image/jpeg,image/jpg,image/svg+xml"
                     @change="preview = URL.createObjectURL($event.target.files[0])"
                     style="width:100%;font-size:0.72rem;color:#6d28d9;padding:0.3rem 0;cursor:pointer;">
              <div style="font-size:0.65rem;color:#9f67f5;margin-top:0.3rem;">PNG, JPG o SVG. Máx 2 MB. Aparece en documentos y reportes del seguro.</div>
            </div>
          </div>
        </div>

      </div>
    </div>


  </div>
</div>

{{-- ══ SECCIÓN 3: Tarifas de Administración (Global y Por Plan) ══ --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:1rem;">
  <div style="font-size:0.72rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.85rem;">
    💰 Tarifas de Administración y Comisiones
    <span style="font-size:0.65rem;color:#94a3b8;text-transform:none;font-weight:400;margin-left:0.5rem;">Se precargan en el formulario de contrato</span>
  </div>

  {{-- Tabla con encabezados --}}
  <div style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:0.82rem;">
    <thead>
      <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
        <th style="padding:0.55rem 0.75rem;text-align:left;color:#475569;font-weight:600;font-size:0.73rem;">PLAN</th>
        <th style="padding:0.55rem 0.75rem;text-align:right;color:#475569;font-weight:600;font-size:0.73rem;">ADMON MENSUAL</th>
        <th style="padding:0.55rem 0.75rem;text-align:right;color:#475569;font-weight:600;font-size:0.73rem;">ADMON ASESOR</th>
        <th style="padding:0.55rem 0.75rem;text-align:right;color:#475569;font-weight:600;font-size:0.73rem;">COSTO AFILIACIÓN</th>
        <th style="padding:0.55rem 0.75rem;text-align:center;color:#b45309;font-weight:600;font-size:0.73rem;">🏷️ PROMOCIÓN</th>
        <th style="padding:0.55rem 0.75rem;text-align:right;color:#7c3aed;font-weight:600;font-size:0.73rem;" title="% del costo de afiliación que va a la empresa">% ADMON AFIL</th>
        <th style="padding:0.55rem 0.75rem;text-align:center;color:#475569;font-weight:600;font-size:0.73rem;">ENCARGADO DEFAULT</th>
        <th style="padding:0.55rem 0.75rem;text-align:center;color:#0369a1;font-weight:600;font-size:0.73rem;">🦺 AFILIACIÓN POR RIESGO ARL</th>
      </tr>
    </thead>
    <tbody x-data="{ arlAbierto: {} }">
      {{-- Fila Global (aplica a todos los planes si no hay específico) --}}
      @php $globalCfg = $configs['global'] ?? null; @endphp
      <tr style="border-bottom:1px solid #f1f5f9;background:#fffbeb;">
        <td style="padding:0.6rem 0.75rem;white-space:nowrap;">
          <span style="font-weight:700;color:#0f172a;font-size:0.82rem;">Todos los planes</span>
        </td>
        @foreach(['administracion','admon_asesor','costo_afiliacion'] as $campo)
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <span style="color:#64748b;font-size:0.75rem;">$</span>
            <input type="text"
                name="configs[global][{{ $campo }}]"
                value="{{ $globalCfg ? number_format($globalCfg->$campo, 0, ',', '.') : '0' }}"
                class="input-miles"
                style="width:110px;padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;">
          </div>
        </td>
        @endforeach
        {{-- Promoción --}}
        <td style="padding:0.5rem 0.75rem;text-align:center;">
          @include('admin.configuracion._boton_promocion', ['key' => 'global', 'cfg' => $globalCfg])
        </td>
        {{-- % Admon Afil --}}
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <input type="number" step="1" min="0" max="100"
                name="configs[global][dist_admon_pct]"
                value="{{ intval($globalCfg?->dist_admon_pct ?? 0) }}"
                style="width:70px;padding:0.38rem 0.5rem;border:1.5px solid #c4b5fd;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;background:#faf5ff;">
            <span style="color:#7c3aed;font-size:0.75rem;">%</span>
          </div>
        </td>
        {{-- % Retiro Afil (oculto, se conserva el valor) --}}
        <input type="hidden" name="configs[global][dist_retiro_pct]" value="{{ intval($globalCfg?->dist_retiro_pct ?? 0) }}">
        {{-- Seguro global ahora en card Parámetros Especiales (hidden aquí para no duplicar) --}}
        <td style="padding:0.5rem 0.75rem;text-align:center;">
          <select name="configs[global][encargado_default_id]"
              style="padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.78rem;background:#fff;max-width:140px;">
            <option value="">— Ninguno —</option>
            @foreach($usuarios as $usr)
            <option value="{{ $usr->id }}" {{ ($globalCfg?->encargado_default_id == $usr->id) ? 'selected' : '' }}>{{ $usr->nombre }}</option>
            @endforeach
          </select>
        </td>
        <td style="padding:0.5rem 0.75rem;text-align:center;color:#cbd5e1;font-size:0.75rem;">—</td>
      </tr>

      {{-- Filas por Plan --}}
      @foreach($planes as $plan)
      @php $cfg = $configs[$plan->id] ?? null; @endphp
      <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background=''">
        <td style="padding:0.55rem 0.75rem;white-space:nowrap;">
          <span style="font-weight:600;color:#0f172a;font-size:0.82rem;">{{ $plan->nombre }}</span>
        </td>
        @foreach(['administracion','admon_asesor','costo_afiliacion'] as $campo)
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <span style="color:#94a3b8;font-size:0.75rem;">$</span>
            <input type="text"
                name="configs[{{ $plan->id }}][{{ $campo }}]"
                value="{{ $cfg ? number_format($cfg->$campo, 0, ',', '.') : '' }}"
                placeholder="{{ $globalCfg ? number_format($globalCfg->$campo, 0, ',', '.') : '0' }}"
                class="input-miles"
                style="width:110px;padding:0.38rem 0.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;background:{{ $cfg ? '#fff' : '#f8fafc' }};"
                onfocus="this.style.borderColor='#3b82f6';this.style.background='#fff'" onblur="this.style.borderColor='#e2e8f0'">
          </div>
        </td>
        @endforeach
        {{-- Promoción --}}
        <td style="padding:0.5rem 0.75rem;text-align:center;">
          @include('admin.configuracion._boton_promocion', ['key' => $plan->id, 'cfg' => $cfg])
        </td>
        {{-- % Admon Afil por plan --}}
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <input type="number" step="1" min="0" max="100"
                name="configs[{{ $plan->id }}][dist_admon_pct]"
                value="{{ $cfg?->dist_admon_pct !== null ? intval($cfg->dist_admon_pct) : '' }}"
                placeholder="{{ intval($globalCfg?->dist_admon_pct ?? 0) }}"
                style="width:70px;padding:0.38rem 0.5rem;border:1.5px solid #e2e8f0;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;background:{{ $cfg?->dist_admon_pct ? '#faf5ff' : '#f8fafc' }};"
                onfocus="this.style.borderColor='#7c3aed';this.style.background='#faf5ff'" onblur="this.style.borderColor='#e2e8f0'">
            <span style="color:#7c3aed;font-size:0.75rem;">%</span>
          </div>
        </td>
        {{-- % Retiro Afil (oculto, conserva valor) --}}
        <input type="hidden" name="configs[{{ $plan->id }}][dist_retiro_pct]" value="{{ $cfg?->dist_retiro_pct !== null ? intval($cfg->dist_retiro_pct) : '' }}">
        {{-- Seguro por plan (oculto, se maneja globalmente) --}}
        <input type="hidden" name="configs[{{ $plan->id }}][seguro_valor]" value="{{ $cfg ? intval($cfg->seguro_valor) : '' }}">
        <td style="padding:0.5rem 0.75rem;text-align:center;">
          <select name="configs[{{ $plan->id }}][encargado_default_id]"
              style="padding:0.38rem 0.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:0.78rem;background:#fff;max-width:140px;">
            <option value="">— Global —</option>
            @foreach($usuarios as $usr)
            <option value="{{ $usr->id }}" {{ ($cfg?->encargado_default_id == $usr->id) ? 'selected' : '' }}>{{ $usr->nombre }}</option>
            @endforeach
          </select>
        </td>
        <td style="padding:0.5rem 0.75rem;text-align:center;">
          @if($plan->incluye_arl)
            <button type="button" class="btn-glass" style="font-size:0.7rem;padding:0.3rem 0.6rem;"
                @click="arlAbierto[{{ $plan->id }}] = !arlAbierto[{{ $plan->id }}]">
              <i class="fas fa-chevron-down" :class="arlAbierto[{{ $plan->id }}] ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
              Desplegar
            </button>
          @else
            <span style="color:#cbd5e1;font-size:0.75rem;">—</span>
          @endif
        </td>
      </tr>
      @if($plan->incluye_arl)
      <tr x-show="arlAbierto[{{ $plan->id }}]" x-cloak>
        <td colspan="8" style="padding:0.85rem 1.25rem;background:#f0f9ff;border-bottom:1px solid #e2e8f0;">
          <div style="font-size:0.68rem;color:#0369a1;margin-bottom:0.6rem;">
            Afiliación específica por modalidad y nivel de riesgo ARL para <strong>{{ $plan->nombre }}</strong>.
            Vacío = usa el "Costo Afiliación" general de este plan (arriba). La mensualidad siempre se calcula sola.
          </div>
          <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:0.78rem;">
            <thead>
              <tr style="background:#e0f2fe;">
                <th style="padding:0.4rem 0.6rem;text-align:left;color:#0369a1;font-size:0.68rem;">MODALIDAD</th>
                @for($n = 1; $n <= 5; $n++)
                <th style="padding:0.4rem 0.6rem;text-align:center;color:#0369a1;font-size:0.68rem;">RIESGO {{ $n }}</th>
                @endfor
              </tr>
            </thead>
            <tbody>
              @foreach($modalidadesArlPorPlan[$plan->id] ?? [] as $modalidad)
              <tr style="border-bottom:1px solid #dbeafe;">
                <td style="padding:0.4rem 0.6rem;white-space:nowrap;color:#0f172a;font-weight:600;">{{ $modalidad['nombre'] }}</td>
                @for($n = 1; $n <= 5; $n++)
                <td style="padding:0.35rem 0.5rem;text-align:center;">
                  <input type="text"
                      name="arl_afiliacion[{{ $plan->id }}][{{ $modalidad['id'] }}][{{ $n }}]"
                      value="{{ $modalidad['niveles'][$n] !== null ? number_format($modalidad['niveles'][$n], 0, ',', '.') : '' }}"
                      placeholder="{{ $cfg ? number_format($cfg->costo_afiliacion, 0, ',', '.') : '0' }}"
                      class="input-miles"
                      style="width:85px;padding:0.3rem 0.4rem;border:1px solid #bae6fd;border-radius:5px;font-size:0.76rem;font-family:monospace;text-align:right;background:#fff;">
                </td>
                @endfor
              </tr>
              @endforeach
            </tbody>
          </table>
          </div>
        </td>
      </tr>
      @endif
      @endforeach
    </tbody>
  </table>
  </div>
  <div style="font-size:0.72rem;color:#94a3b8;margin-top:0.65rem;">
    💡 Si un plan tiene celdas vacías, usa los valores de "Todos los planes". Encargado "Global" usa el configurado en la fila general.
  </div>
</div>

{{-- ══ Botón guardar ══ --}}
<div style="display:flex;justify-content:flex-end;gap:0.75rem;">
  <a href="{{ route('admin.contratos.index') }}"
     style="padding:0.6rem 1.25rem;border:1px solid #cbd5e1;border-radius:8px;color:#475569;text-decoration:none;font-size:0.85rem;">
    Cancelar
  </a>
  <button type="submit"
      style="padding:0.6rem 2rem;background:linear-gradient(135deg,#7c3aed,#5b21b6);border:none;border-radius:9px;color:#fff;font-size:0.88rem;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(124,58,237,0.35);">
    💾 Guardar Configuración
  </button>
</div>

</form>

{{-- ══ Modal: configurar promoción de afiliación ══ --}}
<div id="modalPromocion" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.5);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;padding:1.5rem 1.75rem;width:100%;max-width:380px;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
    <div style="font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:0.2rem;">🏷️ Promoción de afiliación</div>
    <div id="modalPromocionPlan" style="font-size:0.78rem;color:#64748b;margin-bottom:1rem;"></div>

    <label style="display:block;font-size:0.72rem;font-weight:600;color:#334155;margin-bottom:0.3rem;">Precio de afiliación promocional</label>
    <div style="display:flex;align-items:center;gap:0.25rem;margin-bottom:0.9rem;">
      <span style="color:#64748b;font-size:0.85rem;">$</span>
      <input type="text" id="modalPromoPrecio" placeholder="Ej: 80.000"
          style="flex:1;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;font-family:monospace;">
    </div>

    <label style="display:block;font-size:0.72rem;font-weight:600;color:#334155;margin-bottom:0.3rem;">Vence el</label>
    <input type="date" id="modalPromoVence"
        style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;margin-bottom:1.1rem;">

    <div style="font-size:0.7rem;color:#94a3b8;margin-bottom:1.2rem;">
      Mientras esté vigente, este precio reemplaza el costo de afiliación normal en la web, el asistente de WhatsApp y el piloto de marketing — al vencer, vuelve solo al precio normal.
    </div>

    <div style="display:flex;justify-content:space-between;gap:0.5rem;">
      <button type="button" onclick="quitarPromocion()" style="font-size:0.78rem;color:#dc2626;background:none;border:none;cursor:pointer;padding:0.4rem 0;">Quitar promoción</button>
      <div style="display:flex;gap:0.5rem;">
        <button type="button" onclick="cerrarModalPromocion()" style="padding:0.5rem 1rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#475569;font-size:0.8rem;cursor:pointer;">Cancelar</button>
        <button type="button" onclick="guardarModalPromocion()" style="padding:0.5rem 1.1rem;border:none;border-radius:8px;background:#7c3aed;color:#fff;font-size:0.8rem;font-weight:700;cursor:pointer;">Aplicar</button>
      </div>
    </div>
  </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.input-miles');
    
    function formatNumber(val) {
        val = val.toString().replace(/\D/g, '');
        return val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    
    inputs.forEach(input => {
        // Al escribir, formatear con puntos de miles
        input.addEventListener('input', function(e) {
            let cursorPosition = e.target.selectionStart;
            let originalLength = e.target.value.length;
            
            let valClean = e.target.value.replace(/\D/g, '');
            if (valClean === '') {
                e.target.value = '';
                return;
            }
            
            let formatted = formatNumber(valClean);
            e.target.value = formatted;
            
            // Ajustar posición del cursor tras formatear
            let newLength = formatted.length;
            let diff = newLength - originalLength;
            e.target.setSelectionRange(cursorPosition + diff, cursorPosition + diff);
        });
    });
    
    // Antes de enviar el formulario, limpiar los puntos para que a Laravel le lleguen números
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            inputs.forEach(input => {
                input.value = input.value.replace(/\D/g, '');
            });
        });
    }
});

// ── Modal de promoción de afiliación ──────────────────────────────────
let promoKeyActual = null;

function abrirModalPromocion(btn) {
    promoKeyActual = btn.dataset.key;
    document.getElementById('modalPromocionPlan').textContent = btn.closest('tr').querySelector('td').textContent.trim();
    document.getElementById('modalPromoPrecio').value = btn.dataset.precio ? Number(btn.dataset.precio).toLocaleString('es-CO') : '';
    document.getElementById('modalPromoVence').value = btn.dataset.vence || '';
    document.getElementById('modalPromocion').style.display = 'flex';
}

function cerrarModalPromocion() {
    document.getElementById('modalPromocion').style.display = 'none';
    promoKeyActual = null;
}

function actualizarBoton(key, precio, vence) {
    const btn = document.querySelector('.btn-promocion[data-key="' + key + '"]');
    const vigente = precio && vence && new Date(vence + 'T00:00:00') >= new Date(new Date().toDateString());
    if (vigente) {
        const [y, m, d] = vence.split('-');
        btn.textContent = '🏷️ Hasta ' + d + '/' + m + '/' + y;
        btn.style.background = '#fef9c3'; btn.style.color = '#92400e'; btn.style.border = '1px solid #fde047';
    } else {
        btn.textContent = '+ Configurar';
        btn.style.background = '#f8fafc'; btn.style.color = '#94a3b8'; btn.style.border = '1px dashed #cbd5e1';
    }
    btn.dataset.precio = precio || '';
    btn.dataset.vence = vence || '';
}

function guardarModalPromocion() {
    const precio = document.getElementById('modalPromoPrecio').value.replace(/\D/g, '');
    const vence = document.getElementById('modalPromoVence').value;
    if (!precio || !vence) { alert('Completa el precio promocional y la fecha de vencimiento.'); return; }

    document.querySelector('.promo-input-precio[data-key="' + promoKeyActual + '"]').value = precio;
    document.querySelector('.promo-input-vence[data-key="' + promoKeyActual + '"]').value = vence;
    actualizarBoton(promoKeyActual, precio, vence);
    cerrarModalPromocion();
}

function quitarPromocion() {
    document.querySelector('.promo-input-precio[data-key="' + promoKeyActual + '"]').value = '';
    document.querySelector('.promo-input-vence[data-key="' + promoKeyActual + '"]').value = '';
    actualizarBoton(promoKeyActual, null, null);
    cerrarModalPromocion();
}
</script>
@endsection
