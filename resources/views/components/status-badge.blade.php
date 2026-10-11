@props(['file'])

<span {{ $attributes->merge(['class' => 'badge text-bg-'.$file->statusColor()]) }}>
    <i class="bi {{ $file->statusIcon() }}"></i> {{ $file->statusLabel() }}
</span>
