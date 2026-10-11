@props(['label', 'value', 'icon' => 'bi-gem', 'hint' => null])

<div {{ $attributes->merge(['class' => 'card stat-card card-royal h-100']) }}>
    <div class="card-body d-flex gap-3 align-items-start">
        <span class="stat-icon"><i class="bi {{ $icon }}"></i></span>
        <div class="min-w-0">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value">{{ $value }}</div>
            @if($hint)
                <div class="small text-muted">{{ $hint }}</div>
            @endif
        </div>
    </div>
</div>
