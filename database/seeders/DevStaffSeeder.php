<?php

namespace Database\Seeders;

use App\Inventory\Access\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Một nhân viên cho mỗi Vai trò, để đăng nhập dev (/admin/auth/dev) có người mà chọn khi máy
 * dev chưa có Authentik. `authentik_uuid` là giá trị giả cố định: chạy lại vẫn an toàn.
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
            User::updateOrCreate(['authentik_uuid' => $uuid], ['name' => $name, 'email' => $email])
                ->syncRoles([$role]);
        }
    }
}
