@php
    $fmt = fn($v) => '$ '.number_format($v,0,',','.');
    $fpEstilo = [
        'efectivo'     => ['color' => '#16a34a', 'icono' => '💵'],
        'consignacion' => ['color' => '#2563eb', 'icono' => '🏦'],
        'prestamo'     => ['color' => '#d97706', 'icono' => '🧾'],
    ];
@endphp

<div style="margin-bottom:.75rem;font-size:.76rem;color:#94a3b8;">
    {{ str_pad($dia,2,'0',STR_PAD_LEFT) }} de {{ $mesesEs[$mes] }} {{ $anio }} · no incluye seguro
</div>

@forelse($buckets as $fpKey => $b)
    @php $est = $fpEstilo[$fpKey] ?? ['color' => '#64748b', 'icono' => '💰']; @endphp
    <div style="border:1px solid #e2e8f0;border-radius:12px;margin-bottom:1rem;overflow:hidden;">
        <div style="background:{{ $est['color'] }};color:#fff;padding:.55rem .9rem;font-weight:800;font-size:.85rem;display:flex;justify-content:space-between;align-items:center;">
            <span>{{ $est['icono'] }} {{ $b['label'] }}</span>
            <span style="font-family:monospace;">{{ $fmt($b['total_entradas']) }}</span>
        </div>
        <div style="padding:.75rem .9rem;">
            <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:.3rem;">Administración</div>
            @foreach([['Administración',$b['admon']],['IVA',$b['iva']],['Otros',$b['otros']],['Comisión retiros',$b['retiro_campo']],['Trámites',$b['tramites']]] as [$l,$v])
                @if($v > 0)
                <div style="display:flex;justify-content:space-between;padding:.2rem 0;font-size:.8rem;">
                    <span style="color:#475569;">{{ $l }}</span>
                    <span style="font-family:monospace;font-weight:600;">{{ $fmt($v) }}</span>
                </div>
                @endif
            @endforeach
            @if($b['admin_asesor'] > 0)
                <div style="display:flex;justify-content:space-between;padding:.2rem 0;font-size:.7rem;opacity:.7;font-style:italic;">
                    <span>↳ Comisión asesor (ganada, pendiente)</span>
                    <span style="font-family:monospace;color:#f59e0b;">{{ $fmt($b['admin_asesor']) }}</span>
                </div>
            @endif

            @if($b['afiliacion'] > 0)
                <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin:.6rem 0 .3rem;">Afiliaciones</div>
                @foreach([['Admon',$b['dist_admon']],['Comisión asesor',$b['dist_asesor']],['Retiro',$b['dist_retiro']],['Utilidad',$b['dist_utilidad']],['Comisión encargado',$b['dist_encargado']],['Saldo (sin distribuir)',$b['dist_sin_asignar']]] as [$l,$v])
                    @if($v > 0)
                    <div style="display:flex;justify-content:space-between;padding:.2rem 0;font-size:.8rem;">
                        <span style="color:#475569;">{{ $l }}</span>
                        <span style="font-family:monospace;font-weight:600;">{{ $fmt($v) }}</span>
                    </div>
                    @endif
                @endforeach
            @endif

            @if($b['ss'] > 0)
                <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin:.6rem 0 .3rem;">Seguridad Social</div>
                <div style="display:flex;justify-content:space-between;padding:.2rem 0;font-size:.8rem;">
                    <span style="color:#475569;">SS recaudado</span>
                    <span style="font-family:monospace;font-weight:600;color:#0e7490;">{{ $fmt($b['ss']) }}</span>
                </div>
            @endif

            <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin:.6rem 0 .3rem;">Gastos pagados</div>
            @if($b['gasto_operativo'] > 0)
                <div style="display:flex;justify-content:space-between;padding:.2rem 0;font-size:.8rem;">
                    <span style="color:#475569;">Gastos operativos</span>
                    <span style="font-family:monospace;font-weight:600;color:#dc2626;">- {{ $fmt($b['gasto_operativo']) }}</span>
                </div>
            @endif
            @if($b['total_gastos'] == 0)
                <div style="font-size:.76rem;color:#94a3b8;">Sin gastos pagados con este medio.</div>
            @endif

            <div style="display:flex;justify-content:space-between;padding:.5rem .5rem;background:#f8fafc;border-radius:8px;margin-top:.5rem;">
                <span style="font-weight:700;font-size:.8rem;color:#334155;">Saldo neto</span>
                <span style="font-weight:800;font-family:monospace;color:{{ $b['saldo_neto']>=0?'#16a34a':'#dc2626' }};">{{ $fmt($b['saldo_neto']) }}</span>
            </div>
        </div>
    </div>
@empty
    <div style="text-align:center;padding:1.25rem;color:#94a3b8;font-size:.82rem;">Sin ingresos registrados ese día.</div>
@endforelse

{{-- Gastos reportados el día, con quién los registró --}}
<div style="margin-top:.4rem;">
    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:.4rem;">
        💸 Gastos reportados el día ({{ $gastos->count() }})
    </div>
    @forelse($gastos as $g)
        <div style="display:grid;grid-template-columns:1fr auto;gap:.5rem;padding:.4rem 0;border-bottom:1px solid #f1f5f9;font-size:.78rem;align-items:center;">
            <div>
                <div style="color:#1e293b;font-weight:600;">{{ $g['descripcion'] }}</div>
                <div style="color:#94a3b8;font-size:.68rem;">
                    {{ $g['tipo_label'] }} · registrado por {{ $g['usuario'] }} · {{ $g['forma_pago']==='efectivo'?'💵 Efectivo':'🏦 Banco' }}
                </div>
            </div>
            <span style="font-weight:700;font-family:monospace;color:#dc2626;white-space:nowrap;">- {{ $fmt($g['valor']) }}</span>
        </div>
    @empty
        <div style="text-align:center;padding:1rem;color:#94a3b8;font-size:.8rem;">Sin gastos reportados ese día.</div>
    @endforelse
</div>
