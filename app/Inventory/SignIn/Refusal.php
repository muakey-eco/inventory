<?php

namespace App\Inventory\SignIn;

/**
 * Lý do một lần đăng nhập bị từ chối. Giá trị ghi vào Nhật ký bảo mật; câu chữ hiện cho
 * nhân viên ở trang lỗi, nói ở mức họ tự xử lý được hoặc biết phải hỏi ai.
 */
enum Refusal: string
{
    case NoRole = 'no_role';
    case Deactivated = 'deactivated';
    case InvalidState = 'invalid_state';
    case InvalidNonce = 'invalid_nonce';
    case InvalidToken = 'invalid_token';
    case ProviderError = 'provider_error';

    public function message(): string
    {
        return match ($this) {
            self::NoRole => 'Bạn chưa được cấp quyền vào kho, liên hệ Quản trị.',
            self::Deactivated => 'Bạn đã bị khoá khỏi kho, liên hệ Quản trị.',
            self::InvalidState, self::InvalidNonce => 'Phiên đăng nhập đã hết hạn hoặc không khớp. Bấm Thử lại để đăng nhập lại.',
            self::InvalidToken => 'Không xác minh được thông tin đăng nhập từ Authentik. Thử lại, nếu vẫn lỗi thì liên hệ Quản trị.',
            self::ProviderError => 'Authentik báo lỗi hoặc không trả lời. Thử lại sau ít phút, nếu vẫn lỗi thì liên hệ Quản trị.',
        };
    }
}
