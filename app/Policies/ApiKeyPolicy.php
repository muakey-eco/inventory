<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\ApiKey;
use App\Models\User;

/**
 * Khoá API: chỉ người có Quyền Quản lý Khoá API thấy và quản lý. Khoá không bao giờ bị xoá (Nhật ký xem mã và Phiếu xuất
 * tham chiếu tới nó) và không sửa bằng form — tạo, xoay, thu hồi đều gọi ApiKeys.
 */
class ApiKeyPolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::ApiKeyManage);
    }

    public function view(User $user, ApiKey $key): bool
    {
        return $this->gate->allows($user, Permission::ApiKeyManage);
    }

    public function create(User $user): bool
    {
        return $this->gate->allows($user, Permission::ApiKeyManage);
    }

    public function update(User $user, ApiKey $key): bool
    {
        return false;
    }

    public function delete(User $user, ApiKey $key): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, ApiKey $key): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, ApiKey $key): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
