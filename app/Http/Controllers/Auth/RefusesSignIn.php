<?php

namespace App\Http\Controllers\Auth;

use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Inventory\SignIn\SignInRefused;
use Illuminate\Http\Response;

/**
 * Mọi lần từ chối đăng nhập dừng ở một trang tĩnh: không tự chuyển hướng đi đâu, kẻo một
 * lỗi cấu hình thành vòng lặp kho ↔ Authentik. Nhân viên tự bấm "Thử lại".
 */
trait RefusesSignIn
{
    protected function refuse(SignInRefused $refused): Response
    {
        app(SecurityLog::class)->record(
            SecurityEvent::LoginRefused,
            $refused->staff,
            $refused->email,
            details: ['reason' => $refused->reason->value, ...$refused->details],
        );

        return response()->view('auth.refused', ['refusal' => $refused->reason], 403);
    }
}
