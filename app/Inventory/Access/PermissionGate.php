<?php

namespace App\Inventory\Access;

use App\Models\User;
use Muakey\Platform\Platform;

/**
 * Chỗ duy nhất module Kho kiểm tra Quyền (ADR 0012). Mỗi thao tác hỏi đúng một Quyền, và không
 * Quyền nào thay được Quyền khác. Nhân viên bị Khoá nhân viên hoặc mất quyền theo platform không
 * làm được gì. Policy của Laravel gọi vào đây.
 */
class PermissionGate
{
    public function __construct(private Platform $platform) {}

    public function allows(User $user, Permission $permission): bool
    {
        return ! $user->isLockedOut() && in_array($permission, $this->permissionsOf($user), true);
    }

    /**
     * @throws MissingPermission
     */
    public function authorize(User $user, Permission $permission): void
    {
        if (! $this->allows($user, $permission)) {
            throw new MissingPermission($user, $permission);
        }
    }

    /**
     * Quyền của nhân viên đang đăng nhập lấy từ Permission platform vừa trả cho request này
     * (ADR 0011), nên thu quyền trên platform có hiệu lực ngay. Ngoài request đó (lệnh artisan,
     * job, một nhân viên khác) thì theo bản chụp Quyền lần cuối kho thấy.
     *
     * @return list<Permission>
     */
    public function permissionsOf(User $user): array
    {
        return $this->platformPermissionsOf($user) ?? Permission::inOrder($user->permissions);
    }

    /**
     * Quyền theo Permission platform vừa trả cho request này; `null` khi nhân viên không phải
     * người request này đăng nhập, hoặc không có request qua guard `platform`.
     *
     * @return list<Permission>|null
     */
    public function platformPermissionsOf(User $user): ?array
    {
        $current = $this->platform->user();

        return $current !== null && $current->id === $user->platform_user_id
            ? Permission::fromPlatform($current->permissions)
            : null;
    }
}
