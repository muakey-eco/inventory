<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Không seed nhân viên hay Quyền: nhân viên sinh ở lần đầu đăng nhập qua platform, Quyền
     * là Permission trên platform (ADR 0011, ADR 0012). Dev đăng nhập bằng tài khoản thật qua App thử
     * `inventory-dev`.
     */
    public function run(): void
    {
        //
    }
}
