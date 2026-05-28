@extends('layouts.app')
@section('titulo', 'WhatsApp')
@section('modulo', 'Configurar WhatsApp — {{ $aliado->nombre }}')

@push('styles')
<style>
.form-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.75rem; max-width:680px; }
.form-group { margin-bottom:1.1rem; }
.form-label { display:block; font-size:.82rem; font-weight:600; color:#374151; margin-bottom:.35rem; }
.form-control { width:100%; padding:.5rem .75rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.85rem; color:#0f172a; background:#fff; transition:border-color .15s; }
.form-control:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.form-hint { font-size:.73rem; color:#94a3b8; margin-top:.25rem; }
.toggle-group { display:flex; gap:1rem; margin-bottom:1.5rem; }
.toggle-option { flex:1; border:2px solid #e2e8f0; border-radius:10px; padding:1rem; cursor:pointer; transition:border-color .15s, background .15s; text-align:center; }
.toggle-option.active { border-color:#2563eb; background:rgba(37,99,235,.05); }
.toggle-option .icon { font-size:1.8rem; margin-bottom:.4rem; }
.toggle-option .label { font-weight:600; font-size:.85rem; color:#0f172a; }
.toggle-option .desc { font-size:.72rem; color:#64748b; margin-top:.2rem; }
.btn-row { display:flex; gap:.75rem; margin-top:1.5rem; }
.btn { padding:.5rem 1.25rem; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:opacity .15s; }
.btn:hover { opacity:.87; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-outline { background:transparent; border:1px solid #cbd5e1; color:#475569; }
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

    <div style="margin-bottom:1rem">
        <a href="{{ route('admin.whatsapp.config.index') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">← Volver a configuraciones</a>
    </div>

    <div class="form-card" x-data="{ usaBrynex: {{ $config->usa_cuenta_brynex ? 'true' : 'false' }} }">
        <h2 style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:1.5rem">
            ⚙️ WhatsApp — {{ $aliado->nombre }}
        </h2>

        <form method="POST" action="{{ route('admin.whatsapp.config.update', $aliado->id) }}">
            @csrf @method('PUT')

            {{-- Toggle cuenta Brynex / propia --}}
            <div class="section-title">Modo de cuenta</div>
            <div class="toggle-group">
                <div class="toggle-option" :class="usaBrynex ? 'active' : ''" @click="usaBrynex = true" style="cursor:pointer">
                    <div class="icon">🔵</div>
                    <div class="label">Usar cuenta Brynex</div>
                    <div class="desc">El aliado usa el número y WABA de Brynex</div>
                </div>
                <div class="toggle-option" :class="!usaBrynex ? 'active' : ''" @click="usaBrynex = false" style="cursor:pointer">
                    <div class="icon">🏢</div>
                    <div class="label">Cuenta propia del aliado</div>
                    <div class="desc">El aliado tiene su propio WABA y número</div>
                </div>
            </div>

            <input type="hidden" name="usa_cuenta_brynex" :value="usaBrynex ? '1' : '0'">

            {{-- Campos propios solo si usa cuenta propia --}}
            <div x-show="!usaBrynex" x-cloak>
                <div class="section-title">Credenciales de Meta Cloud API</div>

                <div class="form-group">
                    <label class="form-label">WABA ID (WhatsApp Business Account ID)</label>
                    <input type="text" name="waba_id" class="form-control" value="{{ old('waba_id', $config->waba_id) }}"
                           placeholder="Ej: 123456789012345">
                    <div class="form-hint">Encontrado en Meta Business Suite → Configuración de WhatsApp</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number ID</label>
                    <input type="text" name="phone_number_id" class="form-control" value="{{ old('phone_number_id', $config->phone_number_id) }}"
                           placeholder="Ej: 987654321098765">
                    <div class="form-hint">ID del número de teléfono registrado en Meta</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Access Token</label>
                    <input type="password" name="access_token" class="form-control"
                           placeholder="{{ $config->access_token ? '••••••••••••••••••••' : 'Token de Meta (permanente)' }}">
                    @if($config->access_token)
                        <div class="form-hint">⚠️ Dejar vacío para mantener el token actual</div>
                    @else
                        <div class="form-hint">Token permanente de Meta Cloud API</div>
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
                               placeholder="Nombre del perfil WhatsApp Business">
                    </div>
                </div>
            </div>

            {{-- Mensaje cuando usa Brynex --}}
            <div x-show="usaBrynex" x-cloak style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:1rem;font-size:.83rem;color:#1e40af;">
                ℹ️ Este aliado usará las credenciales globales de Brynex. Los mensajes saldrán del número oficial de Brynex.
                Para usar un número propio, selecciona <strong>"Cuenta propia del aliado"</strong>.
            </div>

            <div class="form-group" style="margin-top:1rem">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.83rem;font-weight:500">
                    <input type="checkbox" name="activo" value="1" {{ $config->activo ? 'checked' : '' }} style="width:16px;height:16px;">
                    Módulo WhatsApp activo para este aliado
                </label>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">💾 Guardar configuración</button>
                <a href="{{ route('admin.whatsapp.config.index') }}" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
