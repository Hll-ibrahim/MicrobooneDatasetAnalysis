<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Python yorumlayıcısı
    |--------------------------------------------------------------------------
    | PHP'nin HDF5 okuyabilen olgun bir kütüphanesi yoktur. Bu nedenle .h5
    | dosyası scripts/h5_tool.py ile (h5py) okunur, PostgreSQL'e yazma işi ise
    | tamamen Laravel tarafında (COPY) yapılır.
    */
    'python' => env('H5_PYTHON', 'python3'),

    'script' => base_path('scripts/h5_tool.py'),

    /*
    |--------------------------------------------------------------------------
    | Klasörler
    |--------------------------------------------------------------------------
    */
    'data_dir' => env('H5_DATA_DIR') ?: storage_path('app/h5'),

    'tmp_dir' => storage_path('app/h5-tmp'),

    /*
    |--------------------------------------------------------------------------
    | İçe aktarma varsayılanları
    |--------------------------------------------------------------------------
    */
    'default_max_events' => (int) env('H5_DEFAULT_MAX_EVENTS', 200),

    // PostgreSQL COPY ile tek seferde gönderilen satır sayısı
    'copy_batch' => 20000,

    // Python tarafında h5 dosyasından tek seferde okunan satır sayısı
    'read_chunk' => 1000000,

    // Kuyruk işinin azami süresi (saniye)
    'job_timeout' => 6 * 3600,

    // Grafik sayfasında hit tabanlı grafikler için örneklenen olay sayısı
    'hit_chart_events' => 200,

    /*
    |--------------------------------------------------------------------------
    | MicroBooNE dedektör sınırları (cm) — köşe grafiklerinde çerçeve olarak
    |--------------------------------------------------------------------------
    */
    'detector' => [
        'x' => [0.0, 256.35],
        'y' => [-116.5, 116.5],
        'z' => [0.0, 1036.8],
    ],
];
