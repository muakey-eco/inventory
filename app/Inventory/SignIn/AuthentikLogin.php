<?php

namespace App\Inventory\SignIn;

use DomainException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Hai nửa của luồng OIDC authorization code + PKCE với Authentik: {@see self::begin()} dựng URL
 * sang Authentik và cất state, nonce, code verifier vào phiên; {@see self::complete()} đối chiếu
 * callback với phiên đó, đổi code lấy id_token và xác minh nó. Mọi lần lệch đều thành
 * {@see SignInRefused}, không bao giờ để lọt một danh tính chưa xác minh.
 */
class AuthentikLogin
{
    private const SESSION_KEY = 'authentik.pending';

    /**
     * Độ lệch đồng hồ chấp nhận giữa kho và Authentik khi kiểm iat/exp, tính bằng giây.
     */
    private const CLOCK_LEEWAY = 60;

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

        $url = $this->discovery()['authorization_endpoint'].'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
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
        $token = $this->fetch(fn () => Http::asForm()
            ->withBasicAuth($this->clientId(), (string) config('services.authentik.client_secret'))
            ->post($this->discovery()['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => self::redirectUri(),
                'code_verifier' => $verifier,
            ]));

        if (! is_string($token['id_token'] ?? null)) {
            throw new SignInRefused(Refusal::InvalidToken, details: ['error' => 'missing_id_token']);
        }

        return $token['id_token'];
    }

    /**
     * Chữ ký theo JWKS của Authentik, rồi iss, aud, thời hạn và nonce.
     *
     * @throws SignInRefused
     */
    private function verify(string $idToken, string $nonce): AuthentikIdentity
    {
        $discovery = $this->discovery();
        $keys = $this->fetch(fn () => Http::get($discovery['jwks_uri']));

        try {
            JWT::$leeway = self::CLOCK_LEEWAY;
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($keys, 'RS256'));
        } catch (UnexpectedValueException|DomainException|InvalidArgumentException $exception) {
            throw new SignInRefused(Refusal::InvalidToken, details: ['error' => class_basename($exception)]);
        }

        // php-jwt chỉ kiểm exp/iat khi token có mang; id_token thì bắt buộc phải có.
        if (! is_int($claims['exp'] ?? null) || ! is_int($claims['iat'] ?? null)) {
            throw new SignInRefused(Refusal::InvalidToken, details: ['error' => 'missing_exp_or_iat']);
        }

        $audience = (array) ($claims['aud'] ?? []);

        if (($claims['iss'] ?? null) !== $discovery['issuer'] || ! in_array($this->clientId(), $audience, true)) {
            throw new SignInRefused(Refusal::InvalidToken, details: ['error' => 'wrong_issuer_or_audience']);
        }

        if (! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce'])) {
            throw new SignInRefused(Refusal::InvalidNonce);
        }

        $uuid = $claims['sub'] ?? null;

        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            throw new SignInRefused(Refusal::InvalidToken, details: ['error' => 'sub_not_uuid']);
        }

        $email = $claims['email'] ?? null;
        $name = $claims['name'] ?? $claims['preferred_username'] ?? null;

        return new AuthentikIdentity(
            uuid: $uuid,
            name: is_string($name) && $name !== '' ? $name : $uuid,
            email: is_string($email) && $email !== '' ? $email : null,
            groups: self::strings($claims['kho_groups'] ?? []),
            amr: self::strings($claims['amr'] ?? []),
        );
    }

    /**
     * @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string}
     *
     * @throws SignInRefused
     */
    private function discovery(): array
    {
        $issuer = (string) config('services.authentik.issuer');

        return once(function () use ($issuer): array {
            $discovery = $this->fetch(fn () => Http::get(Str::finish($issuer, '/').'.well-known/openid-configuration'));

            foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
                if (! is_string($discovery[$key] ?? null)) {
                    throw new SignInRefused(Refusal::ProviderError, details: ['error' => "discovery_missing_{$key}"]);
                }
            }

            // Discovery phải nói đúng issuer đã cấu hình, để iss của id_token so với chính nó.
            if (rtrim($discovery['issuer'], '/') !== rtrim($issuer, '/')) {
                throw new SignInRefused(Refusal::ProviderError, details: ['error' => 'discovery_issuer_mismatch']);
            }

            /** @var array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string} $discovery */
            return $discovery;
        });
    }

    /**
     * @param  callable(): Response  $request
     * @return array<string, mixed>
     *
     * @throws SignInRefused
     */
    private function fetch(callable $request): array
    {
        try {
            $response = $request()->throw();
        } catch (ConnectionException|RequestException $exception) {
            $error = $exception instanceof RequestException ? $exception->response->json('error') : null;

            throw new SignInRefused(Refusal::ProviderError, details: [
                'error' => is_string($error) ? Str::limit($error, 100) : class_basename($exception),
            ]);
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new SignInRefused(Refusal::ProviderError, details: ['error' => 'not_json']);
        }

        return $json;
    }

    private function clientId(): string
    {
        return (string) config('services.authentik.client_id');
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
