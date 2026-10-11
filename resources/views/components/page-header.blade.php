@props(['title', 'subtitle' => null, 'icon' => null])

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
    <div>
        <h1 class="page-title">
            @if($icon)<i class="bi {{ $icon }} me-1"></i>@endif
            {{ $title }}
        </h1>
        @if($subtitle)
            <p class="page-subtitle mb-0 mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @if($slot->isNotEmpty())
        <div class="d-flex flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
