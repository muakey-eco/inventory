<?php

namespace App\Inventory\Staff;

use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bản sao trong kho của những gì platform nói về một nhân viên (ADR 0011): bản chụp Vai trò theo
 * lần cuối kho thấy họ, và việc họ có còn quyền vào kho theo Bản sao danh bạ. Mỗi thay đổi ghi
 * Nhật ký bảo mật; không đổi thì không ghi gì, để request và lượt quét bù lặp lại không ghi lặp.
 * Không bao giờ đụng Khoá nhân viên. `syncRoles` tự mở transaction vì nó chạy ở mọi request;
 * `grantOrRevoke` thì phải gọi trong transaction của người gọi, nơi đã khoá hàng nhân viên.
 */
class StaffMirror
{
    public function __construct(private SecurityLog $log) {}

    /**
     * Cập nhật bản chụp Vai trò theo Permission platform vừa trả cho nhân viên. Hai request song
     * song cùng thấy Vai trò mới thì chỉ một request ghi nhật ký.
     *
     * @param  list<Role>  $roles
     */
    public function syncRoles(User $staff, array $roles): void
    {
        $to = self::roleNames($roles);

        if (self::roleNames($staff->roles) === $to) {
            return;
        }

        DB::transaction(function () use ($staff, $roles, $to): void {
            $locked = User::query()->lockForUpdate()->findOrFail($staff->getKey());
            $from = self::roleNames($locked->roles);

            if ($from !== $to) {
                $locked->forceFill(['roles' => $roles])->save();

                $this->log->record(SecurityEvent::RolesChanged, $locked, details: ['from' => $from, 'to' => $to]);
            }

            $staff->setRawAttributes($locked->getAttributes(), sync: true);
        });
    }

    /**
     * Thu quyền vì lý do cho trước, hoặc trả quyền khi lý do là null. Phải gọi trong transaction
     * của người gọi.
     */
    public function grantOrRevoke(User $staff, ?PlatformRevocation $reason): void
    {
        if ($reason === null) {
            $this->restore($staff);
        } else {
            $this->revoke($staff, $reason);
        }
    }

    /**
     * Thu quyền vào kho. Đã mất quyền vì đúng lý do này thì không làm gì.
     */
    private function revoke(User $staff, PlatformRevocation $reason): void
    {
        if ($staff->platform_revoked_reason === $reason) {
            return;
        }

        $staff->forceFill([
            // Đổi lý do (đình chỉ rồi xoá hẳn) không đổi mốc mất quyền.
            'platform_revoked_at' => $staff->platform_revoked_at ?? now(),
            'platform_revoked_reason' => $reason,
        ])->save();

        $this->log->record(SecurityEvent::PlatformAccessRevoked, $staff, details: ['reason' => $reason->value]);
    }

    /**
     * Trả quyền vào kho khi platform cấp lại. Khoá nhân viên, nếu có, vẫn giữ nguyên.
     */
    private function restore(User $staff): void
    {
        if (! $staff->isRevokedByPlatform()) {
            return;
        }

        $staff->forceFill(['platform_revoked_at' => null, 'platform_revoked_reason' => null])->save();

        $this->log->record(SecurityEvent::PlatformAccessRestored, $staff);
    }

    /**
     * Tên Vai trò theo thứ tự khai báo, để so sánh và ghi nhật ký không phụ thuộc thứ tự platform
     * trả Permission.
     *
     * @param  iterable<Role>  $roles
     * @return list<string>
     */
    public static function roleNames(iterable $roles): array
    {
        return array_map(fn (Role $role): string => $role->value, Role::inOrder($roles));
    }
}
