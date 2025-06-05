<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Middleware\Authenticate;

use Tymon\JWTAuth\Http\Middleware\Authenticate as JWTAuthMiddleware;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register route middleware aliases (like 'auth:api')
        $middleware->alias([
            'auth' => Authenticate::class,
            'auth:api' => JWTAuthMiddleware::class,
        ]);
    })
    ->withExceptions(function ($exceptions) {
        //
    })->create();
