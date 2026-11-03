<?php

use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Staff\PlatformRevocation;
use App\Models\SecurityLogEntry;
use App\Models\User;
use Muakey\Platform\Api\UserStatus;
use Muakey\Platform\Contracts\DirectorySink;
use Muakey\Platform\Directory\DirectoryUser;

/*
 * Bản sao danh bạ (ADR 0011): SDK đưa snapshot user của platform tới sink, qua webhook `user.*`
 * hoặc lượt quét bù `platform:directory-sync`. Kho chỉ giữ nhân viên đã biết, và ghi nhận khi họ
 * bị đình chỉ hay bị xoá: đó là Mất quyền theo platform.
 */

function directorySnapshot(User $staff, UserStatus $status = UserStatus::Active, string $name = 'An Nguyễn', string $email = 'an@shop.test'): DirectoryUser
{
    return new DirectoryUser($staff->platform_user_id, $name, $email, $status, []);
}

it('bị đình chỉ trên platform thì Mất quyền theo platform và ghi nhật ký', function () {
    $staff = staffMember(Role::Sale);

    app(DirectorySink::class)->upsert(directorySnapshot($staff, UserStatus::Suspended), seq: 7);

    $staff->refresh();

    expect($staff->platform_revoked_reason)->toBe(PlatformRevocation::Suspended)
        ->and($staff->isLockedOut())->toBeTrue()
        ->and(SecurityLogEntry::sole())
        ->event->toBe(SecurityEvent::PlatformAccessRevoked)
        ->user_id->toBe($staff->id)
        ->details->toBe(['reason' => 'suspended']);
});

it('người chưa từng vào kho thì bỏ qua, không tạo nhân viên', function () {
    $stranger = new DirectoryUser('0b5c8a52-9d0e-4f3b-8c1a-6e2f7d9a4b10', 'Người lạ', 'la@shop.test', UserStatus::Active, []);

    app(DirectorySink::class)->upsert($stranger, seq: 1);

    expect(User::count())->toBe(0);
});

it('bản có seq không mới hơn bản đang giữ thì bỏ qua', function () {
    $staff = staffMember(Role::Sale);
    $sink = app(DirectorySink::class);

    $sink->upsert(directorySnapshot($staff, UserStatus::Suspended), seq: 9);
    $sink->upsert(directorySnapshot($staff, UserStatus::Active, name: 'Tên cũ'), seq: 8);
    $sink->upsert(directorySnapshot($staff, UserStatus::Active, name: 'Tên cũ'), seq: 9);

    expect($staff->fresh())
        ->isRevokedByPlatform()->toBeTrue()
        ->name->toBe('An Nguyễn')
        ->directory_seq->toBe(9);
});

it('platform cấp lại quyền thì có lại quyền, nhưng Khoá nhân viên vẫn giữ', function () {
    $staff = staffMember(Role::Sale);
    $staff->forceFill(['deactivated_at' => now()])->save();
    $sink = app(DirectorySink::class);

    $sink->upsert(directorySnapshot($staff, UserStatus::Deactivated), seq: 1);
    $sink->upsert(directorySnapshot($staff, UserStatus::Active), seq: 2);

    expect($staff->fresh())
        ->isRevokedByPlatform()->toBeFalse()
        ->isDeactivated()->toBeTrue()
        ->and(SecurityLogEntry::orderBy('id')->pluck('event')->all())->toBe([
            SecurityEvent::PlatformAccessRevoked,
            SecurityEvent::PlatformAccessRestored,
        ]);
});

it('đổi từ đình chỉ sang xoá thì đổi lý do, giữ mốc mất quyền', function () {
    $staff = staffMember(Role::Sale);
    $sink = app(DirectorySink::class);

    $sink->upsert(directorySnapshot($staff, UserStatus::Suspended), seq: 1);
    $since = $staff->fresh()->platform_revoked_at;
    $this->travel(1)->hours();
    $sink->upsert(directorySnapshot($staff, UserStatus::Deactivated), seq: 2);

    expect($staff->fresh())
        ->platform_revoked_reason->toBe(PlatformRevocation::Deactivated)
        ->platform_revoked_at->toEqual($since);
});

it('snapshot không đổi gì thì không ghi nhật ký', function () {
    $staff = staffMember(Role::Sale);
    $sink = app(DirectorySink::class);

    $sink->upsert(directorySnapshot($staff, UserStatus::Active), seq: 1);
    $sink->upsert(directorySnapshot($staff, UserStatus::Suspended), seq: 2);
    $sink->upsert(directorySnapshot($staff, UserStatus::Suspended), seq: 3);

    expect(SecurityLogEntry::count())->toBe(1);
});
