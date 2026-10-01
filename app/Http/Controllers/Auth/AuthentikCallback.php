<?php

namespace App\Http\Controllers\Auth;

use App\Inventory\SignIn\AuthentikLogin;
use App\Inventory\SignIn\SignInRefused;
use App\Inventory\SignIn\StaffSignIn;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Authentik trả nhân viên về đây. Đăng nhập xong thì quay về đúng trang họ đang đứng trước
 * khi phiên hết hạn.
 */
class AuthentikCallback
{
    use RefusesSignIn;

    public function __invoke(Request $request, AuthentikLogin $authentik, StaffSignIn $signIn): RedirectResponse|Response
    {
        try {
            $staff = $signIn->signIn($authentik->complete($request));
        } catch (SignInRefused $refused) {
            return $this->refuse($refused);
        }

        Filament::auth()->login($staff);
        $request->session()->regenerate();

        return redirect()->intended(Filament::getUrl());
    }
}
