<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\NavGroup;
use App\Http\Middleware\EndIdleAppSession;
use App\Http\Middleware\SyncRoleSnapshot;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Muakey\FilamentTheme\MuakeyColor;
use Muakey\FilamentTheme\MuakeyThemePlugin;
use Muakey\Platform\Filament\PlatformPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Đăng nhập chỉ qua platform (ADR 0011): màn login thành redirect sang platform, nút
            // đăng xuất kết thúc cả Phiên SSO, panel gác bằng guard `platform`. Không có mật
            // khẩu, 2FA hay trang hồ sơ trong kho.
            ->plugin(PlatformPlugin::make())
            // Báo tiếng Việt khi Phiên App chết giữa chừng (hết 2 giờ không thao tác, đăng xuất ở
            // App khác, mất quyền), thay cho hộp thoại tiếng Anh của Livewire.
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn (): string => view('filament.session-expired')->render())
            // Tên app không chứa chữ "Muakey": logo mặc định của Theme đã có wordmark đó.
            ->brandName('Kho hàng số')
            // Theme khoá Khung (font, bảng gray, Màu ngữ nghĩa, bo góc). App chỉ chọn Màu nhấn, khác
            // các app Muakey còn lại.
            ->plugin(MuakeyThemePlugin::make()->primary(MuakeyColor::Geekblue))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Thứ tự nhóm lấy theo thứ tự case của enum.
            ->navigationGroups(NavGroup::class)
            // Không đăng ký widget mặc định của Filament: sau khi Tổng quan có widget của kho, ô tài
            // khoản và ô phiên bản Filament là hai thứ duy nhất trên đó không nói gì về kho.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Sau `Authenticate` của SDK (plugin khai trước): cả hai chạy ở mọi request, kể cả
            // request Livewire update.
            ->authMiddleware([
                EndIdleAppSession::class,
                SyncRoleSnapshot::class,
            ], isPersistent: true);
    }
}
