@extends('layouts.app')

@section('title', 'H5 Dosyaları')

@section('header')
    <x-page-header title="H5 Dosyaları" icon="bi-file-earmark-binary"
                   subtitle="HDF5 dosyalarını kaydedin, şemasını inceleyin ve PostgreSQL'e aktarın"/>
@endsection

@section('content')
    <x-page-intro key="files"/>

    {{-- Python ortamı --}}
    @if($python[0])
        <div class="alert alert-royal d-flex align-items-center gap-2 py-2">
            <i class="bi bi-check2-circle text-success fs-5"></i>
            <div><strong>HDF5 okuyucu hazır:</strong> <span class="font-mono">{{ $python[1] }}</span></div>
        </div>
    @else
        <div class="alert alert-warning">
            <div class="d-flex align-items-center gap-2"><i class="bi bi-exclamation-triangle fs-5"></i>
                <strong>Python/h5py bulunamadı — içe aktarma çalışmaz.</strong></div>
            <div class="small mt-1 font-mono">{{ $python[1] }}</div>
            <div class="small mt-1">Çözüm: <code>pip install h5py numpy pandas</code> ve gerekirse <code>.env</code> içindeki
                <code>H5_PYTHON</code> değerini Python yolunuzla değiştirin. Ardından <code>php artisan cache:clear</code>.</div>
        </div>
    @endif

    <div class="row g-3">
        {{-- Sunucu klasöründen --}}
        <div class="col-lg-7">
            <div class="card card-royal h-100">
                <div class="card-header d-flex justify-content-between">
                    <span><i class="bi bi-folder2-open text-gold me-1"></i> Veri klasöründeki dosyalar</span>
                    <small class="text-muted font-mono text-truncate ms-2" style="max-width: 60%" title="{{ $dataDir }}">{{ $dataDir }}</small>
                </div>
                <div class="card-body p-0">
                    @if($available->isEmpty())
                        <div class="empty-state py-4">
                            <i class="bi bi-inbox"></i>
                            Klasörde .h5 dosyası yok.<br>
                            <small>Zenodo'dan indirdiğiniz <code>nue_NoWire_XX.h5</code> dosyalarını bu klasöre kopyalayın
                                ya da <code>.env</code> içinde <code>H5_DATA_DIR</code> ile mevcut klasörünüzü gösterin.</small>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-royal table-hover align-middle">
                                <thead><tr><th>Dosya</th><th class="num">Boyut</th><th>Değiştirilme</th><th></th></tr></thead>
                                <tbody>
                                @foreach($available as $a)
                                    <tr>
                                        <td class="font-mono">{{ $a['name'] }}</td>
                                        <td class="num">{{ \App\Models\H5File::bytes($a['size']) }}</td>
                                        <td class="small text-muted">{{ date('d.m.Y H:i', $a['mtime']) }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('files.register') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="filename" value="{{ $a['name'] }}">
                                                <button class="btn btn-sm {{ $a['registered'] ? 'btn-outline-primary' : 'btn-primary' }}">
                                                    <i class="bi bi-plus-circle"></i> {{ $a['registered'] ? 'Yeniden kaydet' : 'Kaydet' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="card-footer bg-white small text-muted">
                    <i class="bi bi-info-circle"></i> GB boyutundaki Zenodo dosyaları için önerilen yol budur:
                    dosyayı klasöre koyun, <em>Kaydet</em> deyin; şema okunur, sonra kaç olay aktarılacağını seçersiniz.
                </div>
            </div>
        </div>

        {{-- Tarayıcıdan yükleme --}}
        <div class="col-lg-5">
            <div class="card card-royal h-100">
                <div class="card-header"><i class="bi bi-cloud-arrow-up text-gold me-1"></i> Tarayıcıdan yükle</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('files.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="h5">.h5 / .hdf5 dosyası</label>
                            <input type="file" class="form-control @error('h5') is-invalid @enderror" id="h5" name="h5" accept=".h5,.hdf5" required>
                            <div class="form-text">PHP yükleme sınırı: <strong>{{ $uploadLimit }}</strong>.
                                Daha büyük dosyalar için soldaki klasör yöntemini kullanın.</div>
                        </div>
                        <button class="btn btn-gold w-100"><i class="bi bi-upload"></i> Yükle ve şemayı oku</button>
                    </form>
                    <hr>
                    <div class="small text-muted">
                        <div class="fw-semibold text-burgundy mb-1">Terminalden de aktarabilirsiniz:</div>
                        <code class="d-block">php artisan h5:inspect dosya.h5 --columns</code>
                        <code class="d-block">php artisan h5:import dosya.h5 --max-events=500</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ornament">Kayıtlı dosyalar</div>
    <div class="card card-royal">
        <div class="table-responsive">
            <table class="table table-royal table-hover align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Ad</th>
                    <th>Durum</th>
                    <th class="num">Boyut</th>
                    <th class="num">Dosyadaki olay</th>
                    <th class="num">Aktarılan olay</th>
                    <th class="num">Toplam satır</th>
                    <th class="text-end">İşlemler</th>
                </tr>
                </thead>
                <tbody>
                @forelse($files as $f)
                    <tr>
                        <td class="text-muted">{{ $f->id }}</td>
                        <td>
                            <a href="{{ route('files.show', $f) }}" class="fw-semibold">{{ $f->name }}</a>
                            @unless($f->fileExists())
                                <span class="badge text-bg-warning" title="{{ $f->path }}">dosya yerinde değil</span>
                            @endunless
                        </td>
                        <td style="min-width: 170px">
                            <x-status-badge :file="$f"/>
                            @if($f->isBusy())
                                <div class="progress mt-1" style="height: .5rem">
                                    <div class="progress-bar progress-bar-royal" style="width: {{ $f->progress }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td class="num">{{ $f->humanSize() }}</td>
                        <td class="num">{{ $f->file_events !== null ? number_format($f->file_events, 0, ',', '.') : '—' }}</td>
                        <td class="num">{{ number_format($f->row_counts['events'] ?? 0, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($f->totalRows(), 0, ',', '.') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('files.show', $f) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Ayrıntılar / içe aktar">
                                <i class="bi bi-eye"></i></a>
                            @if($f->status === \App\Models\H5File::COMPLETED)
                                <a href="{{ route('events.index', ['file' => $f->id]) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Olayları listele">
                                    <i class="bi bi-list-ul"></i></a>
                                <a href="{{ route('files.export-sql', $f) }}" class="btn btn-sm btn-outline-gold" data-bs-toggle="tooltip" title="PostgreSQL .sql olarak indir">
                                    <i class="bi bi-filetype-sql"></i></a>
                            @endif
                            <form method="POST" action="{{ route('files.destroy', $f) }}" class="d-inline"
                                  data-confirm="“{{ $f->name }}” kaydı ve veritabanındaki TÜM verileri silinsin mi?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Sil"><i class="bi bi-trash3"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Henüz kayıtlı dosya yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
