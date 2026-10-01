<?php

namespace App\Inventory\SignIn;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Hai nửa của luồng OIDC authorization code + PKCE với Authentik: {@see self::begin()} dựng URL
 * sang Authentik và cất state, nonce, code verifier vào phiên; {@see self::complete()} đối chiếu
 * callback với phiên đó, đổi code lấy id_token và xác minh nó. Mọi lần lệch đều thành
 * {@see SignInRefused}, không bao giờ để lọt một danh tính chưa xác minh.
 */
class AuthentikLogin
{
    private const SESSION_KEY = 'authentik.pending';

    public function __construct(private AuthentikProvider $provider) {}

    /**
     * @throws SignInRefused nếu không đọc được cấu hình của Authentik
     */
    public function begin(Request $request): string
    {
        $pending = [
            'state' => Str::random(40),
            'nonce' => Str::random(40),
            'verifier' => Str::random(64),
        ];

        $url = $this->refusing(fn () => $this->provider->discovery())['authorization_endpoint'].'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->provider->clientId(),
            'redirect_uri' => self::redirectUri(),
            // `kho` là scope mapping riêng trả claim `kho_groups` (xem README).
            'scope' => 'openid profile email kho',
            'state' => $pending['state'],
            'nonce' => $pending['nonce'],
            'code_challenge' => self::base64url(hash('sha256', $pending['verifier'], true)),
            'code_challenge_method' => 'S256',
        ]);

        $request->session()->put(self::SESSION_KEY, $pending);

        return $url;
    }

    /**
     * @throws SignInRefused
     */
    public function complete(Request $request): AuthentikIdentity
    {
        // Lấy ra rồi xoá luôn: mỗi state chỉ dùng được một lần.
        $pending = $request->session()->pull(self::SESSION_KEY);
        $state = $request->query('state');

        // Kiểm state trước cả lỗi Authentik báo về: không thì ai cũng dựng được một link
        // `?error=...` để chèn chữ tuỳ ý vào Nhật ký bảo mật.
        if (! is_array($pending) || ! is_string($state) || ! hash_equals($pending['state'], $state)) {
            throw new SignInRefused(Refusal::InvalidState);
        }

        $error = $request->query('error');

        if (is_string($error)) {
            throw new SignInRefused(Refusal::ProviderError, details: ['error' => Str::limit($error, 100)]);
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            throw new SignInRefused(Refusal::ProviderError, details: ['error' => 'missing_code']);
        }

        return $this->verify($this->exchange($code, $pending['verifier']), $pending['nonce']);
    }

    public static function redirectUri(): string
    {
        return route('filament.admin.auth.authentik.callback');
    }

    /**
     * @throws SignInRefused
     */
    private function exchange(string $code, string $verifier): string
    {
        $token = $this->refusing(fn () => $this->provider->exchange($code, $verifier, self::redirectUri()));

        if (! is_string($token['id_token'] ?? null)) {
            throw new SignInRefused(Refusal::InvalidToken, details: ['error' => 'missing_id_token']);
        }

        return $token['id_token'];
    }

    /**
     * Chữ ký, iss, aud và thời hạn qua {@see AuthentikProvider}, rồi nonce và sub.
     *
     * @throws SignInRefused
     */
    private function verify(string $idToken, string $nonce): AuthentikIdentity
    {
        $claims = $this->refusing(fn () => $this->provider->claims($idToken));

        if (! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce'])) {
            throw new SignInRefused(Refusal::InvalidNonce);
        }

        $uuid = $claims['sub'] ?? null;

        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            throw new SignInRefused(Refusal::InvalidToken, details: ['error' => 'sub_not_uuid']);
        }

        $email = $claims['email'] ?? null;
        $name = $claims['name'] ?? $claims['preferred_username'] ?? null;
        $sid = $claims['sid'] ?? null;

        return new AuthentikIdentity(
            uuid: $uuid,
            name: is_string($name) && $name !== '' ? $name : $uuid,
            email: is_string($email) && $email !== '' ? $email : null,
            groups: self::strings($claims['kho_groups'] ?? []),
            amr: self::strings($claims['amr'] ?? []),
            sid: is_string($sid) && $sid !== '' ? $sid : null,
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     *
     * @throws SignInRefused
     */
    private function refusing(callable $call): mixed
    {
        try {
            return $call();
        } catch (AuthentikProviderError $exception) {
            throw new SignInRefused(Refusal::ProviderError, details: ['error' => $exception->getMessage()]);
        } catch (InvalidAuthentikToken $exception) {
            throw new SignInRefused(Refusal::InvalidToken, details: ['error' => $exception->getMessage()]);
        }
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
