@extends('layouts.app')

@section('title', 'Olay '.$event->key())

@section('header')
    <x-page-header :title="'Olay '.$event->key()" icon="bi-bullseye"
                   :subtitle="'run : subrun : event · '.($event->h5File?->name ?? '—').' · #'.$event->id">
        @if($neighbors['prev'])
            <a href="{{ route('events.show', $neighbors['prev']) }}" class="btn btn-outline-primary" title="Önceki olay"><i class="bi bi-chevron-left"></i></a>
        @endif
        @if($neighbors['next'])
            <a href="{{ route('events.show', $neighbors['next']) }}" class="btn btn-outline-primary" title="Sonraki olay"><i class="bi bi-chevron-right"></i></a>
        @endif
        <a href="{{ route('events.edit', $event) }}" class="btn btn-gold"><i class="bi bi-pencil"></i> Düzenle</a>
        <form method="POST" action="{{ route('events.destroy', $event) }}" class="d-inline"
              data-confirm="Olay {{ $event->key() }} ve ona bağlı tüm satırlar silinsin mi?">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="bi bi-trash3"></i></button>
        </form>
    </x-page-header>
@endsection

@section('content')
    <x-page-intro key="event"/>

    {{-- ======================================================= özet --}}
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <x-stat-card label="Nötrino" help="Bu olaydaki simüle nötrinonun türü (PDG kodu) ve etkileşim türü: yüklü akım (CC) ya da nötr akım (NC)." :value="$event->nuName()" icon="bi-stars"
                         :hint="($event->is_cc ? 'Yüklü akım (CC)' : ($event->is_cc === false ? 'Nötr akım (NC)' : '')).' · PDG '.$event->nu_pdg"/>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Nötrino enerjisi" help="Gelen nötrinonun gerçek enerjisi ve etkileşimden çıkan elektronun (leptonun) enerjisi." :value="tr_num($event->nu_energy, 3).' GeV'" icon="bi-lightning-charge"
                         :hint="'E lepton '.tr_num($event->lep_energy, 3).' GeV'"/>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Hit" help="Bu olayda tellerde ölçülen sinyal sayısı ve U, V, Y düzlemlerine dağılımı." :value="tr_num($event->hit_count)" icon="bi-activity"
                         :hint="'U '.tr_num($event->hit_count_u).' · V '.tr_num($event->hit_count_v).' · Y '.tr_num($event->hit_count_y)"/>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Nötrino hit oranı" help="Hit’lerin ne kadarının simüle nötrinodan geldiği (edep kaydı olanlar). Gerisi kozmik ışın ve gürültüdür." :value="$event->nuHitFraction() !== null ? '%'.tr_num(100 * $event->nuHitFraction(), 1) : '—'"
                         icon="bi-pie-chart" :hint="tr_num($event->nu_hit_count).' hit edep ile etiketli'"/>
        </div>
    </div>

    {{-- ======================================================= olay görüntüsü --}}
    <div class="ornament">Olay görüntüsü</div>
    <div class="card card-royal">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>Tel × zaman düzlemleri <small class="text-muted">her nokta bir hit</small></span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Renklendirme">
                <input type="radio" class="btn-check" name="edMode" id="edTruth" value="truth" checked>
                <label class="btn btn-outline-primary" for="edTruth"><i class="bi bi-stars"></i> Gerçek (ν / kozmik)</label>
                <input type="radio" class="btn-check" name="edMode" id="edSlice" value="slice" @disabled(! $best)>
                <label class="btn btn-outline-primary" for="edSlice"><i class="bi bi-layers"></i> Pandora en iyi dilim</label>
                <input type="radio" class="btn-check" name="edMode" id="edCharge" value="charge">
                <label class="btn btn-outline-primary" for="edCharge"><i class="bi bi-palette"></i> Yük (integral)</label>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2">
                @foreach(['U' => 0, 'V' => 1, 'Y' => 2] as $name => $p)
                    <div class="col-xl-4">
                        <div class="event-display">
                            <span class="plane-label">{{ $name }} düzlemi
                                <small class="text-white-50 fw-normal">{{ $name === 'Y' ? 'toplama' : 'indüksiyon' }} · {{ tr_num(count($display[$p])) }} hit</small></span>
                            <div class="chart-box" style="height: 360px"><canvas id="ed{{ $name }}"></canvas></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <x-chart-help key="event_display"/>
            @if($best)
                <div class="small text-muted mt-1">Bu olayda Pandora'nın en yüksek <x-term slug="nu-score">nu_score</x-term>'lu dilimi:
                    <strong>#{{ $best->slice_id }}</strong> (nu_score {{ tr_float($best->nu_score) }}).</div>
            @endif
        </div>
    </div>

    {{-- ======================================================= ayrıntılar --}}
    <div class="row g-3 mt-1">
        <div class="col-lg-5">
            <div class="card card-royal h-100">
                <div class="card-header">Gerçek etkileşim bilgisi</div>
                <div class="card-body">
                    <dl class="row kv mb-0">
                        <dt class="col-5">Köşe (x, y, z)</dt>
                        <dd class="col-7">{{ tr_num($event->nu_vtx_x, 2) }}, {{ tr_num($event->nu_vtx_y, 2) }}, {{ tr_num($event->nu_vtx_z, 2) }} cm</dd>
                        <dt class="col-5">Düzeltilmiş köşe</dt>
                        <dd class="col-7">{{ tr_num($event->nu_vtx_corr_x, 2) }}, {{ tr_num($event->nu_vtx_corr_y, 2) }}, {{ tr_num($event->nu_vtx_corr_z, 2) }} cm</dd>
                        <dt class="col-5">Köşe teli (U, V, Y)</dt>
                        <dd class="col-7">{{ tr_num($event->nu_vtx_wire_pos_u, 1) }}, {{ tr_num($event->nu_vtx_wire_pos_v, 1) }}, {{ tr_num($event->nu_vtx_wire_pos_y, 1) }}</dd>
                        <dt class="col-5">Köşe zamanı</dt>
                        <dd class="col-7">{{ tr_num($event->nu_vtx_wire_time, 1) }}</dd>
                        <dt class="col-5">Yön</dt>
                        <dd class="col-7">({{ tr_num($event->nu_dir_x, 3) }}, {{ tr_num($event->nu_dir_y, 3) }}, {{ tr_num($event->nu_dir_z, 3) }})</dd>
                        <dt class="col-5">Parçacık / dilim / flaş</dt>
                        <dd class="col-7">{{ tr_num($event->particle_count) }} / {{ tr_num($event->slice_count) }} / {{ tr_num($event->flash_count) }}</dd>
                        <dt class="col-5">Etiket</dt>
                        <dd class="col-7">@if($event->label)<span class="badge badge-gold">{{ $event->label }}</span>@else — @endif</dd>
                        <dt class="col-5">Notlar</dt>
                        <dd class="col-7">{!! $event->notes ? nl2br(e($event->notes)) : '—' !!}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <x-chart-card id="chIntegral" help="event_integral" title="Hit integral dağılımı" subtitle="düzlem başına · 800 ADC üstü son kutuda" :height="260"/>
        </div>
        <div class="col-lg-6">
            <x-chart-card id="chPdg" help="event_pdg" title="Parçacık türleri (Geant4)" :height="260"/>
        </div>
        <div class="col-lg-6">
            <x-chart-card id="chPmt" help="event_pmt" title="PMT başına toplam fotoelektron" :height="260"/>
        </div>
    </div>

    {{-- ======================================================= tablolar --}}
    <div class="ornament">Bağlı kayıtlar</div>
    <div class="card card-royal">
        <div class="card-header bg-white pb-0 border-0">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tParticles" type="button">Parçacıklar <span class="badge badge-gold">{{ $event->particle_count }}</span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tSlices" type="button">Pandora dilimleri <span class="badge badge-gold">{{ $event->slice_count }}</span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tPfps" type="button">PFP <span class="badge badge-gold">{{ $pfps->count() }}</span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tFlashes" type="button">Flaşlar <span class="badge badge-gold">{{ $flashes->count() }}</span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tAll" type="button">Tüm tablolar</button></li>
            </ul>
        </div>
        <div class="tab-content">
            {{-- parçacıklar --}}
            <div class="tab-pane fade show active" id="tParticles">
                <p class="small text-muted px-3 pt-3 mb-2"><i class="bi bi-info-circle text-gold"></i>
                    {{ \App\Support\DataTables::get('particles')['description'] }}
                    <a href="{{ route('tables.index', ['table' => 'particles', 'event' => $event->id]) }}">Tablo ve sütun açıklamaları →</a></p>
                <div class="table-responsive">
                    <table class="table table-royal table-sm table-hover align-middle">
                        <thead><tr><th>G4</th><th>Ana</th><th>Parçacık</th><th class="num">p [GeV/c]</th><th>Başlangıç süreci</th><th>Bitiş süreci</th><th>Kategori</th><th class="num">Başlangıç (x,y,z)</th><th></th></tr></thead>
                        <tbody>
                        @forelse($particles as $p)
                            <tr @class(['fw-semibold' => $p->isPrimary()])>
                                <td>{{ $p->g4_id }}</td>
                                <td class="text-muted">{{ $p->parent_id }}</td>
                                <td>{{ $p->pdgName() }} <small class="text-muted">({{ $p->g4_pdg }})</small></td>
                                <td class="num">{{ tr_float($p->momentum) }}</td>
                                <td>{{ $p->start_process }}</td>
                                <td class="text-muted">{{ $p->end_process }}</td>
                                <td>{{ $p->category }}</td>
                                <td class="num small text-muted">{{ tr_num($p->start_position_x, 1) }}, {{ tr_num($p->start_position_y, 1) }}, {{ tr_num($p->start_position_z, 1) }}</td>
                                <td class="text-end"><a href="{{ route('tables.edit', ['table' => 'particles', 'id' => $p->id]) }}" class="btn btn-sm btn-outline-gold"><i class="bi bi-pencil"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-3">Parçacık kaydı yok.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-2 small"><a href="{{ route('tables.index', ['table' => 'particles', 'event' => $event->id]) }}">Tüm parçacıkları tabloda aç →</a></div>
            </div>
            {{-- dilimler --}}
            <div class="tab-pane fade" id="tSlices">
                <p class="small text-muted px-3 pt-3 mb-2"><i class="bi bi-info-circle text-gold"></i>
                    {{ \App\Support\DataTables::get('pandora_primaries')['description'] }}
                    <a href="{{ route('tables.index', ['table' => 'pandora_primaries', 'event' => $event->id]) }}">Tablo ve sütun açıklamaları →</a></p>
                <div class="table-responsive">
                    <table class="table table-royal table-sm table-hover align-middle">
                        <thead><tr><th>Dilim</th><th>PDG</th><th class="num">nu_score</th><th class="num">flashmatch</th><th class="num">Köşe (x,y,z) [cm]</th><th></th></tr></thead>
                        <tbody>
                        @forelse($slices as $s)
                            <tr @class(['table-warning' => $best && $s->id === $best->id])>
                                <td>{{ $s->slice_id }} @if($best && $s->id === $best->id)<span class="badge badge-burgundy">en iyi</span>@endif</td>
                                <td>{{ $s->slice_pdg }}</td>
                                <td class="num">{{ $s->nu_score <= -900 ? 'tanımsız' : tr_float($s->nu_score) }}</td>
                                <td class="num">{{ tr_float($s->flashmatch_score) }}</td>
                                <td class="num small text-muted">{{ tr_num($s->vtx_x, 1) }}, {{ tr_num($s->vtx_y, 1) }}, {{ tr_num($s->vtx_z, 1) }}</td>
                                <td class="text-end"><a href="{{ route('tables.edit', ['table' => 'pandora_primaries', 'id' => $s->id]) }}" class="btn btn-sm btn-outline-gold"><i class="bi bi-pencil"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">Pandora dilimi yok.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{-- pfp --}}
            <div class="tab-pane fade" id="tPfps">
                <p class="small text-muted px-3 pt-3 mb-2"><i class="bi bi-info-circle text-gold"></i>
                    {{ \App\Support\DataTables::get('pandora_pfps')['description'] }}
                    <a href="{{ route('tables.index', ['table' => 'pandora_pfps', 'event' => $event->id]) }}">Tablo ve sütun açıklamaları →</a></p>
                <div class="table-responsive">
                    <table class="table table-royal table-sm table-hover align-middle">
                        <thead><tr><th>PFP</th><th>Tür</th><th class="num">İz/sağanak skoru</th><th class="num">Başlangıç (x,y,z) [cm]</th><th></th></tr></thead>
                        <tbody>
                        @forelse($pfps as $p)
                            <tr>
                                <td>{{ $p->pfp_id }}</td>
                                <td>{{ $p->pfp_pdg === 13 ? 'İz benzeri' : ($p->pfp_pdg === 11 ? 'Sağanak benzeri' : $p->pfp_pdg) }}</td>
                                <td class="num">{{ tr_float($p->trkshr_score) }}</td>
                                <td class="num small text-muted">{{ tr_num($p->vtx_x, 1) }}, {{ tr_num($p->vtx_y, 1) }}, {{ tr_num($p->vtx_z, 1) }}</td>
                                <td class="text-end"><a href="{{ route('tables.edit', ['table' => 'pandora_pfps', 'id' => $p->id]) }}" class="btn btn-sm btn-outline-gold"><i class="bi bi-pencil"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Bu olayda PFP kaydı yok (seq_cnt bu olayı atlamış olabilir).</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{-- flaşlar --}}
            <div class="tab-pane fade" id="tFlashes">
                <p class="small text-muted px-3 pt-3 mb-2"><i class="bi bi-info-circle text-gold"></i>
                    {{ \App\Support\DataTables::get('opflashes')['description'] }}
                    <a href="{{ route('tables.index', ['table' => 'opflashes', 'event' => $event->id]) }}">Tablo ve sütun açıklamaları →</a></p>
                <div class="table-responsive">
                    <table class="table table-royal table-sm table-hover align-middle">
                        <thead><tr><th>Flaş</th><th class="num">Zaman [µs]</th><th class="num">Toplam PE</th><th class="num">y merkezi [cm]</th><th class="num">z merkezi [cm]</th><th class="num">z genişliği [cm]</th><th></th></tr></thead>
                        <tbody>
                        @forelse($flashes as $f)
                            <tr>
                                <td>{{ $f->flash_id }}</td>
                                <td class="num">{{ tr_float($f->time) }}</td>
                                <td class="num">{{ tr_num($f->totalpe, 1) }}</td>
                                <td class="num">{{ tr_num($f->y_center, 1) }}</td>
                                <td class="num">{{ tr_num($f->z_center, 1) }}</td>
                                <td class="num">{{ tr_num($f->z_width, 1) }}</td>
                                <td class="text-end"><a href="{{ route('tables.edit', ['table' => 'opflashes', 'id' => $f->id]) }}" class="btn btn-sm btn-outline-gold"><i class="bi bi-pencil"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">Flaş kaydı yok.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{-- tüm tablolar --}}
            <div class="tab-pane fade" id="tAll">
                <div class="list-group list-group-flush">
                    @foreach(\App\Support\DataTables::children() as $t => $def)
                        <a href="{{ route('tables.index', ['table' => $t, 'event' => $event->id]) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                            <span><i class="bi {{ $def['icon'] }} text-gold me-2"></i>{{ $def['label'] }} <small class="text-muted font-mono">{{ $def['group'] }}</small></span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const display = @json($display);
            const planes = {U: 0, V: 1, Y: 2};
            const wireMax = {U: 2400, V: 2400, Y: 3456};
            function draw(mode) {
                Object.entries(planes).forEach(([name, p]) => RC.eventDisplay('ed' + name, display[p], mode, {wmin: 0, wmax: wireMax[name]}));
            }
            draw('truth');
            document.querySelectorAll('input[name="edMode"]').forEach((r) => r.addEventListener('change', (e) => draw(e.target.value)));

            RC.histMulti('chIntegral', @json($charts['integral']), {x: 'İntegral [ADC]', y: 'Hit'});
            RC.bar('chPdg', @json($charts['pdg']), {horizontal: true, multi: true, x: 'Parçacık', y: 'Sayı'});
            RC.bar('chPmt', @json($charts['pmt']), {x: 'PMT kanalı', y: 'PE', color: RC.colors.gold, thick: 14, unit: 'PE'});
        })();
    </script>
@endpush
