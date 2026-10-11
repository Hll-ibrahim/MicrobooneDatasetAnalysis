@props(['column', 'label', 'sort', 'dir'])

@php($active = $sort === $column)
<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $active && $dir === 'asc' ? 'desc' : 'asc', 'page' => null]) }}">
    {{ $label }}@if($active) <i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill"></i>@endif
</a>
