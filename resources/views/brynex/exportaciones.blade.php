@extends('layouts.app')

@section('titulo', 'BryNex')
@section('modulo', 'Entrega de Datos')

@section('contenido')
<div style="max-width:1040px;margin:0 auto">

    {{-- Breadcrumb --}}
    <div style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:#64748b;margin-bottom:1.25rem">
        <a href="{{ route('brynex.hub') }}" style="color:#3b82f6;text-decoration:none">🔵 BryNex</a>
        <span>›</span>
        <span>Entrega de Datos</span>
    </div>

    <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.3rem;font-weight:700;color:#1e293b;margin:0">📦 Entrega de Datos de un Aliado</h1>
        <p style="font-size:.82rem;color:#64748b;margin:.2rem 0 0">
            Genera el paquete con toda la información propia de un aliado, en CSV y TXT, para cuando se va de la plataforma.
        </p>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="alert-bx success"><span style="margin-right:8px">✅</span><div>{{ session('success') }}</div></div>
    @endif
    @if(session('error'))
        <div class="alert-bx error"><span style="margin-right:8px">❌</span><div>{{ session('error') }}</div></div>
    @endif

    {{-- Contraseña recién generada: se muestra una sola vez de forma destacada --}}
    @if(session('generada'))
        @php $nueva = $entregas->firstWhere('id', session('generada')); @endphp
        @if($nueva)
            <div class="alert-bx success" style="flex-direction:column;align-items:stretch">
                <div style="font-weight:700;margin-bottom:.5rem">✅ Entrega #{{ $nueva->id }} lista — {{ $nueva->aliado->nombre ?? '' }}</div>
                <div style="font-size:.8rem;margin-bottom:.6rem">
                    {{ number_format((int) $nueva->filas_total, 0, ',', '.') }} registros · {{ $nueva->tamanoLegible() }}
                </div>
                @if($nueva->passwordPlano())
                    <div style="background:#fff;border:1px dashed #10b981;border-radius:8px;padding:.7rem 1rem">
                        <div style="font-size:.72rem;color:#065f46;text-transform:uppercase;letter-spacing:.05em">Contraseña del ZIP</div>
                        <div style="font-family:ui-monospace,Menlo,monospace;font-size:1.15rem;font-weight:700;color:#065f46;letter-spacing:.08em">
                            {{ $nueva->passwordPlano() }}
                        </div>
                        <div style="font-size:.72rem;color:#065f46;margin-top:.45rem">
                            Mándela por un canal distinto al del archivo.
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endif

    {{-- Paso 2: hay un código esperando --}}
    @if($pendiente)
        <div class="card-bx" style="border-color:#fcd34d;background:#fffbeb">
            <div style="font-size:.95rem;font-weight:700;color:#92400e;margin-bottom:.35rem">
                📲 Confirme el código
            </div>
            <p style="font-size:.82rem;color:#78350f;margin:0 0 1rem">
                Le llegó un código al WhatsApp de BryNex para exportar
                <strong>{{ $pendiente->aliado->nombre ?? '' }}</strong>.
                Vence {{ $pendiente->codigo_expira_at->diffForHumans() }}.
            </p>

            <form action="{{ route('brynex.exportaciones.confirmar') }}" method="POST"
                  style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap" id="formConfirmar">
                @csrf
                <input type="hidden" name="exportacion_id" value="{{ $pendiente->id }}">
                <input type="text" name="codigo" inputmode="numeric" autocomplete="one-time-code"
                       maxlength="6" required autofocus placeholder="000000" class="input-codigo">
                <button type="submit" class="btn-bx-action success" id="btnConfirmar">Generar la entrega</button>
                <span style="font-size:.75rem;color:#92400e">
                    Puede tardar varios minutos si el aliado es grande.
                </span>
            </form>

            <form action="{{ route('brynex.exportaciones.cancelar') }}" method="POST" style="margin-top:.75rem">
                @csrf
                <button type="submit" class="btn-link-bx">Cancelar la solicitud</button>
            </form>
        </div>
    @else
        {{-- Paso 1: elegir aliado --}}
        <div class="card-bx">
            <div style="font-size:.95rem;font-weight:700;color:#1e293b;margin-bottom:1rem">Nueva entrega</div>

            <form action="{{ route('brynex.exportaciones.solicitar') }}" method="POST"
                  style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap">
                @csrf
                <div style="flex:1;min-width:240px">
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#475569;margin-bottom:.3rem">Aliado</label>
                    <select name="aliado_id" required class="select-bx">
                        <option value="">— Seleccione el aliado —</option>
                        @foreach($aliados as $aliado)
                            @php $vol = (int) ($volumenes[$aliado->id] ?? 0); @endphp
                            <option value="{{ $aliado->id }}">
                                {{ $aliado->nombre }}@if(!$aliado->activo) (inactivo)@endif
                                — {{ number_format($vol, 0, ',', '.') }} facturas
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-bx-action">📲 Pedir el código</button>
            </form>

            <div style="margin-top:1rem;font-size:.78rem;color:#64748b;line-height:1.5">
                Al pedir el código, llega un mensaje al WhatsApp de BryNex. Nada se genera hasta confirmarlo.
            </div>

            @if($volumenes->contains(fn ($v) => $v > 40000))
            <div style="margin-top:.8rem;padding:.6rem .8rem;background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;font-size:.76rem;color:#78350f;line-height:1.5">
                Para los aliados de más de 40.000 facturas la generación tarda varios minutos y el
                servidor puede cortar la petición antes de terminar. En esos casos conviene el comando:
                <code>php artisan aliado:exportar {id}</code> — hace exactamente lo mismo y queda
                registrado igual.
            </div>
            @endif
        </div>
    @endif

    {{-- Qué se entrega --}}
    <details class="card-bx" style="padding:0">
        <summary style="cursor:pointer;padding:1rem 1.25rem;font-size:.85rem;font-weight:700;color:#1e293b">
            ℹ️ Qué contiene el paquete
        </summary>
        <div style="padding:0 1.25rem 1.25rem;font-size:.8rem;color:#475569;line-height:1.6">
            <p style="margin:0 0 .6rem">
                <strong>16 informes</strong>, cada uno en CSV (separado por comas) y en TXT (separado por
                tabuladores), más un <code>LEEME.txt</code> con la fecha de corte, el conteo por archivo y
                el aviso de responsabilidad sobre datos personales.
            </p>
            <p style="margin:0 0 .6rem">
                Personas · Beneficiarios · Empresas cliente · Razones sociales · Afiliaciones ·
                Facturación · Pagos recibidos · Gestiones de cobro · Incapacidades y sus gestiones ·
                Trámites y sus movimientos · Tareas y sus gestiones · Prospectos · Usuarios y asesores.
            </p>
            <p style="margin:0 0 .6rem">
                <strong>No van</strong> los catálogos del sistema (EPS, ARL, pensión, caja, ciudades,
                planes, modalidades), ni los planos PILA, ni credenciales, ni adjuntos, ni un solo id
                interno: cada entidad sale con su nombre.
            </p>
            <p style="margin:0 0 .6rem">
                El ZIP se guarda cifrado en el servidor y se borra solo a los
                <strong>{{ config('exportacion.dias_retencion') }} días</strong>.
                Si WhatsApp falla, la salida es
                <code>php artisan aliado:exportar {id}</code>.
            </p>
            <p style="margin:0;padding:.6rem .8rem;background:#fffbeb;border:1px solid #fcd34d;border-radius:6px">
                ⚠️ <strong>Para abrirlo hace falta 7-Zip, WinRAR o Keka.</strong> El ZIP va cifrado con
                AES-256, y el descompresor que traen Windows y Mac de fábrica no entiende ese cifrado:
                pide la contraseña y falla igual. Avísele al aliado cuando le mande el archivo.
            </p>
        </div>
    </details>

    {{-- Historial --}}
    <div style="font-size:.85rem;font-weight:700;color:#1e293b;margin:1.75rem 0 .75rem">Entregas generadas</div>

    @if($entregas->isEmpty())
        <div class="empty-bx-card">
            <div style="font-size:2rem;margin-bottom:.5rem">📦</div>
            <div style="font-size:.85rem;color:#64748b">Todavía no se ha generado ninguna entrega.</div>
        </div>
    @else
        <div class="table-bx-container">
            <table class="table-bx">
                <thead>
                    <tr>
                        <th style="text-align:left">#</th>
                        <th style="text-align:left">Aliado</th>
                        <th style="text-align:left">Generada</th>
                        <th style="text-align:right">Registros</th>
                        <th style="text-align:center">Tamaño</th>
                        <th style="text-align:center">Descargas</th>
                        <th style="text-align:center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($entregas as $entrega)
                    <tr>
                        <td style="color:#94a3b8">{{ $entrega->id }}</td>
                        <td>
                            <div style="font-weight:600;color:#1e293b">{{ $entrega->aliado->nombre ?? '—' }}</div>
                            <div style="font-size:.72rem;color:#94a3b8">por {{ $entrega->solicitante->nombre ?? '—' }}</div>
                            @if($entrega->estado === 'fallido')
                                <div style="font-size:.72rem;color:#b91c1c">⚠️ {{ \Illuminate\Support\Str::limit($entrega->error, 90) }}</div>
                            @endif
                        </td>
                        <td style="font-size:.78rem;color:#475569">{{ $entrega->created_at?->format('d/m/Y H:i') }}</td>
                        <td style="text-align:right">{{ number_format((int) $entrega->filas_total, 0, ',', '.') }}</td>
                        <td style="text-align:center"><span class="badge-bx-size">{{ $entrega->tamanoLegible() }}</span></td>
                        <td style="text-align:center">{{ $entrega->descargas }}</td>
                        <td style="text-align:center;white-space:nowrap">
                            @if($entrega->disponible())
                                <a href="{{ route('brynex.exportaciones.descargar', $entrega->id) }}"
                                   class="btn-bx-download" title="Descargar el ZIP">⬇️</a>
                                <button type="button" class="btn-bx-download" title="Ver la contraseña"
                                        onclick="verPassword({{ $entrega->id }})">🔑</button>
                                <form action="{{ route('brynex.exportaciones.eliminar', $entrega->id) }}"
                                      method="POST" style="display:inline"
                                      onsubmit="return confirm('¿Borrar el archivo del servidor? El registro de la entrega se conserva.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-bx-download" title="Borrar el archivo">🗑️</button>
                                </form>
                            @elseif($entrega->purgado_at)
                                <span style="font-size:.72rem;color:#94a3b8">archivo borrado</span>
                            @else
                                <span style="font-size:.72rem;color:#94a3b8">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div style="margin-top:1.25rem;font-size:.75rem;color:#94a3b8;line-height:1.5">
        Cada solicitud, generación y descarga queda en la bitácora y se avisa por WhatsApp.
        El paquete lleva una referencia firmada; para saber de qué entrega salió un archivo:
        <code>php artisan traza:verificar "&lt;referencia&gt;"</code>.
    </div>

