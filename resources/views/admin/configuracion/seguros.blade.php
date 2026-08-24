@extends('layouts.app')
@section('modulo', 'Seguros')

@section('contenido')
<style>
.sg-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1rem}
.sg-header h1{font-size:1.15rem;font-weight:800;margin:0 0 .2rem}
.sg-header p{font-size:.74rem;color:rgba(255,255,255,.6);margin:0}
.sg-card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:1rem}
.sg-card-head{background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:.65rem 1rem;font-size:.85rem;font-weight:700;color:#0f172a}
.sg-tabla{width:100%;border-collapse:collapse;font-size:.8rem}
.sg-tabla th{background:#f8fafc;color:#475569;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;
             text-align:left;padding:.55rem .8rem;border-bottom:1px solid #e2e8f0}
.sg-tabla td{padding:.55rem .8rem;border-bottom:1px solid #f1f5f9;color:#0f172a}
.sg-tabla tr:last-child td{border-bottom:none}
.sg-tabla tr.inactivo td{opacity:.55}
.sg-valor{font-weight:700;color:#0f172a;white-space:nowrap}
.sg-input{width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:.4rem .55rem;font-size:.8rem;font-family:inherit}
.sg-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.sg-form{display:grid;grid-template-columns:1.6fr .8fr 2fr .5fr auto;gap:.6rem;align-items:end;padding:.9rem 1rem}
.sg-lb{display:block;font-size:.68rem;font-weight:700;color:#64748b;margin-bottom:.25rem}
.btn-accion{background:var(--azul-btn,#2563eb);color:#fff;border:none;padding:.45rem 1rem;border-radius:8px;
            font-size:.82rem;font-weight:600;cursor:pointer;transition:background .15s}
.btn-accion:hover{background:#3b82f6}
.btn-mini{border:1px solid #e2e8f0;background:#fff;color:#475569;border-radius:7px;padding:.28rem .6rem;
          font-size:.72rem;font-weight:600;cursor:pointer}
.btn-mini:hover{border-color:#3b82f6;color:#2563eb}
.btn-mini.peligro:hover{border-color:#ef4444;color:#ef4444}
.badge-ok{background:rgba(34,197,94,.15);color:#15803d;border:1px solid rgba(34,197,94,.3);
          border-radius:999px;padding:.12rem .6rem;font-size:.68rem;font-weight:700}
.badge-off{background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;
           border-radius:999px;padding:.12rem .6rem;font-size:.68rem;font-weight:700}
.badge-uso{background:rgba(59,130,246,.12);color:#1d4ed8;border:1px solid rgba(59,130,246,.25);
           border-radius:999px;padding:.12rem .55rem;font-size:.68rem;font-weight:700}
.sg-vacia{text-align:center;color:#94a3b8;padding:2rem 1rem;font-size:.82rem}
.notif-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#991b1b;
             border-radius:10px;padding:.7rem 1rem;margin-bottom:1rem;font-size:.82rem}
.sg-nota{font-size:.72rem;color:#64748b;padding:.7rem 1rem;background:#f8fafc;border-top:1px solid #e2e8f0}
@media(max-width:820px){.sg-form{grid-template-columns:1fr}}
</style>

<div class="sg-header">
    <h1>💼 Seguros</h1>
    <p>Los seguros que vendes aparte de la seguridad social. En el contrato se escoge uno
       y el sistema cobra este valor cada mes.</p>
</div>

{{-- El success lo pinta el layout; aquí solo lo que el layout no muestra. --}}
@if(session('error'))<div class="notif-error">⚠️ {{ session('error') }}</div>@endif
@if($errors->any())<div class="notif-error">⚠️ {{ $errors->first() }}</div>@endif

<div class="sg-card">
    <div class="sg-card-head">Agregar un seguro</div>
    <form method="POST" action="{{ route('admin.configuracion.seguros.store') }}" class="sg-form">
        @csrf
        <div>
            <label class="sg-lb">Nombre</label>
            <input name="nombre" class="sg-input" placeholder="Plan exequial 2" required maxlength="120">
        </div>
        <div>
            <label class="sg-lb">Valor mensual</label>
            <input name="valor" type="number" class="sg-input" placeholder="30000" min="0" step="100" required>
        </div>
        <div>
            <label class="sg-lb">Qué cubre <span style="font-weight:500;color:#94a3b8">(opcional, sale en el recibo)</span></label>
            <input name="descripcion" class="sg-input" maxlength="500"
                   placeholder="Servicio exequial para el titular y su núcleo familiar">
        </div>
        <div>
            <label class="sg-lb">Orden</label>
            <input name="orden" type="number" class="sg-input" value="99" min="0" max="999">
        </div>
        <div><button class="btn-accion">Agregar</button></div>
    </form>
</div>

<div class="sg-card">
    <div class="sg-card-head">Catálogo ({{ $seguros->count() }})</div>
    <table class="sg-tabla">
        <thead>
            <tr>
                <th style="width:24%">Nombre</th>
                <th style="width:13%">Valor mensual</th>
                <th>Qué cubre</th>
                <th style="width:9%">Estado</th>
                <th style="width:11%">En uso</th>
                <th style="width:13%"></th>
            </tr>
        </thead>
        <tbody>
        @forelse($seguros as $s)
            {{-- El form vive fuera de la tabla y los campos lo referencian con form="":
                 un <form> envolviendo <td> no es HTML válido y el navegador lo expulsa. --}}
            <tr class="{{ $s->activo ? '' : 'inactivo' }}" id="fila-seguro-{{ $s->id }}">
                <td><input form="form-seguro-{{ $s->id }}" name="nombre" class="sg-input" value="{{ $s->nombre }}" maxlength="120" required></td>
                <td><input form="form-seguro-{{ $s->id }}" name="valor" type="number" class="sg-input" value="{{ (int) $s->valor }}" min="0" step="100" required></td>
                <td><input form="form-seguro-{{ $s->id }}" name="descripcion" class="sg-input" value="{{ $s->descripcion }}" maxlength="500"></td>
                <td>
                    <label style="display:flex;align-items:center;gap:.35rem;cursor:pointer">
                        <input form="form-seguro-{{ $s->id }}" type="checkbox" name="activo" value="1" @checked($s->activo)>
                        <span class="{{ $s->activo ? 'badge-ok' : 'badge-off' }}">{{ $s->activo ? 'Activo' : 'Inactivo' }}</span>
                    </label>
                </td>
                <td>
                    @if($s->contratos_vigentes > 0)
                        <span class="badge-uso">{{ $s->contratos_vigentes }} vigente{{ $s->contratos_vigentes == 1 ? '' : 's' }}</span>
                    @else
                        <span style="color:#94a3b8;font-size:.72rem">—</span>
                    @endif
                </td>
                <td style="white-space:nowrap">
                    <button form="form-seguro-{{ $s->id }}" class="btn-mini" type="submit">Guardar</button>
                    <button class="btn-mini peligro" type="button" onclick="eliminarSeguro({{ $s->id }})">Eliminar</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="sg-vacia">
                Todavía no hay seguros. Agrega el primero arriba — por ejemplo "Plan exequial 1" de $20.000.
            </td></tr>
        @endforelse
        </tbody>
    </table>
    @foreach($seguros as $s)
        <form method="POST" action="{{ route('admin.configuracion.seguros.update', $s->id) }}"
              id="form-seguro-{{ $s->id }}">@csrf @method('PATCH')</form>
    @endforeach

    <div class="sg-nota">
        Cambiar un valor aquí solo afecta a los contratos <strong>nuevos</strong>: cada contrato guarda
        el precio con el que se vendió, igual que la administración. Un seguro que ya esté en algún
        contrato no se puede eliminar, solo inactivar.
    </div>
</div>

<script>
function eliminarSeguro(id) {
    if (!confirm('¿Eliminar este seguro del catálogo?')) return;

    fetch('{{ url('admin/configuracion/seguros') }}/' + id, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'Accept': 'application/json',
        },
    })
    .then(async r => {
        const d = await r.json().catch(() => ({}));
        if (r.ok && d.ok) {
            document.getElementById('fila-seguro-' + id)?.remove();
        } else {
            alert(d.mensaje || 'No se pudo eliminar.');
        }
    })
    .catch(() => alert('No se pudo eliminar.'));
}
</script>
@endsection
