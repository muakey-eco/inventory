<?php

namespace App\Inventory\SignIn;

use App\Models\User;
use RuntimeException;

/**
 * Một lần đăng nhập bị từ chối, mang đủ thông tin để ghi Nhật ký bảo mật: nhân viên (nếu
 * kho đã biết họ là ai), email và chi tiết kỹ thuật không nhạy cảm.
 */
class SignInRefused extends RuntimeException
{
    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public readonly Refusal $reason,
        public readonly ?User $staff = null,
        public readonly ?string $email = null,
        public readonly array $details = [],
    ) {
        parent::__construct($reason->message());
    }
}
