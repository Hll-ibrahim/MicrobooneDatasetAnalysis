<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Support\ViewErrorBag;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tarayıcıdan yüklenen .h5 dosyası PHP post_max_size sınırını aşarsa
        // çıplak 413 hatası yerine yol gösteren bir sayfa göster.
        $exceptions->render(function (PostTooLargeException $e) {
            return response()->view('errors.too-large', [
                'errors' => new ViewErrorBag,
                'postMax' => ini_get('post_max_size'),
                'uploadMax' => ini_get('upload_max_filesize'),
            ], 413);
        });
    })->create();
