<?php

use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Staff\AuthentikRevocation;
use App\Inventory\Staff\StaffManager;
use App\Models\SecurityLogEntry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

const AUTHENTIK_ISSUER = 'https://auth.shop.test/application/o/kho/';
const AUTHENTIK_UUID = '6f1c2a8e-4b1d-4c5e-9a3f-2d7b8e0c1a55';

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    config([
        'services.authentik.issuer' => AUTHENTIK_ISSUER,
        'services.authentik.client_id' => 'kho-client',
        'services.authentik.client_secret' => 'kho-secret',
    ]);

    $this->idTokenClaims = [];
    $this->idTokenKey = authentikKeyPair()['private'];

    Http::fake([
        AUTHENTIK_ISSUER.'.well-known/openid-configuration' => Http::response([
            'issuer' => AUTHENTIK_ISSUER,
            'authorization_endpoint' => 'https://auth.shop.test/application/o/authorize/',
            'token_endpoint' => 'https://auth.shop.test/application/o/token/',
            'jwks_uri' => AUTHENTIK_ISSUER.'jwks/',
        ]),
        AUTHENTIK_ISSUER.'jwks/' => Http::response(['keys' => [authentikKeyPair()['jwk']]]),
        'https://auth.shop.test/application/o/token/' => fn () => Http::response([
            'access_token' => 'access',
            'token_type' => 'Bearer',
            'id_token' => JWT::encode(test()->idTokenClaims, test()->idTokenKey, 'RS256', 'kho-key'),
        ]),
    ]);
});

/**
 * Một cặp khoá RSA cho cả lượt chạy: sinh khoá chậm, mà test chỉ cần nó khác khoá của kẻ giả mạo.
 *
 * @return array{private: string, jwk: array<string, string>}
 */
function authentikKeyPair(bool $forger = false): array
{
    static $pairs = [];

    return $pairs[$forger] ??= (function (): array {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $base64url = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return [
            'private' => $private,
            'jwk' => ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'kho-key', 'n' => $base64url($rsa['n']), 'e' => $base64url($rsa['e'])],
        ];
    })();
}

/**
 * Đi trọn một vòng đăng nhập: vào trang đăng nhập của panel, "đăng nhập ở Authentik", rồi quay
 * về callback. `$claims` đè lên id_token hợp lệ mặc định; `$callback` đè lên query của callback.
 *
 * @param  array<string, mixed>  $claims
 * @param  array<string, string>  $callback
 */
function signInViaAuthentik(array $claims = [], array $callback = []): TestResponse
{
    $location = test()->get(Filament::getLoginUrl())->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

    test()->idTokenClaims = [
        'iss' => AUTHENTIK_ISSUER,
        'aud' => 'kho-client',
        'sub' => AUTHENTIK_UUID,
        'nonce' => $query['nonce'],
        'iat' => time(),
        'exp' => time() + 300,
        'name' => 'An Nguyễn',
        'email' => 'an@shop.test',
        'kho_groups' => ['kho-ban-hang'],
        'amr' => ['pwd', 'mfa'],
        ...$claims,
    ];

    return test()->get(route('filament.admin.auth.authentik.callback', [
        'code' => 'authorization-code',
        'state' => $query['state'],
        ...$callback,
    ]));
}

it('trang đăng nhập của panel chuyển thẳng sang Authentik kèm state, nonce và PKCE', function () {
    $location = $this->get(Filament::getLoginUrl())->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://auth.shop.test/application/o/authorize/?')
        ->and($query)->toMatchArray([
            'response_type' => 'code',
            'client_id' => 'kho-client',
            'redirect_uri' => route('filament.admin.auth.authentik.callback'),
            'scope' => 'openid profile email kho',
            'code_challenge_method' => 'S256',
        ])
        ->and($query['state'])->not->toBeEmpty()
        ->and($query['nonce'])->not->toBeEmpty()
        ->and($query['code_challenge'])->not->toBeEmpty();
});

it('lần đầu đăng nhập tạo nhân viên kèm Vai trò theo kho_groups', function () {
    signInViaAuthentik(['kho_groups' => ['kho-ban-hang', 'kho-nhap-kho', 'ngoai-kho']])
        ->assertRedirect(Filament::getUrl());

    $staff = User::sole();

    expect($staff)
        ->authentik_uuid->toBe(AUTHENTIK_UUID)
        ->name->toBe('An Nguyễn')
        ->email->toBe('an@shop.test')
        ->and($staff->roles->pluck('name')->sort()->values()->all())->toBe([Role::BanHang->value, Role::NhapKho->value])
        ->and(Filament::auth()->id())->toBe($staff->id)
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffFirstSeen)->sole())
        ->user_id->toBe($staff->id)
        ->actor_id->toBeNull()
        ->details->toBe(['via' => 'authentik', 'roles' => [Role::NhapKho->value, Role::BanHang->value]])
        ->and(SecurityLogEntry::where('event', SecurityEvent::LoginSucceeded)->sole()->user_id)->toBe($staff->id);
});

