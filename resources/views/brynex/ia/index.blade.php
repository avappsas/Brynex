@extends('layouts.app')
@section('titulo', 'BryNex')
@section('modulo', 'Configuración IA')

@push('styles')
<style>
.ia-wrap { max-width: 1200px; margin: 0 auto; padding: 1rem 0; }
.header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.btn-back-link { display: flex; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.85rem; text-decoration: none; font-weight: 500; transition: color 0.15s; margin-bottom: 0.5rem; }
.btn-back-link:hover { color: var(--azul-btn); }

.ia-title-gradient {
    font-size: 1.75rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--azul-oscuro) 0%, var(--azul-medio) 50%, var(--acento) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.25rem;
}

.form-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    padding: 1.75rem;
    margin-bottom: 2rem;
    transition: box-shadow 0.2s ease;
}
.form-card:hover {
    box-shadow: 0 6px 24px rgba(0,0,0,0.06);
}

.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--azul-oscuro);
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 0.75rem;
}

/* Form controls */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.form-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.form-control {
    width: 100%;
    padding: 0.55rem 0.85rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.83rem;
    color: #0f172a;
    background-color: #fff;
    transition: all 0.15s ease;
}
.form-control:focus {
    outline: none;
    border-color: var(--acento);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
}
.form-control:disabled {
    background-color: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
}

/* API key input helper class */
.input-wrapper .form-control {
    padding-right: 2.5rem;
}
.toggle-password {
    position: absolute;
    right: 0.75rem;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s;
}
.toggle-password:hover {
    color: var(--azul-btn);
}

.form-hint {
    font-size: 0.7rem;
    color: #64748b;
    margin-top: 0.2rem;
    line-height: 1.3;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.55rem 1.25rem;
    border-radius: 8px;
    font-size: 0.83rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.15s ease;
    text-decoration: none;
}
.btn-primary {
    background: var(--azul-btn);
    color: #fff;
    box-shadow: 0 2px 4px rgba(37,99,235,0.15);
}
.btn-primary:hover {
    background: var(--acento);
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(37,99,235,0.2);
}
.btn-secondary {
    background: rgba(59,130,246,0.1);
    border: 1px solid rgba(59,130,246,0.25);
    color: var(--azul-btn);
}
.btn-secondary:hover {
    background: rgba(59,130,246,0.15);
}
.btn-glass {
    background: rgba(59,130,246,0.08);
    border: 1px solid rgba(59,130,246,0.25);
    color: var(--azul-btn);
    font-size: 0.78rem;
    font-weight: 600;
    border-radius: 7px;
    padding: 0.35rem 0.75rem;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.btn-glass:hover {
    background: rgba(59,130,246,0.18);
    color: var(--acento);
}

/* Table */
.card-tabla {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin-bottom: 2rem;
}

.tabla-brynex {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 0.82rem;
}

.tabla-brynex th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    padding: 0.85rem 1.25rem;
    border-bottom: 2px solid #e2e8f0;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.05em;
}

.tabla-brynex td {
    padding: 0.95rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}

.tabla-brynex tr:last-child td {
    border-bottom: none;
}

.tabla-brynex tr:hover td {
    background-color: #f8fafc;
}

