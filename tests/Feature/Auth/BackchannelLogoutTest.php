<?php

use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Models\AuthentikLogout;
use App\Models\SecurityLogEntry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    fakeAuthentik();
});

/**
 * Authentik gửi một logout_token sang kho, như khi nhân viên đăng xuất, bị xoá phiên hay bị tắt
 * bên Authentik. `$claims` đè lên token hợp lệ mặc định (mặc định có cả `sub` lẫn `sid`); giá trị
 * null thì bỏ claim đó đi.
 *
 * @param  array<string, mixed>  $claims
 */
function backchannelLogout(array $claims = [], bool $forged = false): TestResponse
{
    $claims = array_filter([
        'iss' => AUTHENTIK_ISSUER,
        'aud' => 'kho-client',
        'iat' => time(),
        'exp' => time() + 300,
        'jti' => (string) Str::uuid(),
        'events' => ['http://schemas.openid.net/event/backchannel-logout' => new stdClass],
        'sub' => AUTHENTIK_UUID,
        'sid' => 'sid-may-a',
        ...$claims,
    ], fn (mixed $value): bool => $value !== null);

    return test()->post(route('auth.authentik.backchannel-logout'), [
        'logout_token' => JWT::encode($claims, authentikKeyPair($forged)['private'], 'RS256', 'kho-key'),
    ]);
}

/**
 * Phiên kho hiện tại còn dùng được không, hỏi đúng như một request thật vào panel.
 */
function stillSignedIn(): bool
{
    test()->get(Filament::getUrl());

    return Filament::auth()->check();
}

it('huỷ phiên kho mang đúng sid ngay, không chờ đối soát', function () {
    signInViaAuthentik(['sid' => 'sid-may-a']);
    $staff = User::sole();

    backchannelLogout(['sid' => 'sid-may-a'])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->get(Filament::getUrl())->assertRedirect(Filament::getLoginUrl());

    expect(Filament::auth()->check())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::AuthentikSessionEnded)->sole())
        ->user_id->toBe($staff->id)
        ->actor_id->toBeNull()
        ->details->toBe(['scope' => 'session']);
});

it('không đụng phiên kho của một phiên Authentik khác', function () {
    signInViaAuthentik(['sid' => 'sid-may-a']);

    backchannelLogout(['sid' => 'sid-may-b'])->assertOk();

    expect(stillSignedIn())->toBeTrue();
});

it('token chỉ có sub thì huỷ mọi phiên kho của nhân viên đó, cả phiên không có sid', function (array $claims) {
    signInViaAuthentik($claims);

    backchannelLogout(['sid' => null])->assertOk();

    expect(stillSignedIn())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::AuthentikSessionEnded)->sole()->details)
        ->toBe(['scope' => 'all_sessions']);
})->with([
    'phiên có sid' => [['sid' => 'sid-may-a']],
    'phiên không có sid' => [[]],
]);

it('phiên kho không có sid thì token có cả sid lẫn sub vẫn huỷ nó theo sub', function () {
    signInViaAuthentik();

    backchannelLogout(['sid' => 'sid-may-a'])->assertOk();

    expect(stillSignedIn())->toBeFalse();
});

it('đăng nhập lại cùng sid sau khi bị huỷ thì dùng được bình thường', function () {
    signInViaAuthentik(['sid' => 'sid-may-a']);
    backchannelLogout(['sid' => 'sid-may-a'])->assertOk();
    expect(stillSignedIn())->toBeFalse();

    signInViaAuthentik(['sid' => 'sid-may-a']);

    expect(stillSignedIn())->toBeTrue();
});

it('đăng nhập lại sau khi bị huỷ theo sub thì dùng được bình thường', function () {
    signInViaAuthentik();
    backchannelLogout(['sid' => null])->assertOk();
    expect(stillSignedIn())->toBeFalse();

    signInViaAuthentik();

    expect(stillSignedIn())->toBeTrue();
});

it('token chỉ có sub của người khác thì không đụng phiên này và không ghi nhật ký', function () {
    signInViaAuthentik(['sid' => 'sid-may-a']);

    backchannelLogout(['sid' => null, 'sub' => (string) Str::uuid()])->assertOk();

    expect(stillSignedIn())->toBeTrue()
        ->and(SecurityLogEntry::where('event', SecurityEvent::AuthentikSessionEnded)->exists())->toBeFalse();
});

