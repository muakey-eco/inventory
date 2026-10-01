<?php

namespace Database\Seeders;

use App\Inventory\Access\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Một nhân viên cho mỗi Vai trò, để đăng nhập dev (/admin/auth/dev) có người mà chọn khi máy
 * dev chưa có Authentik. `authentik_uuid` là giá trị giả cố định: chạy lại vẫn an toàn.
 *
 * Bật Authentik dev (profile `sso`) thì đối soát thu quyền cả ba người vì uuid giả không có bên
 * đó. Chạy lại seeder là trả quyền, để quay về đăng nhập dev mà không phải dựng lại DB.
 */
class DevStaffSeeder extends Seeder
{
    private const STAFF = [
        '00000000-0000-4000-8000-000000000001' => ['Quản trị (dev)', 'quan-tri@kho.test', Role::Owner],
        '00000000-0000-4000-8000-000000000002' => ['Nhập kho (dev)', 'nhap-kho@kho.test', Role::NhapKho],
        '00000000-0000-4000-8000-000000000003' => ['Bán hàng (dev)', 'ban-hang@kho.test', Role::BanHang],
    ];

    public function run(): void
    {
        foreach (self::STAFF as $uuid => [$name, $email, $role]) {
            $staff = User::firstOrNew(['authentik_uuid' => $uuid]);
            $staff->forceFill([
                'name' => $name,
                'email' => $email,
                'authentik_revoked_at' => null,
                'authentik_revoked_reason' => null,
            ])->save();
            $staff->syncRoles([$role]);
        }
    }
}
