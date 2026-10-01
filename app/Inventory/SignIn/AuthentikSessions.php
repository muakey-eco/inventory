<?php

namespace App\Inventory\SignIn;

use App\Models\AuthentikLogout;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Gắn phiên kho với phiên Authentik đã đăng nhập ra nó, để back-channel logout huỷ được phiên kho
 * (xem {@see BackchannelLogout}). Phiên kho nhớ `sid` và lúc đăng nhập; middleware Authenticate hỏi
 * {@see self::hasEnded()} ở mỗi request, nên phiên bị huỷ ở request kế tiếp dù đang mở ở máy nào.
 */
class AuthentikSessions
{
    private const SID = 'authentik.sid';

    private const SIGNED_IN_AT = 'authentik.signed_in_at';

    /**
     * Gọi ngay sau khi đăng nhập và regenerate phiên.
     *
     * @param  ?string  $sid  claim `sid` của id_token; null với đăng nhập dev hoặc khi Authentik không gửi
     */
    public function start(Request $request, ?string $sid): void
    {
        $request->session()->put([
            self::SID => $sid,
            self::SIGNED_IN_AT => now()->format('Y-m-d H:i:s.uP'),
        ]);
    }

    /**
     * Authentik đã báo kết thúc phiên Authentik của phiên kho này, hoặc mọi phiên của nhân viên,
     * sau lúc phiên kho này mở. Phiên kho không có `sid` thì token nào mang `sub` của nhân viên cũng
     * huỷ được nó, vì không biết nó thuộc phiên Authentik nào. Phiên không rõ lúc đăng nhập thì coi
     * như mở từ trước mọi lần huỷ.
     */
    public function hasEnded(Request $request, User $staff): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $sid = $request->session()->get(self::SID);
        $signedInAt = $request->session()->get(self::SIGNED_IN_AT);

        return AuthentikLogout::query()
            ->when(is_string($signedInAt), fn ($query) => $query->where('received_at', '>', $signedInAt))
            ->where(fn ($query) => $query
                ->when(is_string($sid), fn ($query) => $query->where('sid', $sid))
                ->orWhere(fn ($query) => $query
                    ->where('authentik_uuid', $staff->authentik_uuid)
                    ->when(is_string($sid), fn ($query) => $query->whereNull('sid'))))
            ->exists();
    }
}
