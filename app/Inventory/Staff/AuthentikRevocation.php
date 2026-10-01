<?php

namespace App\Inventory\Staff;

/**
 * Vì sao một nhân viên mất quyền theo Authentik. Khác Khoá nhân viên: tự hết khi Authentik cấp
 * lại quyền (ADR 0008).
 */
enum AuthentikRevocation: string
{
    case Inactive = 'inactive';
    case NotFound = 'not_found';
    case NoRole = 'no_role';

    public function label(): string
    {
        return match ($this) {
            self::Inactive => 'người dùng Authentik bị tắt',
            self::NotFound => 'không còn trên Authentik',
            self::NoRole => 'không còn group kho-* nào',
        };
    }
}
