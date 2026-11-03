<?php

namespace App\Inventory\Access;

/**
 * Nhóm hiển thị của Quyền trên trang Nhân viên. Chỉ để đọc cho dễ, không cấp gì: gom Quyền thành
 * role là việc của platform (ADR 0012).
 */
enum PermissionGroup: string
{
    case General = 'general';
    case Intake = 'intake';
    case Dispatch = 'dispatch';
    case Stock = 'stock';
    case Catalog = 'catalog';
    case Money = 'money';
    case Administration = 'administration';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Xem chung',
            self::Intake => 'Nhập hàng',
            self::Dispatch => 'Xuất hàng',
            self::Stock => 'Kho',
            self::Catalog => 'Danh mục',
            self::Money => 'Tiền',
            self::Administration => 'Quản trị kho',
        };
    }
}
