<?php

use App\Http\Controllers\Auth\ShowSignInFailure;
use Illuminate\Support\Facades\Route;

// Route::view chứ không phải closure: production chạy `route:cache` lúc build image, mà closure
// không serialize được. `panel:admin` dựng panel để trang chủ đứng trong Khung như trang của panel.
Route::view('/', 'welcome')->middleware('panel:admin');

// Trang SDK platform chuyển về khi đăng nhập không thành (ADR 0011). Ngoài guard để hiện được cả
// khi platform sập. Các route `/admin/auth/*` (login, callback, logout, back-channel logout,
// webhook) thì SDK tự mount theo `platform.route_prefix`. Image production nướng route cache lúc
// build, nên đổi `PLATFORM_ERROR_PATH` ở pod không dời được trang này.
Route::get((string) config('platform.error_path'), ShowSignInFailure::class)->middleware('panel:admin');
