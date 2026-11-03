<?php

namespace App\Policies;

use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Models\Batch;
use App\Models\User;

/**
 * Lô nhập: người có Quyền Nhập hàng tạo, xem trước, sửa khi còn Chờ xác nhận và xác nhận. Quy
 * tắc thật nằm trong BatchIntake. Lô nhập đã xác nhận thì không sửa, không xoá.
 */
class BatchPolicy
{
    public function __construct(private PermissionGate $gate) {}

    public function viewAny(User $user): bool
    {
        return $this->gate->allows($user, Permission::Intake);
    }

    public function view(User $user, Batch $batch): bool
    {
        return $this->gate->allows($user, Permission::Intake);
    }

    public function create(User $user): bool
    {
        return $this->gate->allows($user, Permission::Intake);
    }

    public function confirm(User $user, Batch $batch): bool
    {
        return $this->gate->allows($user, Permission::Intake);
    }

    /**
     * Sửa Giá trị áp cho Đơn vị hàng và phần chứng từ khi Lô nhập còn Chờ xác nhận (ADR 0007).
     * Đi đường riêng như `recordInvoiceTotal` thay vì mở `update`: hàng đã vào kho vẫn bất biến,
     * nên không muốn bật affordance sửa mặc định của Filament trên tài nguyên này.
     */
    public function revise(User $user, Batch $batch): bool
    {
        return $this->gate->allows($user, Permission::Intake);
    }

    /**
     * Ghi Tổng tiền hoá đơn: ngoại lệ cố ý duy nhất sửa được sau khi xác nhận (ADR 0006).
     * Đi đường riêng thay vì mở update, vốn sẽ bật affordance sửa mặc định của Filament.
     */
    public function recordInvoiceTotal(User $user, Batch $batch): bool
    {
        return $this->gate->allows($user, Permission::Intake);
    }

    /**
     * Huỷ nhập: Quyền riêng, không đi kèm Quyền Nhập hàng.
     */
    public function reverse(User $user, Batch $batch): bool
    {
        return $this->gate->allows($user, Permission::IntakeReverse);
    }

    public function update(User $user, Batch $batch): bool
    {
        return false;
    }

    public function delete(User $user, Batch $batch): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Batch $batch): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Batch $batch): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
