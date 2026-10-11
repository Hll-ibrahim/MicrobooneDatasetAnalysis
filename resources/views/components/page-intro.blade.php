@props(['key'])
@php $__p = \App\Support\Help::page($key); $__id = 'intro-'.$key; @endphp
@if($__p)
    <div class="card page-intro mb-3">
        <div class="card-body py-2">
            <a class="d-flex align-items-center gap-2 text-decoration-none page-intro-toggle" data-bs-toggle="collapse" href="#{{ $__id }}" role="button" aria-expanded="true">
                <i class="bi bi-lightbulb text-gold fs-5"></i>
                <strong class="serif fs-5 text-burgundy">{{ $__p['title'] }}</strong>
                <i class="bi bi-chevron-down ms-auto small text-muted"></i>
            </a>
            <div class="collapse show" id="{{ $__id }}">
                <p class="mb-1 mt-2">{!! \App\Support\Help::render($__p['body']) !!}</p>
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
