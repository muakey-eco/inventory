<?php

namespace App\Inventory\Access;

use App\Models\User;
use Muakey\Platform\Platform;

/**
 * Chỗ duy nhất module Kho kiểm tra quyền theo Vai trò. Quản trị làm được mọi việc;
 * không có quyền lẻ theo từng nhân viên. Nhân viên bị Khoá nhân viên hoặc mất quyền theo
 * platform không làm được gì.
 * Policy của Laravel gọi vào đây.
 */
class RoleGate
{
    public function __construct(private Platform $platform) {}

    public function allows(User $user, Role ...$roles): bool
    {
        if ($user->isLockedOut()) {
            return false;
        }

        $held = $this->rolesOf($user);

        foreach ([Role::Admin, ...$roles] as $role) {
            if (in_array($role, $held, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws MissingRole
     */
    public function authorize(User $user, Role ...$roles): void
    {
        if (! $this->allows($user, ...$roles)) {
            throw new MissingRole($user, array_values($roles));
        }
    }

    /**
     * Vai trò của nhân viên đang đăng nhập lấy từ Permission platform vừa trả cho request này
     * (ADR 0011), nên thu quyền trên platform có hiệu lực ngay. Ngoài request đó (lệnh artisan,
     * job, một nhân viên khác) thì theo bản chụp Vai trò lần cuối kho thấy.
     *
     * @return list<Role>
     */
    public function rolesOf(User $user): array
    {
        return $this->platformRolesOf($user) ?? Role::inOrder($user->roles);
    }

    /**
     * Vai trò theo Permission platform vừa trả cho request này; `null` khi nhân viên không phải
     * người request này đăng nhập, hoặc không có request qua guard `platform`.
     *
     * @return list<Role>|null
     */
    public function platformRolesOf(User $user): ?array
    {
        $current = $this->platform->user();

        return $current !== null && $current->id === $user->platform_user_id
            ? Role::fromPermissions($current->permissions)
            : null;
    }
}
