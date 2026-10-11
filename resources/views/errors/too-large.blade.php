@extends('layouts.app')

@section('title', 'Dosya çok büyük')

@section('header')
    <x-page-header title="Dosya çok büyük" icon="bi-exclamation-triangle"
                   subtitle="Yüklenen dosya PHP'nin izin verdiği boyutu aşıyor"/>
@endsection

@section('content')
    <div class="card card-royal">
        <div class="card-body">
            <p>PHP ayarları: <code>post_max_size = {{ $postMax }}</code>, <code>upload_max_filesize = {{ $uploadMax }}</code>.</p>
            <p class="mb-2">Zenodo'daki <code>nue_NoWire_XX.h5</code> dosyaları 2–3 GB olduğu için tarayıcıdan yüklemek yerine şunu önerilir:</p>
            <ol>
                <li>Dosyayı <code>{{ config('h5.data_dir') }}</code> klasörüne kopyalayın
                    (ya da <code>.env</code> içinde <code>H5_DATA_DIR</code> ile dosyaların bulunduğu klasörü gösterin).</li>
                <li><a href="{{ route('files.index') }}">H5 Dosyaları</a> sayfasında “Kaydet” düğmesine basın.</li>
            </ol>
            <p class="small text-muted mb-0">Alternatif: terminalde <code>php artisan h5:import /yol/dosya.h5 --max-events=200</code></p>
        </div>
    </div>
@endsection
