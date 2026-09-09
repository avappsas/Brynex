@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Expediente de préstamo')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" style="max-width: 980px;">

    @component('finanzas.partials._header_banner', [
        'titulo' => '📄 '.$exp->pagare_numero,
        'subtitulo' => $exp->deudor_nombre.' · '.$exp->estado_texto,
        'breadcrumb' => [
            'Finanzas Personales' => route('finanzas.dashboard'),
            'Préstamos formales' => route('finanzas.expedientes.index'),
            $exp->pagare_numero => null,
        ],
    ])
        @slot('opciones')
            @if($exp->estado !== 'desembolsado')
                <a href="{{ route('finanzas.expedientes.edit', $exp->id) }}" class="btn-fin-link">✏️ Editar datos</a>
            @endif
            @if($exp->prestamo_id)
                <a href="{{ route('finanzas.prestamos.show', $exp->prestamo_id) }}" class="btn-fin success">📈 Ver el préstamo</a>
            @endif
        @endslot
    @endcomponent

    @include('finanzas.prestamos.expedientes._avisos')

    @if($faltanDatosPrestamista)
        <div style="margin-top:1rem; background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:0.75rem 1rem; border-radius:10px; font-size:0.82rem;">
            ⚠️ A <strong>{{ $exp->prestamista->nombre }}</strong> le falta: {{ implode(', ', $faltanDatosPrestamista) }}.
            Los documentos saldrán con espacios en blanco —
            <a href="{{ route('finanzas.expedientes.index') }}" style="color:#b45309; font-weight:700;">complétalos aquí</a>.
        </div>
    @endif

    {{-- Resumen --}}
    <div class="exp-panel" style="margin-top:1.25rem; padding:1.1rem 1.25rem;">
        <div style="display:flex; gap:2rem; flex-wrap:wrap;">
            <div>
                <div class="exp-dato-label">Monto</div>
                <div class="exp-dato-valor">${{ number_format($exp->monto, 0, ',', '.') }}</div>
            </div>
            <div>
                <div class="exp-dato-label">Interés mensual</div>
                <div class="exp-dato-valor">${{ number_format($exp->interes_mensual, 0, ',', '.') }}</div>
                <div class="exp-dato-sub">{{ rtrim(rtrim(number_format($exp->tasa_interes_mensual, 3, ',', ''), '0'), ',') }}% sobre saldo</div>
            </div>
            <div>
                <div class="exp-dato-label">Plazo del capital</div>
                <div class="exp-dato-valor">{{ $exp->plazo_meses }} meses</div>
                <div class="exp-dato-sub">vence {{ $exp->fecha_vencimiento->format('d/m/Y') }}</div>
            </div>
            <div>
                <div class="exp-dato-label">Corte mensual</div>
                <div class="exp-dato-valor">día {{ $exp->dia_cobro }}</div>
            </div>
            <div>
                <div class="exp-dato-label">Tope del pagaré</div>
                <div class="exp-dato-valor">${{ number_format($exp->pagare_tope, 0, ',', '.') }}</div>
                <div class="exp-dato-sub">{{ $exp->pagare_factor }} veces el monto</div>
            </div>
            <div>
                <div class="exp-dato-label">A nombre de</div>
                <div class="exp-dato-valor" style="font-size:0.9rem;">{{ $exp->prestamista->nombre }}</div>
                <div class="exp-dato-sub">contrato en {{ $exp->ciudad }}</div>
            </div>
        </div>

        <div style="margin-top:1rem; padding-top:0.9rem; border-top:1px dashed #e2e8f0; font-size:0.8rem; color:#334155;">
            <strong>Deudor:</strong> {{ $exp->deudor_nombre }} · C.C. {{ $exp->deudor_cedula }}{{ $exp->deudor_expedida_en ? ' de '.$exp->deudor_expedida_en : '' }}
            @if($exp->deudor_telefono) · {{ $exp->deudor_telefono }} @endif
            @if($exp->tiene_codeudor)
                <br><strong>Codeudor:</strong> {{ $exp->codeudor_nombre }} · C.C. {{ $exp->codeudor_cedula }}
            @endif
            @if($exp->tiene_prenda)
                <br><strong>Prenda:</strong> {{ $exp->prenda_placa }} — {{ $exp->prenda_marca }} {{ $exp->prenda_linea }} {{ $exp->prenda_modelo }}
            @endif
        </div>
    </div>

    {{-- Documentos --}}
    <div class="exp-panel" style="margin-top:1.25rem; padding:1.1rem 1.25rem;">
        <h3 style="margin:0 0 0.2rem 0; font-size:0.92rem; font-weight:800; color:#0f172a;">1. Documentos para firmar</h3>
        <p style="margin:0 0 0.9rem 0; font-size:0.76rem; color:#64748b;">
            Imprime, firma con huella y escanea todo en un solo PDF. La hoja de documentos requeridos dice qué más recoger.
        </p>

        @if($exp->tasa_en_blanco)
            <div style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:0.6rem 0.8rem; border-radius:9px; font-size:0.78rem; margin-bottom:0.9rem;">
                ✍️ Estos documentos salen con <strong>la tasa y el interés mensual en blanco</strong>. Escríbelos a mano antes de firmar.
                El sistema liquidará al {{ rtrim(rtrim(number_format($exp->tasa_interes_mensual, 3, ',', ''), '0'), ',') }}% mensual.
            </div>
        @endif

        <div style="display:flex; gap:0.6rem; flex-wrap:wrap; margin-bottom:0.9rem;">
            <a href="{{ route('finanzas.expedientes.documentos', [$exp->id, 'pdf']) }}" class="btn-guardar-bx" style="text-decoration:none; display:inline-flex; align-items:center;">📥 Descargar todo en PDF</a>
            <a href="{{ route('finanzas.expedientes.documentos', [$exp->id, 'word']) }}" class="btn-cancelar-bx">📝 Todo en Word</a>
        </div>

        <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
            @foreach($documentos as $doc)
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:0.5rem 0;">{{ $servicio->titulo($doc) }}</td>
                    <td style="padding:0.5rem 0; text-align:right; white-space:nowrap;">
                        <a href="{{ route('finanzas.expedientes.documentos', [$exp->id, 'pdf']) }}?doc={{ $doc }}" style="color:#b45309; font-weight:700; text-decoration:none;">PDF</a>
                        <span style="color:#cbd5e1;">·</span>
                        <a href="{{ route('finanzas.expedientes.documentos', [$exp->id, 'word']) }}?doc={{ $doc }}" style="color:#1d4ed8; font-weight:700; text-decoration:none;">Word</a>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    {{-- Firma --}}
    @if($exp->estado !== 'anulado')
    <div class="exp-panel" style="margin-top:1.25rem; padding:1.1rem 1.25rem;">
        <h3 style="margin:0 0 0.2rem 0; font-size:0.92rem; font-weight:800; color:#0f172a;">2. Firma</h3>
        <p style="margin:0 0 0.9rem 0; font-size:0.76rem; color:#64748b;">
            Sube <strong>un solo PDF</strong> con todo escaneado, en este orden: contrato, pagaré, carta de instrucciones,
            @if($exp->tiene_prenda) contrato de prenda, @endif recibo de desembolso, cédulas y los demás soportes de la hoja de requisitos.
        </p>

        @if($exp->fecha_firma)
            <div style="font-size:0.8rem; color:#166534; background:#f0fdf4; border:1px solid #bbf7d0; padding:0.6rem 0.8rem; border-radius:9px; margin-bottom:0.9rem;">
                ✅ Firmado el {{ $exp->fecha_firma->format('d/m/Y') }}.
                @if($exp->documentos_path)
                    <a href="{{ route('finanzas.expedientes.firmados', $exp->id) }}" style="color:#166534; font-weight:700;">Descargar el PDF firmado</a>
                @else
                    <strong>Falta subir el PDF escaneado.</strong>
                @endif
            </div>
        @endif

        <form action="{{ route('finanzas.expedientes.firma', $exp->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
                <div class="form-group-bx" style="flex:1; min-width:170px;">
                    <label class="form-label-bx">Fecha de firma</label>
                    <input type="date" name="fecha_firma" value="{{ optional($exp->fecha_firma)->toDateString() ?: now()->toDateString() }}" class="form-input-bx" required>
                </div>
                <div class="form-group-bx" style="flex:2; min-width:240px;">
                    <label class="form-label-bx">PDF con los documentos firmados (máx. 30 MB)</label>
                    <input type="file" name="documentos" accept="application/pdf" class="form-input-bx" style="padding:0.35rem 0.5rem;">
                </div>
                <button type="submit" class="btn-guardar-bx">{{ $exp->fecha_firma ? '💾 Actualizar' : '✍️ Registrar firma' }}</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Desembolso --}}
    @if($exp->estado === 'firmado')
    <div class="exp-panel" style="margin-top:1.25rem; padding:1.1rem 1.25rem; border-color:#bbf7d0;">
        <h3 style="margin:0 0 0.2rem 0; font-size:0.92rem; font-weight:800; color:#0f172a;">3. Desembolso</h3>
        <p style="margin:0 0 0.9rem 0; font-size:0.76rem; color:#64748b;">
            Al registrarlo se crea el préstamo, empieza a correr el interés y sale el egreso de la cuenta que elijas.
            Es el paso que sí mueve plata.
        </p>

        <form action="{{ route('finanzas.expedientes.desembolsar', $exp->id) }}" method="POST"
              onsubmit="return confirm('¿Confirmas que ya entregaste ${{ number_format($exp->monto, 0, ',', '.') }} a {{ $exp->deudor_nombre }}?');">
            @csrf
            <div style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
                <div class="form-group-bx" style="flex:1; min-width:170px;">
                    <label class="form-label-bx">Fecha real de entrega</label>
                    <input type="date" name="fecha_desembolso" value="{{ optional($exp->fecha_firma)->toDateString() ?: now()->toDateString() }}" class="form-input-bx" required>
                </div>
                <div class="form-group-bx" style="flex:2; min-width:220px;">
                    <label class="form-label-bx">¿De qué cuenta salió el dinero?</label>
                    <select name="cuenta_id" class="form-select-bx">
                        @foreach($cuentas as $cta)
                            <option value="{{ $cta->id }}">{{ $cta->icono }} {{ $cta->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-guardar-bx">💵 Registrar desembolso</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Anular --}}
    @if($exp->estado !== 'desembolsado' && $exp->estado !== 'anulado')
    <div style="margin-top:1.25rem; text-align:right;">
        <form action="{{ route('finanzas.expedientes.anular', $exp->id) }}" method="POST"
              onsubmit="return confirm('¿Anular este expediente? No se podrá desembolsar.');">
            @csrf
            <button type="submit" class="btn-cancelar-bx" style="border:none; cursor:pointer;">⛔ Anular expediente</button>
        </form>
    </div>
    @endif

</div>
@endsection

@push('styles')
@include('finanzas.prestamos.expedientes._estilos_ui')
<style>
    .exp-dato-label { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.02em; }
    .exp-dato-valor { font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-top: 0.1rem; }
    .exp-dato-sub { font-size: 0.72rem; color: #64748b; }
</style>
@endpush
