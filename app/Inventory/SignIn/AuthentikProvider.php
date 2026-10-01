<?php

namespace App\Inventory\SignIn;

use DomainException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * OIDC provider của kho trên Authentik: đọc discovery, đổi code lấy token, và xác minh mọi token
 * Authentik ký cho kho, cả id_token lúc đăng nhập lẫn logout_token gửi qua back-channel.
 */
class AuthentikProvider
{
    /**
     * Độ lệch đồng hồ chấp nhận giữa kho và Authentik khi kiểm iat/exp, tính bằng giây.
     */
    public const CLOCK_LEEWAY = 60;

    /**
     * Chữ ký theo JWKS của Authentik, rồi iss, aud và thời hạn. Phần còn lại tuỳ loại token,
     * người gọi tự kiểm.
     *
     * @return array<string, mixed>
     *
     * @throws AuthentikProviderError
     * @throws InvalidAuthentikToken
     */
    public function claims(string $jwt): array
    {
        $discovery = $this->discovery();
        $keys = $this->fetch(fn () => Http::get($discovery['jwks_uri']));

        try {
            JWT::$leeway = self::CLOCK_LEEWAY;
            $claims = (array) JWT::decode($jwt, JWK::parseKeySet($keys, 'RS256'));
        } catch (UnexpectedValueException|DomainException|InvalidArgumentException $exception) {
            throw new InvalidAuthentikToken(class_basename($exception));
        }

        // php-jwt chỉ kiểm exp/iat khi token có mang; token của Authentik thì luôn phải có.
        if (! is_int($claims['exp'] ?? null) || ! is_int($claims['iat'] ?? null)) {
            throw new InvalidAuthentikToken('missing_exp_or_iat');
        }

        $audience = (array) ($claims['aud'] ?? []);

        if (($claims['iss'] ?? null) !== $discovery['issuer'] || ! in_array($this->clientId(), $audience, true)) {
            throw new InvalidAuthentikToken('wrong_issuer_or_audience');
        }

        return $claims;
    }

    /**
     * Đổi authorization code (kèm PKCE verifier) lấy bộ token ở token endpoint.
     *
     * @return array<string, mixed>
     *
     * @throws AuthentikProviderError
     */
    public function exchange(string $code, string $verifier, string $redirectUri): array
    {
        return $this->fetch(fn () => Http::asForm()
            ->withBasicAuth($this->clientId(), (string) config('services.authentik.client_secret'))
            ->post($this->discovery()['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $verifier,
            ]));
    }

    /**
     * @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string}
     *
     * @throws AuthentikProviderError
     */
    public function discovery(): array
    {
        $issuer = (string) config('services.authentik.issuer');

        return once(function () use ($issuer): array {
            $discovery = $this->fetch(fn () => Http::get(Str::finish($issuer, '/').'.well-known/openid-configuration'));

            foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
                if (! is_string($discovery[$key] ?? null)) {
                    throw new AuthentikProviderError("discovery_missing_{$key}");
                }
            }

            // Discovery phải nói đúng issuer đã cấu hình, để iss của token so với chính nó.
            if (rtrim($discovery['issuer'], '/') !== rtrim($issuer, '/')) {
                throw new AuthentikProviderError('discovery_issuer_mismatch');
            }

            /** @var array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string} $discovery */
            return $discovery;
        });
    }

    /**
     * @param  callable(): Response  $request
     * @return array<string, mixed>
     *
     * @throws AuthentikProviderError
     */
    private function fetch(callable $request): array
    {
        try {
            $response = $request()->throw();
        } catch (ConnectionException|RequestException $exception) {
            $error = $exception instanceof RequestException ? $exception->response->json('error') : null;

            throw new AuthentikProviderError(is_string($error) ? Str::limit($error, 100) : class_basename($exception));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new AuthentikProviderError('not_json');
        }

        return $json;
    }

    public function clientId(): string
    {
        return (string) config('services.authentik.client_id');
    }
}
