<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\ProductType;
use App\Models\User;

/**
 * Loại sản phẩm: Quyền Xem hàng và báo cáo tồn để xem; Quyền Quản lý Sản phẩm và Loại sản phẩm
 * để khai báo, Ngừng dùng và xoá.
 * Quy tắc thật (từ chối trọn gói khi đã có hàng, chặn xoá khi có Sản phẩm) nằm trong
 * ProductTypeCatalog.
 */
class ProductTypePolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::StockView);
    }

    public function view(User $user, ProductType $type): bool
    {
        return $this->gate->allows($user, Permission::StockView);
    }

    public function create(User $user): bool
    {
        return $this->gate->allows($user, Permission::CatalogManage);
    }

    public function update(User $user, ProductType $type): bool
    {
        return $this->gate->allows($user, Permission::CatalogManage);
    }

    public function discontinue(User $user, ProductType $type): bool
    {
        return $this->gate->allows($user, Permission::CatalogManage) && ! $type->isDiscontinued();
    }

    public function delete(User $user, ProductType $type): bool
    {
        return $this->gate->allows($user, Permission::CatalogManage) && $type->products()->doesntExist();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, ProductType $type): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, ProductType $type): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
