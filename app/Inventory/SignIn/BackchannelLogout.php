<?php

namespace App\Inventory\SignIn;

use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Models\AuthentikLogout;
use App\Models\User;

/**
 * Nhận logout_token Authentik gửi qua OIDC back-channel logout: khi nhân viên đăng xuất, bị xoá
 * phiên, bị tắt, hay phiên Authentik hết hạn. Token đã xác minh thì ghi lại để
 * {@see AuthentikSessions} huỷ phiên kho tương ứng: theo `sid` nếu có, không thì mọi phiên của
 * `sub`. Chỉ huỷ phiên, không bao giờ cấp hay đổi quyền: Vai trò và quyền vào kho vẫn chỉ đổi qua
 * đăng nhập và đối soát (ADR 0008).
 */
class BackchannelLogout
{
    public const EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    /**
     * Token phát hành lâu hơn thế này thì từ chối. Authentik gửi ngay khi phiên kết thúc; giới hạn
     * này cũng là thời gian tối thiểu phải nhớ `jti` để chống phát lại.
     */
    private const MAX_AGE = 600;

    public function __construct(private AuthentikProvider $provider, private SecurityLog $log) {}

    /**
     * @throws AuthentikProviderError
     * @throws InvalidAuthentikToken
     */
    public function receive(string $logoutToken): void
    {
        $claims = $this->provider->claims($logoutToken);

        if ($claims['iat'] < now()->getTimestamp() - self::MAX_AGE - AuthentikProvider::CLOCK_LEEWAY) {
            throw new InvalidAuthentikToken('too_old');
        }

        $events = $claims['events'] ?? null;

        if (! is_object($events) || ! property_exists($events, self::EVENT)) {
            throw new InvalidAuthentikToken('not_a_logout_event');
        }

        // Spec cấm logout_token mang nonce, để không ai đem id_token ra giả làm logout_token.
        if (array_key_exists('nonce', $claims)) {
            throw new InvalidAuthentikToken('has_nonce');
        }

        $jti = self::string($claims['jti'] ?? null);
        $sid = self::string($claims['sid'] ?? null);
        $uuid = self::string($claims['sub'] ?? null);

        if ($jti === null) {
            throw new InvalidAuthentikToken('missing_jti');
        }

        if ($sid === null && $uuid === null) {
            throw new InvalidAuthentikToken('missing_sub_and_sid');
        }

        $this->prune();

        $logout = AuthentikLogout::createOrFirst(['jti' => $jti], ['sid' => $sid, 'authentik_uuid' => $uuid, 'received_at' => now()]);

        if (! $logout->wasRecentlyCreated) {
            throw new InvalidAuthentikToken('replayed_jti');
        }

        $staff = $uuid === null ? null : User::firstWhere('authentik_uuid', $uuid);

        if ($staff !== null) {
            $this->log->record(SecurityEvent::AuthentikSessionEnded, $staff, details: [
                'scope' => $sid === null ? 'all_sessions' : 'session',
            ]);
        }
    }

    /**
     * Hàng cũ hơn thời hạn phiên kho không còn huỷ được gì: phiên nào im lâu hơn thế đã tự hết,
     * phiên nào có request sau lúc nhận token thì đã bị huỷ. Giữ ít nhất MAX_AGE để chống phát lại.
     */
    private function prune(): void
    {
        $retention = max((int) config('session.lifetime') * 60, self::MAX_AGE + AuthentikProvider::CLOCK_LEEWAY);

        AuthentikLogout::where('received_at', '<', now()->subSeconds($retention))->delete();
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
