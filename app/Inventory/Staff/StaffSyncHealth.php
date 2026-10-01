<?php

namespace App\Inventory\Staff;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tình trạng đối soát nhân viên với Authentik, để trang Tổng quan cảnh báo Quản trị khi đối soát
 * hỏng liên tục đủ lâu: lúc đó quyền trong kho có thể đã lệch với Authentik mà không ai hay.
 */
class StaffSyncHealth
{
    /** Bảng một hàng; xem migration add_authentik_revocation_and_staff_sync_health. */
    private const TABLE = 'staff_sync_health';

    /**
     * Một hai lần hỏng lẻ tẻ là chuyện mạng; hỏng liên tục từng này phút mới đáng báo.
     */
    private const ALERT_AFTER_MINUTES = 5;

    public function succeeded(): void
    {
        self::row()->update([
            'last_succeeded_at' => now(),
            'failing_since' => null,
            'last_error' => null,
            'updated_at' => now(),
        ]);
    }

    public function failed(string $error): void
    {
        // Giữ mốc hỏng đầu tiên của chuỗi: cảnh báo đếm từ đó, không từ lần hỏng mới nhất.
        self::row()->whereNull('failing_since')->update(['failing_since' => now()]);
        self::row()->update(['last_error' => $error, 'updated_at' => now()]);
    }

    /**
     * Cảnh báo cần hiện lúc này, hoặc null khi đối soát đang chạy được hay mới hỏng chưa lâu.
     *
     * Đếm từ lần chạy được cuối cùng chứ không chỉ từ lần hỏng đầu tiên: scheduler chết, hay
     * lệnh vỡ vì lỗi khác ngoài Authentik, thì không có lần hỏng nào được ghi, mà quyền trong
     * kho vẫn lệch dần với Authentik y như vậy. Kho chưa đối soát lần nào thì chỉ đếm lần hỏng.
     */
    public function alert(): ?StaffSyncAlert
    {
        $row = self::row()->first(['last_succeeded_at', 'failing_since', 'last_error']);
        $since = $row->last_succeeded_at ?? $row->failing_since ?? null;

        if ($since === null) {
            return null;
        }

        $brokenSince = CarbonImmutable::parse((string) $since);

        if ($brokenSince->gt(now()->subMinutes(self::ALERT_AFTER_MINUTES))) {
            return null;
        }

        return new StaffSyncAlert(
            brokenSince: $brokenSince,
            error: $row->last_error === null ? null : (string) $row->last_error,
        );
    }

    private static function row(): Builder
    {
        return DB::table(self::TABLE)->where('id', 1);
    }
}
