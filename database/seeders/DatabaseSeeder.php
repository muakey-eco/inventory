<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Production có nhân viên thật từ Authentik; nhân viên giả chỉ để đăng nhập dev.
        if (app()->isLocal()) {
            $this->call(DevStaffSeeder::class);
        }
    }
}
