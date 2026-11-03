<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\StockUnit;
use App\Models\User;

/**
 * Đơn vị hàng: Quyền Xem hàng và báo cáo tồn xem danh sách ở dạng che; chỉ tạo qua nhập hàng.
 */
class StockUnitPolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::StockView);
    }

    public function view(User $user, StockUnit $stockUnit): bool
    {
        return $this->gate->allows($user, Permission::StockView);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockUnit $stockUnit): bool
    {
        return false;
    }

    public function delete(User $user, StockUnit $stockUnit): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, StockUnit $stockUnit): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, StockUnit $stockUnit): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
