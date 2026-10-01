<?php

namespace App\Inventory\Staff;

use App\Inventory\Access\MissingRole;
use App\Inventory\Access\Role;
use App\Inventory\Access\RoleGate;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Inventory\SignIn\StaffSignIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Công tắc khẩn cấp của Quản trị tại kho: Khoá nhân viên và mở khoá, độc lập với Authentik
 * (ADR 0008). Nhân viên và Vai trò sinh ra từ Authentik ({@see StaffSignIn}),
 * nên ở đây không có thao tác tạo nhân viên hay đổi Vai trò. Mỗi thao tác ghi Nhật ký bảo mật.
 * Không có thao tác xoá nhân viên.
 */
class StaffManager
{
    public function __construct(private RoleGate $roles, private SecurityLog $log) {}

    /**
     * Khoá nhân viên có hiệu lực ngay: phiên đang mở bị cắt ở request kế tiếp
     * (middleware Authenticate của panel) và cookie "ghi nhớ" cũ mất hiệu lực.
     * Không cho khoá Quản trị đang hoạt động cuối cùng, kẻo không ai trong kho mở lại được.
     *
     * @throws MissingRole
     * @throws LastActiveOwner
     */
    public function deactivate(User $actor, User $staff): void
    {
        $this->roles->authorize($actor, Role::Owner);

        DB::transaction(function () use ($actor, $staff): void {
            $this->ensureNotLastActiveOwner($staff);

            $staff->forceFill([
                'deactivated_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $this->log->record(SecurityEvent::StaffDeactivated, $staff, actor: $actor);
        });
    }

    /**
     * @throws MissingRole
     */
    public function reactivate(User $actor, User $staff): void
    {
        $this->roles->authorize($actor, Role::Owner);

        DB::transaction(fn () => $this->unlock($staff, actor: $actor));
    }

    /**
     * Mở khoá cho Quản trị tự khoá mình ngoài hệ thống. Chỉ gọi từ lệnh artisan trên server:
     * không có Quản trị nào thực hiện nên actor để trống.
     *
     * @throws MissingRole nếu nhân viên không mang Vai trò Quản trị
     */
    public function recoverOwnerAccess(User $owner): void
    {
        if (! $owner->hasRole(Role::Owner)) {
            throw new MissingRole($owner, [Role::Owner]);
        }

        DB::transaction(fn () => $this->unlock($owner, details: ['via' => 'artisan']));
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
     * Phải gọi trong transaction.
     *
     * @throws LastActiveOwner
     */
    private function ensureNotLastActiveOwner(User $staff): void
    {
        $this->lockOwnerRole();

        $activeOwnerIds = User::role(Role::Owner->value)
            ->whereNull('deactivated_at')
            ->pluck('id');

        if ($activeOwnerIds->contains($staff->getKey()) && $activeOwnerIds->count() === 1) {
            throw new LastActiveOwner($staff);
        }
    }

    /**
     * Mọi lần Khoá nhân viên khoá cùng một hàng (Vai trò Quản trị) nên chạy tuần tự; đếm
     * sau khi có khoá mới thấy thay đổi vừa commit của thao tác trước. Phải gọi trong
     * transaction.
     */
    private function lockOwnerRole(): void
    {
        RoleModel::query()
            ->where('name', Role::Owner->value)
            ->lockForUpdate()
            ->first();
    }
}
