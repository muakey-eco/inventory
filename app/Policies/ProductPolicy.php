<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\Product;
use App\Models\User;

/**
 * Sản phẩm: Quyền Xem hàng và báo cáo tồn để xem; Quyền Quản lý Sản phẩm và Loại sản phẩm để
 * cấu hình, Ngừng bán và xoá.
 * Quy tắc thật (khoá cấu hình, chặn xoá khi có hàng) nằm trong ProductCatalog.
 */
class ProductPolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::StockView);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->gate->allows($user, Permission::StockView);
    }

    public function create(User $user): bool
    {
        return $this->gate->allows($user, Permission::CatalogManage);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->gate->allows($user, Permission::CatalogManage);
    }

    public function discontinue(User $user, Product $product): bool
    {
        return $this->gate->allows($user, Permission::CatalogManage) && ! $product->isDiscontinued();
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->gate->allows($user, Permission::CatalogManage) && ! $product->hasStock();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Product $product): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
