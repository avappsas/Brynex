@extends('layouts.app')
@section('modulo', 'Marketing')

@section('contenido')
<div style="max-width:940px;margin:0 auto;">

    <a href="{{ route('admin.marketing.index') }}" style="font-size:.8rem;color:#64748b;text-decoration:none;">← Marketing</a>

    <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:.5rem 0 .25rem;">🔄 Reactivación de retirados</h1>
    <p style="font-size:.85rem;color:#64748b;margin:0 0 1.25rem;">
        Clientes que se retiraron hace entre {{ $desde }} y {{ $hasta }} días y que hoy no tienen ningún contrato vigente ni por empezar.
    </p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.85rem;margin-bottom:1.5rem;">
        @foreach ([
            ['Por escribirles', $pendientes->count(), '#166534', '#dcfce7'],
            ['Retirados en la ventana', $resumen['candidatos'], '#0f172a', '#f1f5f9'],
            ['Dieron de baja', $resumen['sin_consentimiento'], '#991b1b', '#fee2e2'],
            ['Dijeron "por ahora no"', $resumen['aplazados'], '#a16207', '#fef9c3'],
            ['Ya contactados', $resumen['ya_enviados'], '#1e40af', '#dbeafe'],
        ] as [$etiqueta, $valor, $color, $fondo])
            <div style="background:{{ $fondo }};border-radius:12px;padding:.9rem 1rem;">
                <div style="font-size:1.6rem;font-weight:700;color:{{ $color }};line-height:1;">{{ number_format($valor) }}</div>
                <div style="font-size:.74rem;color:#475569;margin-top:.35rem;">{{ $etiqueta }}</div>
            </div>
        @endforeach
    </div>

    <form method="GET" style="display:flex;gap:.5rem;align-items:end;margin-bottom:1.25rem;font-size:.8rem;">
        <label style="color:#475569;">Desde (días)
            <input type="number" name="desde" value="{{ $desde }}" min="1" style="display:block;width:90px;padding:.35rem .5rem;border:1px solid #cbd5e1;border-radius:8px;">
        </label>
        <label style="color:#475569;">Hasta (días)
            <input type="number" name="hasta" value="{{ $hasta }}" min="2" style="display:block;width:90px;padding:.35rem .5rem;border:1px solid #cbd5e1;border-radius:8px;">
        </label>
        <button type="submit" style="padding:.45rem 1rem;border:none;border-radius:8px;background:#0f172a;color:#fff;font-size:.8rem;cursor:pointer;">Ver</button>
    </form>

    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:.85rem 1rem;margin-bottom:1.5rem;font-size:.8rem;color:#92400e;line-height:1.5;">
        El envío no se dispara desde aquí. Son mensajes a personas reales con costo por plantilla, así que sale por comando:
        <code style="background:#fff;padding:.15rem .4rem;border-radius:6px;">php artisan marketing:reactivacion --aliado=brygar --plantilla=reactivacion_afiliacion --limite=10 --enviar</code>
    </div>

    <h2 style="font-size:.95rem;font-weight:700;color:#0f172a;margin:0 0 .6rem;">Los siguientes en la fila</h2>

    @if($pendientes->isEmpty())
        <p style="font-size:.85rem;color:#64748b;">Nadie por contactar en esta ventana.</p>
    @else
        <div style="background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.05);overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                <thead>
                    <tr style="background:#f8fafc;color:#475569;text-align:left;">
                        <th style="padding:.6rem .8rem;font-weight:600;">Nombre</th>
                        <th style="padding:.6rem .8rem;font-weight:600;">Celular</th>
                        <th style="padding:.6rem .8rem;font-weight:600;">Se retiró</th>
                        <th style="padding:.6rem .8rem;font-weight:600;text-align:right;">Días</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendientes->take(50) as $p)
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:.55rem .8rem;color:#0f172a;">{{ $p->nombre }}</td>
                            <td style="padding:.55rem .8rem;color:#475569;">{{ $p->telefono }}</td>
                            <td style="padding:.55rem .8rem;color:#475569;">{{ substr((string) $p->fecha_retiro, 0, 10) }}</td>
                            <td style="padding:.55rem .8rem;color:#475569;text-align:right;">{{ $p->dias }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($pendientes->count() > 50)
            <p style="font-size:.78rem;color:#64748b;margin:.6rem 0 0;">Se muestran los primeros 50 de {{ $pendientes->count() }}.</p>
        @endif
    @endif

    @if($envios->isNotEmpty())
        <h2 style="font-size:.95rem;font-weight:700;color:#0f172a;margin:1.75rem 0 .6rem;">Tandas ya enviadas</h2>
        <div style="background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.05);overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                <thead>
                    <tr style="background:#f8fafc;color:#475569;text-align:left;">
                        <th style="padding:.6rem .8rem;font-weight:600;">Fecha</th>
                        <th style="padding:.6rem .8rem;font-weight:600;text-align:right;">Destinatarios</th>
                        <th style="padding:.6rem .8rem;font-weight:600;text-align:right;">Enviados</th>
                        <th style="padding:.6rem .8rem;font-weight:600;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($envios as $e)
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:.55rem .8rem;color:#0f172a;">{{ $e->created_at?->format('d/m/Y H:i') }}</td>
                            <td style="padding:.55rem .8rem;color:#475569;text-align:right;">{{ $e->detalles_count }}</td>
                            <td style="padding:.55rem .8rem;color:#475569;text-align:right;">{{ $e->total_enviados }}</td>
                            <td style="padding:.55rem .8rem;color:#475569;">{{ $e->estado }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
@endsection
