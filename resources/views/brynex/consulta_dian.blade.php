@extends('layouts.app')
@section('modulo', 'Consulta DIAN')
@section('contenido')

{{--
    Consulta del nombre y el correo que la DIAN tiene registrados para un
    documento. Sale por la cuenta de Dataico de BryNex, así que cruza aliados:
    debajo del resultado se muestra dónde vive ya esa cédula dentro de BryNex y
    en qué se diferencia, que es para lo que sirve la pantalla — corregir la
    ficha antes de que el nombre malo viaje en una factura electrónica.
--}}

<div style="max-width:1100px;margin:0 auto;"
     x-data="consultaDian()">

    {{-- ── Cabecera ────────────────────────────────────────────────── --}}
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:#0d2550;margin:0;">🔎 Consulta DIAN</h1>
            <p style="color:#64748b;font-size:0.83rem;margin:0.2rem 0 0 0;">
                El nombre y el correo tal como los tiene registrados la DIAN. Es el nombre que viaja en la factura electrónica.
            </p>
        </div>
        @if($puedeConfigurar)
            <button @click="conexion = !conexion"
                    style="background:#fff;color:#334155;padding:0.5rem 0.9rem;border-radius:8px;font-size:0.82rem;font-weight:600;border:1px solid #cbd5e1;cursor:pointer;">
                ⚙️ Conexión
            </button>
        @endif
    </div>

    @if(session('exito'))
        <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);color:#065f46;border-radius:10px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.85rem;">
            ✅ {{ session('exito') }}
        </div>
    @endif

    {{-- ── Estado de la conexión ───────────────────────────────────── --}}
    @if($estado['bloqueo'])
        <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#991b1b;border-radius:10px;padding:0.85rem 1rem;margin-bottom:1rem;font-size:0.85rem;line-height:1.5;">
            🚫 <strong>Consulta detenida.</strong> {{ $estado['bloqueo'] }}
        </div>
    @elseif(! $estado['configurado'])
        <div style="background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.35);color:#854d0e;border-radius:10px;padding:0.85rem 1rem;margin-bottom:1rem;font-size:0.85rem;line-height:1.5;">
            ⚠️ Falta cargar el usuario y la contraseña del portal de Dataico
            @if($puedeConfigurar) — ábrelo en «Conexión», arriba a la derecha. @else — pídeselo a quien administre las credenciales. @endif
        </div>
    @endif

    {{-- ── Panel de credenciales ───────────────────────────────────── --}}
    @if($puedeConfigurar)
        <div x-show="conexion" x-cloak
             style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:1.25rem;margin-bottom:1.25rem;">
            <form method="POST" action="{{ route('brynex.dian.credenciales') }}">
                @csrf
                <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
                    <div style="flex:1;min-width:240px;">
                        <label style="display:block;font-size:0.72rem;font-weight:600;color:#475569;margin-bottom:0.25rem;">Usuario del portal de Dataico</label>
                        <input type="email" name="correo" required value="{{ $estado['correo'] }}"
                               style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">
                    </div>
                    <div style="flex:1;min-width:240px;">
                        <label style="display:block;font-size:0.72rem;font-weight:600;color:#475569;margin-bottom:0.25rem;">
                            Contraseña {{ $estado['configurado'] ? '(déjala vacía para conservar la actual)' : '' }}
                        </label>
                        <input type="password" name="clave" autocomplete="new-password"
                               style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">
                    </div>
                    <button type="submit"
                            style="background:#2563eb;color:#fff;border:none;padding:0.55rem 1.1rem;border-radius:8px;font-size:0.82rem;font-weight:600;cursor:pointer;">
                        Guardar
                    </button>
                </div>
                <p style="color:#64748b;font-size:0.75rem;margin:0.85rem 0 0 0;line-height:1.5;">
                    La contraseña se guarda cifrada y nunca se vuelve a mostrar.
                    <strong>Si la clave queda mal, la consulta se detiene sola y no se reintenta:</strong>
                    fallar varias veces seguidas activa el captcha de Dataico y deja la cuenta sin ingreso,
                    que es la misma cuenta con la que se emiten las facturas electrónicas.
                </p>
            </form>
        </div>
    @endif

    {{-- ── Consulta ────────────────────────────────────────────────── --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:1.25rem;margin-bottom:1.25rem;">
        <form @submit.prevent="consultar()" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
            <div style="width:230px;">
                <label style="display:block;font-size:0.72rem;font-weight:600;color:#475569;margin-bottom:0.25rem;">Tipo de documento</label>
                <select x-model="tipoDoc"
                        style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;background:#fff;">
                    @foreach($tipos as $codigo => $nombre)
                        <option value="{{ $codigo }}">{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;min-width:200px;">
                <label style="display:block;font-size:0.72rem;font-weight:600;color:#475569;margin-bottom:0.25rem;">Número</label>
                <input type="text" x-model="numero" inputmode="numeric" placeholder="Sin puntos ni guiones"
                       style="width:100%;padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;">
            </div>
            <button type="submit" :disabled="cargando || !numero"
                    :style="(cargando || !numero) ? 'opacity:0.55;cursor:not-allowed;' : 'cursor:pointer;'"
                    style="background:#2563eb;color:#fff;border:none;padding:0.55rem 1.3rem;border-radius:8px;font-size:0.82rem;font-weight:600;">
                <span x-show="!cargando">Consultar</span>
                <span x-show="cargando" x-cloak>Consultando…</span>
            </button>
        </form>

        <p x-show="error" x-cloak x-text="error"
           style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#991b1b;border-radius:10px;padding:0.7rem 0.9rem;margin:1rem 0 0 0;font-size:0.82rem;"></p>
    </div>

    {{-- ── Resultado ───────────────────────────────────────────────── --}}
    <template x-if="dian">
        <div style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);overflow:hidden;margin-bottom:1.25rem;">
            <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:1rem 1.25rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                <div>
                    <div style="color:#fff;font-size:1.05rem;font-weight:700;" x-text="dian.nombre_completo || '(la DIAN no devolvió nombre)'"></div>
                    <div style="color:#93c5fd;font-size:0.78rem;margin-top:0.15rem;">
                        <span x-text="dian.tipo_doc"></span> <span x-text="dian.identificacion"></span>
                        · <span x-text="dian.tipo_persona === 'PERSONA_JURIDICA' ? 'Persona jurídica' : 'Persona natural'"></span>
                    </div>
                </div>
                <span x-show="dian.encontrado" style="background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3);border-radius:999px;padding:0.15rem 0.65rem;font-size:0.72rem;font-weight:600;">Encontrado</span>
                <span x-show="!dian.encontrado" x-cloak style="background:rgba(234,179,8,0.12);color:#fde047;border:1px solid rgba(234,179,8,0.25);border-radius:999px;padding:0.15rem 0.65rem;font-size:0.72rem;font-weight:600;">Sin datos</span>
            </div>

            <div style="padding:1.25rem;">
                <p x-show="dian.mensaje" x-text="dian.mensaje" x-cloak
                   style="color:#64748b;font-size:0.8rem;margin:0 0 1rem 0;line-height:1.5;"></p>

                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;">
                    <template x-for="campo in campos()" :key="campo.etiqueta">
                        <div>
                            <div style="font-size:0.7rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.02em;" x-text="campo.etiqueta"></div>
                            <div style="display:flex;align-items:center;gap:0.4rem;margin-top:0.15rem;">
                                <span style="font-size:0.88rem;color:#1e293b;word-break:break-word;" x-text="campo.valor || '—'"></span>
                                <button x-show="campo.valor" @click="copiar(campo.valor)" title="Copiar"
                                        style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:0.78rem;padding:0;">📋</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    {{-- ── Lo que ya hay en BryNex ─────────────────────────────────── --}}
    <template x-if="dian && brynex.length">
        <div style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:1.25rem;">
            <h2 style="font-size:0.95rem;font-weight:700;color:#0d2550;margin:0 0 0.25rem 0;">Ese documento ya está en BryNex</h2>
            <p style="color:#64748b;font-size:0.78rem;margin:0 0 1rem 0;">
                Lo resaltado no coincide con lo que dice la DIAN.
            </p>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                    <thead>
                        <tr style="text-align:left;color:#64748b;font-size:0.72rem;text-transform:uppercase;">
                            <th style="padding:0.5rem;border-bottom:1px solid #e2e8f0;">Aliado</th>
                            <th style="padding:0.5rem;border-bottom:1px solid #e2e8f0;">Dónde</th>
                            <th style="padding:0.5rem;border-bottom:1px solid #e2e8f0;">Nombre en BryNex</th>
                            <th style="padding:0.5rem;border-bottom:1px solid #e2e8f0;">Correo</th>
                            <th style="padding:0.5rem;border-bottom:1px solid #e2e8f0;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="fila in brynex" :key="fila.tipo + '-' + fila.id">
                            <tr>
                                <td style="padding:0.5rem;border-bottom:1px solid #f1f5f9;" x-text="fila.aliado"></td>
                                <td style="padding:0.5rem;border-bottom:1px solid #f1f5f9;color:#64748b;" x-text="fila.tipo === 'cliente' ? 'Cliente' : 'Empresa'"></td>
                                <td style="padding:0.5rem;border-bottom:1px solid #f1f5f9;"
                                    :style="difiere(fila.nombre, dian.nombre_completo) ? 'background:rgba(234,179,8,0.12);font-weight:600;' : ''"
                                    x-text="fila.nombre || '—'"></td>
                                <td style="padding:0.5rem;border-bottom:1px solid #f1f5f9;"
                                    :style="difiere(fila.correo, dian.correo) ? 'background:rgba(234,179,8,0.12);font-weight:600;' : ''"
                                    x-text="fila.correo || '—'"></td>
                                <td style="padding:0.5rem;border-bottom:1px solid #f1f5f9;text-align:right;">
                                    <template x-if="fila.url">
                                        <a :href="fila.url" target="_blank"
                                           style="color:#2563eb;text-decoration:none;font-weight:600;font-size:0.78rem;">Abrir ficha ↗</a>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <template x-if="dian && !brynex.length">
        <div style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:1.25rem;color:#64748b;font-size:0.83rem;">
            Ese documento no está registrado como cliente ni como empresa en ningún aliado.
        </div>
    </template>
</div>

<script>
function consultaDian() {
    return {
        tipoDoc: 'CC',
        numero: '',
        cargando: false,
        error: '',
        dian: null,
        brynex: [],
        conexion: false,

        async consultar() {
            this.cargando = true;
            this.error = '';
            this.dian = null;
            this.brynex = [];

            try {
                const r = await fetch('{{ route('brynex.dian.consultar') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ tipo_doc: this.tipoDoc, numero: this.numero }),
                });
                const d = await r.json();

                if (!r.ok || !d.ok) {
                    this.error = d.error || 'No se pudo consultar.';
                    return;
                }

                this.dian = d.dian;
                this.brynex = d.brynex || [];
            } catch (e) {
                this.error = 'No se pudo consultar: ' + e.message;
            } finally {
                this.cargando = false;
            }
        },

        campos() {
            const d = this.dian;
            return [
                { etiqueta: 'Primer nombre',   valor: d.primer_nombre },
                { etiqueta: 'Otros nombres',   valor: d.otros_nombres },
                { etiqueta: 'Primer apellido', valor: d.primer_apellido },
                { etiqueta: 'Segundo apellido',valor: d.segundo_apellido },
                { etiqueta: 'Nombre comercial',valor: d.nombre_comercial },
                { etiqueta: 'Correo del RUT',  valor: d.correo },
            ];
        },

        // Compara sin acentos ni mayúsculas: «MUÑOZ» y «Muñoz» son el mismo
        // nombre y marcarlos como diferentes solo agrega ruido.
        difiere(a, b) {
            const n = (s) => (s || '').toString().normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ').trim().toUpperCase();
            return n(a) !== '' && n(b) !== '' && n(a) !== n(b);
        },

        copiar(texto) {
            navigator.clipboard?.writeText(texto);
        },
    };
}
</script>
@endsection
