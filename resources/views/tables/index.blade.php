@extends('layouts.app')

@section('title', $def['label'])

@section('header')
    <x-page-header :title="$def['label']" :icon="$def['icon']" :subtitle="$def['group'].' → '.$table.' · '.$def['description']">
        <a href="{{ route('tables.create', ['table' => $table, 'event' => request('event')]) }}" class="btn btn-gold"><i class="bi bi-plus-lg"></i> Yeni kayıt</a>
        <a href="{{ route('tables.export', ['table' => $table] + request()->only(['file', 'event', 'run', 'col', 'op', 'val'])) }}" class="btn btn-outline-primary">
            <i class="bi bi-filetype-csv"></i> CSV indir</a>
    </x-page-header>
@endsection

@section('content')
    <x-page-intro key="tables"/>
    <x-table-about :table="$table" :def="$def"/>

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
                    <label class="form-label" for="event">Olay #</label>
                    <input type="number" class="form-control" id="event" name="event" value="{{ request('event') }}" title="events.id">
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label" for="run">Run</label>
                    <input type="number" class="form-control" id="run" name="run" value="{{ request('run') }}">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Sütun koşulu</label>
                    <div class="input-group">
                        <select class="form-select" name="col" style="max-width: 45%">
                            <option value="">Sütun…</option>
                            @foreach($def['columns'] as $c => $meta)
                                <option value="{{ $c }}" @selected(request('col') === $c)>{{ $meta[0] }} ({{ $c }})</option>
                            @endforeach
                        </select>
                        <select class="form-select" name="op" style="max-width: 5.5rem">
                            @foreach($ops as $k => $sym)
                                <option value="{{ $k }}" @selected(request('op', '=') === $k)>{{ $sym }}</option>
                            @endforeach
                        </select>
                        <input type="text" class="form-control" name="val" value="{{ request('val') }}" placeholder="değer">
                    </div>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label" for="per_page">Satır</label>
                    <select class="form-select" id="per_page" name="per_page">
                        @foreach([25, 50, 100, 200] as $n)
                            <option value="{{ $n }}" @selected((int) request('per_page', 50) === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-1 d-grid">
                    <button class="btn btn-primary"><i class="bi bi-funnel"></i></button>
                </div>
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="dir" value="{{ $dir }}">
            </form>
            <div class="d-flex flex-wrap gap-3 small mt-2">
                @if($event)
                    <span class="badge badge-gold fs-6 fw-normal">
                        Olay <a href="{{ route('events.show', $event) }}" class="fw-semibold">{{ $event->key() }}</a>
                        · Eν {{ tr_num($event->nu_energy, 3) }} GeV
                    </span>
                @endif
                @if($estimate !== null)
                    <span class="text-muted"><i class="bi bi-database"></i> Tabloda yaklaşık {{ tr_num($estimate) }} satır (PostgreSQL istatistiği)</span>
                @endif
                @if(request()->hasAny(['file', 'event', 'run', 'col']))
                    <a href="{{ route('tables.index', $table) }}" class="link-secondary"><i class="bi bi-x-circle"></i> Filtreleri temizle</a>
                @endif
            </div>
        </div>
    </div>

    {{-- ======================================================= tablo --}}
    <div class="card card-royal">
        <div class="table-responsive">
            <table class="table table-royal table-hover table-sm align-middle">
                <thead>
                <tr>
                    <th><x-sort-link column="id" label="#" :sort="$sort" :dir="$dir"/></th>
                    <th>Olay (run:subrun:event)</th>
                    @foreach($def['list'] as $c)
                        <th class="{{ in_array($def['columns'][$c][1], ['int', 'float'], true) ? 'num' : '' }}">
                            <x-sort-link :column="$c" :label="$def['columns'][$c][0].(isset($def['columns'][$c][2]) ? ' ['.$def['columns'][$c][2].']' : '')" :sort="$sort" :dir="$dir"/>
                        </th>
                    @endforeach
                    <th class="text-end">İşlem</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td class="text-muted">{{ $r->id }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('events.show', $r->event_id) }}">{{ $r->ev_run }}:{{ $r->ev_subrun }}:{{ $r->ev_event }}</a>
                            <a href="{{ request()->fullUrlWithQuery(['event' => $r->event_id, 'page' => null]) }}" class="text-muted ms-1" title="Yalnızca bu olay"><i class="bi bi-funnel"></i></a>
                        </td>
                        @foreach($def['list'] as $c)
                            @php $type = $def['columns'][$c][1]; @endphp
                            <td class="{{ in_array($type, ['int', 'float'], true) ? 'num' : '' }}">
                                @if($type === 'float')
                                    {{ tr_float($r->{$c}) }}
                                @elseif($type === 'int' && $c === 'g4_pdg')
                                    {{ \App\Support\Pdg::label($r->{$c}) }}
                                @elseif($type === 'int' && $c === 'local_plane')
                                    {{ \App\Support\Pdg::plane($r->{$c}) }}
                                @else
                                    {{ $r->{$c} ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end text-nowrap">
                            <a href="{{ route('tables.edit', ['table' => $table, 'id' => $r->id]) }}" class="btn btn-sm btn-outline-gold" title="Düzenle"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('tables.destroy', ['table' => $table, 'id' => $r->id]) }}" class="d-inline"
                                  data-confirm="{{ $def['singular'] }} #{{ $r->id }} silinsin mi?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Sil"><i class="bi bi-trash3"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($def['list']) + 3 }}">
                        <div class="empty-state py-4"><i class="bi bi-search"></i>Kayıt bulunamadı.</div>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $rows->links('pagination.royal', ['approx' => $approx]) }}</div>
    </div>
    <p class="small text-muted mt-2"><i class="bi bi-info-circle"></i>
        Listede seçili sütunlar gösterilir; bir satırın tüm sütunlarını görmek ve değiştirmek için <i class="bi bi-pencil"></i> düğmesini kullanın.</p>
@endsection
