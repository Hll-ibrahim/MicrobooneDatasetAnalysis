@extends('layouts.app')

@section('title', 'Grafikler')

@section('header')
    <x-page-header title="Grafikler" icon="bi-graph-up"
                   subtitle="PostgreSQL'e aktarılmış verinin fizik dağılımları — histogramlar SQL tarafında hesaplanır">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <select name="file" class="form-select" onchange="this.form.submit()" aria-label="Dosya">
                <option value="">Tüm dosyalar</option>
                @foreach($files as $f)
                    <option value="{{ $f->id }}" @selected($fileId === $f->id)>{{ $f->name }}</option>
                @endforeach
            </select>
        </form>
    </x-page-header>
@endsection

@section('content')
    <x-page-intro key="analysis"/>

    @if($nEvents === 0)
        <div class="card card-royal">
            <div class="empty-state">
                <i class="bi bi-graph-up"></i>
                <h3>Grafik için veri yok</h3>
                <p>Önce bir .h5 dosyasını içe aktarın.</p>
                <a href="{{ route('files.index') }}" class="btn btn-primary">H5 dosyalarına git</a>
            </div>
        </div>
    @else
        <div class="alert alert-royal small">
            <i class="bi bi-info-circle text-gold"></i>
            <strong>{{ tr_num($nEvents) }}</strong> olay üzerinden. Hit düzeyindeki grafikler (integral, zaman) hız için ilk
            <strong>{{ tr_num($c['sample_events']) }}</strong> olaydan hesaplanır.
        </div>

        <div class="ornament">Nötrino etkileşimi (gerçek bilgi)</div>
        <div class="row g-3">
            <div class="col-lg-6"><x-chart-card id="aEnergy" help="energy" title="Nötrino enerjisi" subtitle="event_table.nu_energy"/></div>
            <div class="col-lg-6"><x-chart-card id="aLep" help="lep_vs_nu" title="Lepton enerjisi ve nötrino enerjisi" subtitle="rastgele en çok 2 500 olay"/></div>
            <div class="col-lg-6"><x-chart-card id="aVtxZX" help="vtx_zx" title="Etkileşim noktası — üstten (z, x)" subtitle="kesikli çizgi: TPC sınırı"/></div>
            <div class="col-lg-6"><x-chart-card id="aVtxZY" help="vtx_zy" title="Etkileşim noktası — yandan (z, y)" subtitle="kesikli çizgi: TPC sınırı"/></div>
        </div>

        <div class="ornament">Hit'ler</div>
        <div class="row g-3">
            <div class="col-lg-6"><x-chart-card id="aHits" help="file_hits" title="Olay başına hit sayısı" subtitle="%99 üstü son kutuda"/></div>
            <div class="col-lg-6"><x-chart-card id="aNuFrac" help="nufrac" title="Nötrino hit oranı" subtitle="edep'i olan hit / tüm hit"/></div>
            <div class="col-lg-6"><x-chart-card id="aIntegral" help="integral" title="Hit integrali (U / V / Y)" subtitle="800 ADC üstü son kutuda"/></div>
            <div class="col-lg-6"><x-chart-card id="aTime" help="hit_time" title="Hit zamanı (U / V / Y)" subtitle="okuma penceresi 6 400 tick = 3,2 ms"/></div>
        </div>

        <div class="ornament">Pandora rekonstrüksiyonu</div>
        <div class="row g-3">
            <div class="col-lg-5"><x-chart-card id="aNuScore" help="nu_score" title="nu_score dağılımı" subtitle="-999 hariç"/></div>
            <div class="col-lg-3"><x-chart-card id="aSentinel" help="sentinel" title="nu_score geçerliliği"/></div>
            <div class="col-lg-4"><x-chart-card id="aPfp" help="pfp_pdg" title="PFP türleri" subtitle="iz / sağanak"/></div>
            <div class="col-lg-12"><x-chart-card id="aFlashmatch" help="flashmatch" title="Flaş eşleştirme skoru" subtitle="küçük = iyi eşleşme" :height="230"/></div>
        </div>

        <div class="ornament">Parçacıklar (Geant4)</div>
        <div class="row g-3">
            <div class="col-lg-5"><x-chart-card id="aPdg" help="particle_pdg" title="En sık parçacık türleri" :height="320"/></div>
            <div class="col-lg-4"><x-chart-card id="aProcess" help="process" title="Üretim süreci" :height="320"/></div>
            <div class="col-lg-3"><x-chart-card id="aCategory" help="category" title="Kategori" :height="320"/></div>
        </div>

        <div class="ornament">Optik sistem (PMT)</div>
        <div class="row g-3">
            <div class="col-lg-6"><x-chart-card id="aFlashTime" help="flash_time" title="Flaş zamanı [µs]"/></div>
            <div class="col-lg-6"><x-chart-card id="aFlashPe" help="flash_pe" title="Flaş başına toplam PE" subtitle="%98 üstü son kutuda"/></div>
            <div class="col-12"><x-chart-card id="aPmt" help="pmt" title="PMT kanalı başına toplam fotoelektron" :height="240"/></div>
        </div>
    @endif
