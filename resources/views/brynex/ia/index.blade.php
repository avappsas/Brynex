@extends('layouts.app')
@section('titulo', 'BryNex')
@section('modulo', 'Configuración IA')

@push('styles')
<style>
.ia-wrap { max-width: 960px; margin: 0 auto; }
.form-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.08); padding:1.5rem 1.75rem; margin-bottom:1.5rem; }
.form-group { margin-bottom:1rem; }
.form-label { display:block; font-size:.8rem; font-weight:600; color:#374151; margin-bottom:.3rem; }
.form-control { width:100%; padding:.5rem .7rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.83rem; color:#0f172a; }
.form-control:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.form-hint { font-size:.72rem; color:#94a3b8; margin-top:.25rem; }
.btn { padding:.5rem 1.1rem; border-radius:8px; font-size:.83rem; font-weight:600; cursor:pointer; border:none; }
.btn-primary { background:#2563eb; color:#fff; }
.section-title { font-size:1.02rem; font-weight:700; color:#0f172a; margin-bottom:1rem; }
.aliado-row { border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.25rem; margin-bottom:.75rem; }
.aliado-row.activo { border-color:#bbf7d0; background:#f0fdf4; }
.badge { display:inline-block; font-size:.68rem; font-weight:700; padding:.15rem .5rem; border-radius:999px; }
.badge-on { background:#dcfce7; color:#15803d; }
.badge-off { background:#f1f5f9; color:#64748b; }
.row-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:.75rem; align-items:end; }
</style>
@endpush

@section('contenido')
<div class="ia-wrap">

    <div style="margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
        <a href="{{ route('brynex.hub') }}" style="color:#2563eb;text-decoration:none;font-size:.83rem">← Volver al panel BryNex</a>
        <a href="{{ route('brynex.ia.conocimiento.index') }}" class="btn btn-primary" style="text-decoration:none;">🧠 Entrenamiento / Conocimiento</a>
    </div>

    @if(session('success'))
        <div class="flash success" style="margin-bottom:1rem;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="flash" style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.83rem;">
            @foreach($errors->all() as $e) <div>❌ {{ $e }}</div> @endforeach
        </div>
    @endif

    <h1 style="font-size:1.3rem;font-weight:700;color:#0f172a;margin-bottom:.25rem;">🤖 Asistente Virtual IA</h1>
    <p style="font-size:.85rem;color:#64748b;margin-bottom:1.5rem;">Configura el proveedor de IA global y actívalo por aliado.</p>

    {{-- ── Configuración global ─────────────────────────────────── --}}
    <div class="form-card">
        <div class="section-title">⚙️ Proveedor global (cuenta BryNex)</div>
        <form method="POST" action="{{ route('brynex.ia.global') }}">
            @csrf
            <div class="row-grid" style="margin-bottom:1rem;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Proveedor por defecto</label>
                    <select name="proveedor_default" class="form-control">
                        <option value="claude" @selected($global['proveedor_default']==='claude')>Claude (Anthropic)</option>
                        <option value="openai" @selected($global['proveedor_default']==='openai')>OpenAI</option>
                        <option value="gemini" @selected($global['proveedor_default']==='gemini')>Gemini (Google)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Modelo Claude</label>
                    <input type="text" name="modelo_claude" class="form-control" value="{{ $global['modelo_claude'] }}">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Modelo OpenAI</label>
                    <input type="text" name="modelo_openai" class="form-control" value="{{ $global['modelo_openai'] }}">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Modelo Gemini</label>
                    <input type="text" name="modelo_gemini" class="form-control" value="{{ $global['modelo_gemini'] }}">
                </div>
            </div>
            <div class="row-grid">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">API Key Claude {{ $global['tiene_key_claude'] ? '(configurada)' : '(sin configurar)' }}</label>
                    <input type="password" name="claude_api_key" class="form-control" placeholder="sk-ant-...">
                    <div class="form-hint">Déjalo vacío para no cambiarla.</div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">API Key OpenAI {{ $global['tiene_key_openai'] ? '(configurada)' : '(sin configurar)' }}</label>
                    <input type="password" name="openai_api_key" class="form-control" placeholder="sk-...">
                    <div class="form-hint">Déjalo vacío para no cambiarla.</div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">API Key Gemini {{ $global['tiene_key_gemini'] ? '(configurada)' : '(sin configurar)' }}</label>
                    <input type="password" name="gemini_api_key" class="form-control" placeholder="AIza...">
                    <div class="form-hint">Déjalo vacío para no cambiarla.</div>
                </div>
            </div>
            <div style="margin-top:1.25rem;">
                <button type="submit" class="btn btn-primary">Guardar configuración global</button>
            </div>
        </form>
    </div>

    {{-- ── Activación por aliado ────────────────────────────────── --}}
    <div class="form-card">
        <div class="section-title">🏢 Activación por aliado</div>

        @foreach($aliados as $aliado)
            @php($cfg = $configs->get($aliado->id))
            <div class="aliado-row {{ $cfg && $cfg->activo_web ? 'activo' : '' }}">
                <form method="POST" action="{{ route('brynex.ia.aliado', $aliado->id) }}">
                    @csrf
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.75rem;">
                        <div style="font-weight:600; color:#0f172a; font-size:.9rem;">
                            {{ $aliado->nombre }}
                        </div>
                        <span class="badge {{ $cfg && $cfg->activo_web ? 'badge-on' : 'badge-off' }}">
                            {{ $cfg && $cfg->activo_web ? 'ACTIVO' : 'INACTIVO' }}
                        </span>
                    </div>

                    <div class="row-grid">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Proveedor</label>
                            <select name="proveedor" class="form-control">
                                <option value="claude" @selected(($cfg->proveedor ?? 'claude')==='claude')>Claude</option>
                                <option value="openai" @selected(($cfg->proveedor ?? '')==='openai')>OpenAI</option>
                                <option value="gemini" @selected(($cfg->proveedor ?? '')==='gemini')>Gemini</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Cuenta</label>
                            <select name="usa_cuenta_brynex" class="form-control">
                                <option value="1" @selected(($cfg->usa_cuenta_brynex ?? true))>Cuenta BryNex (global)</option>
                                <option value="0" @selected(!($cfg->usa_cuenta_brynex ?? true))>API key propia del aliado</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">API key propia (opcional)</label>
                            <input type="password" name="api_key" class="form-control" placeholder="{{ $cfg && $cfg->api_key ? '••••••••' : 'sin configurar' }}">
                            <div class="form-hint">Del proveedor elegido arriba (Claude, OpenAI o Gemini).</div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Modelo (opcional)</label>
                            <input type="text" name="modelo" class="form-control" value="{{ $cfg->modelo ?? '' }}" placeholder="Usa el global por defecto">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">API key Gemini para imágenes {{ $cfg && $cfg->gemini_api_key ? '(configurada)' : '(sin configurar)' }}</label>
                            <input type="password" name="gemini_api_key" class="form-control" placeholder="{{ $cfg && $cfg->gemini_api_key ? '••••••••' : 'AIza...' }}">
                            <div class="form-hint">Para generar imágenes con IA en Marketing → Publicaciones (independiente del asistente conversacional). Déjalo vacío para no cambiarla.</div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Nombre del asistente</label>
                            <input type="text" name="nombre_bot" class="form-control" value="{{ $cfg->nombre_bot ?? '' }}" placeholder="Asistente Virtual">
                            <div class="form-hint">Cómo se presenta en el widget y firma sus mensajes de WhatsApp.</div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Widget web</label>
                            <select name="activo_web" class="form-control">
                                <option value="1" @selected($cfg && $cfg->activo_web)>Activo</option>
                                <option value="0" @selected(!($cfg && $cfg->activo_web))>Inactivo</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">WhatsApp</label>
                            <select name="activo_whatsapp" class="form-control">
                                <option value="1" @selected($cfg && $cfg->activo_whatsapp)>Activo</option>
                                <option value="0" @selected(!($cfg && $cfg->activo_whatsapp))>Inactivo</option>
                            </select>
                            <div class="form-hint">Responde automáticamente a los clientes por WhatsApp.</div>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary" style="width:100%;">Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        @endforeach
    </div>

</div>
@endsection
