@props(['id', 'title', 'subtitle' => null, 'height' => 280, 'help' => null])

<div {{ $attributes->merge(['class' => 'card card-royal h-100']) }}>
    <div class="card-header d-flex justify-content-between align-items-baseline gap-2">
        <span>{{ $title }}</span>
        @if($subtitle)
            <small class="text-muted">{{ $subtitle }}</small>
        @endif
    </div>
    <div class="card-body d-flex flex-column">
        <div class="chart-box" style="height: {{ $height }}px">
            <canvas id="{{ $id }}"></canvas>
        </div>
        {{ $slot }}
        @if($help)
            <x-chart-help :key="$help"/>
        @endif
    </div>
</div>
