<?php

use Illuminate\Support\Facades\Route;

// Route::view chứ không phải closure: production chạy `route:cache` lúc build image, mà closure
// không serialize được. `panel:admin` dựng panel để trang chủ đứng trong Khung như trang của panel.
Route::view('/', 'welcome')->middleware('panel:admin');
