@extends('layouts.app')

@section('title', 'Olaylar')

@section('header')
    <x-page-header title="Olaylar" icon="bi-bullseye"
                   subtitle="event_table → events · her satır bir dedektör okuması ve simüle νe etkileşimi">
        <a href="{{ route('events.create') }}" class="btn btn-gold"><i class="bi bi-plus-lg"></i> Yeni olay</a>
    </x-page-header>
@endsection

@section('content')
    <x-page-intro key="events"/>
    <x-table-about table="events" :def="\App\Support\DataTables::get('events')"/>

    {{-- ======================================================= filtreler --}}
    <div class="card card-royal mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" for="file">Dosya</label>
                    <select class="form-select" id="file" name="file">
                        <option value="">Tümü</option>
                        @foreach($files as $f)
                            <option value="{{ $f->id }}" @selected(request('file') == $f->id)>{{ $f->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label" for="run">Run</label>
                    <input type="number" class="form-control" id="run" name="run" value="{{ request('run') }}">
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label" for="subrun">Subrun</label>
                    <input type="number" class="form-control" id="subrun" name="subrun" value="{{ request('subrun') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="pdg">Nötrino</label>
                    <select class="form-select" id="pdg" name="pdg">
                        <option value="">Tümü</option>
                        <option value="12" @selected(request('pdg') === '12')>νe (12)</option>
                        <option value="-12" @selected(request('pdg') === '-12')>ν̄e (-12)</option>
                        <option value="14" @selected(request('pdg') === '14')>νμ (14)</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Eν aralığı [GeV]</label>
                    <div class="input-group">
                        <input type="number" step="any" class="form-control" name="emin" placeholder="min" value="{{ request('emin') }}">
                        <input type="number" step="any" class="form-control" name="emax" placeholder="maks" value="{{ request('emax') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="label">Etiket</label>
                    <input type="text" class="form-control" id="label" name="label" value="{{ request('label') }}" placeholder="ör. ilginç">
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-primary"><i class="bi bi-funnel"></i></button>
                </div>
                <div class="col-12 d-flex flex-wrap gap-3 align-items-center small">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="labeled" value="1" id="labeled" @checked(request('labeled') === '1')>
                        <label class="form-check-label" for="labeled">Yalnızca etiketli olaylar</label>
                    </div>
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="dir" value="{{ $dir }}">
                    @if(request()->hasAny(['file', 'run', 'subrun', 'pdg', 'emin', 'emax', 'label', 'labeled']))
                        <a href="{{ route('events.index') }}" class="link-secondary"><i class="bi bi-x-circle"></i> Filtreleri temizle</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="row g-3">
                <div class="col-6 col-md-12">
                    <x-stat-card label="Eşleşen olay" help="Yukarıdaki filtrelere uyan olay sayısı ve bunların kaç farklı run’dan geldiği." :value="tr_num($summary->n)" icon="bi-collection" :hint="tr_num($summary->runs).' run'"/>
                </div>
                <div class="col-6 col-md-12">
                    <x-stat-card label="Ortalama nötrino enerjisi" help="Filtrelenen olaylardaki nötrinoların ortalama gerçek enerjisi (GeV) ve olay başına ortalama hit sayısı." :value="$summary->e !== null ? tr_num($summary->e, 2).' GeV' : '—'" icon="bi-lightning"
                                 :hint="$summary->h !== null ? 'olay başına '.tr_num($summary->h).' hit' : null"/>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <x-chart-card id="chEvEnergy" help="events_energy" title="Eν dağılımı (filtreye göre)" :height="190"/>
        </div>
    </div>

    {{-- ======================================================= tablo --}}
    <div class="card card-royal">
        <div class="table-responsive">
            <table class="table table-royal table-hover table-sm align-middle">
                <thead>
                <tr>
                    <th><x-sort-link column="id" label="#" :sort="$sort" :dir="$dir"/></th>
                    <th><x-sort-link column="run" label="Run" :sort="$sort" :dir="$dir"/></th>
                    <th><x-sort-link column="subrun" label="Subrun" :sort="$sort" :dir="$dir"/></th>
                    <th><x-sort-link column="event" label="Event" :sort="$sort" :dir="$dir"/></th>
                    <th><x-sort-link column="nu_pdg" label="ν" :sort="$sort" :dir="$dir"/></th>
                    <th class="num"><x-sort-link column="nu_energy" label="Eν [GeV]" :sort="$sort" :dir="$dir"/></th>
                    <th class="num"><x-sort-link column="lep_energy" label="E lep [GeV]" :sort="$sort" :dir="$dir"/></th>
                    <th class="num">Köşe (x, y, z) [cm]</th>
                    <th class="num"><x-sort-link column="hit_count" label="Hit" :sort="$sort" :dir="$dir"/></th>
                    <th class="num"><x-sort-link column="nu_hit_count" label="ν hit" :sort="$sort" :dir="$dir"/></th>
                    <th class="num"><x-sort-link column="particle_count" label="Parç." :sort="$sort" :dir="$dir"/></th>
                    <th><x-sort-link column="label" label="Etiket" :sort="$sort" :dir="$dir"/></th>
                    <th class="text-end">İşlem</th>
                </tr>
                </thead>
                <tbody>
                @forelse($events as $e)
                    <tr>
                        <td class="text-muted">{{ $e->id }}</td>
                        <td>{{ $e->run }}</td>
                        <td>{{ $e->subrun }}</td>
                        <td><a href="{{ route('events.show', $e) }}" class="fw-semibold">{{ $e->event }}</a></td>
                        <td><span class="badge {{ $e->nu_pdg === 12 ? 'badge-burgundy' : 'badge-gold' }}">{{ $e->nuName() }}</span>
                            @if($e->is_cc === false)<span class="badge text-bg-light">NC</span>@endif</td>
                        <td class="num">{{ tr_num($e->nu_energy, 3) }}</td>
                        <td class="num">{{ tr_num($e->lep_energy, 3) }}</td>
                        <td class="num small text-muted">{{ tr_num($e->nu_vtx_x, 1) }}, {{ tr_num($e->nu_vtx_y, 1) }}, {{ tr_num($e->nu_vtx_z, 1) }}</td>
                        <td class="num">{{ tr_num($e->hit_count) }}</td>
                        <td class="num">{{ tr_num($e->nu_hit_count) }}</td>
                        <td class="num">{{ tr_num($e->particle_count) }}</td>
                        <td>@if($e->label)<span class="badge badge-gold">{{ $e->label }}</span>@endif</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('events.show', $e) }}" class="btn btn-sm btn-outline-primary" title="Göster"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('events.edit', $e) }}" class="btn btn-sm btn-outline-gold" title="Düzenle"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('events.destroy', $e) }}" class="d-inline"
                                  data-confirm="Olay {{ $e->key() }} ve ona bağlı tüm hit/parçacık/… satırları silinsin mi?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Sil"><i class="bi bi-trash3"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13">
                        <div class="empty-state py-4"><i class="bi bi-search"></i>Kriterlere uyan olay bulunamadı.</div>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $events->links() }}</div>
    </div>
@endsection

@push('scripts')
    @if(! empty($charts['energy']))
        <script>RC.hist('chEvEnergy', @json($charts['energy']), {x: 'Eν [GeV]', y: 'Olay', color: RC.colors.claret});</script>
    @endif
@endpush
