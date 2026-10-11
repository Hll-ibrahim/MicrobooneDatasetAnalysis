{{-- Bir veritabanı tablosunun açıklaması ve sütun sözlüğü --}}
@props(['table', 'def'])
@php $__id = 'cols-'.$table; @endphp
<div class="card card-royal mb-3 table-about">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
            <h2 class="h4 mb-0"><i class="bi {{ $def['icon'] }} text-gold"></i> {{ $def['label'] }}</h2>
            <span class="badge badge-gold font-mono">{{ $def['group'] }} → {{ $table }}</span>
        </div>
        <p class="lead-sm mb-2"><strong>{{ $def['description'] }}</strong></p>
        <p class="mb-2">{!! \App\Support\Help::render(\App\Support\Help::table($table)) !!}</p>
        <a class="small fw-semibold text-decoration-none" data-bs-toggle="collapse" href="#{{ $__id }}" role="button" aria-expanded="false">
            <i class="bi bi-list-columns-reverse"></i> Sütun sözlüğü ({{ count($def['columns']) }} sütun) <i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="{{ $__id }}">
            <div class="table-responsive mt-2">
                <table class="table table-sm align-middle mb-0 col-dict">
                    <thead><tr><th>Sütun</th><th>Veritabanı adı</th><th>Birim</th><th>Anlamı</th></tr></thead>
                    <tbody>
                    @foreach($def['columns'] as $col => $meta)
                        <tr>
                            <td class="fw-semibold text-nowrap">{{ $meta[0] }}</td>
                            <td class="font-mono small">{{ $col }}</td>
                            <td class="small text-muted text-nowrap">{{ $meta[2] ?? '—' }}</td>
                            <td class="small">{!! \App\Support\Help::render(\App\Support\Help::column($table, $col)) ?: '—' !!}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
