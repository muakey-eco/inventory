<?php

use App\Http\Controllers\Auth\AuthentikBackchannelLogout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // API xuất kho cho website: xác thực bằng Khoá API, không dùng session của panel.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Authentik gọi từ server khi một phiên Authentik kết thúc: ngoài nhóm `web`, vì không có
            // session hay CSRF token nào để mang theo. Chữ ký logout_token là thứ xác thực request.
            // Throttle vì mỗi lần xác minh gọi sang Authentik; 60/phút thừa cho shop vài người, và
            // lần nào lỡ bị chặn thì đối soát mỗi phút vẫn thu quyền.
            Route::post('/auth/authentik/backchannel-logout', AuthentikBackchannelLogout::class)
                ->middleware('throttle:60,1')
                ->name('auth.authentik.backchannel-logout');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Production chạy sau một reverse proxy cùng máy đã cầm TLS (ADR 0005). Không khai tin cậy
        // thì Laravel thấy request là http và sinh URL sai scheme, làm panel Filament vỡ asset.
        // `*` an toàn ở đây vì container chỉ bind 127.0.0.1: không ai ngoài máy chạm tới để giả header.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
