<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lô nhập chưa xác nhận quá 24 giờ: quá hạn xác nhận, xoá nội dung tạm và file upload tạm.
Schedule::command('inventory:intake:purge')->hourly();

// Ảnh Báo lỗi không còn Báo lỗi nào trỏ tới (tiến trình dừng giữa lúc lưu ảnh và commit).
Schedule::command('inventory:defect-reports:purge')->hourly();

// Phiếu xuất Đang giữ quá hạn Giữ hàng: nhả Slot về Còn hàng, phiếu sang Hết hạn giữ. Mỗi phút,
// vì hạn Giữ hàng tính bằng phút; giữ lâu hơn cần thì hàng nằm chết trong kho.
Schedule::command('inventory:dispatches:release-holds')->everyMinute();

// Bản sao danh bạ (ADR 0011): quét bù những webhook `user.*` bị lỡ, để nhân viên bị đình chỉ hay
// bị xoá trên platform hiện **Mất quyền theo platform** ở trang Nhân viên. Job `migrate` chạy
// thêm một lần mỗi deploy, cho những gì đổi trong lúc kho dừng.
Schedule::command('platform:directory-sync')->hourly()->withoutOverlapping();
