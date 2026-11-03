<?php

namespace App\Inventory\Reports;

/**
 * Tổng của cả báo cáo Tồn kho, cho ô số trên trang Tổng quan: ba con số mà ai có Quyền Xem hàng và báo cáo
 * tồn cũng xem được.
 *
 * Cố ý không mang Giá trị tồn hay Giá vốn Tồn lỗi: {@see StockReport::seesCost()} chỉ mở cho Quyền
 * Xem giá vốn và Nhà cung cấp, mà một ô hiện/ẩn theo người xem làm bố cục widget co giãn theo Quyền.
 */
final readonly class StockReportTotals
{
    /**
     * @param  int  $sellableSlots  tổng Tồn bán được
     * @param  int  $defectiveSlots  tổng Tồn lỗi
     * @param  int  $lowStockProducts  số Sản phẩm đang bị cảnh báo sắp hết
     */
    public function __construct(
        public int $sellableSlots,
        public int $defectiveSlots,
        public int $lowStockProducts,
    ) {}
}
