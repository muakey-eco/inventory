<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Muakey\Platform\PlatformConfig;
use Muakey\Platform\Sessions\AppSessions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kết thúc Phiên App sau 2 giờ không thao tác (ADR 0011). Phiên App của SDK chỉ có hạn tuyệt đối 7
 * ngày, mà kho có nội dung mã: máy để mở là rủi ro thật. Hạ `SESSION_LIFETIME` thì không đủ: Phiên
 * App không nằm trong session của Laravel, nên tải lại trang là vào tiếp.
 *
 * Lần thao tác cuối nằm trong session của Laravel, gắn với token của Phiên App để đăng nhập lại là
 * tính từ đầu. Session mất (trình duyệt đóng quá lâu) thì tính từ lúc mở Phiên App. Request tự động
 * (Livewire chỉ chạy `wire:poll`) không gia hạn, nên tab để mở vẫn hết hạn.
 *
 * Hết hạn thì xoá Phiên App rồi đi đường "chưa đăng nhập" của SDK: trang HTML về đăng nhập
 * platform, request Livewire nhận 401 và hộp thoại "Phiên đăng nhập đã kết thúc".
 */
class EndIdleAppSession
{
    public const IDLE_SECONDS = 2 * 60 * 60;

    private const SESSION_KEY = 'inventory.app_session_activity';

    public function __construct(private AppSessions $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->sessions->fromRequest($request);

        if ($session === null || ! $request->hasSession()) {
            return $next($request);
        }

        $token = AppSessions::hash((string) $request->cookies->get(AppSessions::COOKIE));
        $seen = $request->session()->get(self::SESSION_KEY);
        $lastActivity = is_array($seen) && ($seen['session'] ?? null) === $token
            ? (int) $seen['at']
            : $session->createdAt->getTimestamp();

        if (now()->getTimestamp() - $lastActivity >= self::IDLE_SECONDS) {
            $this->sessions->end($request);
            $request->session()->forget(self::SESSION_KEY);

            throw new AuthenticationException(
                'Phiên App kết thúc vì 2 giờ không thao tác.',
                ['platform'],
                PlatformConfig::loginPath($request->getRequestUri()),
            );
        }

        if (! self::isAutomatic($request)) {
            $request->session()->put(self::SESSION_KEY, ['session' => $token, 'at' => now()->getTimestamp()]);
        }

        return $next($request);
    }

    /**
     * Request Livewire update mà mọi component chỉ chạy `wire:poll`: không cập nhật ô nào, và mọi
     * lời gọi mang metadata `poll` mà JS của Livewire gắn cho lượt poll.
     */
    private static function isAutomatic(Request $request): bool
    {
        $components = $request->input('components');

        if (! $request->hasHeader('X-Livewire') || ! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $calls = $component['calls'] ?? [];

            if (($component['updates'] ?? []) !== [] || ! is_array($calls) || $calls === []) {
                return false;
            }

            foreach ($calls as $call) {
                if (($call['metadata']['type'] ?? null) !== 'poll') {
                    return false;
                }
            }
        }

        return true;
    }
}
