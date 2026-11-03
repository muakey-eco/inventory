<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\SupplierClaim;
use App\Models\User;

/**
 * Khiếu nại nhà cung cấp: người có Quyền Khiếu nại nhà cung cấp tạo, gửi, giải quyết và huỷ.
 * Quy tắc thật nằm trong SupplierClaims. Khiếu nại không sửa trực tiếp, không xoá.
 */
class SupplierClaimPolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::SupplierClaim);
    }

    public function view(User $user, SupplierClaim $supplierClaim): bool
    {
        return $this->gate->allows($user, Permission::SupplierClaim);
    }

    public function create(User $user): bool
    {
        return $this->gate->allows($user, Permission::SupplierClaim);
    }

    public function update(User $user, SupplierClaim $supplierClaim): bool
    {
        return false;
    }

    public function delete(User $user, SupplierClaim $supplierClaim): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, SupplierClaim $supplierClaim): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, SupplierClaim $supplierClaim): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
