@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Editar expediente')

@section('contenido')
@include('finanzas.partials._responsive_fin')
<div class="finanzas-container" style="max-width: 1000px;">

    @component('finanzas.partials._header_banner', [
        'titulo' => '✏️ Editar '.$exp->pagare_numero,
        'subtitulo' => 'Cambia los datos y vuelve a imprimir los documentos. Solo se puede antes del desembolso.',
        'breadcrumb' => [
            'Finanzas Personales' => route('finanzas.dashboard'),
            'Préstamos formales' => route('finanzas.expedientes.index'),
            $exp->pagare_numero => route('finanzas.expedientes.show', $exp->id),
            'Editar' => null,
        ],
    ])
    @endcomponent

    @include('finanzas.prestamos.expedientes._avisos')

    @if($exp->fecha_firma)
        <div style="margin-top:1rem; background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:0.75rem 1rem; border-radius:10px; font-size:0.82rem;">
            Este expediente ya tiene firma registrada. Si cambias las condiciones, los papeles firmados dejan de coincidir:
            hay que reimprimirlos y volver a firmar.
        </div>
    @endif

    <div class="exp-panel" style="margin-top:1.25rem; padding:1.25rem;">
        @include('finanzas.prestamos.expedientes._formulario', [
            'accion' => route('finanzas.expedientes.update', $exp->id),
            'metodo' => 'PUT',
            'prestamistas' => $prestamistas,
            'exp' => $exp,
        ])
    </div>

</div>
@endsection

@push('styles')
@include('finanzas.prestamos.expedientes._estilos_ui')
@endpush
