<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Görünümlerdeki tr_num() / tr_float() yardımcıları. composer.json → autoload.files
        // ile de yüklenir; composer.json değiştirilmiş ya da dump-autoload yapılmamışsa
        // diye burada da güvenceye alınır.
        require_once app_path('helpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        // Tek sayfa olsa bile her zaman görünen, numaralı sayfalama
        Paginator::defaultView('pagination.royal');

        // Türkçe URL'ler: /olaylar/yeni, /olaylar/5/duzenle
        Route::resourceVerbs([
            'create' => 'yeni',
            'edit' => 'duzenle',
        ]);
    }
}