/* Badges */
.badge-ok {
    background: rgba(34,197,94,0.12);
    color: #15803d;
    border: 1px solid rgba(34,197,94,0.25);
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.badge-err {
    background: rgba(239,68,68,0.1);
    color: #b91c1c;
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.badge-info {
    background: rgba(59,130,246,0.1);
    color: #1d4ed8;
    border: 1px solid rgba(59,130,246,0.2);
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.badge-text {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
}

/* Proveedores badges */
.badge-claude { background: #fdf2f8; color: #be185d; border: 1px solid #fbcfe8; }
.badge-openai { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.badge-gemini { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

/* Modal */
.modal-overlay {
    position: fixed; inset: 0; z-index: 9998;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}
.modal-box {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
    width: 100%; max-width: 650px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    animation: modalFadeIn 0.2s ease-out;
}
@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}
.modal-head h3 { font-size: 1.05rem; font-weight: 700; color: var(--azul-oscuro); display: flex; align-items: center; gap: 0.5rem; }
.modal-close {
    background: none; border: none; font-size: 1.25rem;
    color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 50%; transition: all 0.15s;
}
.modal-close:hover { background: #fee2e2; color: #ef4444; }
.modal-body { padding: 1.5rem; overflow-y: auto; }
.modal-foot {
    display: flex; justify-content: flex-end; gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

.modal-section-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.85rem;
    margin-top: 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 0.25rem;
}

.modal-section-title:first-of-type {
    margin-top: 0;
}

.flash-success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 0.83rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.flash-error {
    background: #fee2e2;
    border: 1px solid #fca5a5;
    color: #991b1b;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 0.83rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-bottom: 1.5rem;
}
</style>
@endpush

@section('contenido')
<div class="ia-wrap" x-data="{
    modalOpen: false,
    aliado: {},
    form: {
        id: '',
        nombre: '',
        proveedor: 'claude',
        usa_cuenta_brynex: '1',
        modelo: '',
        api_key: '',
        gemini_api_key: '',
        nombre_bot: '',
        activo_web: '0',
        activo_whatsapp: '0'
    },
    abrirConfig(item) {
        this.aliado = item;
        this.form.id = item.id;
        this.form.nombre = item.nombre;
        this.form.proveedor = item.proveedor;
        this.form.usa_cuenta_brynex = item.usa_cuenta_brynex;
        this.form.modelo = item.modelo;
        this.form.api_key = '';
        this.form.gemini_api_key = '';
        this.form.nombre_bot = item.nombre_bot;
        this.form.activo_web = item.activo_web;
        this.form.activo_whatsapp = item.activo_whatsapp;
        this.modalOpen = true;
    }
}">

    <div class="header-section">
        <div>
            <a href="{{ route('brynex.hub') }}" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Volver al panel BryNex
            </a>
            <h1 class="ia-title-gradient">🤖 Asistente Virtual IA</h1>
            <p style="font-size:.85rem;color:#64748b;margin:0;">Configura el proveedor de IA global y actívalo por aliado.</p>
        </div>
        <div>
            <a href="{{ route('brynex.ia.conocimiento.index') }}" class="btn btn-secondary">
                <i class="fas fa-brain"></i> Entrenamiento / Conocimiento
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="flash-success">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="flash-error">
            @foreach($errors->all() as $e)
                <div><i class="fas fa-exclamation-circle"></i> {{ $e }}</div>
            @endforeach
        </div>
    @endif

    {{-- ── Configuración global ─────────────────────────────────── --}}
    <div class="form-card" x-data="{ verClaude: false, verOpenAi: false, verGemini: false }">
        <div class="section-title">
            <i class="fas fa-cogs"></i> Proveedor global (cuenta BryNex)
        </div>
        <form method="POST" action="{{ route('brynex.ia.global') }}">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Proveedor por defecto</label>
                    <select name="proveedor_default" class="form-control">
                        <option value="claude" @selected($global['proveedor_default']==='claude')>Claude (Anthropic)</option>
                        <option value="openai" @selected($global['proveedor_default']==='openai')>OpenAI</option>
                        <option value="gemini" @selected($global['proveedor_default']==='gemini')>Gemini (Google)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Modelo Claude</label>
                    <input type="text" name="modelo_claude" class="form-control" value="{{ $global['modelo_claude'] }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Modelo OpenAI</label>
                    <input type="text" name="modelo_openai" class="form-control" value="{{ $global['modelo_openai'] }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Modelo Gemini</label>
                    <input type="text" name="modelo_gemini" class="form-control" value="{{ $global['modelo_gemini'] }}">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">
                        <span>API Key Claude</span>
                        <span class="badge-text" style="background: {{ $global['tiene_key_claude'] ? '#dcfce7; color:#15803d; border:1px solid #bbf7d0;' : '#f1f5f9; color:#64748b; border:1px solid #cbd5e1;' }}">
                            {{ $global['tiene_key_claude'] ? 'Configurada' : 'Sin configurar' }}
                        </span>
                    </label>
                    <div class="input-wrapper">
                        <input :type="verClaude ? 'text' : 'password'" name="claude_api_key" class="form-control" placeholder="sk-ant-...">
                        <button type="button" class="toggle-password" @click="verClaude = !verClaude" title="Alternar visibilidad">
                            <i :class="verClaude ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                        </button>
                    </div>
                    <div class="form-hint">Déjalo vacío para no cambiarla.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <span>API Key OpenAI</span>
                        <span class="badge-text" style="background: {{ $global['tiene_key_openai'] ? '#dcfce7; color:#15803d; border:1px solid #bbf7d0;' : '#f1f5f9; color:#64748b; border:1px solid #cbd5e1;' }}">
                            {{ $global['tiene_key_openai'] ? 'Configurada' : 'Sin configurar' }}
                        </span>
                    </label>
                    <div class="input-wrapper">
                        <input :type="verOpenAi ? 'text' : 'password'" name="openai_api_key" class="form-control" placeholder="sk-...">
                        <button type="button" class="toggle-password" @click="verOpenAi = !verOpenAi" title="Alternar visibilidad">
                            <i :class="verOpenAi ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                        </button>
                    </div>
                    <div class="form-hint">Déjalo vacío para no cambiarla.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <span>API Key Gemini</span>
                        <span class="badge-text" style="background: {{ $global['tiene_key_gemini'] ? '#dcfce7; color:#15803d; border:1px solid #bbf7d0;' : '#f1f5f9; color:#64748b; border:1px solid #cbd5e1;' }}">
                            {{ $global['tiene_key_gemini'] ? 'Configurada' : 'Sin configurar' }}
                        </span>
                    </label>
                    <div class="input-wrapper">
                        <input :type="verGemini ? 'text' : 'password'" name="gemini_api_key" class="form-control" placeholder="AIza...">
                        <button type="button" class="toggle-password" @click="verGemini = !verGemini" title="Alternar visibilidad">
                            <i :class="verGemini ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                        </button>
                    </div>
                    <div class="form-hint">Déjalo vacío para no cambiarla.</div>
                </div>
            </div>

            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar configuración global
                </button>
            </div>
        </form>
    </div>

    {{-- ── Activación por aliado ────────────────────────────────── --}}
    <div class="form-card">
        <div class="section-title">
            <i class="fas fa-building"></i> Configuración por Aliado
        </div>

        <div class="card-tabla" style="margin-bottom: 0; overflow-x: auto;">
            <table class="tabla-brynex">
                <thead>
                    <tr>
                        <th>Aliado</th>
                        <th>Proveedor</th>
                        <th>Cuenta</th>
                        <th style="text-align: center;">Widget Web</th>
                        <th style="text-align: center;">WhatsApp</th>
                        <th>Asistente</th>
                        <th style="text-align: center; width: 120px;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($aliados as $aliado)
                        @php
                            $cfg = $configs->get($aliado->id);
                            $aliadoData = [
                                'id' => $aliado->id,
                                'nombre' => $aliado->nombre,
                                'proveedor' => $cfg->proveedor ?? 'claude',
                                'usa_cuenta_brynex' => ($cfg ? ($cfg->usa_cuenta_brynex ? '1' : '0') : '1'),
                                'modelo' => $cfg->modelo ?? '',
                                'has_api_key' => $cfg ? !empty($cfg->api_key) : false,
                                'has_gemini_api_key' => $cfg ? !empty($cfg->gemini_api_key) : false,
                                'nombre_bot' => $cfg->nombre_bot ?? '',
                                'activo_web' => $cfg ? ($cfg->activo_web ? '1' : '0') : '0',
                                'activo_whatsapp' => $cfg ? ($cfg->activo_whatsapp ? '1' : '0') : '0',
                            ];
                        @endphp
                        <tr>
                            <td style="font-weight: 600; color: var(--azul-oscuro);">
                                {{ $aliado->nombre }}
                            </td>
                            <td>
                                @if(($cfg->proveedor ?? 'claude') === 'claude')
                                    <span class="badge-text badge-claude">Claude</span>
                                @elseif(($cfg->proveedor ?? '') === 'openai')
                                    <span class="badge-text badge-openai">OpenAI</span>
                                @elseif(($cfg->proveedor ?? '') === 'gemini')
                                    <span class="badge-text badge-gemini">Gemini</span>
                                @else
                                    <span class="badge-text" style="background:#f1f5f9; color:#475569;">No config</span>
                                @endif
                            </td>
                            <td>
                                @if($cfg ? $cfg->usa_cuenta_brynex : true)
                                    <span style="color:#64748b; font-size:0.78rem;">Global</span>
                                @else
                                    <span style="color:#047857; font-size:0.78rem; font-weight:600;"><i class="fas fa-key" style="font-size:0.7rem;"></i> Propia</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @if($cfg && $cfg->activo_web)
                                    <span class="badge-ok"><i class="fas fa-check"></i> Activo</span>
                                @else
                                    <span class="badge-err"><i class="fas fa-times"></i> Inactivo</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @if($cfg && $cfg->activo_whatsapp)
                                    <span class="badge-ok"><i class="fas fa-check"></i> Activo</span>
                                @else
                                    <span class="badge-err"><i class="fas fa-times"></i> Inactivo</span>
                                @endif
                            </td>
                            <td style="font-style: italic; color: #475569;">
                                {{ $cfg->nombre_bot ?? 'Asistente Virtual' }}
                            </td>
                            <td style="text-align: center;">
                                <button type="button" @click="abrirConfig({{ json_encode($aliadoData) }})" class="btn-glass">
                                    <i class="fas fa-cog"></i> Configurar
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Modal de Configuración Individual (Alpine.js) ────────── --}}
    <div
        x-show="modalOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        class="modal-overlay"
        @click.self="modalOpen = false"
    >
        <div
            x-show="modalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translateY(10px)"
            x-transition:enter-end="opacity-100 scale-100 translateY(0)"
            class="modal-box"
        >
            <div class="modal-head">
                <h3>
                    <i class="fas fa-robot" style="color:var(--azul-btn);"></i>
                    <span>Configurar Asistente: </span>
                    <span x-text="aliado.nombre" style="color:var(--azul-btn);"></span>
                </h3>
                <button @click="modalOpen = false" class="modal-close" title="Cerrar">&times;</button>
            </div>

            <div class="modal-body">
                <form method="POST" :action="'{{ route('brynex.ia.aliado', ':id') }}'.replace(':id', form.id)">
                    @csrf

                    <!-- Sección 1: Canales e Integración -->
                    <div class="modal-section-title">Canales e Integración</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Widget web</label>
                            <select name="activo_web" class="form-control" x-model="form.activo_web">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">WhatsApp</label>
                            <select name="activo_whatsapp" class="form-control" x-model="form.activo_whatsapp">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                            <div class="form-hint">Responde automáticamente a clientes por WhatsApp.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nombre del asistente</label>
                            <input type="text" name="nombre_bot" class="form-control" x-model="form.nombre_bot" placeholder="Asistente Virtual">
                            <div class="form-hint">Firma de mensajes y presentación en Widget.</div>
                        </div>
                    </div>

                    <!-- Sección 2: Configuración del Motor de IA -->
                    <div class="modal-section-title">Motor de Inteligencia Artificial</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Proveedor</label>
                            <select name="proveedor" class="form-control" x-model="form.proveedor">
                                <option value="claude">Claude</option>
                                <option value="openai">OpenAI</option>
                                <option value="gemini">Gemini</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cuenta de API</label>
                            <select name="usa_cuenta_brynex" class="form-control" x-model="form.usa_cuenta_brynex">
                                <option value="1">Cuenta BryNex (global)</option>
                                <option value="0">API key propia del aliado</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Modelo (opcional)</label>
                            <input type="text" name="modelo" class="form-control" x-model="form.modelo" placeholder="Usa el global por defecto">
                        </div>
                    </div>

                    <div class="form-grid" style="margin-top: 1.25rem;">
                        <!-- API Key Propia: se muestra solo si usa_cuenta_brynex == '0' -->
                        <div class="form-group" x-show="form.usa_cuenta_brynex == '0'" x-transition x-cloak style="grid-column: span 3;">
                            <label class="form-label">
                                <span x-text="'API key propia de ' + (form.proveedor.charAt(0).toUpperCase() + form.proveedor.slice(1))"></span>
                            </label>
                            <div class="input-wrapper" x-data="{ showKey: false }">
                                <input :type="showKey ? 'text' : 'password'" name="api_key" class="form-control" x-model="form.api_key"
                                       :placeholder="aliado.has_api_key ? '•••••••• (Configurada. Déjalo vacío para no cambiarla)' : 'Ingresa la API key del proveedor seleccionado'">
                                <button type="button" class="toggle-password" @click="showKey = !showKey" title="Alternar visibilidad">
                                    <i :class="showKey ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                </button>
                            </div>
                            <div class="form-hint">Será cifrada y guardada de forma segura para este aliado.</div>
                        </div>

                        <!-- API Key Gemini para Imágenes -->
                        <div class="form-group" style="grid-column: span 3;">
                            <label class="form-label">API key Gemini para imágenes (opcional)</label>
                            <div class="input-wrapper" x-data="{ showGeminiKey: false }">
                                <input :type="showGeminiKey ? 'text' : 'password'" name="gemini_api_key" class="form-control" x-model="form.gemini_api_key"
                                       :placeholder="aliado.has_gemini_api_key ? '•••••••• (Configurada. Déjalo vacío para no cambiarla)' : 'AIza...'">
                                <button type="button" class="toggle-password" @click="showGeminiKey = !showGeminiKey" title="Alternar visibilidad">
                                    <i :class="showGeminiKey ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                </button>
                            </div>
                            <div class="form-hint">Requerido en Marketing → Publicaciones para generación de imágenes con IA.</div>
                        </div>
                    </div>

                    <!-- Botones del pie del modal -->
                    <div class="modal-foot" style="margin-top: 1.75rem; padding: 1.25rem 0 0 0; background: none; border-top: 1px solid #e2e8f0;">
                        <button type="button" @click="modalOpen = false" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
