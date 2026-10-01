<?php

namespace App\Filament\Widgets;

use App\Filament\Support\InventoryAction;
use App\Inventory\Access\RoleGate;
use App\Inventory\Staff\StaffSyncHealth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Cảnh báo Quản trị khi đối soát nhân viên với Authentik hỏng liên tục đủ lâu (ADR 0008). Lúc đó
 * kho giữ nguyên quyền của lần đối soát cuối, nên người vừa bị tắt bên Authentik có thể vẫn đang
 * làm việc trong kho; cần chặn ngay thì dùng Khoá nhân viên. Đối soát chạy lại được là widget tự
 * biến mất, khác các widget hàng đợi luôn hiện kể cả khi bằng 0.
 */
class AuthentikSyncAlert extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    private const LABEL = 'Đối soát nhân viên với Authentik';

    protected ?string $heading = 'Cảnh báo';

    public static function canView(): bool
    {
        // allows() không kèm Vai trò nào: chỉ Quản trị.
        return app(RoleGate::class)->allows(InventoryAction::actor())
            && app(StaffSyncHealth::class)->alert() !== null;
    }

    public function mount(): void
    {
        // Chỉ kiểm Vai trò: đối soát có thể vừa chạy lại được giữa lúc trang vẽ và lúc widget
        // nạp, khi đó widget vẽ ô "đã chạy lại" thay vì vỡ.
        abort_unless(app(RoleGate::class)->allows(InventoryAction::actor()), 403);
    }

    protected function getStats(): array
    {
        $alert = app(StaffSyncHealth::class)->alert();

        if ($alert === null) {
            return [
                Stat::make(self::LABEL, 'Đã chạy lại')->color('success'),
            ];
        }

        $cause = $alert->error ?? 'Lệnh inventory:staff:sync không chạy hoặc lỗi ngoài Authentik: kiểm tra service scheduler và log.';

        return [
            Stat::make(self::LABEL, 'Không chạy được từ '.$alert->brokenSince->format('H:i d/m'))
                ->description("{$cause} Quyền nhân viên đang giữ như lần đối soát cuối; cần chặn ngay thì dùng Khoá nhân viên.")
                ->color('danger'),
        ];
    }
}
