<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\DefectReport;
use App\Models\User;

/**
 * Báo lỗi: người có Quyền Báo lỗi và Đổi hàng xem, tạo và xác minh. Quy tắc thật nằm trong
 * DefectReporting. Báo lỗi chỉ tạo từ Phiếu xuất và không xoá.
 */
class DefectReportPolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::DefectReport);
    }

    public function view(User $user, DefectReport $defectReport): bool
    {
        return $this->gate->allows($user, Permission::DefectReport);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DefectReport $defectReport): bool
    {
        return false;
    }

    public function delete(User $user, DefectReport $defectReport): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, DefectReport $defectReport): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, DefectReport $defectReport): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
