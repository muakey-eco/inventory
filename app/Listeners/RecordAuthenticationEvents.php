<?php

namespace App\Listeners;

use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Events\Dispatcher;

/**
 * Chuyển sự kiện đăng nhập thành công của Laravel thành dòng Nhật ký bảo mật. Đăng nhập bị
 * từ chối không đi qua đây mà ghi ở callback Authentik, nơi biết lý do.
 */
class RecordAuthenticationEvents
{
    public function __construct(private SecurityLog $log) {}

    public function onLogin(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->log->record(SecurityEvent::LoginSucceeded, $user);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
        ];
    }
}
