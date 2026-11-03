<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\DispatchFreezePage;
use App\Filament\Resources\DefectReports\DefectReportResource;
use App\Filament\Support\InventoryAction;
use App\Filament\Support\QueueStat;
use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Inventory\Dispatch\DispatchFreeze;
use App\Models\DefectReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Việc chờ xử lý không thuộc hàng đợi Nhập hàng hay Xuất hàng, trên trang Tổng quan. Mỗi ô hiện
 * theo Quyền của chính việc đó; không có ô nào thì ẩn cả widget.
 *
 * Tạm dừng xuất kho không phải hàng đợi đếm được, nhưng đúng là việc chưa xong: kho luôn ở trạng thái
 * này sau khi khôi phục từ backup, cho tới khi người có Quyền Tạm dừng xuất kho đối chiếu xong các đơn
 * phát sinh sau mốc khôi phục. Trước widget này nó không hiện ở đâu ngoài trang của chính nó, nên kho
 * đang dừng mà không ai mở trang ấy thì không ai thấy. Người chỉ xuất hàng không thấy ô này: lúc kho
 * dừng thì thao tác giao hàng đã tự báo lỗi ngay tại chỗ.
 */
class OwnerQueue extends StatsOverviewWidget
{
    protected static ?int $sort = 40;

    protected ?string $heading = 'Việc chờ xử lý';

    public static function canView(): bool
    {
        return self::visibleTiles() !== [];
    }

    public function mount(): void
    {
        abort_unless(self::canView(), 403);
    }

    protected function getStats(): array
    {
        $tiles = self::visibleTiles();
        $stats = [];

        if (in_array(Permission::ReplacementApprove, $tiles, true)) {
            $stats[] = QueueStat::make(
                'Đổi hàng chờ duyệt',
                DefectReport::query()->awaitingApproval()->count(),
                DefectReportResource::getUrl('index', ['tab' => 'approval']),
                description: 'Từ lần đổi thứ 3 trong cùng chuỗi',
            );
        }

        if (in_array(Permission::DispatchFreeze, $tiles, true)) {
            $freeze = app(DispatchFreeze::class)->state();

            $stats[] = Stat::make('Tạm dừng xuất kho', $freeze->isFrozen() ? 'Đang tạm dừng' : 'Đang chạy')
                ->description($freeze->isFrozen() ? $freeze->reason : 'Kênh bán giữ và giao hàng bình thường')
                ->color($freeze->isFrozen() ? 'danger' : 'gray')
                ->url(DispatchFreezePage::getUrl());
        }

        return $stats;
    }

    /**
     * Quyền của các ô mà người xem được thấy: mỗi ô một Quyền, theo thứ tự hiện.
     *
     * @return list<Permission>
     */
    private static function visibleTiles(): array
    {
        $gate = app(PermissionGate::class);
        $actor = InventoryAction::actor();

        return array_values(array_filter(
            [Permission::ReplacementApprove, Permission::DispatchFreeze],
            fn (Permission $permission): bool => $gate->allows($actor, $permission),
        ));
    }
}
