@props(['slug'])
@php
    $__t = \App\Models\GlossaryTerm::lookup($slug);
    $__label = trim((string) $slot) !== '' ? trim(strip_tags((string) $slot)) : ($__t?->term ?? $slug);
@endphp
{!! \App\Support\Help::termLink($slug, $__label, $__t?->short) !!}
