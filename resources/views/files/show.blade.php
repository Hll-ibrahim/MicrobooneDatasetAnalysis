@extends('layouts.app')

@section('title', $file->name)

@php
    $groups = collect($file->schema['groups'] ?? []);
    $nEvents = (int) ($file->file_events ?? 0);
    $done = $file->status === \App\Models\H5File::COMPLETED;
    $selectedGroups = $file->import_groups ?: array_keys($groupMap);
@endphp

@section('header')
    <x-page-header :title="$file->name" icon="bi-file-earmark-binary"
                   :subtitle="$file->humanSize().' · '.number_format($nEvents, 0, ',', '.').' olay · '.$groups->count().' HDF5 grubu'">
        @if($done)
            <a href="{{ route('events.index', ['file' => $file->id]) }}" class="btn btn-primary"><i class="bi bi-list-ul"></i> Olaylar</a>
            <a href="{{ route('analysis', ['file' => $file->id]) }}" class="btn btn-outline-primary"><i class="bi bi-graph-up"></i> Grafikler</a>
            <a href="{{ route('files.export-sql', $file) }}" class="btn btn-gold"><i class="bi bi-filetype-sql"></i> .sql indir</a>
        @endif
    </x-page-header>
@endsection

@section('content')
    {{-- ======================================================= durum --}}
    <div class="card card-royal mb-3" id="statusCard" data-url="{{ route('files.status', $file) }}" data-busy="{{ $file->isBusy() ? 1 : 0 }}">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div>
                    <span id="stBadge" class="badge text-bg-{{ $file->statusColor() }} fs-6">
                        <i class="bi {{ $file->statusIcon() }}"></i> <span id="stLabel">{{ $file->statusLabel() }}</span>
                    </span>
                    <span id="stMsg" class="ms-2 text-muted">{{ $file->status_message }}</span>
                </div>
                <div class="small text-muted">
                    @if($file->started_at) Başlangıç: {{ $file->started_at->format('d.m.Y H:i:s') }} @endif
                    @if($file->finished_at) · Bitiş: {{ $file->finished_at->format('H:i:s') }}
                        ({{ $file->started_at?->diffForHumans($file->finished_at, true) }}) @endif
                </div>
            </div>
            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                <div id="stBar" class="progress-bar progress-bar-royal {{ $file->isBusy() ? 'progress-bar-striped progress-bar-animated' : '' }}"
                     style="width: {{ $file->progress }}%">{{ $file->progress }}%</div>
            </div>
            <div id="stStale" class="alert alert-warning small mt-2 mb-0 d-none">
                <i class="bi bi-hourglass-split"></i> İş kuyrukta bekliyor. Kuyruk işçisi çalışıyor mu?
                Ayrı bir terminalde <code>php artisan queue:work --timeout=0</code> komutunu çalıştırın.
            </div>
            @if($file->error)
                <div class="alert alert-danger small mt-2 mb-0"><i class="bi bi-bug"></i> <span class="font-mono">{{ $file->error }}</span></div>
            @endif
        </div>
    </div>

    <div class="row g-3">
        {{-- =================================================== içe aktarma formu --}}
        <div class="col-lg-7">
            <div class="card card-royal h-100">
                <div class="card-header"><i class="bi bi-database-up text-gold me-1"></i> PostgreSQL'e içe aktar</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('files.import', $file) }}" id="importForm"
                          @if($done) data-confirm="Bu dosyanın veritabanındaki mevcut verileri silinip yeniden aktarılacak. Devam edilsin mi?" @endif>
                        @csrf
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label class="form-label" for="offset">Başlangıç olayı</label>
                                <input type="number" min="0" class="form-control" id="offset" name="offset"
                                       value="{{ old('offset', $file->import_offset) }}">
                                <div class="form-text">Atlanacak olay sayısı</div>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="max_events">Olay sayısı</label>
                                <input type="number" min="1" class="form-control" id="max_events" name="max_events"
                                       value="{{ old('max_events', $file->import_max_events ?? config('h5.default_max_events')) }}"
                                       @if($file->import_max_events === null && $file->status !== 'registered') disabled @endif>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="all_events" name="all_events" value="1"
                                           @checked($file->import_max_events === null && $file->status !== 'registered')>
                                    <label class="form-check-label small" for="all_events">Tüm olaylar ({{ number_format($nEvents, 0, ',', '.') }})</label>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">Tahmini satır</label>
                                <div class="fs-4 serif text-burgundy" id="estTotal">—</div>
                                <div class="form-text">Seçili gruplar için</div>
                            </div>
                        </div>

                        <label class="form-label mt-3">Aktarılacak HDF5 grupları</label>
                        <div class="row row-cols-1 row-cols-md-2 g-1">
                            @foreach($groupMap as $group => $table)
                                @php $g = $groups->where('name', $group)->first(); @endphp
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input grp" type="checkbox" name="groups[]" value="{{ $group }}" id="g_{{ $group }}"
                                               data-rows="{{ $g['rows'] ?? 0 }}"
                                               @checked(in_array($group, $selectedGroups, true))
                                               @disabled($g === null || $group === 'event_table')>
                                        <label class="form-check-label" for="g_{{ $group }}">
                                            <span class="font-mono">{{ $group }}</span>
                                            <small class="text-muted">→ {{ $table }}</small>
                                            @if($g === null)<span class="badge text-bg-light">dosyada yok</span>@endif
                                            <small class="text-muted est" data-group="{{ $group }}"></small>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{-- event_table devre dışı (disabled) olduğu için ayrıca gönderilir --}}
                        <input type="hidden" name="groups[]" value="event_table">

                        <div class="alert alert-royal small mt-3 mb-3">
                            <i class="bi bi-lightbulb text-gold"></i>
                            Olay başına ≈16 000 hit olduğundan <strong>hit_table</strong>, <strong>edep_table</strong> ve
                            <strong>pandoraHit_table</strong> çok büyüktür (1 000 olay ≈ 17 milyon hit). Önce birkaç yüz olayla başlayın.
                        </div>

                        <button class="btn btn-primary" @disabled($file->isBusy() || ! $file->fileExists())>
                            <i class="bi bi-play-circle"></i> {{ $done ? 'Yeniden içe aktar' : 'İçe aktarmayı başlat' }}
                        </button>
                        @unless($file->fileExists())
                            <span class="text-danger small ms-2"><i class="bi bi-exclamation-triangle"></i> Dosya yolunda bulunamadı.</span>
                        @endunless
                    </form>
                </div>
            </div>
        </div>

        {{-- =================================================== dosya bilgisi --}}
        <div class="col-lg-5">
            <div class="card card-royal h-100">
                <div class="card-header"><i class="bi bi-info-circle text-gold me-1"></i> Dosya bilgileri</div>
                <div class="card-body">
                    <dl class="row kv mb-3">
                        <dt class="col-4">Yol</dt><dd class="col-8 font-mono text-break">{{ $file->path }}</dd>
                        <dt class="col-4">Boyut</dt><dd class="col-8">{{ $file->humanSize() }}</dd>
                        <dt class="col-4">Olay</dt><dd class="col-8">{{ number_format($nEvents, 0, ',', '.') }}</dd>
                        <dt class="col-4">Son aktarım</dt>
                        <dd class="col-8">
                            @if($file->row_counts)
                                {{ $file->import_offset }}. olaydan itibaren
                                {{ number_format($file->row_counts['events'] ?? 0, 0, ',', '.') }} olay
                            @else — @endif
                        </dd>
                        @foreach(($file->schema['attrs'] ?? []) as $k => $v)
                            <dt class="col-4 text-truncate" title="{{ $k }}">{{ $k }}</dt>
                            <dd class="col-8 small">{{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v }}</dd>
                        @endforeach
                    </dl>

                    <form method="POST" action="{{ route('files.update', $file) }}">
                        @csrf @method('PUT')
                        <div class="mb-2">
                            <label class="form-label" for="name">Görünen ad</label>
                            <input class="form-control form-control-sm" id="name" name="name" value="{{ old('name', $file->name) }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="description">Not</label>
                            <textarea class="form-control form-control-sm" id="description" name="description" rows="2">{{ old('description', $file->description) }}</textarea>
                        </div>
                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-save"></i> Kaydet</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ======================================================= sonuçlar --}}
    @if($file->row_counts)
        <div class="ornament">Aktarım sonucu</div>
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card card-royal h-100">
                    <div class="card-header">Tablo başına satır</div>
                    <div class="table-responsive">
                        <table class="table table-royal table-sm table-hover align-middle">
                            <thead><tr><th>Tablo</th><th>HDF5 grubu</th><th class="num">Satır</th></tr></thead>
                            <tbody>
                            @foreach($file->row_counts as $t => $n)
                                <tr>
                                    <td>
                                        <a href="{{ $t === 'events' ? route('events.index', ['file' => $file->id]) : route('tables.index', ['table' => $t, 'file' => $file->id]) }}">
                                            <i class="bi {{ $tables[$t]['icon'] ?? 'bi-table' }} text-gold"></i> {{ $tables[$t]['label'] ?? $t }}
                                        </a>
                                    </td>
                                    <td class="font-mono small text-muted">{{ $tables[$t]['group'] ?? '' }}</td>
                                    <td class="num">{{ number_format($n, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot><tr class="fw-semibold"><td colspan="2">Toplam</td><td class="num">{{ number_format($file->totalRows(), 0, ',', '.') }}</td></tr></tfoot>
                        </table>
                    </div>
                    @if($file->unmapped_columns)
                        <div class="card-footer bg-white small">
                            <div class="fw-semibold text-burgundy"><i class="bi bi-exclamation-diamond"></i> Veritabanına yazılmayan sütunlar</div>
                            @foreach($file->unmapped_columns as $g => $cols)
                                <div><span class="font-mono">{{ $g }}</span>: {{ implode(', ', $cols) }}</div>
                            @endforeach
                            <div class="text-muted mt-1">Gerekirse ilgili migration'a sütun ekleyip yeniden aktarabilirsiniz.</div>
                        </div>
                    @endif
                </div>
            </div>
            @if($charts)
                <div class="col-lg-7">
                    <x-chart-card id="chFileEnergy" title="Nötrino enerjisi (bu dosya)" :height="230"/>
                </div>
                <div class="col-lg-7">
                    <x-chart-card id="chFileHits" title="Olay başına hit sayısı" :height="230"/>
                </div>
                <div class="col-lg-5">
                    <x-chart-card id="chFilePlanes" title="Düzlem başına hit" :height="230"/>
                </div>
            @endif
        </div>
    @endif

    {{-- ======================================================= şema --}}
    <div class="ornament">HDF5 şeması</div>
    <div class="accordion" id="schemaAcc">
        @foreach($groups as $i => $g)
            @php $table = $groupMap[$g['name']] ?? null; @endphp
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sg{{ $i }}">
                        <span class="font-mono fw-semibold me-2">{{ $g['name'] }}</span>
                        <span class="badge badge-gold me-2">{{ number_format($g['rows'], 0, ',', '.') }} satır</span>
                        @if($g['events'] !== null)<span class="badge text-bg-light me-2">{{ number_format($g['events'], 0, ',', '.') }} olay</span>@endif
                        <small class="text-muted">→ {{ $table ?? 'eşleşen tablo yok' }}</small>
                    </button>
                </h2>
                <div id="sg{{ $i }}" class="accordion-collapse collapse" data-bs-parent="#schemaAcc">
                    <div class="accordion-body p-0">
                        @if($table && isset($tables[$table]))
                            <p class="small text-muted px-3 pt-3 mb-2">{{ $tables[$table]['description'] }}</p>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-royal table-sm align-middle">
                                <thead><tr><th>Dataset</th><th>Şekil</th><th>Tür</th><th>PostgreSQL sütun(lar)ı</th></tr></thead>
                                <tbody>
                                @foreach($g['columns'] as $col)
                                    @php
                                        $k = count($col['shape']) > 1 ? array_product(array_slice($col['shape'], 1)) : 1;
                                        $outs = in_array($col['name'], ['event_id', 'event_id.seq_cnt'], true) ? [] :
                                            ($k === 1 ? [$col['name']] : ($k === 3
                                                ? array_map(fn ($s) => $col['name'].'_'.$s, str_contains($col['name'], 'wire') ? ['u', 'v', 'y'] : ['x', 'y', 'z'])
                                                : array_map(fn ($j) => $col['name'].'_'.$j, range(0, $k - 1))));
                                    @endphp
                                    <tr>
                                        <td class="font-mono">{{ $col['name'] }}</td>
                                        <td class="font-mono small">({{ implode(', ', $col['shape']) }})</td>
                                        <td class="font-mono small text-muted">{{ $col['dtype'] }}</td>
                                        <td class="small">
                                            @if($col['name'] === 'event_id')
                                                <span class="text-muted">{{ $table === 'events' ? 'run, subrun, event' : 'event_id → events.id' }}</span>
                                            @elseif($col['name'] === 'event_id.seq_cnt')
                                                <span class="text-muted">olay aralığını bulmak için kullanılır</span>
                                            @else
                                                @foreach($outs as $o)
                                                    @if($table && in_array($o, $dbColumns[$table] ?? [], true))
                                                        <span class="badge badge-gold font-mono">{{ $o }}</span>
                                                    @else
                                                        <span class="badge text-bg-light font-mono" title="Veritabanında yok">{{ $o }} ✕</span>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ======================================================= silme --}}
    <div class="card border-danger-subtle mt-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold text-danger"><i class="bi bi-trash3"></i> Bu kaydı sil</div>
                <div class="small text-muted">Veritabanındaki tüm olay, hit, parçacık … satırları silinir.</div>
            </div>
            <form method="POST" action="{{ route('files.destroy', $file) }}" class="d-flex align-items-center gap-3"
                  data-confirm="“{{ $file->name }}” ve veritabanındaki tüm verileri silinsin mi?">
                @csrf @method('DELETE')
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="delete_physical" value="1" id="delPhys">
                    <label class="form-check-label small" for="delPhys">.h5 dosyasını da diskten sil (yalnızca veri klasöründeyse)</label>
                </div>
                <button class="btn btn-outline-danger"><i class="bi bi-trash3"></i> Sil</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // ---- tahmini satır sayısı
        (function () {
            const nEvents = {{ max(1, $nEvents) }};
            const offset = document.getElementById('offset');
            const maxEv = document.getElementById('max_events');
            const all = document.getElementById('all_events');
            const fmt = (n) => RC.nf.format(Math.round(n));
            function update() {
                maxEv.disabled = all.checked;
                const start = Math.min(nEvents, Math.max(0, parseInt(offset.value || '0', 10)));
                const sel = all.checked ? nEvents - start : Math.min(nEvents - start, Math.max(0, parseInt(maxEv.value || '0', 10)));
                let total = 0;
                document.querySelectorAll('.grp').forEach((cb) => {
                    const est = cb.closest('.form-check').querySelector('.est');
                    const rows = parseFloat(cb.dataset.rows || '0') * sel / nEvents;
                    est.textContent = rows ? ` ≈ ${fmt(rows)}` : '';
                    if (cb.checked) total += rows;
                });
                document.getElementById('estTotal').textContent = fmt(total);
            }
            [offset, maxEv, all].forEach((el) => el.addEventListener('input', update));
            document.querySelectorAll('.grp').forEach((el) => el.addEventListener('change', update));
            update();
        })();

        // ---- canlı durum (içe aktarma sürerken)
        (function () {
            const card = document.getElementById('statusCard');
            if (card.dataset.busy !== '1') return;
            const bar = document.getElementById('stBar');
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(card.dataset.url, { headers: { Accept: 'application/json' } });
                    const s = await r.json();
                    bar.style.width = s.progress + '%';
                    bar.textContent = s.progress + '%';
                    document.getElementById('stLabel').textContent = s.label;
                    document.getElementById('stBadge').className = 'badge fs-6 text-bg-' + s.color;
                    document.getElementById('stMsg').textContent = s.message || '';
                    document.getElementById('stStale').classList.toggle('d-none', !s.stale);
                    if (!s.busy) {
                        clearInterval(timer);
                        setTimeout(() => window.location.reload(), 600);
                    }
                } catch (e) { /* ağ hatası: bir sonraki denemede tekrar */ }
            }, 2000);
        })();

        @if($charts)
        RC.hist('chFileEnergy', @json($charts['energy']), {x: 'Eν [GeV]', y: 'Olay'});
        RC.hist('chFileHits', @json($charts['hits']), {x: 'Hit / olay', y: 'Olay', color: RC.colors.plum});
        RC.bar('chFilePlanes', @json($charts['planes']), {multi: true, y: 'Hit'});
        @endif
    </script>
@endpush
