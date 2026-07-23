@extends('layouts.app')
@section('titulo', 'Marketing')
@section('modulo', 'Campañas')

@push('styles')
<style>
.page-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.5rem; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:.75rem; }
.page-title { font-size:1.05rem; font-weight:700; color:#0f172a; }
.btn { padding:.45rem 1rem; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; }
.btn-primary { background:#2563eb; color:#fff; }
.campana-card { border:1px solid #e2e8f0; border-radius:10px; padding:1.1rem 1.3rem; margin-bottom:.9rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.75rem; }
.campana-nombre { font-weight:700; color:#0f172a; font-size:.92rem; }
.campana-meta { font-size:.76rem; color:#64748b; margin-top:.2rem; }
.empty-state { text-align:center; padding:3rem 1rem; color:#94a3b8; }
.empty-state .empty-icon { font-size:3rem; margin-bottom:.75rem; }
</style>
@endpush

@section('contenido')
<div class="contenido">
    @if(session('ok'))
        <div class="flash success">✅ {{ session('ok') }}</div>
    @endif

    <div style="margin-bottom:1rem; display:flex; gap:1rem;">
        <a href="{{ route('admin.marketing.listas.index') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">📋 Listas de contactos</a>
    </div>

    <div class="page-card">
        <div class="page-header">
            <div class="page-title">📣 Campañas de marketing</div>
            <a href="{{ route('admin.marketing.campanas.create') }}" class="btn btn-primary">+ Nueva campaña</a>
        </div>

        @if($campanas->isEmpty())
            <div class="empty-state">
                <div class="empty-icon">📣</div>
                <p>Todavía no has creado ninguna campaña.</p>
            </div>
        @else
            @foreach($campanas as $campana)
                <div class="campana-card">
                    <div>
                        <a href="{{ route('admin.marketing.campanas.show', $campana->id) }}" class="campana-nombre" style="text-decoration:none">{{ $campana->nombre }}</a>
                        <div class="campana-meta">
                            {{ $campana->plantilla->nombre_display ?? '—' }} · {{ $campana->envios_count }} tanda(s) ·
                            {{ (int) $campana->mensajes_enviados }} mensaje(s) enviado(s) · {{ $campana->etiquetaEstado() }}
                        </div>
                    </div>
                    <a href="{{ route('admin.marketing.campanas.show', $campana->id) }}" class="btn" style="background:#f1f5f9;color:#475569">Ver campaña →</a>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
