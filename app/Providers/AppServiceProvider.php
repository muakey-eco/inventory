<?php

namespace App\Providers;

use App\Inventory\Encryption\KeyFingerprints;
use App\Inventory\Staff\StaffDirectory;
use App\Inventory\Staff\StaffResolver;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Muakey\Platform\Contracts\DirectorySink;
use Muakey\Platform\Contracts\PlatformUserResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Nhân viên sinh ở lần đầu đăng nhập qua platform (ADR 0011).
        $this->app->bind(PlatformUserResolver::class, StaffResolver::class);
        // Bật Bản sao danh bạ: webhook `user.*` (khi có PLATFORM_WEBHOOK_SECRET) và lệnh quét bù.
        $this->app->bind(DirectorySink::class, StaffDirectory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // File nhập hàng upload qua Livewire: giới hạn theo cấu hình nhập hàng, không theo mặc định 12 MB.
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.intdiv((int) config('inventory.intake.max_bytes'), 1024)]]);

        // Job nhập và xuất: khoá sai thì worker từ chối khởi động, và mỗi job từ chối chạy
        // (kể cả `queue:work --once`, driver sync, hay .env đổi khi worker đang chạy).
        Event::listen([WorkerStarting::class, JobProcessing::class], fn () => $this->app->make(KeyFingerprints::class)->verify());
    }
}
