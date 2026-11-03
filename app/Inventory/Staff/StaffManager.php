<?php

namespace App\Inventory\Staff;

use App\Inventory\Access\MissingRole;
use App\Inventory\Access\Role;
use App\Inventory\Access\RoleGate;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Công tắc khẩn cấp của Quản trị tại kho: Khoá nhân viên và mở khoá, độc lập với platform
 * (ADR 0011). Nhân viên và Vai trò sinh ra từ platform ({@see StaffResolver}),
 * nên ở đây không có thao tác tạo nhân viên hay đổi Vai trò. Mỗi thao tác ghi Nhật ký bảo mật.
 * Không có thao tác xoá nhân viên.
 */
class StaffManager
{
    public function __construct(private RoleGate $roles, private SecurityLog $log) {}

    /**
     * Khoá nhân viên có hiệu lực ngay: phiên đang mở bị chặn ở request kế tiếp
     * ({@see User::canAccessPanel()}).
     * Không cho khoá Quản trị đang hoạt động cuối cùng, kẻo không ai trong kho mở lại được.
     *
     * @throws MissingRole
     * @throws LastActiveAdmin
     */
    public function deactivate(User $actor, User $staff): void
    {
        $this->roles->authorize($actor, Role::Admin);

        DB::transaction(function () use ($actor, $staff): void {
            $this->ensureNotLastActiveAdmin($staff);

            $staff->forceFill(['deactivated_at' => now()])->save();

            $this->log->record(SecurityEvent::StaffDeactivated, $staff, actor: $actor);
        });
    }

    /**
     * @throws MissingRole
     */
    public function reactivate(User $actor, User $staff): void
    {
        $this->roles->authorize($actor, Role::Admin);

        DB::transaction(fn () => $this->unlock($staff, actor: $actor));
    }

    /**
     * Mở khoá cho Quản trị tự khoá mình ngoài hệ thống. Chỉ gọi từ lệnh artisan trên server:
     * không có Quản trị nào thực hiện nên actor để trống.
     *
     * @throws MissingRole nếu bản chụp Vai trò của nhân viên không có Quản trị
     */
    public function recoverAdminAccess(User $admin): void
    {
        if (! $admin->roles->contains(Role::Admin)) {
            throw new MissingRole($admin, [Role::Admin]);
        }

        DB::transaction(fn () => $this->unlock($admin, details: ['via' => 'artisan']));
    }

    /**
     * @param  array<string, string>  $details
     */
    private function unlock(User $staff, ?User $actor = null, array $details = []): void
    {
        $staff->forceFill(['deactivated_at' => null])->save();

        $this->log->record(SecurityEvent::StaffReactivated, $staff, actor: $actor, details: $details);
    }

    /**
     * Mọi lần Khoá nhân viên khoá cùng các hàng Quản trị đang hoạt động, theo thứ tự id, nên chạy
     * tuần tự; đếm sau khi có khoá mới thấy thay đổi vừa commit của thao tác trước. Quản trị theo
     * bản chụp Vai trò lần cuối kho thấy; người đang mất quyền theo platform không mở khoá được ai
     * nên không tính. Phải gọi trong transaction.
     *
     * @throws LastActiveAdmin
     */
    private function ensureNotLastActiveAdmin(User $staff): void
    {
        $activeAdminIds = User::query()
            ->whereJsonContains('roles', Role::Admin->value)
            ->whereNull('deactivated_at')
            ->whereNull('platform_revoked_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($activeAdminIds->contains($staff->getKey()) && $activeAdminIds->count() === 1) {
            throw new LastActiveAdmin($staff);
        }
    }
}
