<?php

namespace App\Console\Commands;

use App\Inventory\Access\MissingPermission;
use App\Inventory\Staff\StaffManager;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Lối thoát cho người có Quyền quản Nhân viên bị Khoá nhân viên mà không còn ai khác mở được. Chỉ
 * chạy được trên server, mỗi lần ghi Nhật ký bảo mật. Đăng nhập thì nằm ở platform (ADR 0011).
 * Tên lệnh giữ `recover-admin` vì runbook vận hành gọi theo tên đó.
 */
#[Signature('staff:recover-admin {email : Email của người có Quyền quản Nhân viên} {--unlock : Mở khoá nhân viên}')]
#[Description('Khôi phục quyền truy cập cho người có Quyền quản Nhân viên: mở khoá')]
class RecoverAdminAccess extends Command
{
    public function handle(StaffManager $staff): int
    {
        if (! $this->option('unlock')) {
            $this->error('Chọn thao tác: --unlock.');

            return self::FAILURE;
        }

        // Email chỉ là bản sao từ platform nên có thể trùng: trùng thì không đoán.
        $matches = User::where('email', $this->argument('email'))->get();

        if ($matches->count() !== 1) {
            $this->error($matches->isEmpty()
                ? 'Không tìm thấy nhân viên với email này.'
                : 'Có nhiều nhân viên cùng email này.');

            return self::FAILURE;
        }

        $manager = $matches->sole();

        try {
            $staff->recoverStaffManagerAccess($manager);
        } catch (MissingPermission) {
            $this->error('Nhân viên này không có Quyền quản Nhân viên.');

            return self::FAILURE;
        }

        $this->info("Đã khôi phục quyền truy cập cho {$manager->email}.");

        return self::SUCCESS;
    }
}
