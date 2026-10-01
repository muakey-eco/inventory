<?php

namespace App\Console\Commands;

use App\Inventory\Staff\AuthentikUnavailable;
use App\Inventory\Staff\StaffSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Chạy theo lịch mỗi phút: đối soát nhân viên trong kho với Authentik (ADR 0008). Authentik lỗi
 * thì báo lỗi và giữ nguyên quyền; hỏng liên tục thì trang Tổng quan cảnh báo Quản trị.
 */
#[Signature('inventory:staff:sync')]
#[Description('Đối soát tên, email, Vai trò và quyền vào kho của nhân viên với Authentik')]
class SyncStaff extends Command
{
    public function handle(StaffSync $sync): int
    {
        try {
            $count = $sync->run();
        } catch (AuthentikUnavailable $exception) {
            $this->line("Đối soát lỗi, giữ nguyên quyền: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Đã đối soát {$count} nhân viên với Authentik.");

        return self::SUCCESS;
    }
}
