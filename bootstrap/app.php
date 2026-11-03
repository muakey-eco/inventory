<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // API xuất kho cho website: xác thực bằng Khoá API, không dùng session của panel.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Production chạy sau ingress-nginx của k3s, nơi cầm TLS (ADR 0009). Không khai tin cậy
        // thì Laravel thấy request là http và sinh URL sai scheme, làm panel Filament vỡ asset.
        // `*` vì ingress chạy hostNetwork nên không có IP cố định để khai; cái giá (workload khác
        // trong cụm giả được IP client) ADR 0009 đã chấp nhận.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Livewire update không gửi `Accept: application/json`, nên phải nhận ra qua `X-Livewire`:
        // Phiên App hết (idle, back-channel logout) thì nó cần 401 để panel hiện hộp thoại hết
        // phiên, không phải 302 sang platform mà fetch đi theo không được.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson() || $request->hasHeader('X-Livewire'),
        );
    })->create();
