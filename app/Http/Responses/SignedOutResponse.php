<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Đăng xuất dừng ở trang tĩnh thay vì quay về trang đăng nhập, vốn chuyển thẳng sang
 * Authentik và đăng nhập lại ngay nếu phiên Authentik còn sống.
 */
class SignedOutResponse implements LogoutResponse
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('filament.admin.auth.signed-out');
    }
}
