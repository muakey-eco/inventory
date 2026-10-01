<?php

namespace App\Console\Commands;

use App\Inventory\Access\MissingRole;
use App\Inventory\Staff\StaffManager;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Lối thoát cho Quản trị bị Khoá nhân viên mà không còn Quản trị nào khác mở được. Chỉ chạy
 * được trên server, mỗi lần ghi Nhật ký bảo mật. Đăng nhập thì nằm ở Authentik (ADR 0008).
 */
#[Signature('staff:recover-owner {email : Email của Quản trị} {--unlock : Mở khoá nhân viên}')]
#[Description('Khôi phục quyền truy cập cho Quản trị: mở khoá')]
class RecoverOwnerAccess extends Command
{
    public function handle(StaffManager $staff): int
    {
        if (! $this->option('unlock')) {
            $this->error('Chọn thao tác: --unlock.');

            return self::FAILURE;
        }

        // Email chỉ là bản sao từ Authentik nên có thể trùng: trùng thì không đoán.
        $matches = User::where('email', $this->argument('email'))->get();

        if ($matches->count() !== 1) {
            $this->error($matches->isEmpty()
                ? 'Không tìm thấy nhân viên với email này.'
                : 'Có nhiều nhân viên cùng email này.');

            return self::FAILURE;
        }

        $admin = $matches->sole();

        try {
            $staff->recoverOwnerAccess($admin);
        } catch (MissingRole) {
            $this->error('Nhân viên này không mang Vai trò Quản trị.');

            return self::FAILURE;
        }

        $this->info("Đã khôi phục quyền truy cập cho {$admin->email}.");

        return self::SUCCESS;
    }
}
