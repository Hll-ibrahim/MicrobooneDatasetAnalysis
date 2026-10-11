<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\H5FileController;
use App\Http\Controllers\TableController;
use App\Support\DataTables;
use Illuminate\Support\Facades\Route;

// Gösterge paneli
Route::get('/', DashboardController::class)->name('dashboard');

// H5 dosyaları: yükleme, kayıt, içe aktarma
Route::controller(H5FileController::class)->prefix('dosyalar')->name('files.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/yukle', 'upload')->name('upload');
    Route::post('/kaydet', 'register')->name('register');
    Route::get('/{file}', 'show')->name('show');
    Route::get('/{file}/durum', 'status')->name('status');
    Route::post('/{file}/ice-aktar', 'import')->name('import');
    Route::put('/{file}', 'update')->name('update');
    Route::delete('/{file}', 'destroy')->name('destroy');
    Route::get('/{file}/sql', 'exportSql')->name('export-sql');
});

// Olaylar (event_table): tam CRUD — /olaylar, /olaylar/yeni, /olaylar/{id}/duzenle
Route::resource('olaylar', EventController::class)
    ->parameters(['olaylar' => 'event'])
    ->names('events');

// Olaya bağlı diğer tablolar: genel tablo tarayıcı + düzenleyici
Route::controller(TableController::class)
    ->prefix('tablolar/{table}')
    ->name('tables.')
    ->where(['table' => implode('|', array_keys(DataTables::children()))])
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/yeni', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/disa-aktar.csv', 'export')->name('export');
        Route::get('/{id}/duzenle', 'edit')->name('edit')->whereNumber('id');
        Route::put('/{id}', 'update')->name('update')->whereNumber('id');
        Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
    });

// Grafikler
Route::get('/grafikler', AnalysisController::class)->name('analysis');
