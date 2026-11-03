<?php

use App\Inventory\Security\SecurityEvent;
use App\Inventory\Staff\PlatformRevocation;
use App\Models\SecurityLogEntry;
use App\Models\User;
use Muakey\Platform\Contracts\PlatformUserResolver;
use Muakey\Platform\PlatformIdentity;

/*
 * Callback đăng nhập của SDK gọi resolver sau khi platform đã kiểm nhân viên `active` và có
 * `access` (ADR 0011): kho tạo nhân viên ở lần đầu, khớp chỉ theo id trên platform.
 */

const PLATFORM_USER_ID = '6f1c2a8e-4b1d-4c5e-9a3f-2d7b8e0c1a55';

function resolveStaff(?string $email = 'an@shop.test', ?string $name = 'An Nguyễn', string $sub = PLATFORM_USER_ID): User
{
    return app(PlatformUserResolver::class)->resolve(new PlatformIdentity($sub, $email, $name));
}

it('lần đầu đăng nhập thì tạo nhân viên chưa có Vai trò, ghi xuất hiện lần đầu và đăng nhập', function () {
    $staff = resolveStaff();

    expect($staff->only(['platform_user_id', 'name', 'email']))->toBe([
        'platform_user_id' => PLATFORM_USER_ID,
        'name' => 'An Nguyễn',
        'email' => 'an@shop.test',
    ])
        ->and($staff->fresh()->permissions->all())->toBe([])
        ->and(SecurityLogEntry::orderBy('id')->pluck('event')->all())->toBe([
            SecurityEvent::StaffFirstSeen,
            SecurityEvent::LoginSucceeded,
        ]);
});

it('đăng nhập lại thì đồng bộ tên và email, không tạo nhân viên mới', function () {
    $staff = resolveStaff();

    $again = resolveStaff(email: 'an.moi@shop.test', name: 'An Nguyễn Văn');

    expect($again->is($staff))->toBeTrue()
        ->and(User::count())->toBe(1)
        ->and($again->fresh()->only(['name', 'email']))->toBe(['name' => 'An Nguyễn Văn', 'email' => 'an.moi@shop.test'])
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffFirstSeen)->count())->toBe(1);
});

it('không khớp theo email: cùng email mà khác id trên platform là nhân viên khác', function () {
    $old = staffMember();
    $old->forceFill(['email' => 'an@shop.test'])->save();

    $staff = resolveStaff(email: 'an@shop.test');

    expect($staff->is($old))->toBeFalse()
        ->and($staff->platform_user_id)->toBe(PLATFORM_USER_ID);
});

it('platform chưa có tên thì hiện bằng email', function () {
    expect(resolveStaff(name: null)->name)->toBe('an@shop.test');
});

it('platform cho đăng nhập là đã mở lại tài khoản: có lại quyền ngay, không chờ danh bạ', function () {
    $staff = staffMember();
    $staff->forceFill([
        'platform_user_id' => PLATFORM_USER_ID,
        'platform_revoked_at' => now(),
        'platform_revoked_reason' => PlatformRevocation::Suspended,
        'deactivated_at' => now(),
    ])->save();

    resolveStaff();

    expect($staff->fresh())
        ->isRevokedByPlatform()->toBeFalse()
        ->isDeactivated()->toBeTrue()
        ->and(SecurityLogEntry::where('event', SecurityEvent::PlatformAccessRestored)->count())->toBe(1);
});
