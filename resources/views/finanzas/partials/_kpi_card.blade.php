<div class="kpi-card" style="border-left: 4px solid {{ $color ?? 'var(--acento)' }}">
    <div class="kpi-icon">{{ $icon ?? '💰' }}</div>
    <div class="kpi-content">
        <span class="kpi-label">{{ $label }}</span>
        <span class="kpi-val">{{ $value }}</span>
        @if(isset($change))
            <span class="kpi-change {{ $change >= 0 ? 'pos' : 'neg' }}">
                {{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}% vs mes ant.
            </span>
        @endif
    </div>
</div>
