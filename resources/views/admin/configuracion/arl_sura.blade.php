@extends('layouts.app')
@section('titulo','Conexión ARL Sura')
@section('modulo','Configuración')

@section('contenido')
<style>
.arl-wrap { max-width:900px;margin:0 auto;padding:1.25rem 1rem 2.5rem }
.arl-head { background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;padding:1.35rem 1.6rem;margin-bottom:1.25rem;color:#fff }
.arl-head h1 { font-size:1.25rem;font-weight:800;margin:0 0 .3rem }
.arl-head p  { font-size:.8rem;color:#cbd5e1;margin:0;line-height:1.5 }
.arl-card { background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:1.2rem 1.35rem;margin-bottom:1rem }
.arl-card h2 { font-size:.95rem;font-weight:800;color:#0f172a;margin:0 0 .9rem;display:flex;align-items:center;gap:.45rem }
.arl-estado { display:inline-flex;align-items:center;gap:.4rem;border-radius:999px;padding:.25rem .8rem;font-size:.74rem;font-weight:700 }
.arl-estado.ok  { background:rgba(34,197,94,.13);color:#15803d;border:1px solid rgba(34,197,94,.3) }
.arl-estado.sin { background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0 }
.arl-estado.err { background:rgba(239,68,68,.1);color:#b91c1c;border:1px solid rgba(239,68,68,.25) }
.arl-label { display:block;font-size:.7rem;font-weight:700;color:#475569;margin-bottom:.25rem }
.arl-input { width:100%;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;font-family:inherit }
.arl-input:focus { outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12) }
.arl-grid { display:grid;grid-template-columns:130px 1fr;gap:.8rem;margin-bottom:.85rem }
.arl-nota { background:#eff6ff;border:1px solid #bfdbfe;border-radius:9px;padding:.65rem .85rem;font-size:.74rem;color:#1e40af;line-height:1.5;margin-bottom:1rem }
.arl-btn { border:none;border-radius:9px;padding:.55rem 1.15rem;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .15s }
.arl-btn.primario { background:linear-gradient(135deg,#1e40af,#2563eb);color:#fff }
.arl-btn.primario:hover { transform:translateY(-1px);box-shadow:0 5px 14px rgba(37,99,235,.32) }
.arl-btn.ghost { background:#f1f5f9;color:#475569;border:1px solid #e2e8f0 }
.arl-btn.ghost:hover { background:#e2e8f0 }
.arl-btn.rojo { background:#fee2e2;color:#b91c1c;border:1px solid #fecaca }
.arl-acciones { display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-top:.35rem }
.arl-tabla { width:100%;border-collapse:collapse;font-size:.78rem }
.arl-tabla th { text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.03em;color:#64748b;padding:.4rem .5rem;border-bottom:1px solid #e2e8f0 }
.arl-tabla td { padding:.45rem .5rem;border-bottom:1px solid #f1f5f9;color:#334155 }
.arl-pill { background:#dcfce7;color:#166534;border-radius:6px;padding:.1rem .45rem;font-size:.7rem;font-weight:700;font-family:monospace }
.arl-pill.no { background:#f1f5f9;color:#94a3b8;font-family:inherit;font-weight:600 }
.arl-msg { border-radius:10px;padding:.7rem .95rem;font-size:.8rem;margin-bottom:1rem;line-height:1.5 }
.arl-msg.ok  { background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#166534 }
.arl-msg.err { background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.28);color:#991b1b }
</style>

<div class="arl-wrap">

    <div class="arl-head">
        <h1>🛡️ Conexión con ARL Sura</h1>
        <p>Con estas credenciales BryNex entra al portal por su cuenta para afiliar, retirar y descargar carné y soporte.
           Se registran una sola vez.</p>
    </div>

    @if(session('success'))<div class="arl-msg ok">✅ {{ session('success') }}</div>@endif
    @if(session('error'))<div class="arl-msg err">⚠️ {{ session('error') }}</div>@endif

    <div class="arl-card">
        <h2>🔐 Credenciales del portal
            @if($credencial)
                <span class="arl-estado ok">✓ {{ $credencial->tipo_documento }} {{ $credencial->usuario }}</span>
            @else
                <span class="arl-estado sin">Sin configurar</span>
            @endif
        </h2>

        @if($credencial?->ultimo_error)
            <div class="arl-msg err" style="margin-bottom:.9rem">
                Último intento fallido: {{ $credencial->ultimo_error }}
            </div>
        @elseif($credencial?->ultima_sesion_at)
            <p style="font-size:.75rem;color:#64748b;margin:-.4rem 0 .9rem">
                Última sesión abierta {{ $credencial->ultima_sesion_at->diffForHumans() }}.
            </p>
        @endif

        <div class="arl-nota">
            Son los mismos datos con los que entras a <strong>Servicios en Línea de ARL Sura</strong>.
            La contraseña se guarda cifrada y no vuelve a mostrarse: si algún día la cambias en el portal,
            actualízala aquí y listo.
        </div>

        <form method="POST" action="{{ route('admin.configuracion.arl-sura.store') }}" autocomplete="off">
            @csrf
            <div class="arl-grid">
                <div>
                    <label class="arl-label">Tipo</label>
                    <select name="tipo_documento" class="arl-input">
                        <option value="C" @selected(($credencial->tipo_documento ?? 'C') === 'C')>Cédula</option>
                        <option value="N" @selected(($credencial->tipo_documento ?? '') === 'N')>NIT</option>
                        <option value="E" @selected(($credencial->tipo_documento ?? '') === 'E')>C. extranjería</option>
                    </select>
                </div>
                <div>
                    <label class="arl-label">Número de identificación *</label>
                    <input type="text" name="usuario" class="arl-input" required
                           value="{{ old('usuario', $credencial->usuario ?? '') }}" placeholder="Tu número de documento">
                </div>
            </div>

            <div style="margin-bottom:1rem">
                <label class="arl-label">
                    Contraseña {{ $credencial ? '(déjala vacía para no cambiarla)' : '*' }}
                </label>
                <input type="password" name="contrasena" class="arl-input" autocomplete="new-password"
                       placeholder="{{ $credencial ? '••••••••' : 'La del portal de Sura' }}" @required(!$credencial)>
            </div>

            <div class="arl-acciones">
                <button type="submit" class="arl-btn primario">💾 Guardar credenciales</button>
            </div>
        </form>

        @if($credencial)
        <div class="arl-acciones" style="margin-top:.9rem;padding-top:.9rem;border-top:1px solid #f1f5f9">
            <form method="POST" action="{{ route('admin.configuracion.arl-sura.probar') }}">
                @csrf
                <button type="submit" class="arl-btn ghost"
                        title="Entra al portal de verdad: es la única forma de saber si las credenciales sirven">
                    🔌 Probar conexión
                </button>
            </form>
            <form method="POST" action="{{ route('admin.configuracion.arl-sura.destroy') }}"
                  onsubmit="return confirm('¿Eliminar las credenciales? Las afiliaciones automáticas dejarán de funcionar.')">
                @csrf @method('DELETE')
                <button type="submit" class="arl-btn rojo">🗑️ Eliminar</button>
            </form>
            <span style="font-size:.72rem;color:#94a3b8">Probar tarda unos segundos: abre el portal en segundo plano.</span>
        </div>
        @endif
    </div>

    <div class="arl-card">
        <h2>🏢 Pólizas registradas</h2>
        <p style="font-size:.76rem;color:#64748b;margin:-.5rem 0 .8rem;line-height:1.5">
            La póliza es el número de contrato con Sura. Varias razones sociales pueden compartir una,
            y una sola sesión sirve para todas.
        </p>
        <table class="arl-tabla">
            <thead><tr><th>Razón social</th><th>NIT</th><th>Póliza ARL</th></tr></thead>
            <tbody>
            @forelse($razones as $rs)
                <tr>
                    <td>{{ $rs->razon_social }}</td>
                    <td style="font-family:monospace;color:#64748b">{{ $rs->nit }}</td>
                    <td>
                        @if($rs->arl_poliza)
                            <span class="arl-pill">{{ $rs->arl_poliza }}</span>
                        @else
                            <span class="arl-pill no">sin registrar</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" style="color:#94a3b8;text-align:center;padding:1rem">Sin razones sociales.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