</div>
@endsection

@push('styles')
<style>
    .card-bx {
        background:#fff;
        border:1px solid #e2e8f0;
        border-radius:12px;
        padding:1.25rem;
        margin-bottom:1.25rem;
        box-shadow:0 1px 6px rgba(0,0,0,.05);
    }
    .select-bx {
        width:100%;
        padding:.6rem .75rem;
        border:1px solid #cbd5e1;
        border-radius:8px;
        font-size:.85rem;
        color:#1e293b;
        background:#fff;
    }
    .input-codigo {
        width:150px;
        padding:.6rem .75rem;
        border:1px solid #f59e0b;
        border-radius:8px;
        font-size:1.3rem;
        font-weight:700;
        letter-spacing:.35em;
        text-align:center;
        font-family:ui-monospace,Menlo,monospace;
        color:#78350f;
        background:#fff;
    }
    .btn-bx-action {
        display:inline-flex;
        align-items:center;
        background-color:#3b82f6;
        color:#fff;
        border:none;
        border-radius:8px;
        padding:.65rem 1.1rem;
        font-size:.82rem;
        font-weight:700;
        cursor:pointer;
        transition:all .2s ease;
    }
    .btn-bx-action:hover { background-color:#2563eb; transform:translateY(-1px); }
    .btn-bx-action.success { background-color:#10b981; }
    .btn-bx-action.success:hover { background-color:#059669; }
    .btn-bx-action:disabled { background-color:#cbd5e1; color:#94a3b8; cursor:not-allowed; transform:none; }

    .btn-link-bx {
        background:none;
        border:none;
        padding:0;
        font-size:.78rem;
        color:#92400e;
        text-decoration:underline;
        cursor:pointer;
    }

    .alert-bx {
        display:flex;
        align-items:flex-start;
        padding:.85rem 1.2rem;
        border-radius:8px;
        margin-bottom:1.25rem;
        font-size:.82rem;
        line-height:1.4;
    }
    .alert-bx.success { background-color:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
    .alert-bx.error   { background-color:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }

    .table-bx-container {
        background:#fff;
        border-radius:12px;
        overflow:hidden;
        border:1px solid #e2e8f0;
        box-shadow:0 1px 6px rgba(0,0,0,.05);
    }
    .table-bx { width:100%; border-collapse:collapse; font-size:.82rem; }
    .table-bx th {
        background-color:#f8fafc;
        color:#475569;
        font-weight:700;
        padding:.9rem 1.1rem;
        border-bottom:1px solid #e2e8f0;
    }
    .table-bx td { padding:.9rem 1.1rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
    .table-bx tr:last-child td { border-bottom:none; }
    .table-bx tr:hover td { background-color:#f8fafc; }

    .badge-bx-size {
        background-color:#f1f5f9;
        color:#475569;
        font-weight:700;
        font-size:.75rem;
        padding:.3rem .6rem;
        border-radius:20px;
        display:inline-block;
    }
    .btn-bx-download {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:32px;
        height:32px;
        background-color:#f1f5f9;
        border-radius:50%;
        text-decoration:none;
        border:1px solid #e2e8f0;
        cursor:pointer;
        transition:all .2s ease;
    }
    .btn-bx-download:hover { background-color:#e2e8f0; transform:scale(1.08); }

    .empty-bx-card {
        background:#fff;
        border-radius:12px;
        padding:3rem;
        text-align:center;
        box-shadow:0 1px 6px rgba(0,0,0,.05);
        border:1px solid #e2e8f0;
    }
</style>
@endpush

@push('scripts')
<script>
    // Generar puede tardar minutos en un aliado grande: se bloquea el botón
    // para que no se dispare dos veces.
    const formConfirmar = document.getElementById('formConfirmar');
    if (formConfirmar) {
        formConfirmar.addEventListener('submit', function () {
            const btn = document.getElementById('btnConfirmar');
            btn.disabled = true;
            btn.innerHTML = '⏳ Generando, no cierre esta pestaña...';
        });
    }

    function verPassword(id) {
        fetch('{{ url('brynex/exportaciones') }}/' + id + '/password')
            .then(r => r.json())
            .then(d => {
                if (d.password) {
                    window.prompt('Contraseña del ZIP (cópiela):', d.password);
                } else {
                    alert('Esa entrega no tiene contraseña guardada.');
                }
            })
            .catch(() => alert('No pude consultar la contraseña.'));
    }
</script>
@endpush
