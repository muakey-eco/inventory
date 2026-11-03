<?php

namespace App\Inventory\Access;

/**
 * Quyền: một việc nghiệp vụ mà nhân viên được làm trong kho (ADR 0012). Mỗi Quyền ứng với đúng
 * một Permission cùng tên trên platform, khai trong `platform.yaml`. Không Quyền nào bao trùm Quyền
 * khác; Permission vào cửa `access` không phải Quyền.
 */
enum Permission: string
{
    case StockView = 'stock.view';
    case CostView = 'cost.view';
    case Intake = 'intake';
    case IntakeReverse = 'intake.reverse';
    case SupplierManage = 'supplier.manage';
    case SupplierClaim = 'supplier_claim';
    case Dispatch = 'dispatch';
    case DefectReport = 'defect_report';
    case ReplacementApprove = 'replacement.approve';
    case DeadlineOverride = 'deadline.override';
    case StockVoid = 'stock.void';
    case StockDefect = 'stock.defect';
    case ContentExposure = 'content.exposure';
    case RevealInStock = 'reveal.in_stock';
    case CatalogManage = 'catalog.manage';
    case ReportProfit = 'report.profit';
    case StaffManage = 'staff.manage';
    case SalesChannelManage = 'sales_channel.manage';
    case ApiKeyManage = 'api_key.manage';
    case DispatchFreeze = 'dispatch_freeze';
    case LogView = 'log.view';

    /**
     * Các Quyền cho trước, theo thứ tự khai báo: để so sánh, hiển thị và ghi nhật ký không phụ
     * thuộc thứ tự platform trả Permission hay thứ tự trong bản chụp.
     *
     * @param  iterable<Permission>  $permissions
     * @return list<Permission>
     */
    public static function inOrder(iterable $permissions): array
    {
        $given = [...$permissions];

        return array_values(array_filter(self::cases(), fn (Permission $permission): bool => in_array($permission, $given, true)));
    }

    /**
     * Quyền mà một bộ Permission trên platform mang lại. Permission khác (`access`, hay thứ
     * platform thêm sau) không phải Quyền nên bị bỏ qua.
     *
     * @param  iterable<string>  $names
     * @return list<Permission>
     */
    public static function fromPlatform(iterable $names): array
    {
        $permissions = [];

        foreach ($names as $name) {
            $permission = self::tryFrom($name);

            if ($permission !== null) {
                $permissions[] = $permission;
            }
        }

        return self::inOrder($permissions);
    }

    /**
     * Tên Permission theo thứ tự khai báo, để so sánh và ghi nhật ký.
     *
     * @param  iterable<Permission>  $permissions
     * @return list<string>
     */
    public static function names(iterable $permissions): array
    {
        return array_map(fn (Permission $permission): string => $permission->value, self::inOrder($permissions));
    }

    public function label(): string
    {
        return match ($this) {
            self::StockView => 'Xem hàng và báo cáo tồn',
            self::CostView => 'Xem giá vốn và Nhà cung cấp',
            self::Intake => 'Nhập hàng',
            self::IntakeReverse => 'Huỷ nhập',
            self::SupplierManage => 'Quản lý Nhà cung cấp',
            self::SupplierClaim => 'Khiếu nại nhà cung cấp',
            self::Dispatch => 'Xuất hàng',
            self::DefectReport => 'Báo lỗi và Đổi hàng',
            self::ReplacementApprove => 'Duyệt Đổi hàng',
            self::DeadlineOverride => 'Vượt hạn',
            self::StockVoid => 'Huỷ hàng',
            self::StockDefect => 'Đánh dấu Lỗi và Khôi phục',
            self::ContentExposure => 'Ghi nhận lộ nội dung',
            self::RevealInStock => 'Xem hàng Còn hàng',
            self::CatalogManage => 'Quản lý Sản phẩm và Loại sản phẩm',
            self::ReportProfit => 'Xem Lãi/lỗ',
            self::StaffManage => 'Quản lý Nhân viên',
            self::SalesChannelManage => 'Quản lý Kênh bán',
            self::ApiKeyManage => 'Quản lý Khoá API',
            self::DispatchFreeze => 'Tạm dừng xuất kho',
            self::LogView => 'Xem nhật ký',
        };
    }

    public function group(): PermissionGroup
    {
        return match ($this) {
            self::StockView, self::CostView => PermissionGroup::General,
            self::Intake, self::IntakeReverse, self::SupplierManage, self::SupplierClaim => PermissionGroup::Intake,
            self::Dispatch, self::DefectReport, self::ReplacementApprove, self::DeadlineOverride => PermissionGroup::Dispatch,
            self::StockVoid, self::StockDefect, self::ContentExposure, self::RevealInStock => PermissionGroup::Stock,
            self::CatalogManage => PermissionGroup::Catalog,
            self::ReportProfit => PermissionGroup::Money,
            self::StaffManage, self::SalesChannelManage, self::ApiKeyManage, self::DispatchFreeze, self::LogView => PermissionGroup::Administration,
        };
    }
}
