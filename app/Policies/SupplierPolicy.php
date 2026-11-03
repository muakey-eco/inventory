<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\Supplier;
use App\Models\User;

/**
 * Nhà cung cấp: chỉ người có Quyền Quản lý Nhà cung cấp thấy và quản lý.
 */
class SupplierPolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::SupplierManage);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->gate->allows($user, Permission::SupplierManage);
    }

    public function create(User $user): bool
    {
        return $this->gate->allows($user, Permission::SupplierManage);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->gate->allows($user, Permission::SupplierManage);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->gate->allows($user, Permission::SupplierManage);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Supplier $supplier): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
