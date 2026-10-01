<?php

namespace App\Inventory\Staff;

use Carbon\CarbonImmutable;

/**
 * Đối soát với Authentik đã không chạy được đủ lâu để báo Quản trị.
 */
final readonly class StaffSyncAlert
{
    /**
     * @param  CarbonImmutable  $brokenSince  lần chạy được cuối cùng, hoặc lần hỏng đầu tiên nếu
     *                                        kho chưa đối soát được lần nào
     * @param  ?string  $error  lỗi Authentik gần nhất; null khi đối soát không chạy hay vỡ vì lỗi khác
     */
    public function __construct(
        public CarbonImmutable $brokenSince,
        public ?string $error,
    ) {}
}
