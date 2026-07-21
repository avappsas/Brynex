<div class="fin-banner-header">
    <div class="fin-banner-text">
        @if(!empty($breadcrumb) && is_array($breadcrumb))
            <div class="fin-banner-breadcrumb">
                <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
                @foreach($breadcrumb as $name => $route)
                    <span>›</span>
                    @if($route)
                        <a href="{{ $route }}">{{ $name }}</a>
                    @else
                        <span>{{ $name }}</span>
                    @endif
                @endforeach
            </div>
        @endif
        <div class="fin-banner-title-row">
            <span class="fin-banner-title">{{ $titulo }}</span>
        </div>
        @if(!empty($subtitulo))
            <div class="fin-banner-sub">{{ $subtitulo }}</div>
        @endif
    </div>
    @if(isset($opciones))
        <div class="fin-banner-options">
            {{ $opciones }}
        </div>
    @endif
</div>
