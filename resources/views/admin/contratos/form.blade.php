@extends('layouts.app')
@section('modulo', isset($contrato->id) ? 'Contrato #'.$contrato->id : 'Nuevo Contrato')

@section('contenido')
@php
  $esEdicion      = isset($contrato->id) && $contrato->id;
  // En edición: usar SOLO el valor guardado en el contrato (sin fallback al cliente).
  // En creación: usar el valor del cliente como sugerencia inicial.
  $epsDefault     = old('eps_id',     $esEdicion ? ($contrato->eps_id     ?? '') : ($contrato->eps_id     ?? $clienteEpsId      ?? ''));
  $pensionDefault = old('pension_id', $esEdicion ? ($contrato->pension_id ?? '') : ($contrato->pension_id ?? $clientePensionId  ?? ''));
  $arlDefault     = old('arl_id',     $esEdicion ? ($contrato->arl_id     ?? '') : ($contrato->arl_id     ?? $arlIdRazonSocial  ?? ''));
  // intval evita que PHP emita "1750905.00" que rompe el JS de Alpine
  $defAdmon       = (int) old('administracion',   $contrato->administracion  ?? $defaultTarifas['administracion']    ?? 0);
  $defAdmonAsesor = (int) old('admon_asesor',     $contrato->admon_asesor    ?? $defaultTarifas['admon_asesor']      ?? 0);
  $defCosto       = (int) old('costo_afiliacion', $contrato->costo_afiliacion ?? $defaultTarifas['costo_afiliacion'] ?? 0);
  $defSeguro      = (int) old('seguro',           $contrato->seguro           ?? $defaultTarifas['seguro']           ?? 0);
  $defEncargado   = old('encargado_id',     $contrato->encargado_id     ?? $defaultTarifas['encargado_id']     ?? auth()->id());
  $defSalario     = (int) old('salario',          $contrato->salario          ?? $salarioMinimo);
  $defIbc         = (int) old('ibc',              $contrato->ibc              ?? $defSalario);
  $S = 'width:100%;padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem;background:#fff;box-sizing:border-box;';
  $I = 'width:100%;padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem;box-sizing:border-box;';
  $M = 'width:100%;padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem;font-family:monospace;box-sizing:border-box;';
@endphp

<div style="max-width:1240px;margin:0 auto;" x-data="cotizador()">

