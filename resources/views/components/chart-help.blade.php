@props(['key'])
@php $__h = \App\Support\Help::chart($key); @endphp
@if($__h)
    <div class="chart-help">
        <div><i class="bi bi-eye"></i> <strong>Ne gösteriyor?</strong> {!! \App\Support\Help::render($__h['what']) !!}</div>
        <div class="mt-1"><i class="bi bi-book"></i> <strong>Nasıl okunur?</strong> {!! \App\Support\Help::render($__h['read']) !!}</div>
    </div>
@endif
