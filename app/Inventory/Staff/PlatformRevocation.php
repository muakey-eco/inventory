<?php

namespace App\Inventory\Staff;

/**
 * Vì sao một nhân viên mất quyền theo platform, theo Trạng thái user trong Bản sao danh bạ. Khác
 * Khoá nhân viên: tự hết khi platform cấp lại quyền (ADR 0011).
 */
enum PlatformRevocation: string
{
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return match ($this) {
            self::Suspended => 'bị đình chỉ trên platform',
            self::Deactivated => 'bị xoá khỏi platform',
        };
    }
}
