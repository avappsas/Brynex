@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Préstamos Formales')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" x-data="{ nuevoPrestamista: false, editando: null }">

    @component('finanzas.partials._header_banner', [
        'titulo' => '📄 Préstamos Formales',
        'subtitulo' => 'Expedientes con contrato de mutuo, pagaré y carta de instrucciones. El dinero sale solo después de la firma.',
        'breadcrumb' => [
            'Finanzas Personales' => route('finanzas.dashboard'),
            'Préstamos' => route('finanzas.prestamos.index'),
            'Formales' => null,
        ],
    ])
        @slot('opciones')
            <div class="period-selector-bx" style="margin:0; display:inline-flex; gap:0.25rem;">
                <a href="{{ route('finanzas.expedientes.index', ['estado' => 'tramite']) }}" class="btn-state-filtro {{ $estado === 'tramite' ? 'activo' : '' }}">⏳ En trámite</a>
                <a href="{{ route('finanzas.expedientes.index', ['estado' => 'desembolsado']) }}" class="btn-state-filtro {{ $estado === 'desembolsado' ? 'activo' : '' }}">✅ Desembolsados</a>
                <a href="{{ route('finanzas.expedientes.index', ['estado' => 'anulado']) }}" class="btn-state-filtro {{ $estado === 'anulado' ? 'activo' : '' }}">⛔ Anulados</a>
            </div>
            <a href="{{ route('finanzas.prestamos.create') }}" class="btn-fin success">➕ Nuevo préstamo formal</a>
        @endslot
    @endcomponent

    @include('finanzas.prestamos.expedientes._avisos')

    {{-- Prestamistas: los datos que encabezan los documentos --}}
    <div class="exp-panel" style="margin-top:1.25rem; padding:1rem 1.25rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
            <div>
                <h3 style="margin:0; font-size:0.9rem; font-weight:800; color:#0f172a;">👤 Prestamistas</h3>
                <p style="margin:0.15rem 0 0 0; font-size:0.75rem; color:#64748b;">A nombre de quién salen los documentos. Se guardan una vez y se reutilizan en cada expediente.</p>
            </div>
            <button type="button" class="btn-fin" @click="nuevoPrestamista = ! nuevoPrestamista; editando = null">➕ Agregar</button>
        </div>

        @if($prestamistas->isNotEmpty())
        <div style="margin-top:0.9rem; display:flex; flex-direction:column; gap:0.5rem;">
            @foreach($prestamistas as $p)
                @php $faltan = $p->camposFaltantes(); @endphp
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:0.7rem 0.9rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
                        <div>
                            <strong style="font-size:0.85rem; color:#0f172a;">{{ $p->nombre }}</strong>
                            @if($p->por_defecto)<span style="font-size:0.68rem; background:#dcfce7; color:#166534; padding:0.1rem 0.45rem; border-radius:99px; margin-left:0.4rem;">por defecto</span>@endif
                            <div style="font-size:0.74rem; color:#64748b; margin-top:0.15rem;">
                                {{ $p->cedula ? 'C.C. '.$p->cedula.($p->expedida_en ? ' de '.$p->expedida_en : '') : 'Sin cédula' }}
                                @if($p->telefono) · {{ $p->telefono }} @endif
                            </div>
                            @if($faltan)
                                <div style="font-size:0.72rem; color:#b45309; margin-top:0.25rem;">⚠️ Falta: {{ implode(', ', $faltan) }} — los documentos saldrían con espacios en blanco.</div>
                            @endif
                        </div>
                        <button type="button" class="btn-fin-link" @click="editando = (editando === {{ $p->id }} ? null : {{ $p->id }}); nuevoPrestamista = false">✏️ Editar</button>
                    </div>

                    <div x-show="editando === {{ $p->id }}" x-cloak style="margin-top:0.75rem; border-top:1px dashed #e2e8f0; padding-top:0.75rem;">
                        @include('finanzas.prestamos.expedientes._form_prestamista', ['p' => $p])
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        <div x-show="nuevoPrestamista" x-cloak style="margin-top:0.9rem; border-top:1px dashed #e2e8f0; padding-top:0.9rem;">
            @include('finanzas.prestamos.expedientes._form_prestamista', ['p' => null])
        </div>
    </div>

    {{-- Expedientes --}}
    <div style="margin-top:1.25rem; display:flex; flex-direction:column; gap:0.75rem;">
        @forelse($expedientes as $e)
            <a href="{{ route('finanzas.expedientes.show', $e->id) }}" class="exp-panel" style="display:block; padding:1rem 1.25rem; text-decoration:none;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap;">
                    <div>
                        <div style="font-size:0.95rem; font-weight:800; color:#0f172a;">{{ $e->deudor_nombre }}</div>
                        <div style="font-size:0.76rem; color:#64748b; margin-top:0.2rem;">
                            {{ $e->pagare_numero }} · C.C. {{ $e->deudor_cedula }} · a nombre de {{ $e->prestamista->nombre }}
                        </div>
                        <div style="font-size:0.74rem; color:#64748b; margin-top:0.2rem;">
                            @if($e->tiene_codeudor) 👥 Con codeudor @endif
                            @if($e->tiene_prenda) 🏍️ Prenda {{ $e->prenda_placa }} @endif
                            @if(! $e->tiene_codeudor && ! $e->tiene_prenda) Sin garantías adicionales @endif
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:1.05rem; font-weight:800; color:#0f172a;">${{ number_format($e->monto, 0, ',', '.') }}</div>
                        <div style="font-size:0.74rem; color:#64748b;">{{ rtrim(rtrim(number_format($e->tasa_interes_mensual, 3, ',', ''), '0'), ',') }}% mensual · {{ $e->plazo_meses }} meses</div>
                        @php
                            $colores = [
                                'pendiente_firma' => ['#fef3c7', '#92400e'],
                                'firmado' => ['#dbeafe', '#1e40af'],
                                'desembolsado' => ['#dcfce7', '#166534'],
                                'anulado' => ['#f1f5f9', '#475569'],
                            ][$e->estado] ?? ['#f1f5f9', '#475569'];
                        @endphp
                        <span style="display:inline-block; margin-top:0.35rem; font-size:0.7rem; font-weight:700; padding:0.15rem 0.55rem; border-radius:99px; background:{{ $colores[0] }}; color:{{ $colores[1] }};">
                            {{ $e->estado_texto }}
                        </span>
                    </div>
                </div>
            </a>
        @empty
            <div class="exp-panel" style="padding:2rem; text-align:center; color:#64748b; font-size:0.85rem;">
                No hay expedientes en este estado.
            </div>
        @endforelse
    </div>

</div>
@endsection

@push('styles')
@include('finanzas.prestamos.expedientes._estilos_ui')
@endpush
