<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Widgets\AuthentikSyncAlert;
use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\SignIn\AuthentikIdentity;
use App\Inventory\Staff\StaffManager;
use App\Models\SecurityLogEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
 * Đối soát nhân viên với Authentik mỗi phút (ADR 0008): kho kéo `GET /api/v3/core/users/` bằng
 * token chỉ đọc và thu hoặc trả quyền theo đó. Authentik giả ở đây giữ danh sách người dùng trong
 * `$this->authentikUsers`, lọc theo `uuid` (một giá trị, như UUIDFilter thật) và chia trang theo
 * `pagination.next` như API thật.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00'));

    config([
        'services.authentik.issuer' => 'https://auth.shop.test/application/o/kho/',
        'services.authentik.api_token' => 'sync-token',
    ]);

    $this->authentikUsers = [];
    $this->authentikPageSize = 100;
    $this->authentikDown = false;
    $this->authentikBroken = null;
    $this->authentikIgnoresUuidFilter = false;

    Http::fake([
        'https://auth.shop.test/api/v3/core/users/*' => function (Request $request) {
            if ($this->authentikBroken !== null) {
                return ($this->authentikBroken)();
            }

            if ($this->authentikDown) {
                return Http::response(['detail' => 'Server Error'], 500);
            }

            // Như Django: tham số lặp thì bộ lọc `uuid` (UUIDFilter một giá trị) lấy cái cuối.
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $matching = array_values(array_filter(
                $this->authentikUsers,
                fn (array $user): bool => $this->authentikIgnoresUuidFilter || $user['uuid'] === ($query['uuid'] ?? null),
            ));

            $page = (int) ($query['page'] ?? 1);
            // Authentik giới hạn page_size phía server; kho chỉ được dựa vào `next`.
            $size = min((int) ($query['page_size'] ?? 20), $this->authentikPageSize);
            $pages = max(1, (int) ceil(count($matching) / $size));

            return Http::response([
                'pagination' => ['next' => $page < $pages ? $page + 1 : 0, 'current' => $page, 'total_pages' => $pages],
                'results' => array_slice($matching, ($page - 1) * $size, $size),
            ]);
        },
    ]);
});

/**
 * Nhân viên đã từng đăng nhập vào kho, kèm bản ghi tương ứng bên Authentik.
 *
 * @param  list<string>  $groups
 */
function syncedStaff(array $groups = ['kho-ban-hang'], bool $active = true, string $name = 'Lan'): User
{
    $staff = staffMember(...AuthentikIdentity::rolesFor($groups));
    $staff->forceFill(['name' => $name])->save();

    authentikSays($staff, groups: $groups, active: $active, name: $name);

    return $staff;
}

/**
 * Đặt (hoặc ghi đè) bản ghi của nhân viên bên Authentik giả.
 *
 * @param  list<string>  $groups
 */
function authentikSays(User $staff, array $groups, bool $active = true, ?string $name = null, ?string $email = null): void
{
    $users = test()->authentikUsers;
    $users[$staff->authentik_uuid] = [
        'pk' => crc32($staff->authentik_uuid),
        'uuid' => $staff->authentik_uuid,
        'username' => 'u'.$staff->id,
        'name' => $name ?? $staff->name,
        'email' => $email ?? (string) $staff->email,
        'is_active' => $active,
        'groups_obj' => array_map(fn (string $group): array => ['pk' => md5($group), 'name' => $group], $groups),
    ];
    test()->authentikUsers = $users;
}

function runStaffSync(int $exitCode = 0): void
{
    test()->artisan('inventory:staff:sync')->assertExitCode($exitCode);
}

it('lên lịch đối soát mỗi phút', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('inventory:staff:sync');
});

it('gọi API Authentik bằng token chỉ đọc, lọc theo uuid nhân viên trong kho', function () {
    $staff = syncedStaff();

    runStaffSync();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://auth.shop.test/api/v3/core/users/')
        && $request->hasHeader('Authorization', 'Bearer sync-token')
        && str_contains($request->url(), 'uuid='.$staff->authentik_uuid)
        && str_contains($request->url(), 'include_groups=true'));
});

it('người dùng Authentik bị tắt thì mất quyền, phiên đang mở bị chặn ở request kế tiếp', function () {
    $staff = syncedStaff();
    $this->actingAs($staff)->get(Dashboard::getUrl())->assertOk();

    authentikSays($staff, groups: ['kho-ban-hang'], active: false);
    runStaffSync();

    // Phiên thật nạp lại nhân viên từ CSDL ở mỗi request; actingAs thì giữ bản trong bộ nhớ.
    $this->actingAs($staff->fresh())->get(Dashboard::getUrl())->assertRedirect(Filament::getLoginUrl());
    $this->assertGuest();

    expect($staff->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::AuthentikAccessRevoked)->sole()->details)
        ->toBe(['via' => 'authentik_sync', 'reason' => 'inactive']);
});

