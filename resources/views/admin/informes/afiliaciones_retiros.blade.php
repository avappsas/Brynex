@extends('layouts.app')
@section('modulo','Afiliaciones y Retiros')
@section('contenido')
@php
$mesesEs=['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$years=range(now()->year-3,now()->year);
@endphp
<div style="max-width:1000px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">📥 Afiliaciones y Retiros</h1>
    </div>

    <form method="GET" style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center;">
        <select name="mes" style="padding:.45rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.84rem;">
            @foreach($mesesEs as $n=>$nm) @if($n>0) <option value="{{ $n }}" {{ $mes==$n?'selected':'' }}>{{ $nm }}</option> @endif @endforeach
        </select>
        <select name="anio" style="padding:.45rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.84rem;">
            @foreach($years as $y) <option value="{{ $y }}" {{ $anio==$y?'selected':'' }}>{{ $y }}</option> @endforeach
        </select>
        <button type="submit" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.45rem 1.25rem;font-size:.85rem;cursor:pointer;">Ver</button>
    </form>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        {{-- Afiliaciones --}}
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1e40af,#3b82f6);padding:1rem 1.25rem;display:flex;align-items:center;gap:.75rem;">
                <span style="font-size:1.4rem;">📥</span>
                <div>
                    <div style="color:#fff;font-weight:700;">Afiliaciones</div>
                    <div style="color:rgba(255,255,255,.7);font-size:.8rem;">{{ $mesesEs[$mes] }} {{ $anio }}</div>
                </div>
                <div style="margin-left:auto;font-size:2rem;font-weight:800;color:#fff;">{{ $afiliaciones->sum('total') }}</div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:.84rem;">
                <thead><tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <th style="padding:.5rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Motivo</th>
                    <th style="padding:.5rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Total</th>
                </tr></thead>
                <tbody>
                    @forelse($afiliaciones as $a)
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:.55rem 1rem;">{{ $a->motivo ?? 'Sin motivo' }}</td>
                        <td style="padding:.55rem 1rem;text-align:center;font-weight:700;color:#2563eb;">{{ $a->total }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" style="padding:1.5rem;text-align:center;color:#94a3b8;">Sin afiliaciones</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Retiros --}}
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
            <div style="background:linear-gradient(135deg,#b45309,#f59e0b);padding:1rem 1.25rem;display:flex;align-items:center;gap:.75rem;">
                <span style="font-size:1.4rem;">🚪</span>
                <div>
                    <div style="color:#fff;font-weight:700;">Retiros</div>
                    <div style="color:rgba(255,255,255,.7);font-size:.8rem;">{{ $mesesEs[$mes] }} {{ $anio }}</div>
                </div>
                <div style="margin-left:auto;font-size:2rem;font-weight:800;color:#fff;">{{ $retiros->sum('total') }}</div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:.84rem;">
                <thead><tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <th style="padding:.5rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;color:#64748b;">Motivo</th>
                    <th style="padding:.5rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase;color:#64748b;">Total</th>
                </tr></thead>
                <tbody>
                    @forelse($retiros as $r)
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:.55rem 1rem;">{{ $r->motivo ?? 'Sin motivo' }}</td>
                        <td style="padding:.55rem 1rem;text-align:center;font-weight:700;color:#d97706;">{{ $r->total }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" style="padding:1.5rem;text-align:center;color:#94a3b8;">Sin retiros</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
