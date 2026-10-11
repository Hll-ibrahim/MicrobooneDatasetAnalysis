@extends('layouts.app')

@section('title', 'Terimler Sözlüğü')

@section('header')
    <x-page-header title="Terimler Sözlüğü" icon="bi-journal-bookmark"
                   :subtitle="$total.' terim · fizik, dedektör, veri ve yazılım kavramları sade bir dille'">
        <a href="{{ route('glossary.create') }}" class="btn btn-gold"><i class="bi bi-plus-lg"></i> Yeni terim</a>
    </x-page-header>
@endsection

@section('content')
    <x-page-intro key="glossary"/>

    <div class="row g-3">
        {{-- ======================================================= kenar: arama + kategoriler --}}
        <div class="col-lg-3">
            <div class="card card-royal glossary-side">
                <div class="card-body">
                    <form method="GET" class="mb-3">
                        <label class="form-label" for="q">Ara</label>
                        <div class="input-group">
                            <input type="search" class="form-control" id="q" name="q" value="{{ $q }}" placeholder="ör. hit, nu_score">
                            @if($cat)<input type="hidden" name="kategori" value="{{ $cat }}">@endif
                            <button class="btn btn-primary"><i class="bi bi-search"></i></button>
                        </div>
                    </form>
                    <div class="form-label">Kategoriler</div>
                    <div class="list-group list-group-flush small">
                        <a href="{{ route('glossary.index', array_filter(['q' => $q])) }}"
                           class="list-group-item list-group-item-action d-flex justify-content-between @if(! $cat) active @endif">
                            Tümü <span class="badge badge-gold">{{ $total }}</span>
                        </a>
                        @foreach($categories as $key => $label)
                            <a href="{{ route('glossary.index', array_filter(['kategori' => $key, 'q' => $q])) }}"
                               class="list-group-item list-group-item-action d-flex justify-content-between @if($cat === $key) active @endif">
                                {{ $label }} <span class="badge badge-gold">{{ $counts[$key] ?? 0 }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ======================================================= terimler --}}
        <div class="col-lg-9">
            @if($grouped->isNotEmpty())
                <div class="letter-index mb-3">
                    @foreach($grouped->keys() as $letter)
                        <a href="#harf-{{ $loop->index }}" class="btn btn-sm btn-outline-gold">{{ $letter }}</a>
                    @endforeach
                </div>
            @endif

            @forelse($grouped as $letter => $items)
                <h2 class="letter-heading" id="harf-{{ $loop->index }}">{{ $letter }}</h2>
                @foreach($items as $t)
                    <article class="card glossary-term mb-3" id="{{ $t->slug }}">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <h3 class="h4 mb-1">{{ $t->term }}</h3>
                                    <span class="badge badge-gold">{{ $t->categoryLabel() }}</span>
                                    <span class="small text-muted font-mono ms-1">#{{ $t->slug }}</span>
                                </div>
                                <div class="text-nowrap">
                                    <a href="{{ route('glossary.edit', $t) }}" class="btn btn-sm btn-outline-gold" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('glossary.destroy', $t) }}" class="d-inline" data-confirm="“{{ $t->term }}” sözlükten silinsin mi?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Sil"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </div>
                            </div>
                            <p class="glossary-short mt-2 mb-2">{{ $t->short }}</p>
                            @if($t->body)
                                <p class="mb-2">{!! \App\Support\Help::render(e($t->body)) !!}</p>
                            @endif
                            @if(! empty($t->related))
                                <div class="small">
                                    <span class="text-muted">İlgili:</span>
                                    @foreach($t->related as $r)
                                        <x-term :slug="$r"/>@if(! $loop->last), @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            @empty
                <div class="card card-royal"><div class="empty-state"><i class="bi bi-search"></i>Aramaya uyan terim bulunamadı.</div></div>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Bağlantıyla gelinen terimi kısa süre vurgula
        (function () {
            const h = decodeURIComponent(location.hash.slice(1));
            const el = h && document.getElementById(h);
            if (el && el.classList.contains('glossary-term')) { el.classList.add('flash'); setTimeout(() => el.classList.remove('flash'), 2400); }
        })();
    </script>
@endpush
