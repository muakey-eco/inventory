<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\Dispatch;
use App\Models\User;

/**
 * Phiếu xuất: người có Quyền Xuất hàng tạo, xem và sửa phiếu Hoàn tất. Quy tắc
 * thật nằm trong ManualDispatch và DispatchEditor. Phiếu xuất không xoá.
 */
class DispatchPolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::Dispatch);
    }

    public function view(User $user, Dispatch $dispatch): bool
    {
        return $this->gate->allows($user, Permission::Dispatch);
    }

    public function create(User $user): bool
    {
        return $this->gate->allows($user, Permission::Dispatch);
    }

    public function update(User $user, Dispatch $dispatch): bool
    {
        return $this->gate->allows($user, Permission::Dispatch);
    }

    public function delete(User $user, Dispatch $dispatch): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Dispatch $dispatch): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Dispatch $dispatch): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
