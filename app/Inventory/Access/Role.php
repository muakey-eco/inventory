<?php

namespace App\Inventory\Access;

/**
 * Vai trò của nhân viên. Giá trị là tên role trong spatie/laravel-permission.
 */
enum Role: string
{
    case Owner = 'owner';
    case NhapKho = 'nhap-kho';
    case BanHang = 'ban-hang';

    /**
     * Các Vai trò cho trước, theo thứ tự khai báo: để so sánh, hiển thị và ghi nhật ký không
     * phụ thuộc thứ tự group bên Authentik hay thứ tự trong bảng pivot.
     *
     * @param  iterable<Role>  $roles
     * @return list<Role>
     */
    public static function inOrder(iterable $roles): array
    {
        $given = [...$roles];

        return array_values(array_filter(self::cases(), fn (Role $role): bool => in_array($role, $given, true)));
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Quản trị',
            self::NhapKho => 'Nhập kho',
            self::BanHang => 'Bán hàng',
        };
    }
}
