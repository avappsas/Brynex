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

<form method="POST" action="{{ route('admin.configuracion.store') }}">
@csrf

{{-- ══ SECCIÓN 1: Porcentajes Seguridad Social ══ --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:1rem;">
  @php $esSuperadmin = auth()->user()->hasRole('superadmin'); @endphp
  <div style="font-size:0.72rem;font-weight:700;color:#0891b2;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.85rem;">
    🔒 Parámetros Globales BryNex
    @if(!$esSuperadmin)
    <span style="font-size:0.65rem;color:#94a3b8;text-transform:none;font-weight:400;margin-left:0.5rem;">Solo superadmin puede modificarlos</span>
    @else
    <span style="background:#dcfce7;color:#166534;font-size:0.65rem;font-weight:600;padding:0.1rem 0.5rem;border-radius:999px;text-transform:none;margin-left:0.5rem;">✏️ Editables como Superadmin</span>
    @endif
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
    <div style="background:#f8fafc;border-radius:8px;padding:0.65rem 0.75rem;border:1px solid {{ $esSuperadmin ? '#bfdbfe' : '#e2e8f0' }};overflow:hidden;">
      <div style="font-size:0.6rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:0.3rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $cfg['label'] }}</div>
      @if($esSuperadmin)
      <div style="display:flex;align-items:center;gap:0.2rem;">
        @if($cfg['prefix']) <span style="color:#64748b;font-size:0.72rem;flex-shrink:0;">{{ $cfg['prefix'] }}</span> @endif
        <input type="number" step="{{ $cfg['step'] }}" min="0"
            name="brynex[{{ $clave }}]"
            value="{{ $valor !== null ? $valor : '' }}"
            style="width:100%;padding:0.28rem 0.35rem;border:1px solid #93c5fd;border-radius:5px;font-size:0.82rem;font-family:monospace;font-weight:700;background:#fff;min-width:0;color:#0f172a;box-sizing:border-box;">
        @if($cfg['suffix']) <span style="color:#64748b;font-size:0.72rem;flex-shrink:0;">{{ $cfg['suffix'] }}</span> @endif
      </div>
      @else
      <div style="font-size:0.95rem;font-weight:700;color:#0f172a;">
        {{ $cfg['prefix'] }}{{ $valor !== null ? number_format($valor, $cfg['decimals'], ',', '.') : '—' }}{{ $cfg['suffix'] }}
      </div>
      @endif
    </div>
    @endforeach
  </div>
</div>

{{-- ══ SECCIÓN 2: Tarifas ARL ══ --}}
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
        <th style="padding:0.55rem 0.75rem;text-align:right;color:#7c3aed;font-weight:600;font-size:0.73rem;" title="% del costo de afiliación que va a la empresa">% ADMON AFIL</th>
        <th style="padding:0.55rem 0.75rem;text-align:right;color:#0369a1;font-weight:600;font-size:0.73rem;" title="% del costo de afiliación reservado para retiro/novedad">% RETIRO AFIL</th>
        <th style="padding:0.55rem 0.75rem;text-align:right;color:#475569;font-weight:600;font-size:0.73rem;">SEGURO</th>
        <th style="padding:0.55rem 0.75rem;text-align:center;color:#475569;font-weight:600;font-size:0.73rem;">ENCARGADO DEFAULT</th>
      </tr>
    </thead>
    <tbody>
      {{-- Fila Global (aplica a todos los planes si no hay específico) --}}
      @php $globalCfg = $configs['global'] ?? null; @endphp
      <tr style="border-bottom:1px solid #f1f5f9;background:#fffbeb;">
        <td style="padding:0.6rem 0.75rem;">
          <div style="font-weight:700;color:#0f172a;">Todos los planes</div>
          <div style="font-size:0.68rem;color:#94a3b8;">Usado cuando no hay config específica</div>
        </td>
        @foreach(['administracion','admon_asesor','costo_afiliacion'] as $campo)
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <span style="color:#64748b;font-size:0.75rem;">$</span>
            <input type="number" step="100" min="0"
                name="configs[global][{{ $campo }}]"
                value="{{ $globalCfg ? $globalCfg->$campo : 0 }}"
                style="width:110px;padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;">
          </div>
        </td>
        @endforeach
        {{-- % Admon Afil --}}
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <input type="number" step="1" min="0" max="100"
                name="configs[global][dist_admon_pct]"
                value="{{ $globalCfg?->dist_admon_pct ?? 0 }}"
                style="width:70px;padding:0.38rem 0.5rem;border:1.5px solid #c4b5fd;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;background:#faf5ff;">
            <span style="color:#7c3aed;font-size:0.75rem;">%</span>
          </div>
        </td>
        {{-- % Retiro Afil --}}
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <input type="number" step="1" min="0" max="100"
                name="configs[global][dist_retiro_pct]"
                value="{{ $globalCfg?->dist_retiro_pct ?? 0 }}"
                style="width:70px;padding:0.38rem 0.5rem;border:1.5px solid #bae6fd;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;background:#f0f9ff;">
            <span style="color:#0369a1;font-size:0.75rem;">%</span>
          </div>
        </td>
        {{-- Seguro --}}
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <span style="color:#64748b;font-size:0.75rem;">$</span>
            <input type="number" step="100" min="0"
                name="configs[global][seguro_valor]"
                value="{{ $globalCfg ? $globalCfg->seguro_valor : 0 }}"
                style="width:110px;padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;">
          </div>
        </td>
        <td style="padding:0.5rem 0.75rem;text-align:center;">
          <select name="configs[global][encargado_default_id]"
              style="padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.78rem;background:#fff;max-width:140px;">
            <option value="">— Ninguno —</option>
            @foreach($usuarios as $usr)
            <option value="{{ $usr->id }}" {{ ($globalCfg?->encargado_default_id == $usr->id) ? 'selected' : '' }}>{{ $usr->nombre }}</option>
            @endforeach
          </select>
        </td>
      </tr>

      {{-- Filas por Plan --}}
      @foreach($planes as $plan)
      @php $cfg = $configs[$plan->id] ?? null; @endphp
      <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background=''">
        <td style="padding:0.6rem 0.75rem;">
          <div style="font-weight:600;color:#0f172a;">{{ $plan->nombre }}</div>
          <div style="font-size:0.67rem;color:#94a3b8;margin-top:0.15rem;">
            @if($plan->incluye_eps) <span style="background:#dbeafe;color:#1d4ed8;padding:0 0.3rem;border-radius:3px;">EPS</span> @endif
            @if($plan->incluye_arl) <span style="background:#d1fae5;color:#065f46;padding:0 0.3rem;border-radius:3px;">ARL</span> @endif
            @if($plan->incluye_pension) <span style="background:#ede9fe;color:#6d28d9;padding:0 0.3rem;border-radius:3px;">AFP</span> @endif
            @if($plan->incluye_caja) <span style="background:#fef3c7;color:#92400e;padding:0 0.3rem;border-radius:3px;">CCF</span> @endif
          </div>
        </td>
        @foreach(['administracion','admon_asesor','costo_afiliacion'] as $campo)
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <span style="color:#94a3b8;font-size:0.75rem;">$</span>
            <input type="number" step="100" min="0"
                name="configs[{{ $plan->id }}][{{ $campo }}]"
                value="{{ $cfg ? $cfg->$campo : '' }}"
                placeholder="{{ $globalCfg ? $globalCfg->$campo : '0' }}"
                style="width:110px;padding:0.38rem 0.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;background:{{ $cfg ? '#fff' : '#f8fafc' }};"
                onfocus="this.style.borderColor='#3b82f6';this.style.background='#fff'" onblur="this.style.borderColor='#e2e8f0'">
          </div>
        </td>
        @endforeach
        {{-- % Admon Afil por plan --}}
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <input type="number" step="1" min="0" max="100"
                name="configs[{{ $plan->id }}][dist_admon_pct]"
                value="{{ $cfg?->dist_admon_pct ?? '' }}"
                placeholder="{{ $globalCfg?->dist_admon_pct ?? '0' }}"
                style="width:70px;padding:0.38rem 0.5rem;border:1.5px solid #e2e8f0;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;background:{{ $cfg?->dist_admon_pct ? '#faf5ff' : '#f8fafc' }};"
                onfocus="this.style.borderColor='#7c3aed';this.style.background='#faf5ff'" onblur="this.style.borderColor='#e2e8f0'">
            <span style="color:#7c3aed;font-size:0.75rem;">%</span>
          </div>
        </td>
        {{-- % Retiro Afil por plan --}}
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <input type="number" step="1" min="0" max="100"
                name="configs[{{ $plan->id }}][dist_retiro_pct]"
                value="{{ $cfg?->dist_retiro_pct ?? '' }}"
                placeholder="{{ $globalCfg?->dist_retiro_pct ?? '0' }}"
                style="width:70px;padding:0.38rem 0.5rem;border:1.5px solid #e2e8f0;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;background:{{ $cfg?->dist_retiro_pct ? '#f0f9ff' : '#f8fafc' }};"
                onfocus="this.style.borderColor='#0369a1';this.style.background='#f0f9ff'" onblur="this.style.borderColor='#e2e8f0'">
            <span style="color:#0369a1;font-size:0.75rem;">%</span>
          </div>
        </td>
        {{-- Seguro --}}
        <td style="padding:0.5rem 0.75rem;text-align:right;">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
            <span style="color:#94a3b8;font-size:0.75rem;">$</span>
            <input type="number" step="100" min="0"
                name="configs[{{ $plan->id }}][seguro_valor]"
                value="{{ $cfg ? $cfg->seguro_valor : '' }}"
                placeholder="{{ $globalCfg ? $globalCfg->seguro_valor : '0' }}"
                style="width:110px;padding:0.38rem 0.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:0.83rem;font-family:monospace;text-align:right;background:{{ $cfg ? '#fff' : '#f8fafc' }};"
                onfocus="this.style.borderColor='#3b82f6';this.style.background='#fff'" onblur="this.style.borderColor='#e2e8f0'">
          </div>
        </td>
        <td style="padding:0.5rem 0.75rem;text-align:center;">
          <select name="configs[{{ $plan->id }}][encargado_default_id]"
              style="padding:0.38rem 0.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:0.78rem;background:#fff;max-width:140px;">
            <option value="">— Global —</option>
            @foreach($usuarios as $usr)
            <option value="{{ $usr->id }}" {{ ($cfg?->encargado_default_id == $usr->id) ? 'selected' : '' }}>{{ $usr->nombre }}</option>
            @endforeach
          </select>
        </td>
      </tr>
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
</div>
@endsection