it('gửi đúng code, PKCE và thông tin client sang token endpoint', function () {
    signInViaAuthentik();

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://auth.shop.test/application/o/token/') {
            return false;
        }

        return $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'authorization-code'
            && $request['redirect_uri'] === route('filament.admin.auth.authentik.callback')
            && strlen($request['code_verifier']) >= 43
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('kho-client:kho-secret'));
    });
});

it('lần sau khớp nhân viên theo sub dù email đổi, và đồng bộ tên, email', function () {
    $staff = tap(User::factory()->create([
        'authentik_uuid' => AUTHENTIK_UUID,
        'name' => 'Tên cũ',
        'email' => 'cu@shop.test',
    ]))->assignRole(Role::BanHang);

    signInViaAuthentik(['name' => 'An Nguyễn', 'email' => 'moi@shop.test'])
        ->assertRedirect(Filament::getUrl());

    expect(User::sole())
        ->id->toBe($staff->id)
        ->name->toBe('An Nguyễn')
        ->email->toBe('moi@shop.test')
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffFirstSeen)->exists())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::RolesChanged)->exists())->toBeFalse();
});

it('kho_groups đổi thì Vai trò đổi theo và ghi Nhật ký bảo mật không kèm người thực hiện', function () {
    $staff = tap(User::factory()->create(['authentik_uuid' => AUTHENTIK_UUID]))->assignRole(Role::BanHang);

    signInViaAuthentik(['kho_groups' => ['kho-quan-tri']])->assertRedirect(Filament::getUrl());

    expect($staff->fresh()->roles->pluck('name')->all())->toBe([Role::Owner->value])
        ->and(SecurityLogEntry::where('event', SecurityEvent::RolesChanged)->sole())
        ->user_id->toBe($staff->id)
        ->actor_id->toBeNull()
        ->details->toBe(['to' => [Role::Owner->value], 'via' => 'authentik', 'from' => [Role::BanHang->value]]);
});

it('quay về đúng trang đang đứng khi phiên hết hạn giữa chừng', function () {
    $intended = Filament::getUrl().'/nhan-vien';

    $this->get($intended)->assertRedirect(Filament::getLoginUrl());

    signInViaAuthentik(['kho_groups' => ['kho-quan-tri']])->assertRedirect($intended);
});

it('amr thiếu "mfa" vẫn vào được nhưng ghi cảnh báo', function (array $claims) {
    signInViaAuthentik($claims)->assertRedirect(Filament::getUrl());

    expect(Filament::auth()->check())->toBeTrue()
        ->and(SecurityLogEntry::where('event', SecurityEvent::LoginWithoutMfa)->sole())
        ->user_id->toBe(User::sole()->id);
})->with([
    'chỉ có mật khẩu' => [['amr' => ['pwd']]],
    'không có amr' => [['amr' => null]],
]);

it('có "mfa" trong amr thì không ghi cảnh báo', function () {
    signInViaAuthentik();

    expect(SecurityLogEntry::where('event', SecurityEvent::LoginWithoutMfa)->exists())->toBeFalse();
});

it('từ chối và dừng ở trang lỗi, ghi Nhật ký bảo mật', function (array $claims, array $callback, string $reason, string $message) {
    signInViaAuthentik($claims, $callback)
        ->assertForbidden()
        ->assertSee($message)
        ->assertSee(Filament::getLoginUrl());

    expect(Filament::auth()->check())->toBeFalse()
        ->and(User::count())->toBe(0)
        ->and(SecurityLogEntry::sole())
        ->event->toBe(SecurityEvent::LoginRefused)
        ->details->reason->toBe($reason);
})->with([
    'không có group kho-*' => [['kho_groups' => ['ngoai-kho']], [], 'no_role', 'Bạn chưa được cấp quyền vào kho'],
    'không có claim kho_groups' => [['kho_groups' => null], [], 'no_role', 'Bạn chưa được cấp quyền vào kho'],
    'state sai' => [[], ['state' => 'state-gia'], 'invalid_state', 'Phiên đăng nhập đã hết hạn'],
    'nonce sai' => [['nonce' => 'nonce-gia'], [], 'invalid_nonce', 'Phiên đăng nhập đã hết hạn'],
    'issuer lạ' => [['iss' => 'https://ke-gian.test/'], [], 'invalid_token', 'Không xác minh được'],
    'cấp cho client khác' => [['aud' => 'client-khac'], [], 'invalid_token', 'Không xác minh được'],
    'hết hạn' => [['iat' => time() - 900, 'exp' => time() - 600], [], 'invalid_token', 'Không xác minh được'],
    'Authentik trả lỗi' => [[], ['error' => 'access_denied'], 'provider_error', 'Authentik báo lỗi'],
    'lỗi Authentik kèm state giả' => [[], ['error' => 'chen-vao-nhat-ky', 'state' => 'state-gia'], 'invalid_state', 'Phiên đăng nhập đã hết hạn'],
    'thiếu exp' => [['exp' => null], [], 'invalid_token', 'Không xác minh được'],
]);

it('từ chối id_token ký bằng khoá không phải của Authentik', function () {
    $this->idTokenKey = authentikKeyPair(forger: true)['private'];

    signInViaAuthentik()->assertForbidden();

    expect(User::count())->toBe(0)
        ->and(SecurityLogEntry::sole()->details['reason'])->toBe('invalid_token');
});

