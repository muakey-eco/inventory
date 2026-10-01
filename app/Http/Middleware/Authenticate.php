<?php

namespace App\Http\Middleware;

use App\Inventory\SignIn\AuthentikSessions;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

/**
 * Cắt phiên đang mở của nhân viên vừa bị Khoá nhân viên, mất quyền theo Authentik, hoặc có phiên
 * Authentik vừa bị huỷ qua back-channel logout: đăng xuất và huỷ phiên rồi đưa về trang đăng
 * nhập, thay vì để Filament trả 403 cho phiên còn sống.
 * Đăng nhập lại thì Authentik và StaffSignIn nói rõ vì sao bị từ chối.
 * Nằm trong Authenticate vì Laravel luôn xếp middleware xác thực lên trước
 * middleware thường của panel.
 */
class Authenticate extends FilamentAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && ($user->isLockedOut() || app(AuthentikSessions::class)->hasEnded($request, $user))) {
            Filament::auth()->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        parent::authenticate($request, $guards);
    }
}
