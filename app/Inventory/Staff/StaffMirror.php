<?php

namespace App\Inventory\Staff;

use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Inventory\SignIn\StaffSignIn;
use App\Models\User;

/**
 * Bản sao trong kho của những gì Authentik nói về một nhân viên (ADR 0008): tên, email, Vai trò và
 * việc họ có còn quyền vào kho. Đăng nhập ({@see StaffSignIn}) và đối soát ({@see StaffSync}) cùng
 * ghi qua đây, nên hai đường ghi Nhật ký bảo mật giống nhau, chỉ khác `via`. Không bao giờ đụng
 * Khoá nhân viên. Mọi method ghi phải gọi trong transaction của người gọi.
 */
class StaffMirror
{
    public function __construct(private SecurityLog $log) {}

    /**
     * @param  list<Role>  $roles
     */
    public function sync(User $staff, string $name, ?string $email, array $roles, string $via): void
    {
        $staff->fill(['name' => $name, 'email' => $email])->save();

        $from = self::roleNames($staff->getRoleNames()->map(fn (string $name): Role => Role::from($name))->all());
        $to = self::roleNames($roles);

        if ($from !== $to) {
            $staff->syncRoles($roles);

            $this->log->record(SecurityEvent::RolesChanged, $staff, details: [
                'from' => $from,
                'to' => $to,
                'via' => $via,
            ]);
        }
    }

    /**
     * Thu quyền vì lý do cho trước, hoặc trả quyền khi lý do là null.
     */
    public function grantOrRevoke(User $staff, ?AuthentikRevocation $reason, string $via): void
    {
        if ($reason === null) {
            $this->restore($staff, $via);
        } else {
            $this->revoke($staff, $reason, $via);
        }
    }

    /**
     * Thu quyền vào kho. Đã mất quyền vì đúng lý do này thì không làm gì, để đối soát mỗi phút
     * không ghi lặp nhật ký.
     */
    private function revoke(User $staff, AuthentikRevocation $reason, string $via): void
    {
        if ($staff->authentik_revoked_reason === $reason) {
            return;
        }

        $staff->forceFill([
            // Đổi lý do (tắt rồi xoá hẳn) không đổi mốc mất quyền.
            'authentik_revoked_at' => $staff->authentik_revoked_at ?? now(),
            'authentik_revoked_reason' => $reason,
        ])->save();

        $this->log->record(SecurityEvent::AuthentikAccessRevoked, $staff, details: [
            'reason' => $reason->value,
            'via' => $via,
        ]);
    }

    /**
     * Trả quyền vào kho khi Authentik cấp lại. Khoá nhân viên, nếu có, vẫn giữ nguyên.
     */
    private function restore(User $staff, string $via): void
    {
        if (! $staff->isRevokedByAuthentik()) {
            return;
        }

        $staff->forceFill(['authentik_revoked_at' => null, 'authentik_revoked_reason' => null])->save();

        $this->log->record(SecurityEvent::AuthentikAccessRestored, $staff, details: ['via' => $via]);
    }

    /**
     * Tên Vai trò theo thứ tự khai báo, để so sánh và ghi nhật ký không phụ thuộc thứ tự group.
     *
     * @param  array<Role>  $roles
     * @return list<string>
     */
    public static function roleNames(array $roles): array
    {
        return array_map(fn (Role $role): string => $role->value, Role::inOrder($roles));
    }
}