it('huỷ cả phiên đăng nhập dev khi Authentik huỷ mọi phiên của nhân viên', function () {
    $staff = tap(User::factory()->create(['authentik_uuid' => AUTHENTIK_UUID]))->assignRole(Role::BanHang);
    $this->actingAs($staff);

    backchannelLogout(['sid' => null])->assertOk();

    expect(stillSignedIn())->toBeFalse();
});

it('chỉ huỷ phiên, không đổi Vai trò hay quyền vào kho', function () {
    signInViaAuthentik(['sid' => 'sid-may-a', 'kho_groups' => ['kho-quan-tri']]);
    $staff = User::sole();

    backchannelLogout(['sid' => null])->assertOk();

    expect($staff->fresh())
        ->isLockedOut()->toBeFalse()
        ->and($staff->fresh()->roles->pluck('name')->all())->toBe([Role::Owner->value]);
});

it('từ chối logout_token không hợp lệ và giữ nguyên phiên', function (array $claims) {
    signInViaAuthentik(['sid' => 'sid-may-a']);

    backchannelLogout($claims)
        ->assertBadRequest()
        ->assertExactJson(['error' => 'invalid_request']);

    expect(stillSignedIn())->toBeTrue()
        ->and(AuthentikLogout::count())->toBe(0)
        ->and(SecurityLogEntry::where('event', SecurityEvent::AuthentikSessionEnded)->exists())->toBeFalse();
})->with([
    'issuer lạ' => [['iss' => 'https://ke-gian.test/']],
    'cấp cho client khác' => [['aud' => 'client-khac']],
    'hết hạn' => [['iat' => time() - 900, 'exp' => time() - 600]],
    'phát hành quá lâu' => [['iat' => time() - 3600]],
    'thiếu iat' => [['iat' => null]],
    'thiếu jti' => [['jti' => null]],
    'thiếu events' => [['events' => null]],
    'events không phải logout' => [['events' => ['http://schemas.openid.net/event/khac' => new stdClass]]],
    'có nonce' => [['nonce' => 'nonce']],
    'không có cả sub lẫn sid' => [['sub' => null, 'sid' => null]],
]);

it('từ chối logout_token ký bằng khoá không phải của Authentik', function () {
    signInViaAuthentik(['sid' => 'sid-may-a']);

    backchannelLogout(forged: true)->assertBadRequest();

    expect(stillSignedIn())->toBeTrue();
});

it('từ chối request không có logout_token', function () {
    $this->post(route('auth.authentik.backchannel-logout'))
        ->assertBadRequest()
        ->assertExactJson(['error' => 'invalid_request']);
});

it('từ chối phát lại cùng một jti', function () {
    backchannelLogout(['jti' => 'jti-mot-lan'])->assertOk();

    backchannelLogout(['jti' => 'jti-mot-lan'])->assertBadRequest();

    expect(AuthentikLogout::count())->toBe(1);
});

it('không xác minh được vì Authentik không trả lời thì báo lỗi tạm thời và giữ nguyên phiên', function () {
    signInViaAuthentik(['sid' => 'sid-may-a']);
    // Stub của beforeEach khớp trước, nên trỏ sang một Authentik khác đang sập.
    config(['services.authentik.issuer' => 'https://auth-sap.shop.test/application/o/kho/']);
    Http::fake(['auth-sap.shop.test/*' => Http::response('Bad Gateway', 502)]);

    backchannelLogout()->assertServiceUnavailable();

    expect(AuthentikLogout::count())->toBe(0);
});

it('dọn các lần huỷ cũ hơn thời hạn phiên kho', function () {
    $old = AuthentikLogout::create([
        'jti' => 'jti-cu',
        'sid' => 'sid-cu',
        'received_at' => now()->subMinutes(config('session.lifetime') + 1),
    ]);

    backchannelLogout()->assertOk();

    expect(AuthentikLogout::find($old->id))->toBeNull()
        ->and(AuthentikLogout::count())->toBe(1);
});

it('endpoint không chạy session hay CSRF: Authentik gọi thẳng từ server', function () {
    $middleware = Route::getRoutes()->getByName('auth.authentik.backchannel-logout')->gatherMiddleware();

    expect($middleware)->not->toContain('web');
});