@endsection

@push('scripts')
    @if($nEvents > 0)
        <script>
            const D = @json($c);
            const det = @json($detector);
            RC.hist('aEnergy', D.energy, {x: 'Eν [GeV]', y: 'Olay'});
            RC.scatter('aLep', D.lep_vs_nu, {x: 'Eν [GeV]', y: 'E lepton [GeV]', diagonal: true, color: RC.colors.plum});
            RC.scatter('aVtxZX', D.vtx_zx, {x: 'z [cm]', y: 'x [cm]', box: [det.z, det.x], radius: 1.8});
            RC.scatter('aVtxZY', D.vtx_zy, {x: 'z [cm]', y: 'y [cm]', box: [det.z, det.y], radius: 1.8, color: RC.colors.teal});
            RC.hist('aHits', D.hits, {x: 'Hit / olay', y: 'Olay', color: RC.colors.plum});
            RC.hist('aNuFrac', D.nufrac, {x: 'ν hit oranı', y: 'Olay', color: RC.colors.gold});
            RC.histMulti('aIntegral', D.integral, {x: 'İntegral [ADC]', y: 'Hit'});
            RC.histMulti('aTime', D.hit_time, {x: 'Zaman [tick]', y: 'Hit'});
            RC.hist('aNuScore', D.nu_score, {x: 'nu_score', y: 'Dilim', color: RC.colors.burgundy, logY: true});
            RC.doughnut('aSentinel', D.sentinel, {colors: [RC.colors.burgundy, RC.colors.goldSoft]});
            RC.doughnut('aPfp', D.pfp_pdg, {colors: [RC.colors.gold, RC.colors.burgundy, RC.colors.plum]});
            RC.hist('aFlashmatch', D.flashmatch, {x: 'flashmatch_score', y: 'Dilim', color: RC.colors.teal});
            RC.bar('aPdg', D.particle_pdg, {horizontal: true, multi: true, x: 'Parçacık', y: 'Sayı'});
            RC.bar('aProcess', D.process, {horizontal: true, color: RC.colors.plum, x: 'Süreç', y: 'Sayı'});
            RC.bar('aCategory', D.category, {multi: true, x: 'Kategori', y: 'Sayı'});
            RC.hist('aFlashTime', D.flash_time, {x: 'Zaman [µs]', y: 'Flaş', color: RC.colors.navy});
            RC.hist('aFlashPe', D.flash_pe, {x: 'Toplam PE', y: 'Flaş', color: RC.colors.gold});
            RC.bar('aPmt', D.pmt, {x: 'PMT kanalı', y: 'PE', color: RC.colors.claret, thick: 22, unit: 'PE'});
        </script>
    @endif
@endpush
