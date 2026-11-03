<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Muakey\Platform\Oidc\LoginFailure;
use Muakey\Platform\PlatformConfig;

/**
 * Trang SDK platform chuyển về kèm `?reason=` khi đăng nhập không thành (`platform.error_path`,
 * ADR 0011). Ngoài guard: phải hiện được cả khi platform sập, và không gọi platform. Trang không
 * tự chuyển hướng, kẻo một lỗi cấu hình thành vòng lặp kho ↔ platform; nhân viên tự bấm "Thử lại".
 */
class ShowSignInFailure extends Controller
{
    public function __invoke(Request $request): View
    {
        $reason = LoginFailure::tryFrom((string) $request->query('reason')) ?? LoginFailure::LoginFailed;

        return view('auth.sign-in-failed', [
            'message' => match ($reason) {
                LoginFailure::NoAccess => 'Bạn chưa được cấp quyền vào kho, hoặc tài khoản đang bị đình chỉ trên platform. Nhờ người quản platform cấp quyền rồi thử lại.',
                LoginFailure::Unavailable => 'Kho không hỏi được platform để kiểm quyền. Đây không phải lỗi đăng nhập của bạn: thử lại sau ít phút, nếu vẫn lỗi thì báo người vận hành.',
                LoginFailure::LoginFailed => 'Lượt đăng nhập này hết hạn hoặc bị từ chối giữa chừng. Bấm Thử lại để đăng nhập từ đầu.',
            },
            'retryUrl' => PlatformConfig::loginPath('/'.Filament::getPanel('admin')->getPath()),
        ]);
    }
}
