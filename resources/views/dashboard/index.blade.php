@extends('layouts.app')

@section('title', 'Gösterge Paneli')

@section('header')
    <x-page-header title="Gösterge Paneli" icon="bi-speedometer2"
                   subtitle="MicroBooNE νe overlay (NoWire) HDF5 dosyalarının PostgreSQL'e aktarılmış hali">
        <a href="{{ route('files.index') }}" class="btn btn-gold"><i class="bi bi-cloud-arrow-up"></i> H5 dosyası ekle</a>
        <a href="{{ route('analysis') }}" class="btn btn-outline-primary"><i class="bi bi-graph-up"></i> Tüm grafikler</a>
    </x-page-header>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <x-stat-card label="H5 dosyası" :value="number_format($stats['files'], 0, ',', '.')" icon="bi-file-earmark-binary"
                         :hint="$stats['completed'].' tanesi aktarıldı'"/>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Olay" :value="number_format($stats['events'], 0, ',', '.')" icon="bi-bullseye"
                         :hint="$stats['runs'].' farklı run'"/>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Hit" :value="number_format($stats['hits'], 0, ',', '.')" icon="bi-activity"
                         :hint="$stats['nu_frac'] !== null ? 'Nötrino hit oranı %'.number_format(100 * $stats['nu_frac'], 1, ',', '.') : null"/>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Parçacık" :value="number_format($stats['particles'], 0, ',', '.')" icon="bi-diagram-3"
                         :hint="number_format($stats['slices'], 0, ',', '.').' Pandora dilimi · '.number_format($stats['flashes'], 0, ',', '.').' flaş'"/>
        </div>
    </div>

    @if($stats['events'] === 0)
        <div class="card card-royal mt-4">
            <div class="empty-state">
                <i class="bi bi-database-add"></i>
                <h3>Henüz veri yok</h3>
                <p class="mb-3">Başlamak için bir .h5 dosyası kaydedin ve içe aktarın.<br>
                    Gerçek dosyanız yoksa <code>python3 scripts/make_sample_h5.py storage/app/h5/ornek_sentetik.h5</code>
                    ile küçük bir <strong>sentetik</strong> örnek üretebilirsiniz.</p>
                <a href="{{ route('files.index') }}" class="btn btn-primary"><i class="bi bi-cloud-arrow-up"></i> H5 dosyalarına git</a>
            </div>
        </div>
    @else
        <div class="ornament">Genel bakış</div>
        <div class="row g-3">
            <div class="col-lg-8">
                <x-chart-card id="chEnergy" title="Nötrino enerjisi dağılımı"
                              :subtitle="$stats['e_mean'] !== null ? 'ortalama '.number_format($stats['e_mean'], 2, ',', '.').' GeV' : null"/>
            </div>
            <div class="col-lg-4">
                <x-chart-card id="chPdg" title="Nötrino türü"/>
            </div>
            <div class="col-lg-4">
                <x-chart-card id="chPlanes" title="Düzlem başına hit" subtitle="U · V · Y"/>
            </div>
            <div class="col-lg-8">
                <x-chart-card id="chRuns" title="Run başına olay sayısı"/>
            </div>
        </div>
    @endif

    <div class="ornament">Son dosyalar</div>
    <div class="card card-royal">
        <div class="table-responsive">
            <table class="table table-royal table-hover align-middle">
                <thead>
                <tr>
                    <th>Dosya</th>
                    <th>Durum</th>
                    <th class="num">Boyut</th>
                    <th class="num">Aktarılan olay</th>
                    <th class="num">Toplam satır</th>
                    <th>Eklendi</th>
                </tr>
                </thead>
                <tbody>
                @forelse($files as $f)
                    <tr>
                        <td><a href="{{ route('files.show', $f) }}" class="fw-semibold">{{ $f->name }}</a></td>
                        <td><x-status-badge :file="$f"/></td>
                        <td class="num">{{ $f->humanSize() }}</td>
                        <td class="num">{{ number_format($f->row_counts['events'] ?? 0, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($f->totalRows(), 0, ',', '.') }}</td>
                        <td class="text-muted small">{{ $f->created_at?->format('d.m.Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Kayıtlı dosya yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    @if($stats['events'] > 0)
        <script>
            RC.hist('chEnergy', @json($charts['energy']), {x: 'Eν [GeV]', y: 'Olay'});
            RC.doughnut('chPdg', @json($charts['pdg']), {colors: [RC.colors.burgundy, RC.colors.gold, RC.colors.plum, RC.colors.teal]});
            RC.bar('chPlanes', @json($charts['planes']), {multi: true, y: 'Hit'});
            RC.bar('chRuns', @json($charts['runs']), {x: 'Run', y: 'Olay', color: RC.colors.plum, thick: 18});
        </script>
    @endif
@endpush
