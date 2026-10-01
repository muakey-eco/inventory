<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Đăng nhập giả cho máy dev, khi chưa có Authentik để mà đăng nhập: chọn một nhân viên rồi
 * vào thẳng. Route chỉ đăng ký khi APP_ENV=local; kiểm lại ở đây phòng route bị cache từ một
 * môi trường khác.
 */
class DevSignIn
{
    public function index(): View
    {
        abort_unless(app()->isLocal(), 404);

        return view('auth.dev-sign-in', [
            'staff' => User::with('roles')->whereNull('deactivated_at')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_unless(app()->isLocal(), 404);
        abort_if($user->isDeactivated(), 403);

        Filament::auth()->login($user);
        $request->session()->regenerate();

        return redirect()->intended(Filament::getUrl());
    }
}
