<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * Không có nút tạo nhân viên: nhân viên sinh ra ở lần đầu đăng nhập qua Authentik.
 */
class ManageStaff extends ManageRecords
{
    protected static string $resource = StaffResource::class;
}
