@if ($paginator->hasPages())
<nav style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;font-size:.8rem;">

    {{-- Info --}}
    <span style="color:#64748b;font-size:.75rem;">
        Mostrando <strong>{{ $paginator->firstItem() }}</strong> – <strong>{{ $paginator->lastItem() }}</strong>
        de <strong>{{ $paginator->total() }}</strong> registros
    </span>

    {{-- Botones --}}
    <div style="display:flex;align-items:center;gap:.3rem;flex-wrap:wrap;">

        {{-- Anterior --}}
        @if ($paginator->onFirstPage())
            <span style="padding:.35rem .75rem;border-radius:8px;background:#f1f5f9;color:#cbd5e1;font-weight:600;cursor:default;border:1px solid #e2e8f0;">
                &#8592;
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
               style="padding:.35rem .75rem;border-radius:8px;background:#fff;color:#475569;font-weight:600;border:1px solid #e2e8f0;text-decoration:none;transition:all .15s;"
               onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
                &#8592;
            </a>
        @endif

        {{-- Páginas --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span style="padding:.35rem .5rem;color:#94a3b8;">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span style="padding:.35rem .75rem;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;border:1px solid #2563eb;min-width:2rem;text-align:center;">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}"
                           style="padding:.35rem .75rem;border-radius:8px;background:#fff;color:#475569;font-weight:600;border:1px solid #e2e8f0;text-decoration:none;min-width:2rem;text-align:center;transition:all .15s;"
                           onmouseover="this.style.background='#eff6ff';this.style.color='#2563eb';this.style.borderColor='#bfdbfe'"
                           onmouseout="this.style.background='#fff';this.style.color='#475569';this.style.borderColor='#e2e8f0'">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Siguiente --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
               style="padding:.35rem .75rem;border-radius:8px;background:#fff;color:#475569;font-weight:600;border:1px solid #e2e8f0;text-decoration:none;transition:all .15s;"
               onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
                &#8594;
            </a>
        @else
            <span style="padding:.35rem .75rem;border-radius:8px;background:#f1f5f9;color:#cbd5e1;font-weight:600;cursor:default;border:1px solid #e2e8f0;">
                &#8594;
            </span>
        @endif
    </div>
</nav>
@endif
