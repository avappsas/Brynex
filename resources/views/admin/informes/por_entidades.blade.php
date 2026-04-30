@extends('layouts.app')
@section('modulo','Por Entidades')
@section('contenido')
<div style="max-width:1100px;margin:0 auto;" x-data="{tab:'eps'}">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
        <a href="{{ route('admin.informes.hub') }}" style="color:#64748b;font-size:.82rem;text-decoration:none;">← Informes</a>
        <h1 style="font-size:1.2rem;font-weight:700;color:#0d2550;flex:1;">🏥 Distribución por Entidades</h1>
    </div>

    {{-- Tabs --}}
    <div style="display:flex;gap:.5rem;margin-bottom:1rem;border-bottom:2px solid #e2e8f0;padding-bottom:0;">
        @foreach(['eps'=>'EPS','pension'=>'Pensión','arl'=>'ARL','caja'=>'Caja Compensación'] as $key=>$label)
        <button @click="tab='{{ $key }}'" :style="tab==='{{ $key }}'?'border-bottom:3px solid #2563eb;color:#2563eb;margin-bottom:-2px;':'color:#64748b;'"
            style="background:none;border:none;padding:.5rem 1.25rem;font-size:.85rem;font-weight:600;cursor:pointer;transition:color .15s;">
            {{ $label }}
        </button>
        @endforeach
    </div>

    @php
    $tabData = ['eps'=>$eps,'pension'=>$pension,'arl'=>$arl,'caja'=>$caja];
    @endphp

    @foreach($tabData as $key=>$rows)
    <div x-show="tab==='{{ $key }}'" style="background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:.84rem;">
            <thead><tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Entidad</th>
                <th style="padding:.65rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase;color:#64748b;">Contratos Vigentes</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;">Distribución</th>
            </tr></thead>
            <tbody>
                @php $maxRows = $rows->max('total') ?: 1; @endphp
                @forelse($rows as $r)
                <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                    <td style="padding:.6rem 1rem;font-weight:600;color:#0d2550;">{{ $r->nombre }}</td>
                    <td style="padding:.6rem 1rem;text-align:center;font-size:1.1rem;font-weight:800;color:#2563eb;">{{ $r->total }}</td>
                    <td style="padding:.6rem 1rem;min-width:160px;">
                        <div style="height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                            <div style="height:100%;width:{{ round($r->total/$maxRows*100) }}%;background:#3b82f6;border-radius:4px;"></div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" style="padding:2rem;text-align:center;color:#94a3b8;">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endforeach
</div>
@endsection