it('gỡ khỏi mọi group kho-* thì mất quyền, thêm lại thì có lại quyền', function () {
    $staff = syncedStaff(['kho-ban-hang', 'nhom-khac']);

    authentikSays($staff, groups: ['nhom-khac']);
    runStaffSync();

    expect($staff->fresh()->isRevokedByAuthentik())->toBeTrue()
        ->and($staff->fresh()->getRoleNames()->all())->toBe([]);
    $this->actingAs($staff->fresh())->get(Dashboard::getUrl())->assertRedirect(Filament::getLoginUrl());

    authentikSays($staff, groups: ['kho-nhap-kho']);
    runStaffSync();

    expect($staff->fresh()->isRevokedByAuthentik())->toBeFalse()
        ->and($staff->fresh()->getRoleNames()->all())->toBe([Role::NhapKho->value]);
    $this->actingAs($staff->fresh())->get(Dashboard::getUrl())->assertOk();

    expect(SecurityLogEntry::orderBy('id')->pluck('event')->all())->toBe([
        SecurityEvent::RolesChanged,
        SecurityEvent::AuthentikAccessRevoked,
        SecurityEvent::RolesChanged,
        SecurityEvent::AuthentikAccessRestored,
    ]);
});

it('đồng bộ tên, email, Vai trò và ghi Nhật ký bảo mật như đường đăng nhập', function () {
    $staff = syncedStaff(['kho-ban-hang']);

    authentikSays($staff, groups: ['kho-quan-tri', 'kho-ban-hang'], name: 'Lan Nguyễn', email: 'lan@shop.test');
    runStaffSync();

    expect($staff->fresh())
        ->name->toBe('Lan Nguyễn')
        ->email->toBe('lan@shop.test')
        ->and($staff->fresh()->getRoleNames()->sort()->values()->all())->toBe(['ban-hang', 'owner']);

    $entry = SecurityLogEntry::sole();
    expect($entry->event)->toBe(SecurityEvent::RolesChanged)
        ->and($entry->actor_id)->toBeNull()
        ->and($entry->details)->toBe(['to' => ['owner', 'ban-hang'], 'via' => 'authentik_sync', 'from' => ['ban-hang']]);
});

it('không có gì đổi thì không ghi Nhật ký bảo mật', function () {
    syncedStaff();

    runStaffSync();
    runStaffSync();

    expect(SecurityLogEntry::count())->toBe(0);
});

it('đang bị Khoá nhân viên thì đối soát không mở khoá dù Authentik vẫn active', function () {
    $admin = syncedStaff(['kho-quan-tri']);
    $staff = syncedStaff();
    app(StaffManager::class)->deactivate($admin, $staff);

    runStaffSync();

    expect($staff->fresh()->isDeactivated())->toBeTrue();
    $this->actingAs($staff->fresh())->get(Dashboard::getUrl())->assertRedirect(Filament::getLoginUrl());
});

it('mất quyền theo Authentik rồi có lại quyền vẫn không mở Khoá nhân viên', function () {
    $admin = syncedStaff(['kho-quan-tri']);
    $staff = syncedStaff();
    app(StaffManager::class)->deactivate($admin, $staff);

    authentikSays($staff, groups: ['kho-ban-hang'], active: false);
    runStaffSync();
    authentikSays($staff, groups: ['kho-ban-hang']);
    runStaffSync();

    expect($staff->fresh())
        ->isDeactivated()->toBeTrue()
        ->authentik_revoked_at->toBeNull();
});

it('user không còn tồn tại bên Authentik thì mất quyền', function () {
    $staff = syncedStaff();
    unset($this->authentikUsers[$staff->authentik_uuid]);

    runStaffSync();

    expect($staff->fresh()->isRevokedByAuthentik())->toBeTrue()
        ->and(SecurityLogEntry::sole()->details)->toBe(['via' => 'authentik_sync', 'reason' => 'not_found']);
});

it('đối soát nhiều nhân viên trong một lượt không thu nhầm quyền ai', function () {
    $staff = collect(range(1, 4))->map(fn () => syncedStaff());

    runStaffSync();

    expect($staff->filter(fn (User $member): bool => $member->fresh()->isRevokedByAuthentik()))->toBeEmpty()
        ->and(SecurityLogEntry::count())->toBe(0);
});

it('đọc hết mọi trang của API, kể cả khi Authentik bỏ qua bộ lọc uuid', function () {
    $this->authentikPageSize = 2;
    $this->authentikIgnoresUuidFilter = true;
    $staff = collect(range(1, 5))->map(fn () => syncedStaff());
    $last = $staff->last();
    authentikSays($last, groups: ['kho-ban-hang'], active: false);

    runStaffSync();

    expect($last->fresh()->isRevokedByAuthentik())->toBeTrue()
        ->and($staff->filter(fn (User $member): bool => $member->fresh()->isRevokedByAuthentik()))->toHaveCount(1);
});