it('state chỉ dùng được một lần', function () {
    $location = $this->get(Filament::getLoginUrl())->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
    $this->idTokenClaims = ['iss' => AUTHENTIK_ISSUER, 'aud' => 'kho-client', 'sub' => AUTHENTIK_UUID, 'nonce' => $query['nonce'],
        'iat' => time(), 'exp' => time() + 300, 'name' => 'An', 'kho_groups' => ['kho-ban-hang'], 'amr' => ['mfa']];
    $callback = route('filament.admin.auth.authentik.callback', ['code' => 'c', 'state' => $query['state']]);

    $this->get($callback)->assertRedirect();
    Filament::auth()->logout();

    $this->get($callback)->assertForbidden();
});

it('từ chối khi discovery khai issuer khác với cấu hình', function () {
    config(['services.authentik.issuer' => 'https://auth-khac.shop.test/application/o/kho/']);
    Http::fake(['auth-khac.shop.test/*' => Http::response([
        'issuer' => AUTHENTIK_ISSUER,
        'authorization_endpoint' => 'https://auth.shop.test/application/o/authorize/',
        'token_endpoint' => 'https://auth.shop.test/application/o/token/',
        'jwks_uri' => AUTHENTIK_ISSUER.'jwks/',
    ])]);

    $this->get(Filament::getLoginUrl())->assertForbidden();

    expect(SecurityLogEntry::sole()->details)->toBe(['error' => 'discovery_issuer_mismatch', 'reason' => 'provider_error']);
});

it('Authentik không trả lời thì dừng ở trang lỗi thay vì vỡ trang', function () {
    // Stub của beforeEach khớp trước, nên trỏ sang một Authentik khác đang sập.
    config(['services.authentik.issuer' => 'https://auth-sap.shop.test/application/o/kho/']);
    Http::fake(['auth-sap.shop.test/*' => Http::response('Bad Gateway', 502)]);

    $this->get(Filament::getLoginUrl())
        ->assertForbidden()
        ->assertSee('Authentik báo lỗi');

    expect(SecurityLogEntry::sole()->details['reason'])->toBe('provider_error');
});

it('nhân viên đang bị Khoá nhân viên không đăng nhập lại được qua Authentik', function () {
    $owner = staffMember(Role::Owner);
    $staff = tap(User::factory()->create(['authentik_uuid' => AUTHENTIK_UUID]))->assignRole(Role::BanHang);
    app(StaffManager::class)->deactivate($owner, $staff);

    signInViaAuthentik()
        ->assertForbidden()
        ->assertSee('Bạn đã bị khoá');

    expect(Filament::auth()->check())->toBeFalse()
        ->and($staff->fresh()->isDeactivated())->toBeTrue()
        ->and(SecurityLogEntry::where('event', SecurityEvent::LoginRefused)->sole())
        ->user_id->toBe($staff->id)
        ->details->toBe(['reason' => 'deactivated']);
});

it('nhân viên cũ mất hết group kho-* thì mất Vai trò ở kho và bị từ chối', function () {
    $staff = tap(User::factory()->create(['authentik_uuid' => AUTHENTIK_UUID]))->assignRole(Role::BanHang);

    signInViaAuthentik(['kho_groups' => []])->assertForbidden();

    expect($staff->fresh()->roles)->toBeEmpty()
        ->and($staff->fresh()->authentik_revoked_reason)->toBe(AuthentikRevocation::NoRole)
        ->and(SecurityLogEntry::where('event', SecurityEvent::RolesChanged)->sole()->details)
        ->toBe(['to' => [], 'via' => 'authentik', 'from' => [Role::BanHang->value]])
        ->and(SecurityLogEntry::where('event', SecurityEvent::LoginRefused)->sole())
        ->user_id->toBe($staff->id);
});

it('đăng nhập lại khi đã có group kho-* thì có lại quyền ngay, không chờ đối soát', function () {
    $staff = tap(User::factory()->create(['authentik_uuid' => AUTHENTIK_UUID]))->assignRole(Role::BanHang);
    $staff->forceFill(['authentik_revoked_at' => now(), 'authentik_revoked_reason' => AuthentikRevocation::Inactive])->save();

    signInViaAuthentik()->assertRedirect(Filament::getUrl());

    expect($staff->fresh()->isRevokedByAuthentik())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::AuthentikAccessRestored)->sole()->details)
        ->toBe(['via' => 'authentik']);
});

it('đăng xuất huỷ phiên kho rồi dừng ở trang tĩnh, không đụng Authentik', function () {
    signInViaAuthentik();

    $this->post(route('filament.admin.auth.logout'))
        ->assertRedirect(route('filament.admin.auth.signed-out'));

    $this->get(route('filament.admin.auth.signed-out'))
        ->assertOk()
        ->assertSee('Bạn đã đăng xuất khỏi kho')
        ->assertSee(Filament::getLoginUrl());

    expect(Filament::auth()->check())->toBeFalse();
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'end-session'));
});
