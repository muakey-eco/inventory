<?php

namespace App\Inventory\Staff;

use App\Inventory\Access\MissingPermission;
use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Công tắc khẩn cấp tại kho của người có Quyền quản Nhân viên: Khoá nhân viên và mở khoá, độc lập
 * với platform (ADR 0011). Nhân viên và Quyền sinh ra từ platform ({@see StaffResolver}),
 * nên ở đây không có thao tác tạo nhân viên hay đổi Quyền. Mỗi thao tác ghi Nhật ký bảo mật.
 * Không có thao tác xoá nhân viên.
 */
class StaffManager
{
    public function __construct(private PermissionGate $gate, private SecurityLog $log) {}

    /**
     * Khoá nhân viên có hiệu lực ngay: phiên đang mở bị chặn ở request kế tiếp
     * ({@see User::canAccessPanel()}).
     * Không cho khoá người cuối cùng đang hoạt động có Quyền quản Nhân viên, kẻo không ai trong kho
     * mở lại được.
     *
     * @throws MissingPermission
     * @throws LastActiveStaffManager
     */
    public function deactivate(User $actor, User $staff): void
    {
        $this->gate->authorize($actor, Permission::StaffManage);

        DB::transaction(function () use ($actor, $staff): void {
            $this->ensureNotLastActiveStaffManager($staff);

            $staff->forceFill(['deactivated_at' => now()])->save();

            $this->log->record(SecurityEvent::StaffDeactivated, $staff, actor: $actor);
        });
    }

    /**
     * @throws MissingPermission
     */
    public function reactivate(User $actor, User $staff): void
    {
        $this->gate->authorize($actor, Permission::StaffManage);

        DB::transaction(fn () => $this->unlock($staff, actor: $actor));
    }

    /**
     * Mở khoá cho người có Quyền quản Nhân viên tự khoá mình ngoài hệ thống. Chỉ gọi từ lệnh
     * artisan trên server: không nhân viên nào thực hiện nên actor để trống.
     *
     * @throws MissingPermission nếu bản chụp Quyền của nhân viên không có Quyền quản Nhân viên
     */
    public function recoverStaffManagerAccess(User $manager): void
    {
        if (! $manager->permissions->contains(Permission::StaffManage)) {
            throw new MissingPermission($manager, Permission::StaffManage);
        }

        DB::transaction(fn () => $this->unlock($manager, details: ['via' => 'artisan']));
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
     * Mọi lần Khoá nhân viên khoá cùng các hàng đang hoạt động có Quyền quản Nhân viên, theo thứ tự
     * id, nên chạy tuần tự; đếm sau khi có khoá mới thấy thay đổi vừa commit của thao tác trước.
     * Quyền theo bản chụp lần cuối kho thấy; người đang mất quyền theo platform không mở khoá được
     * ai nên không tính. Phải gọi trong transaction.
     *
     * @throws LastActiveStaffManager
     */
    private function ensureNotLastActiveStaffManager(User $staff): void
    {
        $activeManagerIds = User::query()
            ->whereJsonContains('permissions', Permission::StaffManage->value)
            ->whereNull('deactivated_at')
            ->whereNull('platform_revoked_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($activeManagerIds->contains($staff->getKey()) && $activeManagerIds->count() === 1) {
            throw new LastActiveStaffManager($staff);
        }
    }
}