{{-- ENCABEZADO --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.85rem;">
  <div style="display:flex;align-items:center;gap:0.75rem;">
    <div style="width:36px;height:36px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 12px rgba(37,99,235,0.35);">&#128203;</div>
    <div>
      <h1 style="font-size:1.05rem;font-weight:700;color:#0f172a;margin:0;">
        {{ $esEdicion ? 'Contrato #'.$contrato->id : 'Nuevo Contrato' }}
      </h1>
      @if($cliente)
      <div style="font-size:0.76rem;color:#475569;">
        <strong>{{ $cliente->primer_nombre }} {{ $cliente->primer_apellido }}</strong>
        &middot; CC {{ number_format($cliente->cedula,0,',','.') }}
        @if($cliente->iva === 'SI')<span style="background:#fef3c7;color:#92400e;padding:0.1rem 0.4rem;border-radius:999px;font-size:0.68rem;margin-left:4px;">IVA</span>@endif
      </div>
      @endif
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:0.6rem;">
    @if($esEdicion)
    <span style="padding:0.28rem 0.9rem;border-radius:999px;font-size:0.78rem;font-weight:700;
        background:{{ $contrato->estado === 'vigente' ? '#dcfce7' : ($contrato->estado === 'retirado' ? '#fee2e2' : '#f1f5f9') }};
        color:{{ $contrato->estado === 'vigente' ? '#15803d' : ($contrato->estado === 'retirado' ? '#dc2626' : '#475569') }};">
      {{ strtoupper($contrato->estado) }}
    </span>
    @endif
    <a href="{{ $backUrl ?? route('admin.contratos.index') }}"
       style="padding:0.38rem 0.9rem;border:1px solid #cbd5e1;border-radius:7px;color:#475569;text-decoration:none;font-size:0.8rem;">&larr; Volver</a>
  </div>
</div>

@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;padding:0.55rem 1rem;margin-bottom:0.65rem;font-size:0.8rem;">
  <strong>Errores:</strong> @foreach($errors->all() as $e) &middot; {{ $e }} @endforeach
</div>
@endif

<form method="POST"
      action="{{ $esEdicion ? route('admin.contratos.update', $contrato->id) : route('admin.contratos.store') }}"
      id="form-contrato">
  @csrf
  @if($esEdicion) @method('PUT') @endif
  <input type="hidden" name="cedula" value="{{ old('cedula', $cliente->cedula ?? $contrato->cedula ?? '') }}">
  <input type="hidden" name="back_url" value="{{ $backUrl ?? '' }}">
  @if(request()->has('iframe'))
  <input type="hidden" name="iframe" value="1">
  @endif

<div style="display:grid;grid-template-columns:1fr 300px;gap:1.1rem;align-items:start;">

{{-- ══ COLUMNA IZQUIERDA ══ --}}
<div style="display:flex;flex-direction:column;gap:0.7rem;">

  {{-- Panel 1: RS + Modalidad + Plan + F.Ingreso --}}
  <div class="cp">
    <div class="pt" style="color:#2563eb;">&#127970; Contrato</div>
    <div style="display:grid;grid-template-columns:1.6fr 1.2fr 1.2fr 120px;gap:0.5rem;">
@php
            // ¿Hay radicados que bloquean la RS?
            $rsLock = $esEdicion && ($rsBloquedaPorAfiliacion ?? false);
          @endphp
      <div>
        <label class="lb">Razon Social</label>
        <div class="{{ $rsLock ? 'tip-lock' : '' }}" data-tip="🔒 Bloqueado — hay afiliaciones en trámite u OK">
        <select name="razon_social_id" id="sel_rs" style="{{ $S }}{{ $rsLock ? 'background:#f1f5f9;color:#1e293b;cursor:not-allowed;opacity:1;' : '' }}"
            onchange="onRazonSocialChange(this)"
            {{ $rsLock ? 'disabled' : '' }}>
          <option value="">-- Ninguna --</option>
          @foreach($razonesSociales as $rs)
          @php
            $rsOcupada  = in_array($rs->id, $rsOcupadasIds ?? []);
            $rsInactiva = (strtolower(trim($rs->estado ?? 'Activa')) !== 'activa');
            $rsDisabled = $rsLock || $rsOcupada || $rsInactiva;
            $rsSufijo   = $rsOcupada ? ' — contrato vigente' : ($rsInactiva ? ' (Inactiva)' : '');
            $rsEstilo   = $rsOcupada ? 'color:#94a3b8;background:#f8fafc;font-style:italic;'
                        : ($rsInactiva ? 'color:#cbd5e1;background:#f8fafc;font-style:italic;' : '');
          @endphp
          <option value="{{ $rs->id }}"
              data-arl="{{ $rs->arl_nit ?? '' }}"
              data-independiente="{{ $rs->es_independiente ? '1' : '0' }}"
              {{ old('razon_social_id', $contrato->razon_social_id ?? '') == $rs->id ? 'selected' : '' }}
              {{ $rsDisabled ? 'disabled' : '' }}
              style="{{ $rsEstilo }}">
            {{ $rs->razon_social }}{{ $rsSufijo }}
          </option>
          @endforeach
        </select>
        @if($rsLock)
        {{-- Campo oculto para mantener el valor al hacer submit --}}
        <input type="hidden" name="razon_social_id" value="{{ old('razon_social_id', $contrato->razon_social_id ?? '') }}">
        @endif
        </div>

      </div>
      <div>
        <label class="lb">Modalidad</label>
        <div class="{{ $rsLock ? 'tip-lock' : '' }}" data-tip="🔒 Bloqueado — hay afiliaciones en trámite u OK">
        <select name="tipo_modalidad_id" id="sel_modalidad" x-model="tipoModalidadId" @change="onModalidadChange"
            style="{{ $S }}{{ $rsLock ? 'background:#f1f5f9;color:#1e293b;cursor:not-allowed;opacity:1;' : '' }}"
            {{ $rsLock ? 'disabled' : '' }}>
          <option value="">-- Modalidad --</option>
          @foreach($tiposModalidad as $tm)
          <option value="{{ $tm->id }}"
              data-independiente="{{ in_array($tm->id, $modalidadesIndependientes) ? '1' : '0' }}"
              {{ old('tipo_modalidad_id', $contrato->tipo_modalidad_id ?? '') == $tm->id ? 'selected' : '' }}>
            {{ $tm->observacion ?: $tm->tipo_modalidad }}
          </option>
          @endforeach
        </select>
        @if($rsLock)
        <input type="hidden" name="tipo_modalidad_id" value="{{ old('tipo_modalidad_id', $contrato->tipo_modalidad_id ?? '') }}">
        @endif
        </div>
      </div>
      <div>
        <label class="lb">Plan
          {{-- Badge AFP cuando el cliente puede omitirlo --}}
          @if(!empty($clienteExentoAfp))
          <span id="badge-exento-afp"
              title="{{ $clienteTipoDoc && in_array($clienteTipoDoc,['CE','PP','PE','PA']) ? 'Documento: '.$clienteTipoDoc : 'Edad: '.$clienteEdad.' años ('.($clienteGenero==='M'?'hombre':'mujer').')' }}"
              style="background:#ede9fe;color:#7c3aed;font-size:.6rem;font-weight:700;padding:.12rem .45rem;border-radius:20px;margin-left:.4rem;cursor:help;letter-spacing:.02em;">
            📌 Puede omitir AFP
          </span>
          @endif
        </label>
        <div class="{{ $rsLock ? 'tip-lock' : '' }}" data-tip="🔒 Bloqueado — hay afiliaciones en trámite u OK">
        <select name="plan_id" id="sel_plan" x-model="planId" @change="onPlanChange"
            style="{{ $S }}{{ $rsLock ? 'background:#f1f5f9;color:#1e293b;cursor:not-allowed;opacity:1;' : '' }}"
            {{ $rsLock ? 'disabled' : '' }}>
          <option value="">-- Plan --</option>
          @foreach($planes as $plan)
          <option value="{{ $plan->id }}"
              data-eps="{{ $plan->incluye_eps ? '1':'0' }}"
              data-arl="{{ $plan->incluye_arl ? '1':'0' }}"
              data-pen="{{ $plan->incluye_pension ? '1':'0' }}"
              data-caja="{{ $plan->incluye_caja ? '1':'0' }}"
              {{ ($esEdicion && old('plan_id', $contrato->plan_id ?? '') == $plan->id) ? 'selected' : '' }}>
            {{ $plan->nombre }}
          </option>
          @endforeach
        </select>
        @if($rsLock)
        <input type="hidden" name="plan_id" value="{{ old('plan_id', $contrato->plan_id ?? '') }}">
        @endif
        </div>
        <div id="nota-plan-modalidad" style="display:none;font-size:.6rem;color:#94a3b8;margin-top:.15rem;">Seleccione primero la modalidad</div>

      </div>
      <div>
        <label class="lb">F. Ingreso</label>
        <div class="{{ $rsLock ? 'tip-lock' : '' }}" data-tip="🔒 Bloqueado — hay afiliaciones en trámite u OK">
        <input type="date" name="fecha_ingreso"
            value="{{ old('fecha_ingreso', isset($contrato->fecha_ingreso) ? $contrato->fecha_ingreso->format('Y-m-d') : now()->format('Y-m-d')) }}"
            @change="calcularDiasDesde($event.target.value); recalcular()"
            style="{{ $I }}{{ $rsLock ? 'background:#f1f5f9;color:#1e293b;cursor:not-allowed;opacity:1;' : '' }}"
            {{ $rsLock ? 'readonly' : '' }}>
        @if($rsLock)
        <input type="hidden" name="fecha_ingreso" value="{{ old('fecha_ingreso', isset($contrato->fecha_ingreso) ? $contrato->fecha_ingreso->format('Y-m-d') : now()->format('Y-m-d')) }}">
        @endif
        </div>
      </div>

    </div>
    <div style="display:grid;grid-template-columns:1.8fr 1.2fr 1fr;gap:0.5rem;margin-top:0.5rem;">
      <div>
        <label class="lb">Actividad Economica</label>
        <select name="actividad_economica_id" @change="onActividadChange" style="{{ $S }}">
          <option value="">--</option>
          @foreach($actividades as $act)
          <option value="{{ $act->id }}" data-nivel="{{ $act->nivel_arl_sugerido }}"
              {{ old('actividad_economica_id', $contrato->actividad_economica_id ?? '') == $act->id ? 'selected' : '' }}>
            [N{{ $act->nivel_arl_sugerido }}] {{ $act->nombre }}
          </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="lb">Motivo Afiliacion</label>
        <select name="motivo_afiliacion_id" style="{{ $S }}">
          <option value="">--</option>
          @foreach($motivosAfiliacion as $m)
          <option value="{{ $m->id }}" {{ old('motivo_afiliacion_id', $contrato->motivo_afiliacion_id ?? '') == $m->id ? 'selected' : '' }}>{{ $m->nombre }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="lb">Cargo / Ocupacion</label>
        <input type="text" name="cargo" value="{{ old('cargo', $contrato->cargo ?? '') }}" style="{{ $I }}">
      </div>
    </div>
  </div>

  {{-- Panel 2: Entidades --}}
  <div class="cp">
    <div class="pt" style="color:#16a34a;">&#127963; Entidades</div>
    @php
      // Radicados indexados por tipo para mostrar estado en cada entidad
      $rPT = $radicadosPorTipo ?? collect();
      // Helper: badge HTML según estado del radicado
      $badgeEstado = function(?object $rad): string {
        if (!$rad) return '';
        $cfg = [
          'pendiente' => ['bg'=>'#fef3c7','txt'=>'#92400e','icono'=>'⏳','label'=>'Pendiente'],
          'tramite'   => ['bg'=>'#dbeafe','txt'=>'#1e40af','icono'=>'🔵','label'=>'En Trámite'],
          'traslado'  => ['bg'=>'#ffedd5','txt'=>'#9a3412','icono'=>'🔄','label'=>'Traslado'],
          'error'     => ['bg'=>'#fee2e2','txt'=>'#991b1b','icono'=>'❌','label'=>'Error'],
          'ok'        => ['bg'=>'#dcfce7','txt'=>'#166534','icono'=>'✅','label'=>'Afiliado OK'],
        ];
        $c = $cfg[$rad->estado] ?? ['bg'=>'#f1f5f9','txt'=>'#475569','icono'=>'❓','label'=>$rad->estado];
        $num = $rad->numero_radicado ? $rad->numero_radicado : '';
        return '<div style="margin-top:0.22rem;width:100%;box-sizing:border-box;">'
          .'<span style="display:flex;align-items:center;justify-content:center;gap:0.2rem;width:100%;box-sizing:border-box;background:'.$c['bg'].';color:'.$c['txt'].';font-size:0.62rem;font-weight:700;padding:0.18rem 0.4rem;border-radius:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'
          .$c['icono'].' '.$c['label'].($num ? ' · '.$num : '').'</span>'
          .'</div>';
      };
    @endphp
    <div style="display:grid;grid-template-columns:1.3fr 1.3fr 1.4fr 60px 1fr;gap:0.5rem;align-items:start;">
      <div>
        <label class="lb">EPS</label>
        <select name="eps_id" id="sel_eps" style="{{ $S }}">
          <option value="">-- Ninguna --</option>
          @foreach($epsList as $eps)
          <option value="{{ $eps->id }}" {{ $epsDefault == $eps->id ? 'selected' : '' }}>{{ $eps->nombre }}</option>
          @endforeach
        </select>
        @if($esEdicion && $contrato->eps_id && collect($epsList)->contains('id', (int)$contrato->eps_id))
        {!! $badgeEstado($rPT->get('eps')) !!}
        @endif
      </div>
      <div>
        <label class="lb">AFP / Pension</label>
        <select name="pension_id" id="sel_pen" style="{{ $S }}">
          <option value="">-- Ninguna --</option>
          @foreach($pensiones as $pen)
          <option value="{{ $pen->id }}" {{ $pensionDefault == $pen->id ? 'selected' : '' }}>{{ $pen->razon_social }}</option>
          @endforeach
        </select>
        @if($esEdicion && $contrato->pension_id && collect($pensiones)->contains('id', (int)$contrato->pension_id))
        {!! $badgeEstado($rPT->get('pension')) !!}
        @endif
      </div>
      <div>
        <label class="lb">ARL <span id="lbl_arl_lock" style="color:#94a3b8;font-weight:400;font-size:0.63rem;">(de la R.Social)</span></label>
        <select name="arl_id" id="sel_arl" style="{{ $S }}">
          <option value="">-- Ninguna --</option>
          @foreach($arlList as $arl)
          <option value="{{ $arl->id }}" data-nit="{{ $arl->nit ?? '' }}"
              {{ $arlDefault == $arl->id ? 'selected' : '' }}>{{ $arl->nombre_arl }}</option>
          @endforeach
        </select>
        @if($esEdicion && $contrato->arl_id && collect($arlList)->contains('id', (int)$contrato->arl_id))
        {!! $badgeEstado($rPT->get('arl')) !!}
        @endif
      </div>
      <div>
        <label class="lb">N.ARL</label>
        <select name="n_arl" x-model="nivelArl" @change="recalcular" style="{{ $S }}text-align:center;">
          @for($i=1;$i<=5;$i++)
          <option value="{{ $i }}" {{ old('n_arl', $contrato->n_arl ?? 1) == $i ? 'selected' : '' }}>{{ $i }}</option>
          @endfor
        </select>
      </div>
      <div>
        <label class="lb">Caja Compensacion</label>
        <select name="caja_id" id="sel_caja" style="{{ $S }}">
          <option value="">-- Ninguna --</option>
          @php
            $cajasLocales = $cajas->where('es_local', true)->values();
            $cajasResto   = $cajas->where('es_local', false)->values();
          @endphp
          @if($cajasLocales->isNotEmpty())
          <optgroup label="📍 Tu departamento">
            @foreach($cajasLocales as $caja)
            <option value="{{ $caja->id }}"
                {{ old('caja_id', $contrato->caja_id ?? '') == $caja->id ? 'selected' : '' }}
                style="font-weight:600;">
              ★ {{ $caja->nombre }}
            </option>
            @endforeach
          </optgroup>
          <optgroup label="─── Otras cajas ───────────────">
          @else
          @php $cajasResto = $cajas; @endphp
          @endif
          @foreach($cajasResto as $caja)
          <option value="{{ $caja->id }}"
              {{ old('caja_id', $contrato->caja_id ?? '') == $caja->id ? 'selected' : '' }}>
            {{ $caja->nombre }}
          </option>
          @endforeach
          @if($cajasLocales->isNotEmpty())
          </optgroup>
          @endif
        </select>
        @if($esEdicion && $contrato->caja_id && collect($cajas)->contains('id', (int)$contrato->caja_id))
        {!! $badgeEstado($rPT->get('caja')) !!}
        @endif
      </div>
    </div>
    <div x-show="mostrarModoArl" id="panel-modo-arl" style="display:none;margin-top:0.5rem;">
      <div style="display:grid;grid-template-columns:200px 1fr;gap:0.5rem;align-items:end;max-width:560px;">
        {{-- Col 1: Modo ARL --}}
        <div>
          <label class="lb">Modo ARL</label>
          <select name="arl_modo" id="sel_arl_modo" style="{{ $S }}" onchange="onArlModoChange(this)">
            <option value="">--</option>
            <option value="razon_social"  {{ old('arl_modo', $contrato->arl_modo ?? '') === 'razon_social'  ? 'selected' : '' }}>Por Razon Social</option>
            <option value="independiente" {{ old('arl_modo', $contrato->arl_modo ?? '') === 'independiente' ? 'selected' : '' }}>Independiente</option>
          </select>
        </div>

        {{-- Col 2: Selector de RS (cuando razon_social) o cédula readonly (cuando independiente) --}}
        <div>
          {{-- Panel "Por Razón Social": select de razones sociales --}}
          <div id="panel_arl_rs" style="display:none;">
            <label class="lb">Razón Social ARL
              <span style="font-weight:400;color:#64748b;font-size:0.6rem;margin-left:3px;">bajo cuya RS se cotiza la ARL</span>
            </label>
            <select id="sel_arl_rs_cotizante" style="{{ $S }}" onchange="onArlRsChange(this)">
              <option value="">-- Seleccione RS --</option>
              @foreach($razonesSociales as $rs)
              <option value="{{ $rs->id }}"
                  data-nombre="{{ $rs->razon_social }}"
                  {{ old('arl_nit_cotizante', $contrato->arl_nit_cotizante ?? '') == $rs->id ? 'selected' : '' }}>
                {{ $rs->razon_social }} ({{ number_format($rs->id, 0, '', '.') }})
              </option>
              @endforeach
            </select>
          </div>

          {{-- Panel "Independiente": cédula + operador planilla --}}
          <div id="panel_arl_cedula" style="display:none;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;align-items:end;">
              <div>
                <label class="lb">Cédula Cotizante
                  <span style="font-weight:400;color:#64748b;font-size:0.6rem;margin-left:3px;">el cliente cotiza por sí mismo</span>
                </label>
                <input type="text" id="disp_arl_cedula" readonly
                    style="{{ $I }}background:#f8fafc;color:#475569;font-family:monospace;cursor:not-allowed;"
                    value="{{ old('cedula', $cliente->cedula ?? $contrato->cedula ?? '') }}">
              </div>
              <div>
                <label class="lb" style="color:#1d4ed8;">&#x1F4B3; Operador Planilla</label>
                <select name="operador_planilla_id"
                        style="{{ $S }}border-color:#bfdbfe;background:#eff6ff;color:#1d4ed8;font-weight:600;">
                  <option value="">&mdash; Seleccione &mdash;</option>
                  @foreach($operadoresPlanilla ?? [] as $op)
                  <option value="{{ $op->id }}"
                      {{ old('operador_planilla_id', $clienteOperadorId ?? '') == $op->id ? 'selected' : '' }}>
                    {{ $op->nombre }}{{ $op->codigo_ni ? ' ('.$op->codigo_ni.')' : '' }}
                  </option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Campo oculto que guarda el bigInteger en BD --}}
      <input type="hidden" name="arl_nit_cotizante" id="inp_arl_nit"
          value="{{ old('arl_nit_cotizante', $contrato->arl_nit_cotizante ?? '') }}">
    </div>
  </div>

  {{-- Panel 3: Salario + Asesor + Tarifas --}}
  <div class="cp">
    <div class="pt" style="color:#7c3aed;">&#128176; Salario y Tarifas</div>

    {{-- Fila 1: Salario + IBC(indep) + Asesor + Encargado --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1.4fr;gap:0.5rem;margin-bottom:0.5rem;">
      <div>
        <label class="lb">Salario Mensual</label>
        <input type="text" inputmode="numeric" name="salario" id="inp_salario" class="campo-money"
            @input="onSalarioChange"
            value="{{ number_format($defSalario, 0, '', '.') }}" style="{{ $M }}"
            data-raw="{{ $defSalario }}">
      </div>
      <div x-show="esIndependiente" style="display:none;">
        <label class="lb">IBC <span style="color:#f59e0b;font-size:0.63rem;" x-text="ibcSugFmt ? 'sug:'+ibcSugFmt : ''"></span></label>
        <input type="number" step="1" min="0" name="ibc" x-model="ibc" @input="recalcular"
            value="{{ old('ibc', $contrato->ibc ?? '') }}"
            style="width:100%;padding:0.38rem 0.5rem;border:1px solid #f59e0b;border-radius:6px;font-size:0.82rem;font-family:monospace;background:#fffbeb;box-sizing:border-box;">
      </div>
      <div x-show="esIndependiente" id="div-pct-caja" style="display:none;">
        <label class="lb">% Caja</label>
        <select name="porcentaje_caja" x-model="pctCaja" @change="recalcular" style="{{ $S }}">
          <option value="2">2% Normal</option>
          <option value="0.6" {{ old('porcentaje_caja', $contrato->porcentaje_caja ?? '') == 0.6 ? 'selected' : '' }}>0.6% Reducido</option>
        </select>
      </div>
      <div>
        <label class="lb">Asesor</label>
        <select name="asesor_id" id="sel_asesor" style="{{ $S }}" onchange="onAsesorChange(this)">
          <option value="">-- Sin asesor --</option>
          @foreach($asesores as $as)
          <option value="{{ $as->id }}"
              data-admon="{{ $as->comision_admon_valor ?? 0 }}"
              {{ old('asesor_id', $contrato->asesor_id ?? '') == $as->id ? 'selected' : '' }}>
            {{ $as->nombre }}
          </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="lb">Encargado Afiliacion</label>
        <select name="encargado_id" style="{{ $S }}">
          <option value="">-- Responsable --</option>
          @foreach($usuarios as $usr)
          <option value="{{ $usr->id }}" {{ $defEncargado == $usr->id ? 'selected' : '' }}>{{ $usr->nombre }}</option>
          @endforeach
        </select>
      </div>
    </div>

    {{-- Fila 2: Admon + Admon Asesor + Costo + Seguro + Envio Planilla --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:0.5rem;">
      <div>
        <label class="lb">Admon Mensual $</label>
        <input type="text" inputmode="numeric" name="administracion" id="inp_admon" class="campo-money"
            @input="recalcular"
            value="{{ number_format($defAdmon, 0, '', '.') }}" style="{{ $M }}"
            data-raw="{{ $defAdmon }}">
      </div>
      <div>
        <label class="lb">Admon Asesor $</label>
        <input type="text" inputmode="numeric" name="admon_asesor" id="inp_admon_asesor" class="campo-money"
            value="{{ number_format($defAdmonAsesor, 0, '', '.') }}" style="{{ $M }}"
            data-raw="{{ $defAdmonAsesor }}">
      </div>
      <div>
        <label class="lb">Costo Afiliacion $</label>
        <input type="text" inputmode="numeric" name="costo_afiliacion" id="inp_costo" class="campo-money"
            value="{{ number_format($defCosto, 0, '', '.') }}" style="{{ $M }}"
            data-raw="{{ $defCosto }}">
      </div>
      <div>
        <label class="lb">Seguro $</label>
        <input type="text" inputmode="numeric" name="seguro" id="inp_seguro" class="campo-money"
            @input="recalcular"
            value="{{ number_format($defSeguro, 0, '', '.') }}" style="{{ $M }}"
            data-raw="{{ $defSeguro }}">
      </div>
      <div>
        <label class="lb">Envio Planilla</label>
        <select name="envio_planilla" style="{{ $S }}">
          <option value="">--</option>
          @foreach(['Correo','WhatsApp','Fisica','Web','Otro'] as $ep)
          <option value="{{ $ep }}" {{ old('envio_planilla', $contrato->envio_planilla ?? '') === $ep ? 'selected' : '' }}>{{ $ep }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  {{-- Panel 4: Observacion --}}
  <div class="cp" style="padding:0.65rem 0.9rem;">
    <label class="lb">Observacion</label>
    <input type="text" name="observacion" value="{{ old('observacion', $contrato->observacion ?? '') }}"
        style="{{ $I }}" placeholder="Nota general del contrato...">
  </div>

  {{-- Guardar --}}
  <div style="display:flex;justify-content:flex-end;">
    <button type="submit" id="btn-guardar-contrato"
        style="padding:0.6rem 2.2rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;border-radius:9px;color:#fff;font-size:0.9rem;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(37,99,235,0.4);transition:opacity .2s;display:inline-flex;align-items:center;gap:.5rem;">
        <span id="btn-guardar-ico">&#128190;</span>
        <span id="btn-guardar-txt">{{ $esEdicion ? 'Actualizar Contrato' : 'Crear Contrato' }}</span>
    </button>
  </div>

</div>{{-- /col izq --}}

{{-- ══ COLUMNA DERECHA: Cotizador + Botones + Radicados ══ --}}
<div style="position:sticky;top:1rem;display:flex;flex-direction:column;gap:0.7rem;">

  {{-- Cotizador --}}
  <div style="background:linear-gradient(160deg,#0f172a,#1e3a5f);border-radius:14px;padding:1rem 1.15rem;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,0.35);">
    <div style="font-weight:700;font-size:0.82rem;margin-bottom:0.6rem;display:flex;align-items:center;gap:0.5rem;">
      &#129518; Cotizacion
      <span style="font-size:0.63rem;background:rgba(255,255,255,0.1);padding:0.1rem 0.45rem;border-radius:999px;font-weight:400;" x-text="planNombre || 'Sin plan'"></span>
    </div>
    <div style="background:rgba(255,255,255,0.06);border-radius:6px;padding:0.4rem 0.6rem;margin-bottom:0.55rem;font-size:0.72rem;display:flex;justify-content:space-between;align-items:center;gap:0.5rem;">
      <div style="display:flex;align-items:center;gap:0.35rem;">
        <span style="color:#94a3b8;">IBC: <strong x-text="fmt(ibc)">$0</strong></span>
        {{-- Badge Tiempo Parcial --}}
        <span x-show="esTiempoParcial" style="display:none;background:#f59e0b;color:#000;font-size:0.58rem;font-weight:800;padding:.1rem .38rem;border-radius:999px;">⏱ T.PARCIAL</span>
      </div>
      {{-- Selector de días: solo visible en modalidades normales --}}
      <div style="display:flex;align-items:center;gap:0.3rem;" x-show="!esTiempoParcial">
        <span style="color:#94a3b8;font-size:0.67rem;white-space:nowrap;">Días</span>
        <select id="sel_dias_cotizar" x-model="diasCotizar"
            @change="recalcular()"
            style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.25);border-radius:5px;color:#fff;font-size:0.7rem;padding:0.15rem 0.25rem;cursor:pointer;width:52px;">
          @for($d = 1; $d <= 30; $d++)
          <option value="{{ $d }}" style="color:#000;">{{ $d }}</option>
          @endfor
        </select>
      </div>
    </div>
    <div style="font-size:0.75rem;">
      {{-- EPS: oculta en Tiempo Parcial --}}
      <div class="cr" x-show="!esTiempoParcial"><span style="color:#93c5fd;">EPS <span class="cp2" x-text="'('+pctEps+'%)'"></span></span><strong x-text="fmt(result.eps)">$0</strong></div>
      {{-- ARL --}}
      <div class="cr">
        <span style="color:#6ee7b7;">ARL <span class="cp2" x-text="'N'+nivelArl+' ('+pctArl+'%)'"></span>
          <template x-if="esTiempoParcial">
            <span style="color:#f59e0b;font-size:.58rem;font-weight:700;"> · 30d</span>
          </template>
        </span>
        <strong x-text="fmt(result.arl)">$0</strong>
      </div>
      {{-- PENSIÓN --}}
      <div class="cr">
        <span style="color:#c4b5fd;">PENSIÓN <span class="cp2" x-text="'('+pctPen+'%)'"></span>
          <template x-if="esTiempoParcial && diasAfp">
            <span style="color:#f59e0b;font-size:.58rem;font-weight:700;" x-text="' · '+diasAfp+'d'"></span>
          </template>
        </span>
        <strong x-text="fmt(result.pen)">$0</strong>
      </div>
      {{-- CAJA --}}
      <div class="cr">
        <span style="color:#fcd34d;">CAJA <span class="cp2" x-text="'('+pctCajaCalc+'%)'"></span>
          <template x-if="esTiempoParcial && diasCaja">
            <span style="color:#f59e0b;font-size:.58rem;font-weight:700;" x-text="' · '+diasCaja+'d'"></span>
          </template>
        </span>
        <strong x-text="fmt(result.caja)">$0</strong>
      </div>
      {{-- S. Social --}}
      <div class="cr" style="border-bottom:2px solid rgba(255,255,255,0.18);padding-bottom:0.35rem;margin-bottom:0.1rem;font-weight:700;">
        <span>S. Social
          <span class="cp2" x-show="!esTiempoParcial && diasCotizar < 30" x-text="'('+diasCotizar+'d)'"></span>
        </span>
        <span x-text="fmt(result.ss)">$0</span>
      </div>
      <div class="cr"><span style="color:#94a3b8;">Seguro <span class="cp2" style="color:#34d399;" x-show="!esTiempoParcial && diasCotizar < 30">(completo)</span></span><strong x-text="fmt(result.seguro)">$0</strong></div>
      <div class="cr"><span style="color:#94a3b8;">Admon <span class="cp2" style="color:#34d399;" x-show="!esTiempoParcial && diasCotizar < 30">(completo)</span></span><strong x-text="fmt(result.admon)">$0</strong></div>
      <div x-show="result.iva > 0" class="cr"><span style="color:#fca5a5;">IVA 19%</span><strong x-text="fmt(result.iva)">$0</strong></div>
      <div class="cr" style="font-size:0.95rem;font-weight:800;padding-top:0.4rem;"><span>TOTAL</span><span style="color:#34d399;" x-text="fmt(result.total)">$0</span></div>
    </div>
  </div>

  {{-- Botones de Accion --}}
  @if($esEdicion)
  <div class="cp" style="padding:0.7rem 0.85rem;">
    <div class="pt" style="color:#475569;">&#9889; Acciones</div>
    <div style="display:flex;flex-direction:column;gap:0.4rem;">
      <a onclick="abrirModalFacturarContrato()" style="display:flex;align-items:center;gap:0.55rem;padding:0.5rem 0.75rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;color:#15803d;text-decoration:none;font-size:0.8rem;font-weight:600;cursor:pointer;">
        &#129534; Facturar
      </a>
      <button type="button" onclick="abrirHistorialPagos()" style="display:flex;align-items:center;gap:0.55rem;padding:0.5rem 0.75rem;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;color:#6d28d9;text-decoration:none;font-size:0.8rem;font-weight:600;cursor:pointer;width:100%;text-align:left;">
        &#128202; Historial Pagos
      </button>
      <button type="button" onclick="document.getElementById('modal-radicados').style.display='flex'"
          style="display:flex;align-items:center;gap:0.55rem;padding:0.5rem 0.75rem;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;color:#0369a1;font-size:0.8rem;font-weight:600;cursor:pointer;width:100%;text-align:left;">
        &#128193; Ver Radicados
        @if($esEdicion && $contrato->radicados->count() > 0)
        <span style="margin-left:auto;background:#0369a1;color:#fff;border-radius:999px;padding:0 0.45rem;font-size:0.65rem;">{{ $contrato->radicados->count() }}</span>
        @endif
      </button>
      @if($contrato->estaVigente())
      <button type="button"
          onclick="document.getElementById('modal-retiro').style.display='flex';mrInitSelects();mrSetDefault();"
          style="display:flex;align-items:center;gap:0.55rem;padding:0.5rem 0.75rem;background:#fff5f5;border:1px solid #fecaca;border-radius:8px;color:#dc2626;font-size:0.8rem;font-weight:600;cursor:pointer;width:100%;text-align:left;">
        &#128683; Marcar Retiro
      </button>
      @endif
    </div>
  </div>
  @endif


</div>{{-- /col der --}}
</div>{{-- /grid --}}
</form>

{{-- ══ MODAL RADICADOS ══ --}}
@if($esEdicion)
<div id="modal-radicados" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:14px;padding:1.4rem;width:100%;max-width:520px;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <h3 style="margin:0;font-size:0.95rem;color:#0f172a;">&#128193; Radicados — Contrato #{{ $contrato->id }}</h3>
      <button onclick="document.getElementById('modal-radicados').style.display='none'" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#94a3b8;line-height:1;">&times;</button>
    </div>
    {{-- Nota informativa --}}
    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:0.5rem 0.75rem;margin-bottom:0.85rem;font-size:0.73rem;color:#0369a1;display:flex;align-items:center;gap:0.4rem;">
      &#128270; Los radicados se gestionan desde el módulo <strong>Afiliaciones</strong>. Aquí solo se visualizan.
    </div>
    @if($contrato->radicados->count() === 0)
    <p style="text-align:center;color:#94a3b8;font-size:0.82rem;padding:1.5rem 0;">No hay radicados generados para este contrato.</p>
    @else
    @php
      // Determinar qué tipos incluye el plan del contrato
      $planContrato = $contrato->plan;
      $tiposIncluidos = [
        'eps'     => $planContrato?->incluye_eps     ?? true,
        'pension' => $planContrato?->incluye_pension ?? true,
        'arl'     => $planContrato?->incluye_arl     ?? true,
        'caja'    => $planContrato?->incluye_caja    ?? true,
      ];
    @endphp
    @foreach($contrato->radicados as $rad)
    @php
      // Saltar si el tipo de este radicado no está incluido en el plan
      $tipoRad = strtolower($rad->tipo ?? '');
      if (isset($tiposIncluidos[$tipoRad]) && !$tiposIncluidos[$tipoRad]) continue;

      $estadoCfg = [
        'pendiente' => ['bg'=>'#fef3c7','txt'=>'#92400e','icono'=>'⏳','label'=>'Pendiente'],
        'tramite'   => ['bg'=>'#dbeafe','txt'=>'#1e40af','icono'=>'🔵','label'=>'En Trámite'],
        'traslado'  => ['bg'=>'#ffedd5','txt'=>'#9a3412','icono'=>'🔄','label'=>'Traslado'],
        'error'     => ['bg'=>'#fee2e2','txt'=>'#991b1b','icono'=>'❌','label'=>'Error'],
        'ok'        => ['bg'=>'#dcfce7','txt'=>'#166534','icono'=>'✅','label'=>'OK — Afiliado'],
      ];
      $ec = $estadoCfg[$rad->estado] ?? ['bg'=>'#f1f5f9','txt'=>'#475569','icono'=>'❓','label'=>$rad->estado];
      $canalLabel = [
        'web'=>'Web','correo'=>'Correo','asesor'=>'Asesor',
        'presencial'=>'Presencial','otro'=>'Otro',
      ][$rad->canal_envio ?? ''] ?? ($rad->canal_envio ? ucfirst($rad->canal_envio) : '—');
    @endphp
    <div style="border:1px solid #e2e8f0;border-radius:9px;padding:0.65rem 0.8rem;margin-bottom:0.55rem;">
      {{-- Tipo + Badge estado --}}
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.45rem;">
        <span style="font-weight:700;font-size:0.82rem;color:#1e293b;">{{ $rad->tipoLabel() }}</span>
        <span style="display:inline-flex;align-items:center;gap:0.25rem;background:{{ $ec['bg'] }};color:{{ $ec['txt'] }};font-size:0.7rem;font-weight:700;padding:0.18rem 0.6rem;border-radius:999px;">
          {{ $ec['icono'] }} {{ $ec['label'] }}
        </span>
      </div>
      {{-- N° Radicado + Canal --}}
      <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;font-size:0.73rem;color:#475569;margin-bottom:0.3rem;">
        @if($rad->numero_radicado)
        <span style="background:#f1f5f9;border-radius:6px;padding:0.15rem 0.5rem;font-family:monospace;font-weight:600;color:#0f172a;">
          # {{ $rad->numero_radicado }}
        </span>
        @else
        <span style="color:#94a3b8;font-style:italic;">Sin número de radicado</span>
        @endif
        <span style="color:#94a3b8;">|</span>
        <span>Canal: <strong>{{ $canalLabel }}</strong></span>
      </div>
      {{-- Observación --}}
      @if($rad->observacion)
      <div style="font-size:0.7rem;color:#64748b;background:#f8fafc;border-radius:5px;padding:0.25rem 0.5rem;margin-top:0.25rem;">
        {{ $rad->observacion }}
      </div>
      @endif
      {{-- Documento subido desde Afiliaciones --}}
      @if($rad->ruta_pdf)
      <div style="margin-top:0.45rem;">
        <a href="{{ route('admin.radicados.pdf.download', $rad->id) }}" target="_blank"
           style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.28rem 0.7rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;color:#1d4ed8;font-size:0.72rem;font-weight:600;text-decoration:none;">
          &#128196; Ver Documento
        </a>
      </div>
      @else
      <div style="margin-top:0.35rem;font-size:0.68rem;color:#94a3b8;font-style:italic;">Sin documento adjunto</div>
      @endif
    </div>
    @endforeach
    @endif
    <div style="text-align:right;margin-top:0.75rem;">
      <button onclick="document.getElementById('modal-radicados').style.display='none'"
          style="padding:0.42rem 1.2rem;border:1px solid #cbd5e1;border-radius:7px;background:#fff;cursor:pointer;font-size:0.82rem;">Cerrar</button>
    </div>
  </div>
</div>
@endif

{{-- MODAL RETIRO --}}
@if($esEdicion && $contrato->estaVigente())
<div id="modal-retiro" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;padding:1.5rem;max-width:460px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <h3 style="margin:0 0 1rem;color:#dc2626;font-size:0.95rem;">&#128683; Retirar Contrato #{{ $contrato->id }}</h3>

    {{-- Selector de tipo de retiro --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:1rem;">
      <label id="mr-lbl-real" onclick="mrTipo('real')"
        style="cursor:pointer;border:2px solid #ef4444;border-radius:10px;padding:0.6rem 0.8rem;background:#fff5f5;transition:.15s;">
        <div style="display:flex;align-items:center;gap:0.4rem;">
          <input type="radio" name="_tipo_retiro_ui" value="real" checked onclick="event.stopPropagation();mrTipo('real')">
          <strong style="font-size:0.78rem;color:#dc2626;">Retiro Real</strong>
        </div>
        <p style="font-size:0.65rem;color:#6b7280;margin:.25rem 0 0 1.2rem;line-height:1.3;">
          Genera plano con días cotizados. EPS, ARL, Pensión y Caja se calculan automáticamente (registro de costo, no ingreso).
        </p>
      </label>
      <label id="mr-lbl-info" onclick="mrTipo('informativo')"
        style="cursor:pointer;border:2px solid #e2e8f0;border-radius:10px;padding:0.6rem 0.8rem;background:#f8fafc;transition:.15s;">
        <div style="display:flex;align-items:center;gap:0.4rem;">
          <input type="radio" name="_tipo_retiro_ui" value="informativo" onclick="event.stopPropagation();mrTipo('informativo')">
          <strong style="font-size:0.78rem;color:#475569;">Retiro Informativo</strong>
        </div>
        <p style="font-size:0.65rem;color:#6b7280;margin:.25rem 0 0 1.2rem;line-height:1.3;">
          Solo marca el retiro (0 días). El cliente paga por sus propios medios.
        </p>
      </label>
    </div>

    <form method="POST" action="{{ route('admin.contratos.retirar', $contrato->id) }}" onsubmit="return mrOnSubmit()">
      @csrf @method('PATCH')
      <input type="hidden" name="back_url" value="{{ $backUrl ?? '' }}">
      <input type="hidden" name="tipo_retiro" id="mr-tipo-hidden" value="real">
      @if(request()->has('iframe'))
      <input type="hidden" name="iframe" value="1">
      @endif

      <div style="margin-bottom:0.7rem;">
        <label class="lb">Motivo *</label>
        <select name="motivo_retiro_id" required style="{{ $S }}">
          <option value="">-- Seleccione --</option>
          @foreach($motivosRetiro as $mr)
          <option value="{{ $mr->id }}">{{ $mr->nombre }}</option>
          @endforeach
        </select>
      </div>

      {{-- Mes/Año del plano --}}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
        <div>
          <label class="lb">Mes del Plano *</label>
          <select name="mes_plano" id="mr-mes" onchange="mrSetDefault()" style="{{ $S }}">
            <option value="1">Enero</option>
            <option value="2">Febrero</option>
            <option value="3">Marzo</option>
            <option value="4">Abril</option>
            <option value="5">Mayo</option>
            <option value="6">Junio</option>
            <option value="7">Julio</option>
            <option value="8">Agosto</option>
            <option value="9">Septiembre</option>
            <option value="10">Octubre</option>
            <option value="11">Noviembre</option>
            <option value="12">Diciembre</option>
          </select>
        </div>
        <div>
          <label class="lb">Año del Plano *</label>
          <select name="anio_plano" id="mr-anio" onchange="mrSetDefault()" style="{{ $S }}">
            @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
            <option value="{{ $y }}">{{ $y }}</option>
            @endfor
          </select>
        </div>
      </div>
      {{-- Error inline: mes anterior al ingreso --}}
      <div id="mr-mes-error" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-radius:7px;padding:0.35rem 0.65rem;font-size:0.72rem;color:#dc2626;margin-bottom:0.4rem;">
        &#9888; El mes del plano no puede ser anterior al mes de ingreso del contrato.
      </div>

      {{-- Fecha + Días (bidireccional) --}}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.7rem;">
        <div>
          <label class="lb">Fecha Retiro *</label>
          <input type="date" name="fecha_retiro" id="mr-fecha" required
                 oninput="mrCalcDias()" style="{{ $I }}">
        </div>
        <div id="mr-dias-wrap">
          <label class="lb">Días a pagar</label>
          <div style="display:flex;align-items:center;gap:0.4rem;">
            <input type="number" name="num_dias" id="mr-num-dias"
                   value="1" oninput="mrCalcFecha()" style="{{ $I }};width:70px;font-weight:700;">
            <span style="font-size:0.7rem;color:#6b7280;">/30</span>
          </div>
        </div>
      </div>

      <div style="margin-bottom:1rem;">
        <label class="lb">Observacion</label>
        <textarea name="observacion" rows="2" style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:7px;font-size:0.8rem;resize:none;box-sizing:border-box;"></textarea>
      </div>

      {{-- ⚠️ Mora real informativa — solo si aplica mora en el período actual --}}
      @php
        $mrMoraInfo = null;
        try {
            if ($esEdicion && $contrato->razonSocial && $contrato->estaVigente()) {
                $mrAliadoId = session('aliado_id_activo');
                $mrRs       = $contrato->razonSocial;
                $mrNit      = (int)($mrRs->nit ?: $mrRs->id);
                $mrDiaH     = $mrRs->dia_habil ?? null;
                // Estimación SS mes actual con salario del contrato
                $mrSalario  = (float)($contrato->salario ?? 0);
                $mrSS       = round($mrSalario * 0.285);
                if ($mrNit && $mrSS > 0) {
                    $mrCalc = \App\Services\MoraClienteService::calcular($mrAliadoId, $mrNit, $mrDiaH, $mrSS, now()->month, now()->year);
                    if ($mrCalc['aplica'] && ($mrCalc['mora_real'] ?? 0) > 0) {
                        $mrMoraInfo = ['dias' => $mrCalc['dias_mora'], 'mora_real' => (int)round($mrCalc['mora_real'])];
                    }
                }
            }
        } catch (\Throwable) {}
      @endphp
      @if($mrMoraInfo)
      <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:9px;padding:0.55rem 0.8rem;margin-bottom:0.85rem;font-size:0.75rem;color:#92400e;display:flex;align-items:center;gap:0.5rem;">
        <span style="font-size:1rem;">⚠️</span>
        <div>
          <strong>Mora real estimada: ${{ number_format($mrMoraInfo['mora_real'], 0, '', '.') }}</strong>
          <span style="color:#b45309;"> · {{ $mrMoraInfo['dias'] }} días de mora</span>
          <div style="font-size:0.65rem;color:#a16207;margin-top:0.1rem;">Interés real sin mínimos · Se registrará en "Otros" de la factura. El aliado asume este costo.</div>
        </div>
      </div>
      @endif

      <div style="display:flex;gap:0.6rem;justify-content:flex-end;">
        <button type="button" onclick="document.getElementById('modal-retiro').style.display='none'"
            style="padding:0.45rem 1rem;border:1px solid #cbd5e1;border-radius:7px;background:#fff;cursor:pointer;font-size:0.82rem;">Cancelar</button>
        <button type="submit" id="mr-submit-btn"
            style="padding:0.45rem 1.1rem;background:#dc2626;border:none;border-radius:7px;color:#fff;font-size:0.82rem;font-weight:700;cursor:pointer;min-width:130px;display:flex;align-items:center;justify-content:center;gap:0.4rem;">Confirmar Retiro</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Modal Retiro: datos del contrato ──────────────────────────────────────
const MR = {
    // Fecha ingreso del contrato (ISO string o null)
    fechaIngreso: @if($contrato->fecha_ingreso)'{{ sqldate($contrato->fecha_ingreso)->format('Y-m-d') }}'@else null @endif,
    // ¿Es independiente mes actual? (tipo_modalidad_id = 11)
    esMesActual: {{ (int)($contrato->tipo_modalidad_id) === 11 ? 'true' : 'false' }},
};

// ── Leer mes/año seleccionados (fuente de verdad) ─────────────────────────
function mrMesPlan() {
    const mesEl  = document.getElementById('mr-mes');
    const anioEl = document.getElementById('mr-anio');
    if (mesEl && anioEl && mesEl.value && anioEl.value) {
        return { anio: parseInt(anioEl.value, 10), mes: parseInt(mesEl.value, 10) };
    }
    // Fallback si los selects no existen todavía
    const hoy = new Date();
    if (MR.esMesActual) return { anio: hoy.getFullYear(), mes: hoy.getMonth() + 1 };
    const m = hoy.getMonth(); // 0-based; 0 = enero
    return { anio: m === 0 ? hoy.getFullYear() - 1 : hoy.getFullYear(), mes: m === 0 ? 12 : m };
}

// ── Inicializar selects al abrir el modal ────────────────────────────────
function mrInitSelects() {
    const hoy = new Date();
    let mesDef, anioDef;
    if (MR.esMesActual) {
        mesDef  = hoy.getMonth() + 1;
        anioDef = hoy.getFullYear();
    } else {
        const m = hoy.getMonth(); // 0-based
        mesDef  = m === 0 ? 12 : m;
        anioDef = m === 0 ? hoy.getFullYear() - 1 : hoy.getFullYear();
    }

    // Si el mes vencido calculado es ANTERIOR a fecha_ingreso, avanzar al mes de ingreso
    if (MR.fechaIngreso) {
        const [fAnio, fMes] = MR.fechaIngreso.split('-').map(Number);
        const defVal  = anioDef * 100 + mesDef;
        const ingrVal = fAnio   * 100 + fMes;
        if (defVal < ingrVal) {
            mesDef  = fMes;
            anioDef = fAnio;
        }
    }

    const mesEl  = document.getElementById('mr-mes');
    const anioEl = document.getElementById('mr-anio');
    if (mesEl)  mesEl.value  = mesDef;
    if (anioEl) anioEl.value = anioDef;
}

// ── Validar que mes_plano no sea anterior al mes de ingreso ───────────────
function mrValidarMes() {
    if (!MR.fechaIngreso) return true; // sin ingreso: siempre válido
    const { anio, mes } = mrMesPlan();
    const [fAnio, fMes] = MR.fechaIngreso.split('-').map(Number);
    const errEl = document.getElementById('mr-mes-error');
    const esInvalido = (anio * 100 + mes) < (fAnio * 100 + fMes);
    if (errEl) errEl.style.display = esInvalido ? 'block' : 'none';
    if (esInvalido) return false; // bloquea el submit
    return true;
}

// ── Establecer fecha/días según mes/año seleccionado ─────────────────────
function mrSetDefault() {
    // Ocultar error previo al cambiar el select
    const errEl = document.getElementById('mr-mes-error');
    if (errEl) errEl.style.display = 'none';

    const { anio, mes } = mrMesPlan();
    const mesStr = String(mes).padStart(2, '0');
    let fechaDefault, diasDefault = 1;

    if (MR.fechaIngreso) {
        const [fAnio, fMes] = MR.fechaIngreso.split('-').map(Number);
        if (fAnio === anio && fMes === mes) {
            // Se afilió exactamente en el mes seleccionado → usar fecha_ingreso, 1 día
            fechaDefault = MR.fechaIngreso;
            diasDefault  = 1;
        } else {
            // Otro mes → día 1 del mes seleccionado, 1 día
            fechaDefault = `${anio}-${mesStr}-01`;
            diasDefault  = 1;
        }
    } else {
        fechaDefault = `${anio}-${mesStr}-01`;
        diasDefault  = 1;
    }

    const fechaEl = document.getElementById('mr-fecha');
    const diasEl  = document.getElementById('mr-num-dias');
    if (fechaEl) fechaEl.value = fechaDefault;
    if (diasEl)  diasEl.value  = diasDefault;
}

// ── Fecha → Días (día del mes, máx 30) ───────────────────────────────────
function mrCalcDias() {
    const val = document.getElementById('mr-fecha')?.value;
    if (!val) return;
    const dia  = parseInt(val.split('-')[2], 10);
    const dias = Math.min(dia, 30);
    const input = document.getElementById('mr-num-dias');
    if (input) input.value = dias;
}

// ── Días → Fecha (coloca el día en el mes seleccionado) ──────────────────
function mrCalcFecha() {
    const diasInput = document.getElementById('mr-num-dias');
    const fechaEl   = document.getElementById('mr-fecha');
    if (!diasInput || !fechaEl) return;
    let dias = parseInt(diasInput.value, 10);
    if (isNaN(dias) || dias < 1) { dias = 1; diasInput.value = 1; }
    if (dias > 30)               { dias = 30; diasInput.value = 30; }

    const { anio, mes } = mrMesPlan();
    // Último día real del mes (para no poner 31 en febrero)
    const ultimoDia = new Date(anio, mes, 0).getDate();
    const dia = Math.min(dias, ultimoDia);
    fechaEl.value = `${anio}-${String(mes).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
}

// ── Toggle tipo retiro ────────────────────────────────────────────────────
function mrTipo(tipo) {
    document.getElementById('mr-tipo-hidden').value = tipo;
    const lblReal  = document.getElementById('mr-lbl-real');
    const lblInfo  = document.getElementById('mr-lbl-info');
    const diasWrap = document.getElementById('mr-dias-wrap');
    const numDias  = document.getElementById('mr-num-dias');

    if (tipo === 'real') {
        lblReal.style.borderColor = '#ef4444';
        lblReal.style.background  = '#fff5f5';
        lblInfo.style.borderColor = '#e2e8f0';
        lblInfo.style.background  = '#f8fafc';
        diasWrap.style.display    = 'block';
        numDias.disabled = false;
        numDias.required = true;
        if (!numDias.value || numDias.value == 0) numDias.value = 1;
        mrSetDefault();
    } else {
        lblInfo.style.borderColor = '#3b82f6';
        lblInfo.style.background  = '#eff6ff';
        lblReal.style.borderColor = '#e2e8f0';
        lblReal.style.background  = '#f8fafc';
        diasWrap.style.display    = 'none';
        // Deshabilitar: el browser ignora campos disabled en la validación nativa
        numDias.disabled = true;
        numDias.required = false;
        numDias.value    = 0;
    }
}

// ── Init: establecer defaults al cargar ──────────────────────────────────
document.addEventListener('DOMContentLoaded', function() { mrSetDefault(); });

// ── Submit con loading state ──────────────────────────────────────────────
function mrOnSubmit() {
    if (!mrValidarMes()) return false; // bloquea si mes inválido
    const btn = document.getElementById('mr-submit-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg style="width:14px;height:14px;animation:mr-spin 0.8s linear infinite;flex-shrink:0;" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.35)" stroke-width="3"/><path d="M12 2a10 10 0 0 1 10 10" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg> Procesando...';
        btn.style.background = '#b91c1c';
        btn.style.cursor = 'not-allowed';
    }
    return true;
}
</script>

<style>
@keyframes mr-spin { to { transform: rotate(360deg); } }
</style>

@endif

</div>{{-- /x-data --}}

{{-- ══ MODAL FACTURAR PLANILLA (unificado, individual) ══ --}}
@if($esEdicion)
@include('admin.facturacion._modal_facturar', ['bancos' => $bancos])
@endif

{{-- ══ MODAL HISTORIAL DE PAGOS (iframe, sin layout) ══ --}}
@if($esEdicion)
<div id="modal-historial"
     onclick="if(event.target.id==='modal-historial')cerrarHistorial()"
     style="display:none;position:fixed;inset:0;background:rgba(10,16,30,.72);backdrop-filter:blur(5px);z-index:10500;align-items:center;justify-content:center;padding:.5rem">
  <div style="position:relative;width:min(1180px,97vw);height:94vh;background:#f0f4f8;border-radius:14px;overflow:hidden;box-shadow:0 28px 80px rgba(0,0,0,.55);display:flex;flex-direction:column">
    {{-- Header --}}
    <div style="background:linear-gradient(135deg,#0f172a,#4c1d95);padding:.55rem 1rem;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
      <div style="display:flex;align-items:center;gap:.55rem">
        <span style="font-size:1.1rem">📊</span>
        <span style="color:#fff;font-size:.92rem;font-weight:700;letter-spacing:.02em">Historial de Pagos</span>
        <span style="color:rgba(255,255,255,.55);font-size:.78rem">— CC {{ number_format($contrato->cedula, 0, '', '.') }}</span>
      </div>
      <button type="button" onclick="cerrarHistorial()"
              style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:7px;width:30px;height:30px;font-size:1rem;cursor:pointer;font-weight:700;line-height:1;transition:background .15s"
              onmouseover="this.style.background='rgba(255,255,255,.28)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">&#x2715;</button>
    </div>
    {{-- iframe --}}
    <div style="flex:1;overflow:hidden">
      <iframe id="historial-frame" src="" style="width:100%;height:100%;border:none;display:block"></iframe>
    </div>
  </div>
</div>
@endif

<style>
.lb  { display:block;font-size:0.67rem;font-weight:700;color:#475569;margin-bottom:0.15rem;text-transform:uppercase;letter-spacing:0.03em; }
.cp  { background:#fff;border-radius:11px;border:1px solid #e2e8f0;padding:0.8rem 0.95rem; }
.pt  { font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.5rem; }
.cr  { display:flex;justify-content:space-between;padding:0.25rem 0;border-bottom:1px solid rgba(255,255,255,0.06); }
.cp2 { font-size:0.63rem;opacity:0.6; }
select,input[type="text"],input[type="number"],input[type="date"],textarea { font-family:inherit; }
input:focus,select:focus { outline:none;border-color:#3b82f6 !important;box-shadow:0 0 0 2px rgba(59,130,246,0.15); }
select:disabled { background:#f1f5f9;color:#1e293b;cursor:not-allowed;opacity:1; }
/* ── Tooltip on-hover para campos bloqueados ─────────────── */
.tip-lock { position:relative; }
.tip-lock::after {
  content: attr(data-tip);
  position:absolute; bottom:calc(100% + 5px); left:50%; transform:translateX(-50%);
  background:#1e293b; color:#f1f5f9; font-size:0.65rem; font-weight:500;
  padding:0.3rem 0.65rem; border-radius:6px; white-space:nowrap;
  pointer-events:none; opacity:0; transition:opacity 0.15s ease;
  z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.25);
}
.tip-lock:hover::after { opacity:1; }
@keyframes spin-btn { to { transform: rotate(360deg); } }
</style>


@push('scripts')
<script src="{{ asset('js/modal_facturar.js') }}"></script>
<script>
const MODALIDADES_MODO_ARL  = @json($modalidadesModoArl ?? []);
const MODALIDADES_ARL_LIBRE = @json($modalidadesArlLibre ?? []);
const ARL_ID_RS             = {{ $arlIdRazonSocial ?? 'null' }};
const SALARIO_MINIMO        = {{ $salarioMinimo ?? 0 }};
const PLAN_DATA             = {};
// URLs generadas por Laravel (incluyen subdirectorio correcto)
const URL_COTIZAR  = '{{ route('admin.contratos.cotizar') }}';
const URL_TARIFAS  = '{{ route('admin.contratos.tarifas') }}';
const URL_RADICADO = '{{ url('admin/contratos/api/radicado') }}';
// Datos del cliente para auto-selección de entidades
const CLIENTE_EPS_ID     = {{ $clienteEpsId ?? 'null' }};
const CLIENTE_PENSION_ID = {{ $clientePensionId ?? 'null' }};
// ── Filtrado inteligente: modalidades → planes ─────────────────────
const MODALIDAD_PLANES   = @json($planesPermitidos ?? []);
const MODALIDADES_INDEP          = @json($modalidadesIndependientes ?? [10,11,14]);
const CLIENTE_EXENTO_AFP         = {{ ($clienteExentoAfp ?? false) ? 'true' : 'false' }};
const ES_EDICION                 = {{ ($esEdicion ?? false) ? 'true' : 'false' }};
// ── Regla AFP obligatorio ──────────────────────────────────────────────────
// Activa: planes sin AFP quedan deshabilitados para las modalidades indicadas
// (a menos que el cliente esté exento por tipo_doc o edad)
const REGLA_AFP_ACTIVA           = {{ ($reglaAfpActiva ?? false) ? 'true' : 'false' }};
const MODALIDADES_AFP_OBLIGATORIO = @json($modalidadesAfpObligatorio ?? [0,10,11]);
@php
// Construir mapa de días TP antes de inyectarlo como JS
// factor_salario: fraccion del salario mínimo a usar como salario mensual
$_factorMap = [7 => 0.25, 14 => 0.50, 21 => 0.75, 30 => 1.00];
$_modalidadesTPData = [];
foreach ($tiposModalidad as $_tm) {
    if ($_tm->esTiempoParcial()) {
        $_factor = $_factorMap[$_tm->dias_afp] ?? 1.0;
        $_modalidadesTPData[$_tm->id] = [
            'dias_arl'       => 30,                // ARL siempre 30 días
            'dias_afp'       => $_tm->dias_afp,
            'dias_caja'      => $_tm->dias_caja,
            'factor_salario' => $_factor,
        ];
    }
}
@endphp
const MODALIDADES_TP = {!! json_encode($_modalidadesTPData) !!};


// ── Formateador de miles para campos .campo-money ────────────────
function numRaw(el)  { return parseInt(el.value.replace(/\./g, '') || 0); }
function numFmt(v)   { return Math.round(v||0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
function fmtMoney(el) {
    const raw = numRaw(el);
    el.dataset.raw = raw;
    el.value = raw > 0 ? numFmt(raw) : (raw === 0 ? '0' : '');
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.campo-money').forEach(el => {
        // Formatear al inicio
        const initRaw = parseInt(el.dataset.raw || el.value.replace(/\./g,'') || 0);
        el.dataset.raw = initRaw;
        el.value       = initRaw > 0 ? numFmt(initRaw) : '0';
        // En foco: mostrar numero limpio para editar
        el.addEventListener('focus', () => { el.value = el.dataset.raw || '0'; });
        // Al salir: formatear con puntos
        el.addEventListener('blur',  () => {
            el.dataset.raw = parseInt(el.value.replace(/\./g,'') || 0);
            el.value = numFmt(el.dataset.raw);
            // Si es el campo salario, actualizar IBC automáticamente
            if (el.id === 'inp_salario') {
                const alpineComp = document.querySelector('[x-data]')?._x_dataStack?.[0];
                if (alpineComp) alpineComp.onSalarioChange();
            }
        });
        // Al tipear: mantener solo digitos
        el.addEventListener('input', () => {
            const pos = el.selectionStart;
            const raw = el.value.replace(/[^0-9]/g,'');
            el.value = raw;
            el.dataset.raw = raw || '0';
        });
    });
    // Antes de enviar el form: limpiar puntos de todos los campos money
    const form = document.getElementById('form-contrato');
    if (form) form.addEventListener('submit', () => {
        document.querySelectorAll('.campo-money').forEach(el => {
            el.value = el.dataset.raw || el.value.replace(/\./g,'') || '0';
        });
    }, true);
});


// Indexar NITs de ARL
const arlNitMap = {};
document.querySelectorAll('#sel_arl option[data-nit]').forEach(opt => {
    if (opt.dataset.nit) arlNitMap[opt.dataset.nit] = opt.value;
});

// Indexar data de planes
document.querySelectorAll('#sel_plan option[value]').forEach(opt => {
    if (opt.value) PLAN_DATA[opt.value] = {
        eps:   opt.dataset.eps  === '1',
        arl:   opt.dataset.arl  === '1',
        pen:   opt.dataset.pen  === '1',
        caja:  opt.dataset.caja === '1',
    };
});

function onRazonSocialChange(sel) {
    const opt    = sel.options[sel.selectedIndex];
    const arlNit = opt?.dataset?.arl || '';
    if (arlNit && arlNitMap[arlNit]) {
        document.getElementById('sel_arl').value = arlNitMap[arlNit];
    }
    // Resetear Plan al cambiar Razón Social SOLO en modo creación
    if (!ES_EDICION) {
        const selPlan = document.getElementById('sel_plan');
        if (selPlan) {
            selPlan.value = '';
            // Notificar a Alpine.js que cambió el plan
            const alpineComp = document.querySelector('[x-data]')?._x_dataStack?.[0];
            if (alpineComp) { alpineComp.planId = ''; alpineComp.planNombre = ''; }
        }
    }
    // Filtrar modalidades según si la RS es independiente
    const esIndep = opt?.dataset?.independiente === '1';
    filtrarModalidades(esIndep);
    actualizarBloqueoArl();
    // Actualizar NIT cotizante si ya hay un modo ARL seleccionado
    const modoSel = document.getElementById('sel_arl_modo');
    if (modoSel) sincronizarNitCotizante(modoSel.value, opt?.value || '');
}

/**
 * Se dispara cuando cambia el selector de Modo ARL.
 * Muestra el panel correcto (RS o cédula) y sincroniza el campo oculto.
 */
function onArlModoChange(sel) {
    mostrarPanelArlSegunModo(sel.value);
}

/**
 * Se dispara cuando el usuario elige una RS en el select de RS ARL.
 * Guarda su ID (=NIT) en el campo oculto.
 */
function onArlRsChange(sel) {
    const inp = document.getElementById('inp_arl_nit');
    if (inp) inp.value = sel.value;
}

/**
 * Muestra/oculta los paneles de la segunda columna según el modo elegido:
 *  - razon_social  → panel con select de razones sociales
 *  - independiente → panel con cédula del cliente (readonly)
 *  - otro/vacío    → ambos ocultos
 * También sincroniza el campo oculto inp_arl_nit.
 */
function mostrarPanelArlSegunModo(modo) {
    const panelRs     = document.getElementById('panel_arl_rs');
    const panelCedula = document.getElementById('panel_arl_cedula');
    const inpNit      = document.getElementById('inp_arl_nit');

    if (!panelRs || !panelCedula) return;

    if (modo === 'razon_social') {
        panelRs.style.display     = 'block';
        panelCedula.style.display = 'none';
        // Sincronizar con la RS ya seleccionada en el select (si hay)
        const selRS = document.getElementById('sel_arl_rs_cotizante');
        if (inpNit) inpNit.value = selRS?.value || '';

    } else if (modo === 'independiente') {
        panelRs.style.display     = 'none';
        panelCedula.style.display = 'block';
        // Auto-llenar cédula del cliente
        const cedula = document.querySelector('input[name="cedula"]')?.value || '';
        const disp   = document.getElementById('disp_arl_cedula');
        if (disp) disp.value = cedula;
        if (inpNit) inpNit.value = cedula;

    } else {
        panelRs.style.display     = 'none';
        panelCedula.style.display = 'none';
        // No limpiar inp_arl_nit para no perder valores ya guardados
    }
}

// Almacén de todas las options originales (para restaurarlas)
const _OPTS_MODALIDAD = [];
const _OPTS_PLAN      = [];

// Inicializar stores — se llama antes de DOMContentLoaded,
// usar defer o llamar desde DOMContentLoaded
function _initOptsStore() {
    const selMod = document.getElementById('sel_modalidad');
    const selPln = document.getElementById('sel_plan');
    if (selMod && !_OPTS_MODALIDAD.length) {
        selMod.querySelectorAll('option').forEach(opt => {
            _OPTS_MODALIDAD.push({ el: opt, value: opt.value });
        });
    }
    if (selPln && !_OPTS_PLAN.length) {
        selPln.querySelectorAll('option').forEach(opt => {
            _OPTS_PLAN.push({ el: opt, value: opt.value, text: opt.textContent.trim() });
        });
    }
}

// ── Filtrado 1: RS → Modalidades ─────────────────────────────────
function filtrarModalidades(soloIndependiente) {
    const selMod = document.getElementById('sel_modalidad');
    if (!selMod) return;

    const valorActual = selMod.value;
    // Limpiar select (excepto opción vacía)
    while (selMod.options.length > 0) selMod.remove(0);

    let primerValido = null;
    let valorSigueDisponible = false;

    _OPTS_MODALIDAD.forEach(({ el, value }) => {
        if (!value) {
            selMod.appendChild(el);  // siempre: opción vacía
            return;
        }
        const esIndepOpt = MODALIDADES_INDEP.includes(parseInt(value));
        const mostrar = soloIndependiente ? esIndepOpt : !esIndepOpt;
        if (mostrar) {
            selMod.appendChild(el);
            if (!primerValido) primerValido = value;
            if (value === valorActual) valorSigueDisponible = true;
        }
    });

    // Sincronizar valor seleccionado
    selMod.value = valorSigueDisponible ? valorActual : '';
    // Sincronizar Alpine
    if (!valorSigueDisponible) {
        const alpineComp = document.querySelector('[x-data]')?._x_dataStack?.[0];
        if (alpineComp) { alpineComp.tipoModalidadId = ''; alpineComp.planId = ''; }
    }
    // Filtrar planes y mostrar nota
    filtrarPlanes(selMod.value);
    const divNota = document.getElementById('nota-plan-modalidad');
    if (divNota) divNota.style.display = (!selMod.value ? 'block' : 'none');
}

// ── Filtrado 2: Modalidad → Planes ───────────────────────────────
function filtrarPlanes(modalidadId) {
    const selPlan = document.getElementById('sel_plan');
    if (!selPlan) return;
    const divNota    = document.getElementById('nota-plan-modalidad');

    const valorActual = parseInt(selPlan.value) || 0;
    // Limpiar select
    while (selPlan.options.length > 0) selPlan.remove(0);

    if (!modalidadId) {
        // Sin modalidad: restaurar todas las opciones
        _OPTS_PLAN.forEach(({ el }) => selPlan.appendChild(el));
        if (divNota)    divNota.style.display    = 'block';
        return;
    }

    const permitidos = (MODALIDAD_PLANES[modalidadId] || []).map(Number);
    const esTP       = !!MODALIDADES_TP[parseInt(modalidadId)];
    if (divNota) divNota.style.display = 'none';

    // ── Regla AFP obligatorio ──────────────────────────────────
    // Se activa si: la regla global está ON + modalidad en la lista + cliente NO exento
    const modalidadIdInt     = parseInt(modalidadId);
    const modalidadAfpOblig  = MODALIDADES_AFP_OBLIGATORIO.includes(modalidadIdInt);
    const aplicarAfpObligatorio = REGLA_AFP_ACTIVA && modalidadAfpOblig && !CLIENTE_EXENTO_AFP;


    // ── Regla: RS independiente + modalidad independiente → excluir planes "solo ARL"
    // Un plan "solo ARL" es aquel con arl=1 pero sin EPS, Pensión ni Caja.
    const selRS      = document.getElementById('sel_rs');
    const esIndepRS  = selRS?.options[selRS.selectedIndex]?.dataset?.independiente === '1';
    const esIndepMod = MODALIDADES_INDEP.includes(modalidadIdInt);
    const excluirSoloArl = esIndepRS && esIndepMod;

    let planActualPermitido = false;
    let planesAgregados     = [];

    _OPTS_PLAN.forEach(({ el, value, text }) => {
        if (!value) { selPlan.appendChild(el); return; }  // opción vacía siempre
        const planId = parseInt(value);
        const permitido = permitidos.includes(planId);
        if (!permitido) return;

        // ── Filtro Tiempo Parcial: solo planes sin EPS ──────────
        if (esTP && el.dataset.eps === '1') return;

        // ── Filtro Independiente: excluir planes que son SOLO ARL ──────
        // (independiente no puede cotizar únicamente la ARL)
        if (excluirSoloArl
            && el.dataset.arl  === '1'
            && el.dataset.eps  !== '1'
            && el.dataset.pen  !== '1'
            && el.dataset.caja !== '1') return;

        // ── Regla AFP obligatorio: ocultar planes sin AFP si aplica la regla ──
        // Un plan sin AFP tiene incluye_pension = false (data-pen !== '1')
        if (aplicarAfpObligatorio && el.dataset.pen !== '1') return;

        // Resaltar sin-AFP cuando el cliente puede omitirlo
        if (CLIENTE_EXENTO_AFP) {
            const sinAfp = el.dataset.pen !== '1';
            el.textContent = (sinAfp ? '⭐ ' : '') + text;
        } else {
            el.textContent = text;  // restaurar texto original
        }

        selPlan.appendChild(el);
        planesAgregados.push(planId);
        if (planId === valorActual) planActualPermitido = true;
    });

    // Si el plan ya no aplica, limpiar
    if (valorActual && !planActualPermitido) {
        selPlan.value = '';
        const alpineComp = document.querySelector('[x-data]')?._x_dataStack?.[0];
        if (alpineComp) { alpineComp.planId = ''; }
    } else {
        selPlan.value = valorActual || '';
        if (selPlan.value) bloquearEntidadesPorPlan(selPlan.value);
    }

    // ── Auto-selección: si es TP y solo hay 1 plan válido, seleccionarlo ──
    if (esTP && !selPlan.value && planesAgregados.length === 1) {
        selPlan.value = String(planesAgregados[0]);
        const alpineComp = document.querySelector('[x-data]')?._x_dataStack?.[0];
        if (alpineComp) {
            alpineComp.planId = String(planesAgregados[0]);
            alpineComp.recalcular();
        }
        bloquearEntidadesPorPlan(String(planesAgregados[0]));
    }
}

function actualizarBloqueoArl() {
    const midId   = parseInt(document.querySelector('select[name=tipo_modalidad_id]')?.value || 0);
    const selRS   = document.getElementById('sel_rs');
    const arlSel  = document.getElementById('sel_arl');
    const lbl     = document.getElementById('lbl_arl_lock');

    // Libre por modalidad (Independiente Activo, Independiente Vencido, etc.)
    const libreParaModalidad = MODALIDADES_ARL_LIBRE.includes(midId);
    // Libre también cuando la Razón Social es del tipo Independiente (es_independiente=1)
    const esIndepRS = selRS?.options[selRS.selectedIndex]?.dataset?.independiente === '1';
    const libre = libreParaModalidad || esIndepRS;

    arlSel.disabled         = !libre;
    arlSel.style.background = libre ? '#fff' : '#f8fafc';
    if (lbl) lbl.textContent = libre ? '(editable)' : '(de la R.Social)';
    // Restricción de niveles ARL para modalidad id=8 con RS no-independiente
    actualizarNivelesArl();
}

/**
 * Cuando la RS seleccionada NO es independiente y la modalidad es id=8,
 * sólo se permiten los niveles de ARL 4 y 5.
 * En cualquier otro caso se restauran todos los niveles (1 al 5).
 */
function actualizarNivelesArl() {
    const selMod    = document.querySelector('select[name=tipo_modalidad_id]');
    const selRS     = document.getElementById('sel_rs');
    const selNivel  = document.querySelector('select[name=n_arl]');
    if (!selNivel) return;

    const modalidadId  = parseInt(selMod?.value || 0);
    const esIndepRS    = selRS?.options[selRS.selectedIndex]?.dataset?.independiente === '1';
    // Condición: modalidad 8 + RS no independiente
    const soloNivel4y5 = (modalidadId === 8) && !esIndepRS;

    // Guardar valor actual antes de manipular opciones
    const nivelActual = parseInt(selNivel.value || 1);

    // Limpiar y reconstruir opciones
    selNivel.innerHTML = '';
    const nivelesPermitidos = soloNivel4y5 ? [4, 5] : [1, 2, 3, 4, 5];
    nivelesPermitidos.forEach(n => {
        const opt = document.createElement('option');
        opt.value = n;
        opt.textContent = n;
        selNivel.appendChild(opt);
    });

    // Restaurar nivel si sigue siendo válido, si no usar el menor disponible
    if (nivelesPermitidos.includes(nivelActual)) {
        selNivel.value = nivelActual;
    } else {
        selNivel.value = nivelesPermitidos[0];
    }

    // Sincronizar Alpine x-model
    const alpineComp = document.querySelector('[x-data]')?._x_dataStack?.[0];
    if (alpineComp) {
        alpineComp.nivelArl = parseInt(selNivel.value);
        alpineComp.recalcular();
    }

    // Indicador visual: marcar el selector con borde si está restringido
    selNivel.style.border   = soloNivel4y5 ? '2px solid #f59e0b' : '1px solid #cbd5e1';
    selNivel.title          = soloNivel4y5 ? 'Modalidad id=8 con RS empresa: solo niveles 4 y 5' : '';
}

// Estilos base para entidades
const STYLE_REQUERIDO  = 'width:100%;padding:0.38rem 0.5rem;border:2px solid #ef4444;border-radius:6px;font-size:0.82rem;background:#fff;box-sizing:border-box;';
const STYLE_DESHABILITADO = 'width:100%;padding:0.38rem 0.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:0.82rem;background:#f1f5f9;color:#94a3b8;box-sizing:border-box;cursor:not-allowed;';
const STYLE_NORMAL     = 'width:100%;padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem;background:#fff;box-sizing:border-box;';
const STYLE_COMPLETO   = 'width:100%;padding:0.38rem 0.5rem;border:2px solid #22c55e;border-radius:6px;font-size:0.82rem;background:#f0fdf4;box-sizing:border-box;';

function aplicarEstadoEntidad(sel, incluido) {
    if (!sel) return;
    if (!incluido) {
        // Deshabilitado: no aplica este plan
        sel.disabled  = true;
        sel.required  = false;
        sel.style.cssText = STYLE_DESHABILITADO;
        sel.value     = '';   // limpiar
    } else {
        // Habilitado y requerido
        sel.disabled  = false;
        sel.required  = true;
        // Color segun si tiene valor
        if (!sel.value) {
            sel.style.cssText = STYLE_REQUERIDO;
        } else {
            sel.style.cssText = STYLE_COMPLETO;
        }
    }
}

function bloquearEntidadesPorPlan(planId) {
    const d      = PLAN_DATA[planId] || null;
    const epsSel = document.getElementById('sel_eps');
    const penSel = document.getElementById('sel_pen');
    const arlSel = document.getElementById('sel_arl');
    const cajSel = document.getElementById('sel_caja');

    if (!d) {
        [epsSel,penSel,arlSel,cajSel].forEach(s => { if(s) { s.disabled=false; s.required=false; s.style.cssText=STYLE_NORMAL; } });
        return;
    }

    aplicarEstadoEntidad(epsSel, d.eps);
    aplicarEstadoEntidad(penSel, d.pen);
    aplicarEstadoEntidad(cajSel, d.caja);

    // ARL especial
    if (!d.arl) {
        aplicarEstadoEntidad(arlSel, false);
        // Ocultar panel Modo ARL e inactivar nivel ARL cuando el plan no incluye ARL
        const panelModoArl = document.getElementById('panel-modo-arl');
        const selNivelArl  = document.querySelector('select[name=n_arl]');
        if (panelModoArl) panelModoArl.style.display = 'none';
        if (selNivelArl) {
            selNivelArl.disabled = true;
            selNivelArl.style.background = '#f1f5f9';
            selNivelArl.style.opacity    = '0.5';
            selNivelArl.style.cursor     = 'not-allowed';
        }
    } else {
        actualizarBloqueoArl();
        arlSel.required = true;
        arlSel.style.cssText = arlSel.value ? STYLE_COMPLETO : STYLE_REQUERIDO;
        // Restaurar panel Modo ARL y nivel ARL si la modalidad lo permite
        const panelModoArl = document.getElementById('panel-modo-arl');
        const selNivelArl  = document.querySelector('select[name=n_arl]');
        const midId = parseInt(document.querySelector('select[name=tipo_modalidad_id]')?.value || 0);
        if (panelModoArl) {
            // Solo mostrar el panel si la modalidad lo habilita (Alpine lo controla con x-show,
            // pero forzamos display para el caso en que Alpine ya lo tenía visible)
            const alpineComp = document.querySelector('[x-data]')?._x_dataStack?.[0];
            if (alpineComp && alpineComp.mostrarModoArl) panelModoArl.style.display = '';
        }
        if (selNivelArl) {
            selNivelArl.disabled = false;
            selNivelArl.style.background = '';
            selNivelArl.style.opacity    = '';
            selNivelArl.style.cursor     = '';
        }
    }

    // % Caja: ocultar cuando el plan no incluye caja
    const divPctCaja = document.getElementById('div-pct-caja');
    if (divPctCaja) {
        // Solo aplica si estamos en modalidad independiente (de lo contrario Alpine ya lo oculta)
        const alpineComp = document.querySelector('[x-data]')?._x_dataStack?.[0];
        const esIndep = alpineComp ? alpineComp.esIndependiente : false;
        if (esIndep) {
            divPctCaja.style.display = d.caja ? '' : 'none';
        }
    }

    // Auto-seleccionar entidades del cliente si el campo quedó habilitado pero vacío
    if (d.eps  && epsSel  && !epsSel.value  && CLIENTE_EPS_ID)     { epsSel.value  = CLIENTE_EPS_ID;     epsSel.style.cssText  = STYLE_COMPLETO; }
    if (d.pen  && penSel  && !penSel.value  && CLIENTE_PENSION_ID) { penSel.value  = CLIENTE_PENSION_ID; penSel.style.cssText  = STYLE_COMPLETO; }

    // Actualizar en tiempo real al seleccionar una entidad
    [epsSel, penSel, arlSel, cajSel].forEach(sel => {
        if (!sel) return;
        sel.onchange = function() {
            if (sel.required) {
                sel.style.cssText = sel.value ? STYLE_COMPLETO : STYLE_REQUERIDO;
            }
            // Recalcular solo para ARL (afecta el %)
            if (sel === arlSel) {
                const alpineComp = document.querySelector('[x-data]')?._x_dataStack?.[0];
                if (alpineComp) alpineComp.recalcular();
            }
        };
    });
}

// Validacion al enviar el formulario
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-contrato');
    if (form) {
        form.addEventListener('submit', function(e) {
            const planId = document.getElementById('sel_plan')?.value;
            const d = PLAN_DATA[planId] || {};
            const checks = [
                { sel: 'sel_eps',   incl: d.eps,  nombre: 'EPS' },
                { sel: 'sel_pen',   incl: d.pen,  nombre: 'AFP/Pensión' },
                { sel: 'sel_arl',   incl: d.arl,  nombre: 'ARL' },
                { sel: 'sel_caja',  incl: d.caja, nombre: 'Caja' },
            ];
            let hayError = false;
            checks.forEach(({ sel, incl, nombre }) => {
                const el = document.getElementById(sel);
                if (!el || !incl) return;
                if (!el.value) {
                    el.style.cssText = STYLE_REQUERIDO;
                    hayError = true;
                }
            });
            if (hayError) {
                e.preventDefault();
                alert('Por favor seleccione todas las entidades requeridas por el plan (marcadas en rojo).');
            }
        });
    }
});

function onAsesorChange(sel) {
    const opt      = sel.options[sel.selectedIndex];
    const admonAse = parseFloat(opt?.dataset?.admon || 0);
    const admonIp  = document.getElementById('inp_admon_asesor');
    const admonTot = document.getElementById('inp_admon');
    if (!admonIp) return;

    if (!sel.value) {
        // Sin asesor: admon_asesor = 0, admon queda completo
        admonIp.value = 0;
    } else {
        // Con asesor: admon_asesor = su comision, admon = admon_plan - comision
        admonIp.value = admonAse;
        if (admonTot) {
            const admonActual = parseFloat(admonTot.value || 0);
            // Solo resta si el admon actual NO fue ya reducido (es mayor que admonAse)
            // Usamos el dataset del option del plan como referencia
            const planOpt = document.querySelector('#sel_plan option[value="'+ (document.getElementById('sel_plan')?.value||'') +'"]');
            const admonPlan = planOpt ? parseFloat(planOpt.dataset.admonPlan || admonActual) : admonActual;
            // Guardar el admon del plan en el option para futuras referencias
            if (planOpt && !planOpt.dataset.admonPlan) planOpt.dataset.admonPlan = admonActual;
            admonTot.value = Math.max(0, (planOpt?.dataset.admonPlan ? parseFloat(planOpt.dataset.admonPlan) : admonActual) - admonAse);
            // Actualizar Alpine
            const alpineData = document.querySelector('[x-data]')?._x_dataStack?.[0];
            if (alpineData) { alpineData.admon = parseFloat(admonTot.value); alpineData.recalcular(); }
        }
    }
}

function updateRadicado(id, campo, valor) {
    fetch(`${URL_RADICADO}/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ [campo]: valor })
    }).catch(console.error);
}

document.addEventListener('DOMContentLoaded', () => {
    // ── Inicializar stores de opciones (DEBE ser primero) ──────────
    _initOptsStore();

    // ── Aplicar filtros al cargar (modo edición) ──────────────────
    const selRS  = document.getElementById('sel_rs');
    const selMod = document.getElementById('sel_modalidad');
    const selPln = document.getElementById('sel_plan');

    if (selRS?.value) {
        const esIndepRS = selRS.options[selRS.selectedIndex]?.dataset?.independiente === '1';
        filtrarModalidades(esIndepRS);   // filtra modalidades sin disparar selectOnChange
    }
    if (selMod?.value) {
        filtrarPlanes(selMod.value);     // filtra planes sin limpiar selección
    }

    actualizarBloqueoArl();  // incluye actualizarNivelesArl()
    // ⚡ Asignar ARL de la RS ANTES de bloquearEntidadesPorPlan para que el
    //    estilo se calcule con el valor ya presente (evita borde rojo falso).
    if (ARL_ID_RS && !document.getElementById('sel_arl').value) {
        document.getElementById('sel_arl').value = ARL_ID_RS;
    }
    const planId = selPln?.value;
    if (planId) bloquearEntidadesPorPlan(planId);

    // Nota inicial si no hay modalidad seleccionada
    if (!selMod?.value && document.getElementById('nota-plan-modalidad')) {
        document.getElementById('nota-plan-modalidad').style.display = 'block';
    }

    // ── Inicializar paneles Modo ARL (modo edición: restaurar estado) ─
    const modoArlSel = document.getElementById('sel_arl_modo');
    if (modoArlSel?.value) {
        mostrarPanelArlSegunModo(modoArlSel.value);
    }

    // ── Cotizador inicial: disparo directo con vanilla JS ─────────
    const cotizarInicial = () => {
        const fechaInp   = document.querySelector('input[name=fecha_ingreso]')?.value || '';
        // Calcular dias antes del fetch (igual que Alpine)
        let diasInit = 30;
        if (fechaInp) {
            const f = new Date(fechaInp + 'T00:00:00');
            const h = new Date();
            if (f.getFullYear() === h.getFullYear() && f.getMonth() === h.getMonth()) {
                diasInit = Math.max(1, 30 - f.getDate() + 1);
            }
        }
        // Sincronizar el select de dias
        const selDias = document.getElementById('sel_dias_cotizar');
        if (selDias) selDias.value = diasInit;

        const salario     = parseInt(document.getElementById('inp_salario')?.dataset.raw || 0);
        const planIdVal   = document.getElementById('sel_plan')?.value || '';
        const modalidadId = document.querySelector('select[name=tipo_modalidad_id]')?.value || '';
        const nArl        = parseInt(document.querySelector('select[name=n_arl]')?.value || 1);
        const admon       = parseInt(document.getElementById('inp_admon')?.dataset.raw || 0);
        const admonAse    = parseInt(document.getElementById('inp_admon_asesor')?.dataset.raw || 0);
        const seguro      = parseInt(document.getElementById('inp_seguro')?.dataset.raw || 0);
        const pctCaja     = parseFloat(document.querySelector('select[name=porcentaje_caja]')?.value || 4);
        const cedula      = document.querySelector('input[name=cedula]')?.value || '';

        if (!salario || !planIdVal) return;

        // Calcular IBC correcto: independiente = 40% con piso en salario mínimo
        const esIndepInit = MODALIDADES_INDEP.includes(parseInt(modalidadId));
        let ibcInicial = salario;
        if (esIndepInit && salario > 0) {
            const raw40 = Math.round(salario * {{ $pctIbcSugerido }} / 100);
            ibcInicial  = Math.max(raw40, SALARIO_MINIMO);
        }
        // Si Alpine ya calculó el IBC correcto, respetarlo (evitar sobrescribir)
        const alpineData = document.querySelector('[x-data]')?._x_dataStack?.[0];
        const ibcAlpine  = alpineData?.ibc;
        if (ibcAlpine && ibcAlpine > 0 && esIndepInit) ibcInicial = ibcAlpine;

        fetch(URL_COTIZAR, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                tipo_modalidad_id: modalidadId,
                plan_id:           planIdVal,
                n_arl:             nArl,
                salario:           salario,
                ibc:               ibcInicial,
                administracion:    admon,
                admon_asesor:      admonAse,
                seguro:            seguro,
                porcentaje_caja:   pctCaja || 4,
                dias:              diasInit,
                cedula
            }),
        })
        .then(r => r.json())
        .then(d => {
            const fmt = v => '$' + Math.round(v||0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            // Solo actualizar si Alpine aún no tomó el control
            const alpine = document.querySelector('[x-data]')?._x_dataStack?.[0];
            if (!alpine?.result?.total) {
                document.querySelectorAll('[x-text="fmt(result.eps)"]').forEach(el => el.textContent = fmt(d.eps));
                document.querySelectorAll('[x-text="fmt(result.arl)"]').forEach(el => el.textContent = fmt(d.arl));
                document.querySelectorAll('[x-text="fmt(result.pen)"]').forEach(el => el.textContent = fmt(d.pen));
                document.querySelectorAll('[x-text="fmt(result.caja)"]').forEach(el => el.textContent = fmt(d.caja));
                document.querySelectorAll('[x-text="fmt(result.ss)"]').forEach(el => el.textContent = fmt(d.ss));
                document.querySelectorAll('[x-text="fmt(result.seguro)"]').forEach(el => el.textContent = fmt(d.seguro));
                document.querySelectorAll('[x-text="fmt(result.admon)"]').forEach(el => el.textContent = fmt(d.admon));
                document.querySelectorAll('[x-text="fmt(result.iva)"]').forEach(el => el.textContent = fmt(d.iva));
                document.querySelectorAll('[x-text="fmt(result.total)"]').forEach(el => el.textContent = fmt(d.total));
            }
            // IBC: siempre mostrar el calculado (no el salario completo)
            document.querySelectorAll('[x-text="fmt(ibc)"]').forEach(el => el.textContent = fmt(ibcInicial));
        })
        .catch(err => console.error('Cotizador inicial error:', err));
    };

    // Esperar un poco para que Alpine inicialice también
    setTimeout(cotizarInicial, 400);
});

// ── Alpine.js cotizador ──────────────────────────────────────────
function cotizador() {
    return {
        salario:         {{ $defSalario }},
        ibc:             {{ $defIbc }},
        admon:           {{ $defAdmon }},
        seguro:          {{ $defSeguro }},
        nivelArl:        {{ (int)old('n_arl', $contrato->n_arl ?? 1) }},
        pctCaja:         {{ (float)old('porcentaje_caja', $contrato->porcentaje_caja ?? 4) }},
        planId:          '{{ $esEdicion ? old('plan_id', $contrato->plan_id ?? '') : '' }}',
        planNombre:      '',
        tipoModalidadId: '{{ old('tipo_modalidad_id', $contrato->tipo_modalidad_id ?? '') }}',
        esIndependiente: false,
        esTiempoParcial: false,
        mostrarModoArl:  false,
        ibcSugFmt:       '',
        pctEps:0, pctPen:0, pctArl:0, pctCajaCalc:0,
        diasCotizar: 30,
        diasArl: 0, diasAfp: 0, diasCaja: 0,
        result: { eps:0, arl:0, pen:0, caja:0, ss:0, seguro:0, admon:0, iva:0, total:0 },

        fmt(v) {
            // Formato colombiano: punto de miles, sin decimales (ej: 1.750.905)
            const n = Math.round(v || 0);
            return '$' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        },

        init() {
            const opt = document.querySelector(`select[name=tipo_modalidad_id] option[value="${this.tipoModalidadId}"]`);
            this.esIndependiente = opt?.dataset.independiente === '1';
            this.mostrarModoArl  = MODALIDADES_MODO_ARL.includes(parseInt(this.tipoModalidadId));
            this.planNombre      = document.querySelector(`#sel_plan option[value="${this.planId}"]`)?.textContent?.trim() || '';
            // Inicializar Tiempo Parcial al cargar
            const tpData = MODALIDADES_TP[parseInt(this.tipoModalidadId)] || null;
            this.esTiempoParcial = !!tpData;
            if (tpData) {
                this.diasArl  = tpData.dias_arl;
                this.diasAfp  = tpData.dias_afp;
                this.diasCaja = tpData.dias_caja;
            }
            // Sincronizar IBC según modalidad
            if (!this.esIndependiente) {
                this.ibc = this.salario;
            } else if (this.salario > 0) {
                // En modo edición: si ya hay IBC guardado, respetar; si no, auto-calcular
                if (!this.ibc || this.ibc <= 0) {
                    this.calcularIbcIndependiente();
                } else {
                    // Solo actualizar el badge sugerido
                    const sug = this.ibcSugerido();
                    this.ibcSugFmt = this.fmt(sug);
                }
            }
            // Auto-calcular dias desde fecha_ingreso al iniciar
            this.calcularDiasDesde(document.querySelector('input[name=fecha_ingreso]')?.value);
            // Escuchar cambios en fecha_ingreso
            const fecInp = document.querySelector('input[name=fecha_ingreso]');
            if (fecInp) fecInp.addEventListener('change', e => {
                this.calcularDiasDesde(e.target.value);
                this.recalcular();
            });
            // Disparar cotizacion inicial después de que Alpine termine de montar
            setTimeout(() => {
                if (this.salario > 0 && this.planId) this.recalcular();
            }, 300);
        },

        /** Calcula el IBC sugerido (40% del salario) con piso en salario_minimo */
        ibcSugerido() {
            // Leer salario real del campo money (puede estar en dataset.raw)
            const inpSal = document.getElementById('inp_salario');
            const salReal = parseInt(inpSal?.dataset?.raw || inpSal?.value?.replace(/\./g,'') || this.salario || 0);
            if (salReal > 0) this.salario = salReal;
            const raw = Math.round(this.salario * {{ $pctIbcSugerido }} / 100);
            return Math.max(raw, SALARIO_MINIMO);
        },

        /**
         * Auto-llena el campo IBC con el valor sugerido:
         * = max(salario_minimo, salario * {{ $pctIbcSugerido }}%)
         * Actualiza Alpine + el <input> visible + el badge sugerido.
         */
        calcularIbcIndependiente() {
            const sug = this.ibcSugerido();
            this.ibc       = sug;
            this.ibcSugFmt = this.fmt(sug);
            // Sincronizar el <input name="ibc"> visible
            const inpIbc = document.querySelector('input[name="ibc"]');
            if (inpIbc) inpIbc.value = sug;
        },

        onSalarioChange() {
            // Leer el valor real del campo money (puede estar formateado con puntos)
            const inpSal = document.getElementById('inp_salario');
            const salReal = parseInt(inpSal?.dataset?.raw || inpSal?.value?.replace(/\./g,'') || 0);
            if (salReal > 0) this.salario = salReal;

            if (!this.esIndependiente) {
                // Dependiente: IBC siempre = salario
                this.ibc = this.salario;
            } else if (this.salario > 0) {
                // Independiente: IBC = max(salario_minimo, salario * {{ $pctIbcSugerido }}%)
                this.calcularIbcIndependiente();
            } else {
                this.ibcSugFmt = '';
            }
            this.recalcular();
        },
        // Calcula dias a cotizar segun fecha_ingreso
        // Si es el mes actual: dias = 30 - dia + 1
        // Si es otro mes: 30 (mes completo)
        calcularDiasDesde(fechaStr) {
            if (!fechaStr) { this.diasCotizar = 30; return; }
            const fecha  = new Date(fechaStr + 'T00:00:00'); // evitar timezone
            const hoy    = new Date();
            if (fecha.getFullYear() === hoy.getFullYear() && fecha.getMonth() === hoy.getMonth()) {
                const dia = fecha.getDate();
                this.diasCotizar = Math.max(1, 30 - dia + 1);
            } else {
                this.diasCotizar = 30;
            }
            // Sincronizar el select
            const sel = document.getElementById('sel_dias_cotizar');
            if (sel) sel.value = this.diasCotizar;
        },


        onModalidadChange(e) {
            const opt = e.target.options[e.target.selectedIndex];
            const id  = parseInt(e.target.value || 0);
            this.esIndependiente = MODALIDADES_INDEP.includes(id);
            this.mostrarModoArl  = MODALIDADES_MODO_ARL.includes(id);
            this.pctCaja = this.esIndependiente ? 2 : 4;
            // Tiempo Parcial
            const tpData = MODALIDADES_TP[id] || null;
            this.esTiempoParcial = !!tpData;
            if (tpData) {
                this.diasArl  = tpData.dias_arl;
                this.diasAfp  = tpData.dias_afp;
                this.diasCaja = tpData.dias_caja;
                // ── Auto-rellenar Salario e IBC según factor del plan ──
                const factor     = tpData.factor_salario || 1;
                const salarioTP  = Math.round(SALARIO_MINIMO * factor);
                this.salario     = salarioTP;
                this.ibc         = salarioTP;
                // Actualizar el campo .campo-money del salario
                const inpSal = document.getElementById('inp_salario');
                if (inpSal) {
                    inpSal.dataset.raw = salarioTP;
                    inpSal.value       = numFmt(salarioTP);
                }
            } else {
                this.diasArl = this.diasAfp = this.diasCaja = 0;
            }
            if (!this.esIndependiente) {
                // Dependiente: IBC = salario
                this.ibc = this.salario;
                this.ibcSugFmt = '';
            } else if (this.salario > 0) {
                // Al pasar a independiente: auto-calcular IBC
                this.calcularIbcIndependiente();
            }
            actualizarBloqueoArl();
            filtrarPlanes(e.target.value);
            this.recalcular();
        },


        onPlanChange(e) {
            this.planNombre = e.target.options[e.target.selectedIndex]?.textContent?.trim() || '';
            bloquearEntidadesPorPlan(this.planId);
        fetch(`${URL_TARIFAS}?plan_id=${this.planId}`)
                .then(r => r.json())
                .then(d => {
                    const f        = document.getElementById('form-contrato');
                    const asesorSel = document.getElementById('sel_asesor');
                    const asesorOpt = asesorSel?.options[asesorSel.selectedIndex];
                    const admonAse  = parseFloat(asesorOpt?.dataset?.admon || 0);
                    const tieneAsesor = asesorSel?.value && parseFloat(asesorSel.value) > 0;

                    // Seguro y costo (solo actualizar si la API devuelve valor > 0)
                    if (d.seguro > 0) {
                        const inpSeg = document.getElementById('inp_seguro');
                        if (inpSeg) { inpSeg.dataset.raw = d.seguro; inpSeg.value = numFmt(d.seguro); }
                        this.seguro = d.seguro;
                    }
                    if (d.costo_afiliacion > 0) {
                        const inpCosto = document.getElementById('inp_costo');
                        if (inpCosto) { inpCosto.dataset.raw = d.costo_afiliacion; inpCosto.value = numFmt(d.costo_afiliacion); }
                    }
                    if (d.encargado_id && f.elements['encargado_id']) f.elements['encargado_id'].value = d.encargado_id;

                    // Admon: solo actualizar si la API devuelve valor > 0 (no borrar valor existente)
                    if (d.administracion > 0) {
                        const admonPlan  = d.administracion;
                        const planOpt    = e.target.options[e.target.selectedIndex];
                        if (planOpt) planOpt.dataset.admonPlan = admonPlan;

                        const admonFinal = tieneAsesor ? Math.max(0, admonPlan - admonAse) : admonPlan;
                        this.admon       = admonFinal;
                        const inpAdmon   = document.getElementById('inp_admon');
                        if (inpAdmon) { inpAdmon.dataset.raw = admonFinal; inpAdmon.value = numFmt(admonFinal); }
                    }
                    // Admon asesor
                    if (d.administracion > 0 || tieneAsesor) {
                        const admonAsesorInput = document.getElementById('inp_admon_asesor');
                        const admonAseVal = tieneAsesor ? (d.admon_asesor ?? admonAse) : 0;
                        if (admonAsesorInput) { admonAsesorInput.dataset.raw = admonAseVal; admonAsesorInput.value = numFmt(admonAseVal); }
                    }

                    this.recalcular();
                }).catch(() => this.recalcular());
        },

        onActividadChange(e) {
            const nivel = parseInt(e.target.options[e.target.selectedIndex]?.dataset.nivel || 1);
            this.nivelArl = nivel;
            document.querySelector('select[name=n_arl]').value = nivel;
            this.recalcular();
        },

        recalcular() {
            const cedula  = document.querySelector('input[name=cedula]')?.value || '';
            // Leer salario del campo money (dataset.raw) o del Alpine state
            const salRaw  = parseInt(document.getElementById('inp_salario')?.dataset.raw || this.salario || 0);
            if (salRaw > 0 && salRaw !== this.salario) this.salario = salRaw;
            const ibcVal  = (this.esIndependiente && this.ibc > 0) ? this.ibc : (salRaw || this.salario);
            if (!this.planId || !this.salario) return;
            fetch(URL_COTIZAR, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    tipo_modalidad_id: this.tipoModalidadId,
                    plan_id:           this.planId,
                    n_arl:             this.nivelArl,
                    salario:           this.salario,
                    ibc:               ibcVal,
                    administracion:    this.admon || 0,
                    admon_asesor:      parseInt(document.getElementById('inp_admon_asesor')?.dataset?.raw || document.getElementById('inp_admon_asesor')?.value?.replace(/\./g,'') || 0),
                    seguro:            this.seguro || 0,
                    porcentaje_caja:   this.pctCaja || 4,
                    dias:              parseInt(this.diasCotizar) || 30,
                    cedula
                }),
            })
            .then(r => { if (!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
            .then(d => {
                this.result = {
                    eps:    d.eps    ?? 0,
                    arl:    d.arl    ?? 0,
                    pen:    d.pen    ?? 0,
                    caja:   d.caja   ?? 0,
                    ss:     d.ss     ?? 0,
                    seguro: d.seguro ?? 0,
                    admon:  d.admon  ?? 0,
                    iva:    d.iva    ?? 0,
                    total:  d.total  ?? 0
                };
                this.pctEps      = d.pctEps  ?? 0;
                this.pctPen      = d.pctPen  ?? 0;
                this.pctArl      = d.pctArl  ?? 0;
                this.pctCajaCalc = d.pctCaja ?? 0;
                if (d.ibcSugerido && this.esIndependiente) this.ibcSugFmt = this.fmt(d.ibcSugerido);
                // Tiempo Parcial: actualizar días y flag desde el servidor
                if (typeof d.es_tiempo_parcial !== 'undefined') {
                    this.esTiempoParcial = !!d.es_tiempo_parcial;
                }
                if (d.es_tiempo_parcial) {
                    this.diasArl  = d.dias_arl  ?? 0;
                    this.diasAfp  = d.dias_afp  ?? 0;
                    this.diasCaja = d.dias_caja ?? 0;
                }
            })
            .catch(err => console.warn('Cotizador error:', err));
        },
    };
}

// ── Modal Unificado de Facturación (modo individual) ──────────────────
const FC_CSRF          = document.querySelector('meta[name="csrf-token"]').content;
const FC_URL_FAC       = '{{ route('admin.facturacion.facturar') }}';
const FC_URL_MES_PAG   = '{{ url('admin/facturacion/api/mes-pagado') }}';
const FC_CONTRATO_ID   = {{ $esEdicion ? $contrato->id : 'null' }};
const FC_FECHA_ING_MES = {{ $contrato->fecha_ingreso ? $contrato->fecha_ingreso->month : 0 }};
const FC_FECHA_ING_ANO = {{ $contrato->fecha_ingreso ? $contrato->fecha_ingreso->year : 0 }};
const FC_ES_INDEP      = {{ $contrato->tipoModalidad?->esIndependiente() ? 'true' : 'false' }};
const FC_COSTO_AFIL    = {{ (int)($contrato->costo_afiliacion ?? 0) }};
const FC_ARL_NIVEL     = {!! json_encode($contrato->n_arl ?? '') !!};
const FC_DIST_DEFAULTS = {
    asesor:    {{ (int)($contrato->asesor?->comision_afil_valor ?? 0) }},
    retiro:    0,
    encargado: 0,
};

if (typeof MF !== 'undefined' && FC_CONTRATO_ID) {
    MF.init({
        modo:              'individual',
        urlFacturar:       FC_URL_FAC,
        urlMesPagado:      FC_URL_MES_PAG,
        csrf:              FC_CSRF,
        contratoId:        FC_CONTRATO_ID,
        fechaIngresoMes:   FC_FECHA_ING_MES,
        fechaIngresoAnio:  FC_FECHA_ING_ANO,
        esIndependiente:   FC_ES_INDEP,
        costoAfiliacion:   FC_COSTO_AFIL,
        arlNivel:          FC_ARL_NIVEL,
        distDefaults:      FC_DIST_DEFAULTS,
        getAlpineResult:   () => document.querySelector('[x-data]')?._x_dataStack?.[0]?.result || {},
        onExito: (data) => {
            if (data.recibo_url) window.open(data.recibo_url, '_blank');
            @if(request()->has('iframe'))
            // Modo iframe: notificar al padre para actualizar solo esta fila
            if (window.parent !== window) {
                window.parent.postMessage(
                    { type: 'brynex:iframe_done', accion: 'facturacion', contratoId: FC_CONTRATO_ID, mensaje: data.mensaje },
                    window.location.origin
                );
            }
            @else
            alert(data.mensaje || 'Factura generada correctamente.');
            @endif
        }
    });
}

function abrirModalFacturarContrato() {
    if (typeof MF !== 'undefined') {
        MF.abrir(
            [FC_CONTRATO_ID],
            '{{ $cliente?->primer_nombre }} {{ $cliente?->primer_apellido }}'
        );
    }
}

// Alias para compatibilidad con botones que todavía llaman a funciones fc*
// Todas las funciones del modal han sido reemplazadas por MF (modal_facturar.js)












@if(request()->has('iframe') && session('success') === 'Contrato retirado correctamente.')
// Modo iframe: retiro completado → notificar al padre
if (window.parent !== window) {
    window.parent.postMessage(
        { type: 'brynex:iframe_done', accion: 'retiro', contratoId: {{ $contrato->id ?? 'null' }}, mensaje: 'Contrato retirado correctamente.' },
        window.location.origin
    );
}
@endif

@if(request()->has('iframe') && session('success') && session('success') !== 'Contrato retirado correctamente.')
// Modo iframe: actualización normal del contrato → notificar al padre
if (window.parent !== window) {
    window.parent.postMessage(
        { type: 'brynex:iframe_done', accion: 'update', contratoId: {{ $contrato->id ?? 'null' }}, mensaje: {!! json_encode(session('success')) !!} },
        window.location.origin
    );
}
@endif

// ── Loading state en botón Guardar ──────────────────────────────────────
(function () {
    const btn  = document.getElementById('btn-guardar-contrato');
    const ico  = document.getElementById('btn-guardar-ico');
    const txt  = document.getElementById('btn-guardar-txt');
    if (!btn) return;

    // Detectar el form padre del botón
    const form = btn.closest('form');
    if (!form) return;

    form.addEventListener('submit', function () {
        // Evitar doble clic
        btn.disabled = true;
        btn.style.opacity = '0.75';
        btn.style.cursor  = 'not-allowed';

        // Spinner SVG inline
        ico.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="animation:spin-btn .7s linear infinite"><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg>';
        txt.textContent = 'Guardando…';
    });
})();

// ── Modal Historial de Pagos ───────────────────────────────────────────────
@if($esEdicion)
const HISTORIAL_URL = '{{ route('admin.facturacion.historial', $contrato->cedula) }}';

function abrirHistorialPagos() {
    const modal  = document.getElementById('modal-historial');
    const iframe = document.getElementById('historial-frame');
    if (!modal || !iframe) return;
    iframe.src = HISTORIAL_URL + '?iframe=1';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarHistorial() {
    const modal  = document.getElementById('modal-historial');
    const iframe = document.getElementById('historial-frame');
    if (modal)  modal.style.display = 'none';
    if (iframe) iframe.src = '';
    document.body.style.overflow = '';
}
@endif
</script>
@endpush

@endsection
