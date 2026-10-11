{{--
    Her zaman görünen sayfalama: tek sayfa olsa bile özet ve numara gösterilir.
    $approx = true ise toplam, PostgreSQL istatistiğinden gelen tahmindir.
--}}
@php $approx = $approx ?? false; @endphp
<nav class="royal-pagination d-flex flex-wrap justify-content-between align-items-center gap-2" aria-label="Sayfalama">
    <div class="small text-muted">
        @if($paginator->total() > 0)
            <strong>{{ tr_num($paginator->firstItem()) }}–{{ tr_num($paginator->lastItem()) }}</strong> arası gösteriliyor ·
            toplam <strong>{{ $approx ? '≈ ' : '' }}{{ tr_num($paginator->total()) }}</strong> kayıt ·
            sayfa <strong>{{ tr_num($paginator->currentPage()) }}</strong> / {{ tr_num($paginator->lastPage()) }}
        @else
            Kayıt yok
        @endif
    </div>
    <ul class="pagination pagination-sm mb-0">
        @if($paginator->onFirstPage())
            <li class="page-item disabled"><span class="page-link" aria-hidden="true">&laquo;</span></li>
            <li class="page-item disabled"><span class="page-link" aria-hidden="true">&lsaquo;</span></li>
        @else
            <li class="page-item"><a class="page-link" href="{{ $paginator->url(1) }}" title="İlk sayfa">&laquo;</a></li>
            <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" title="Önceki">&lsaquo;</a></li>
        @endif

        @foreach($elements as $element)
            @if(is_string($element))
                <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
            @endif
            @if(is_array($element))
                @foreach($element as $page => $url)
                    @if($page == $paginator->currentPage())
                        <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if($paginator->hasMorePages())
            <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" title="Sonraki">&rsaquo;</a></li>
            <li class="page-item"><a class="page-link" href="{{ $paginator->url($paginator->lastPage()) }}" title="Son sayfa">&raquo;</a></li>
        @else
            <li class="page-item disabled"><span class="page-link" aria-hidden="true">&rsaquo;</span></li>
            <li class="page-item disabled"><span class="page-link" aria-hidden="true">&raquo;</span></li>
        @endif
    </ul>
</nav>
