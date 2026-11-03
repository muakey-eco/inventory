<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\SecurityLogEntry;
use App\Models\User;

/**
 * Nhật ký bảo mật: chỉ người có Quyền Xem nhật ký xem; không ai tạo, sửa, xoá qua giao diện.
 */
class SecurityLogEntryPolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::LogView);
    }

    public function view(User $user, SecurityLogEntry $entry): bool
    {
        return $this->gate->allows($user, Permission::LogView);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SecurityLogEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, SecurityLogEntry $entry): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
