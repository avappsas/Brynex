@extends('layouts.app')

@section('title', 'Saldos a favor')

@section('contenido')
<style>
/* ══════════════════════════════════════
   Saldos a favor — BryNex
══════════════════════════════════════ */
.sf-page { max-width: 1300px; margin: 0 auto; padding: 1.5rem 1rem; }

.sf-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
.sf-title { font-size: 1.45rem; font-weight: 900; color: #0f172a; display: flex; align-items: center; gap: .55rem; }
.sf-title span { font-size: 1.6rem; }
.sf-volver { padding: .42rem 1.1rem; background: #fff; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 8px; text-decoration: none; font-size: .8rem; font-weight: 700; }

/* Pestañas anticipos / saldos */
.sf-tabs { display: flex; gap: .4rem; margin-bottom: 1.25rem; }
.sf-tab {
    padding: .45rem 1.1rem; border-radius: 9px; text-decoration: none;
    font-size: .82rem; font-weight: 800; border: 1.5px solid #e2e8f0;
    background: #fff; color: #64748b; transition: all .15s;
}
.sf-tab:hover { border-color: #cbd5e1; color: #334155; }
.sf-tab.activa { background: linear-gradient(135deg,#78350f,#d97706); color: #fff; border-color: transparent; }

/* Explicación */
.sf-nota {
    background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 12px;
    padding: .8rem 1.1rem; font-size: .8rem; color: #92400e; line-height: 1.55;
    margin-bottom: 1.25rem;
}
.sf-nota b { font-weight: 800; }

/* Tarjetas */
.sf-totales { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: .85rem; margin-bottom: 1.25rem; }
.sf-card { border-radius: 13px; padding: .85rem 1.1rem; display: flex; flex-direction: column; gap: .3rem; }
.sf-card-label { font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; opacity: .8; }
.sf-card-val { font-size: 1.45rem; font-weight: 900; font-family: monospace; }
.sf-card-clientes { background: linear-gradient(135deg,#dbeafe,#eff6ff); color: #1e40af; }
.sf-card-total    { background: linear-gradient(135deg,#fef3c7,#fffbeb); color: #92400e; }
.sf-card-revisar  { background: linear-gradient(135deg,#fee2e2,#fff1f2); color: #991b1b; }
.sf-card-ajustado { background: linear-gradient(135deg,#f1f5f9,#f8fafc); color: #475569; }

/* Barra de acción */
.sf-barra {
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 14px;
    padding: .8rem 1.1rem; display: flex; gap: .7rem; flex-wrap: wrap;
    align-items: center; margin-bottom: 1rem; box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.sf-buscar { padding: .38rem .65rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: .82rem; outline: none; font-family: inherit; min-width: 210px; }
.sf-buscar:focus { border-color: #d97706; }
.sf-chk-lbl { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 700; color: #475569; cursor: pointer; }
.sf-sel { font-size: .78rem; font-weight: 800; color: #92400e; margin-left: auto; }
.sf-btn {
    padding: .45rem 1.2rem; background: linear-gradient(135deg,#7f1d1d,#be123c);
    color: #fff; border: none; border-radius: 8px; cursor: pointer;
    font-size: .82rem; font-weight: 800; transition: all .18s; white-space: nowrap;
}
.sf-btn:hover:not(:disabled) { opacity: .9; transform: translateY(-1px); }
.sf-btn:disabled { opacity: .4; cursor: not-allowed; }

/* Tabla */
.sf-table-wrap { background: #fff; border: 1.5px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
.sf-table-wrap table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.sf-table-wrap thead th {
    background: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: .55rem .85rem;
    font-weight: 800; color: #64748b; font-size: .65rem; text-transform: uppercase;
    letter-spacing: .06em; text-align: left; white-space: nowrap;
}
.sf-table-wrap tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .12s; }
.sf-table-wrap tbody tr:last-child { border-bottom: none; }
.sf-table-wrap tbody tr:hover { background: #fafafa; }
.sf-table-wrap tbody tr.marcada { background: #fffbeb; }
.sf-table-wrap td { padding: .5rem .85rem; color: #1e293b; vertical-align: middle; }
.sf-mono { font-family: 'JetBrains Mono', monospace; font-weight: 700; }
.sf-nombre { font-weight: 700; }
.sf-empresa { display: inline-block; font-size: .65rem; font-weight: 800; color: #6d28d9; background: #ede9fe; border-radius: 5px; padding: .1rem .4rem; }
.sf-indiv { font-size: .68rem; color: #94a3b8; font-weight: 600; }
.sf-origen { font-size: .68rem; color: #64748b; }
.sf-origen a { color: #1d4ed8; text-decoration: none; font-weight: 700; }
.sf-origen a:hover { text-decoration: underline; }
.sf-badge-revisar { display: inline-flex; align-items: center; gap: .22rem; padding: .16rem .5rem; border-radius: 20px; font-size: .62rem; font-weight: 800; background: #fee2e2; color: #991b1b; }
.sf-badge-ok { display: inline-flex; padding: .16rem .5rem; border-radius: 20px; font-size: .62rem; font-weight: 800; background: #dcfce7; color: #15803d; }

/* Ajustes ya hechos */
.sf-hechos { margin-top: 1.5rem; }
.sf-hechos > summary {
    cursor: pointer; font-size: .82rem; font-weight: 800; color: #475569;
    padding: .6rem .9rem; background: #fff; border: 1.5px solid #e2e8f0;
    border-radius: 10px; list-style: none;
}
.sf-hechos > summary::-webkit-details-marker { display: none; }
.sf-hechos > summary:hover { border-color: #cbd5e1; }
.sf-hechos[open] > summary { border-radius: 10px 10px 0 0; border-bottom: none; }
.sf-hechos .sf-table-wrap { border-radius: 0 0 14px 14px; }
.sf-fecha { font-size: .7rem; color: #64748b; }
.btn-deshacer {
    padding: .22rem .6rem; border-radius: 6px; font-size: .68rem; font-weight: 800;
    background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; cursor: pointer;
}
.btn-deshacer:hover { background: #ffe4e6; }

.sf-empty { padding: 3rem; text-align: center; color: #94a3b8; font-size: .9rem; font-weight: 600; }
.sf-empty span { font-size: 2.5rem; display: block; margin-bottom: .75rem; }

@media (max-width: 640px) { .sf-table-wrap { overflow-x: auto; } }
</style>

<div class="sf-page"
     x-data="saldosFavor()"
     x-init="$nextTick(() => recontar())">

    {{-- HEADER --}}
    <div class="sf-header">
        <h1 class="sf-title"><span>🧾</span> Saldos a favor</h1>
        <a href="{{ route('admin.facturacion.index') }}" class="sf-volver">← Volver</a>
    </div>

    <div class="sf-tabs">
        <a href="{{ route('admin.anticipos.informe') }}" class="sf-tab">💰 Anticipos</a>
        <a href="{{ route('admin.anticipos.saldos') }}" class="sf-tab activa">🧾 Saldos a favor</a>
    </div>

    <div class="sf-nota">
        Un <b>saldo a favor</b> no es un anticipo: no es plata que el cliente entregó, sino lo que
        una factura vieja le quedó debiendo de vuelta. Varios nacieron de <b>“Otros” que se escribieron
        y nunca se cobraron</b> — esos van marcados <b>Revisar</b>. Marcar un saldo como
        <b>utilizado por el aliado</b> no toca la factura: solo deja el ajuste, con quién y cuándo, y se puede deshacer.
    </div>

    {{-- TOTALES --}}
    <div class="sf-totales">
        <div class="sf-card sf-card-clientes">
            <span class="sf-card-label">Clientes con saldo</span>
            <span class="sf-card-val">{{ number_format($totales['clientes'], 0, ',', '.') }}</span>
        </div>
        <div class="sf-card sf-card-total">
            <span class="sf-card-label">Saldo a favor vivo</span>
            <span class="sf-card-val">${{ number_format($totales['disponible'], 0, ',', '.') }}</span>
        </div>
        <div class="sf-card sf-card-revisar">
            <span class="sf-card-label">Por revisar (“Otros”)</span>
            <span class="sf-card-val">${{ number_format($totales['sospechoso'], 0, ',', '.') }}</span>
        </div>
        <div class="sf-card sf-card-ajustado">
            <span class="sf-card-label">Ya ajustado</span>
            <span class="sf-card-val">${{ number_format($totales['ajustado'], 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- BARRA DE ACCIÓN --}}
    <div class="sf-barra">
        <input type="text" class="sf-buscar" placeholder="Buscar cédula o nombre…"
               x-model="busqueda" @input="filtrar()">
        <label class="sf-chk-lbl">
            <input type="checkbox" x-model="soloRevisar" @change="filtrar()"> Solo los de revisar
        </label>
        <span class="sf-sel" x-show="seleccion > 0" x-cloak>
            <span x-text="seleccion"></span> seleccionado(s) · $<span x-text="formato(montoSel)"></span>
        </span>
        <button class="sf-btn" :disabled="seleccion === 0 || enviando"
                @click="marcar()"
                x-text="enviando ? 'Guardando…' : 'Marcar utilizado por el aliado'"></button>
    </div>

    {{-- TABLA --}}
    <div class="sf-table-wrap">
        @if ($filas->isEmpty())
            <div class="sf-empty"><span>✅</span> Ningún cliente tiene saldo a favor pendiente.</div>
        @else
        <table>
            <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" @change="marcarTodos($event)"></th>
                    <th>Cédula</th>
                    <th>Cliente</th>
                    <th>Empresa</th>
                    <th>Viene de</th>
                    <th style="text-align:right;">Saldo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $f)
                <tr class="sf-fila"
                    data-busqueda="{{ mb_strtolower($f->cedula.' '.$f->nombre) }}"
                    data-revisar="{{ $f->sospechoso ? 1 : 0 }}"
                    :class="{ 'marcada': sel['{{ $f->cedula }}'] }">
                    <td>
                        <input type="checkbox" class="sf-chk" value="{{ $f->cedula }}"
                               data-monto="{{ $f->disponible }}"
                               x-model="sel['{{ $f->cedula }}']" @change="recontar()">
                    </td>
                    <td class="sf-mono">{{ $f->cedula }}</td>
                    <td class="sf-nombre">{{ $f->nombre }}</td>
                    <td>
                        @if ($f->empresa)
                            <span class="sf-empresa">{{ $f->empresa }}</span>
                        @else
                            <span class="sf-indiv">Individual</span>
                        @endif
                    </td>
                    <td class="sf-origen">
                        @foreach ($f->facturas as $o)
                            <a href="{{ route('admin.facturacion.recibo', $o->id) }}" target="_blank"
                               title="${{ number_format($o->saldo_proximo, 0, ',', '.') }}">#{{ $o->numero_factura }}</a>
                            <span>({{ $o->mes }}/{{ $o->anio }})</span>@if (! $loop->last), @endif
                        @endforeach
                    </td>
                    <td class="sf-mono" style="text-align:right;">${{ number_format($f->disponible, 0, ',', '.') }}</td>
                    <td>
                        @if ($f->sospechoso)
                            <span class="sf-badge-revisar">⚠ Revisar</span>
                        @else
                            <span class="sf-badge-ok">Real</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- AJUSTES YA HECHOS --}}
    @if ($ajustesHechos->isNotEmpty())
    <details class="sf-hechos">
        <summary>🗂 Saldos ya dados por consumidos ({{ $ajustesHechos->count() }})</summary>
        <div class="sf-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cédula</th>
                        <th>Cliente</th>
                        <th>Motivo</th>
                        <th>Quién</th>
                        <th style="text-align:right;">Valor</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ajustesHechos as $a)
                    <tr>
                        <td class="sf-fecha">{{ $a->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="sf-mono">{{ $a->cedula }}</td>
                        <td>{{ nombre_oracion($a->nombre_cliente ?? '—') }}</td>
                        <td style="font-size:.75rem;color:#475569;">{{ $a->motivo }}</td>
                        <td style="font-size:.72rem;color:#64748b;">{{ $a->usuario?->nombre ?? '—' }}</td>
                        <td class="sf-mono" style="text-align:right;">${{ number_format($a->valor, 0, ',', '.') }}</td>
                        <td>
                            <button class="btn-deshacer" @click="deshacer({{ $a->id }}, '{{ $a->cedula }}')">
                                Deshacer
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
    @endif
</div>

<script>
function saldosFavor() {
    return {
        sel: {},
        busqueda: '',
        soloRevisar: false,
        seleccion: 0,
        montoSel: 0,
        enviando: false,

        formato(n) { return new Intl.NumberFormat('es-CO').format(n); },

        // El contador vive del DOM: así una fila escondida por el filtro sigue
        // contando si el usuario ya la había marcado.
        recontar() {
            let n = 0, monto = 0;
            document.querySelectorAll('.sf-chk').forEach(chk => {
                if (this.sel[chk.value]) { n++; monto += parseInt(chk.dataset.monto, 10) || 0; }
            });
            this.seleccion = n;
            this.montoSel = monto;
        },

        filtrar() {
            // Sin tildes: "jose" debe encontrar a "José" y al revés.
            const norm = t => (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            const q = norm(this.busqueda.trim());
            document.querySelectorAll('.sf-fila').forEach(fila => {
                const pasaTexto = !q || norm(fila.dataset.busqueda).includes(q);
                const pasaTipo = !this.soloRevisar || fila.dataset.revisar === '1';
                fila.style.display = (pasaTexto && pasaTipo) ? '' : 'none';
            });
        },

        // Solo alcanza a lo que se ve: marcar todo con un filtro puesto no
        // debería llevarse por delante a los clientes escondidos.
        marcarTodos(e) {
            document.querySelectorAll('.sf-fila').forEach(fila => {
                if (fila.style.display === 'none') return;
                const chk = fila.querySelector('.sf-chk');
                if (chk) this.sel[chk.value] = e.target.checked;
            });
            this.recontar();
        },

        async deshacer(id, cedula) {
            if (!confirm('¿Devolverle el saldo a favor a la c.c. ' + cedula + '?')) return;
            try {
                const r = await fetch('{{ url('admin/anticipos/saldos/ajuste') }}/' + id, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                const data = await r.json();
                if (!r.ok || !data.ok) throw new Error(data.message || data.mensaje || 'No se pudo deshacer');
                window.location.reload();
            } catch (err) {
                alert('Error: ' + err.message);
            }
        },

        async marcar() {
            const cedulas = Object.keys(this.sel).filter(c => this.sel[c]);
            if (!cedulas.length) return;

            const texto = '¿Marcar el saldo a favor de ' + cedulas.length + ' cliente(s) por $'
                + this.formato(this.montoSel) + ' como utilizado por el aliado?\n\n'
                + 'La factura no se toca y el ajuste se puede deshacer.';
            if (!confirm(texto)) return;

            this.enviando = true;
            try {
                const r = await fetch('{{ route('admin.anticipos.saldos.ajustar') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ cedulas }),
                });
                const data = await r.json();
                if (!r.ok || !data.ok) throw new Error(data.message || data.mensaje || 'No se pudo guardar');
                alert(data.mensaje);
                window.location.reload();
            } catch (err) {
                alert('Error: ' + err.message);
                this.enviando = false;
            }
        },
    };
}
</script>
@endsection
