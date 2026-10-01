<?php

namespace App\Http\Controllers\Auth;

use App\Inventory\SignIn\AuthentikLogin;
use App\Inventory\SignIn\SignInRefused;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Trang đăng nhập của panel: không có form hay nút bấm, chuyển thẳng sang Authentik.
 */
class RedirectToAuthentik
{
    use RefusesSignIn;

    public function __invoke(Request $request, AuthentikLogin $authentik): RedirectResponse|Response
    {
        try {
            return redirect()->away($authentik->begin($request));
        } catch (SignInRefused $refused) {
            return $this->refuse($refused);
        }
    }
}
