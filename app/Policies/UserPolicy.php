<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\User;

/**
 * Nhân viên: chỉ người có Quyền Quản lý Nhân viên xem. Nhân viên không bao giờ bị xoá vì các
 * nhật ký tham chiếu tới họ; thay vào đó dùng Khoá nhân viên.
 */
class UserPolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::StaffManage);
    }

    public function view(User $user, User $staff): bool
    {
        return $this->gate->allows($user, Permission::StaffManage);
    }

    public function create(User $user): bool
    {
        return $this->gate->allows($user, Permission::StaffManage);
    }

    public function update(User $user, User $staff): bool
    {
        return false;
    }

    public function delete(User $user, User $staff): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $staff): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $staff): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
