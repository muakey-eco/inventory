<?php

namespace Tests;

use App\Inventory\Access\Permission;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Muakey\Platform\Facades\Platform;
use Muakey\Platform\Testing\InteractsWithPlatform;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithPlatform;

    protected function setUp(): void
    {
        parent::setUp();

        // Không test nào được lỡ gọi platform thật: quyền, Trạng thái user và Phiên App giả.
        Platform::fake();
    }

    /**
     * Nhân viên đăng nhập kho qua platform giả (ADR 0011), với `access` và đúng Permission ứng với
     * bản chụp Quyền của họ: request HTTP mang Phiên App giả và đi qua guard như thật.
     *
     * Guard `platform` chỉ nhận user qua middleware và quên user khi request đổi. Code chạy ngay
     * trong thân test thấy nhân viên đặt sẵn; `Livewire::test` gọi component bằng request mới mà
     * không chạy middleware, nên chỉ ở những request đó nhân viên được đặt lại. Ở đó không có
     * Permission của request, PermissionGate đọc bản chụp.
     *
     * @param  string|null  $guard
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        if (! $user instanceof User) {
            throw new InvalidArgumentException('Chỉ nhân viên mới đăng nhập kho.');
        }

        $this->actingAsPlatformUser($user, ['access', ...Permission::names($user->permissions)]);

        Auth::guard('platform')->setUser($user);
        $this->app->rebinding('request', function () use ($user): void {
            if ($this->app->shouldSkipMiddleware()) {
                Auth::guard('platform')->setUser($user);
            }
        });

        return $this;
    }
}
