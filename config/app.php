<?php

/*
| Laravel 11+ çerçeve varsayılanlarının üzerine yalnızca değişen anahtarlar
| yazılır; geri kalan her şey vendor/laravel/framework/config/app.php'den gelir.
*/

return [
    'timezone' => env('APP_TIMEZONE', 'Europe/Istanbul'),
    'locale' => env('APP_LOCALE', 'tr'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'tr_TR'),
];
