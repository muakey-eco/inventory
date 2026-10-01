<?php

namespace App\Http\Controllers\Auth;

use App\Inventory\SignIn\AuthentikProviderError;
use App\Inventory\SignIn\BackchannelLogout;
use App\Inventory\SignIn\InvalidAuthentikToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Authentik gọi thẳng từ server sang đây khi một phiên Authentik kết thúc (OIDC back-channel
 * logout, xem README). Không có session hay CSRF: thứ duy nhất làm tin được request là chữ ký
 * của logout_token. Token bị từ chối chỉ ghi log ứng dụng, không ghi Nhật ký bảo mật, để ai
 * gọi bừa vào đây cũng không chèn được gì vào đó.
 */
class AuthentikBackchannelLogout
{
    public function __invoke(Request $request, BackchannelLogout $logout): Response|JsonResponse
    {
        $token = $request->input('logout_token');

        if (! is_string($token) || $token === '') {
            return $this->invalid('missing_logout_token');
        }

        try {
            $logout->receive($token);
        } catch (InvalidAuthentikToken $exception) {
            return $this->invalid($exception->getMessage());
        } catch (AuthentikProviderError $exception) {
            Log::error('Không xác minh được logout_token vì không đọc được Authentik', ['error' => $exception->getMessage()]);

            return response()->json(['error' => 'temporarily_unavailable'], 503, ['Cache-Control' => 'no-store']);
        }

        return response()->noContent(200, ['Cache-Control' => 'no-store']);
    }

    private function invalid(string $error): JsonResponse
    {
        Log::warning('Từ chối logout_token của Authentik', ['error' => $error]);

        return response()->json(['error' => 'invalid_request'], 400, ['Cache-Control' => 'no-store']);
    }
}
