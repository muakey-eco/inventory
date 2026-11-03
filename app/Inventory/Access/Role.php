<?php

namespace App\Inventory\Access;

/**
 * Vai trò của nhân viên. Mỗi Vai trò ứng với đúng một Permission cùng tên trên platform, khai
 * trong `platform.yaml` (ADR 0011): giữ Vai trò là giữ Permission đó.
 */
enum Role: string
{
    case Admin = 'admin';
    case Importer = 'importer';
    case Sale = 'sale';

    /**
     * Các Vai trò cho trước, theo thứ tự khai báo: để so sánh, hiển thị và ghi nhật ký không
     * phụ thuộc thứ tự platform trả Permission hay thứ tự trong bản chụp.
     *
     * @param  iterable<Role>  $roles
     * @return list<Role>
     */
    public static function inOrder(iterable $roles): array
    {
        $given = [...$roles];

        return array_values(array_filter(self::cases(), fn (Role $role): bool => in_array($role, $given, true)));
    }

    /**
     * Vai trò mà một bộ Permission trên platform mang lại. Permission khác (`access`, hay thứ
     * platform thêm sau) không phải Vai trò nên bị bỏ qua.
     *
     * @param  iterable<string>  $permissions
     * @return list<Role>
     */
    public static function fromPermissions(iterable $permissions): array
    {
        $roles = [];

        foreach ($permissions as $permission) {
            $role = self::tryFrom($permission);

            if ($role !== null) {
                $roles[] = $role;
            }
        }

        return self::inOrder($roles);
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị',
            self::Importer => 'Nhập kho',
            self::Sale => 'Bán hàng',
        };
    }
}
