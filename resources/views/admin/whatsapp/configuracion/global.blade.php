@extends('layouts.app')
@section('titulo', 'WhatsApp')
@section('modulo', 'Configuración WhatsApp Global (Brynex)')

@push('styles')
<style>
.form-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.75rem; max-width:680px; }
.form-group { margin-bottom:1.1rem; }
.form-label { display:block; font-size:.82rem; font-weight:600; color:#374151; margin-bottom:.35rem; }
.form-control { width:100%; padding:.5rem .75rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.85rem; color:#0f172a; background:#fff; transition:border-color .15s; }
.form-control:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.form-hint { font-size:.73rem; color:#94a3b8; margin-top:.25rem; }
.btn-row { display:flex; gap:.75rem; margin-top:1.5rem; }
.btn { padding:.5rem 1.25rem; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:opacity .15s; }
.btn:hover { opacity:.87; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-outline  { background:transparent; border:1px solid #cbd5e1; color:#475569; }
.section-title { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; margin-bottom:1rem; padding-bottom:.4rem; border-bottom:1px solid #f1f5f9; }
</style>
@endpush

@section('contenido')
<div class="contenido">
    @if($errors->any())
        <div class="flash" style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.83rem;">
            @foreach($errors->all() as $e) <div>❌ {{ $e }}</div> @endforeach
        </div>
    @endif

    <div style="margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem;">
        <a href="{{ route('admin.whatsapp.config.index') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">← Volver a configuraciones</a>
        <div style="display:inline-flex; gap:.5rem;">
            <a href="{{ route('admin.whatsapp.plantillas.index') }}" class="btn btn-outline" style="padding:.35rem .75rem; font-size:.75rem; border-radius:6px; display:inline-flex; align-items:center; gap:.25rem; margin:0;">
                📋 Plantillas Meta
            </a>
            <a href="{{ route('admin.whatsapp.plantillas.create') }}" class="btn btn-primary" style="padding:.35rem .75rem; font-size:.75rem; border-radius:6px; background:#10b981; display:inline-flex; align-items:center; gap:.25rem; margin:0;">
                ➕ Crear Plantilla
            </a>
        </div>
    </div>

    <div class="form-card">
        <h2 style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:1.5rem">
            ⚙️ WhatsApp — Cuenta Global (Brynex)
        </h2>

        <form method="POST" action="{{ route('admin.whatsapp.config.global.update') }}">
            @csrf

            <div class="section-title">Credenciales de Meta Cloud API Global</div>

            <div class="form-group">
                <label class="form-label">WABA ID (WhatsApp Business Account ID)</label>
                <input type="text" name="waba_id" class="form-control" value="{{ old('waba_id', $config->waba_id) }}"
                       placeholder="Ej: 123456789012345" required>
                <div class="form-hint">ID de la cuenta de WhatsApp Business para Brynex global</div>
            </div>

            <div class="form-group">
                <label class="form-label">Phone Number ID</label>
                <input type="text" name="phone_number_id" class="form-control" value="{{ old('phone_number_id', $config->phone_number_id) }}"
                       placeholder="Ej: 987654321098765" required>
                <div class="form-hint">ID del número de teléfono global en Meta</div>
            </div>

            <div class="form-group">
                <label class="form-label">Access Token</label>
                <input type="password" name="access_token" class="form-control"
                       placeholder="{{ $config->tiene_token ? '••••••••••••••••••••' : 'Token de Meta (permanente)' }}">
                @if($config->tiene_token)
                    <div class="form-hint">⚠️ Dejar vacío para mantener el token actual</div>
                @else
                    <div class="form-hint">Token permanente de Meta Cloud API global</div>
                @endif
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label class="form-label">Número de Teléfono</label>
                    <input type="text" name="numero_telefono" class="form-control" value="{{ old('numero_telefono', $config->numero_telefono) }}"
                           placeholder="+573001234567">
                </div>
                <div class="form-group">
                    <label class="form-label">Nombre de la cuenta</label>
                    <input type="text" name="nombre_cuenta" class="form-control" value="{{ old('nombre_cuenta', $config->nombre_cuenta) }}"
                           placeholder="Ej: Brynex Oficial">
                </div>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">💾 Guardar configuración global</button>
                <a href="{{ route('admin.whatsapp.config.index') }}" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
