<?php
use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\AuthorizeRoles;
use App\Http\Middleware\GeneralShiftHours;
use App\Http\Middleware\LegacyCors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt' => AuthenticateJwt::class,
            'roles' => AuthorizeRoles::class,
            'general.shift' => GeneralShiftHours::class,
            'legacy.cors' => LegacyCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