it('không đụng nhân viên đã mất quyền mà Authentik vẫn báo y như cũ', function () {
    $staff = syncedStaff();
    authentikSays($staff, groups: ['kho-ban-hang'], active: false);

    runStaffSync();
    runStaffSync();

    expect(SecurityLogEntry::where('event', SecurityEvent::AuthentikAccessRevoked)->count())->toBe(1);
});

it('Authentik lỗi thì giữ nguyên quyền và không đổi gì', function (string $failure) {
    $staff = syncedStaff();
    authentikSays($staff, groups: [], active: false);

    match ($failure) {
        '500' => $this->authentikDown = true,
        'timeout' => $this->authentikBroken = fn () => Http::failedConnection(),
        'token sai' => $this->authentikBroken = fn () => Http::response(['detail' => 'Token invalid/expired'], 403),
        'không phải JSON' => $this->authentikBroken = fn () => Http::response('<html>proxy</html>'),
    };

    runStaffSync(1);

    expect($staff->fresh()->isRevokedByAuthentik())->toBeFalse()
        ->and($staff->fresh()->getRoleNames()->all())->toBe([Role::BanHang->value])
        ->and(SecurityLogEntry::count())->toBe(0);
})->with(['500', 'timeout', 'token sai', 'không phải JSON']);

it('chưa cấu hình token thì coi là lỗi, không đổi quyền', function () {
    $staff = syncedStaff();
    authentikSays($staff, groups: [], active: false);
    config(['services.authentik.api_token' => null]);

    runStaffSync(1);

    expect($staff->fresh()->isRevokedByAuthentik())->toBeFalse();
    Http::assertNothingSent();
});

it('đối soát hỏng liên tục từ 5 phút thì Quản trị thấy cảnh báo, chạy lại được thì cảnh báo ẩn', function () {
    $admin = syncedStaff(['kho-quan-tri']);
    $this->actingAs($admin);

    runStaffSync();
    $this->authentikDown = true;

    runStaffSync(1);
    $this->travel(4)->minutes();
    runStaffSync(1);
    expect(AuthentikSyncAlert::canView())->toBeFalse();

    $this->travel(1)->minutes();
    runStaffSync(1);
    expect(AuthentikSyncAlert::canView())->toBeTrue();
    $this->get(Dashboard::getUrl())->assertOk()->assertSeeLivewire(AuthentikSyncAlert::class);

    $this->authentikDown = false;
    runStaffSync();
    expect(AuthentikSyncAlert::canView())->toBeFalse();
});

it('đối soát không chạy từ 5 phút thì cũng cảnh báo, dù không lần nào báo lỗi', function () {
    $this->actingAs(syncedStaff(['kho-quan-tri']));

    runStaffSync();
    $this->travel(4)->minutes();
    expect(AuthentikSyncAlert::canView())->toBeFalse();

    $this->travel(1)->minutes();
    expect(AuthentikSyncAlert::canView())->toBeTrue();
});

it('cảnh báo đối soát chỉ dành cho Quản trị', function (Role $role) {
    syncedStaff();
    $this->authentikDown = true;
    runStaffSync(1);
    $this->travel(10)->minutes();
    runStaffSync(1);

    $this->actingAs(staffMember($role));

    expect(AuthentikSyncAlert::canView())->toBeFalse();
})->with([Role::NhapKho, Role::BanHang]);

it('kho chưa có nhân viên nào thì không gọi Authentik', function () {
    runStaffSync();

    Http::assertNothingSent();
});

it('trang Nhân viên phân biệt mất quyền theo Authentik với Khoá nhân viên', function () {
    $admin = syncedStaff(['kho-quan-tri']);
    $gone = syncedStaff();
    $locked = syncedStaff();
    $active = syncedStaff();
    app(StaffManager::class)->deactivate($admin, $locked);
    authentikSays($gone, groups: ['kho-ban-hang'], active: false);
    runStaffSync();

    $this->actingAs($admin);

    Livewire::test(ManageStaff::class)
        ->assertTableColumnFormattedStateSet('deactivated_at', 'Đã khoá', $locked)
        ->assertTableColumnFormattedStateSet('deactivated_at', 'Hoạt động', $active)
        ->assertTableColumnFormattedStateSet('deactivated_at', 'Hoạt động', $gone)
        ->assertTableColumnFormattedStateSet('authentik_revoked_at', 'Mất quyền: người dùng Authentik bị tắt', $gone)
        ->assertTableColumnFormattedStateSet('authentik_revoked_at', 'Có quyền', $active);
});
