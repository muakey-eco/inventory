<?php

use App\Inventory\Access\MissingRole;
use App\Inventory\Access\Role;
use App\Inventory\Access\RoleGate;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Staff\LastActiveAdmin;
use App\Inventory\Staff\PlatformRevocation;
use App\Inventory\Staff\StaffManager;
use App\Models\SecurityLogEntry;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->staff = app(StaffManager::class);
});

it('Quản trị Khoá nhân viên và ghi Nhật ký bảo mật', function () {
    $admin = staffMember(Role::Admin);
    $seller = staffMember(Role::Sale);

    $this->staff->deactivate($admin, $seller);

    expect($seller->fresh()->isDeactivated())->toBeTrue()
        ->and(app(RoleGate::class)->allows($seller->fresh(), Role::Sale))->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffDeactivated)->sole())
        ->user_id->toBe($seller->id)
        ->actor_id->toBe($admin->id);
});

it('chỉ Quản trị Khoá được nhân viên', function (Role $role) {
    $seller = staffMember(Role::Sale);

    expect(fn () => $this->staff->deactivate(staffMember($role), $seller))
        ->toThrow(MissingRole::class);

    expect($seller->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffDeactivated)->exists())->toBeFalse();
})->with([
    'Nhập kho' => Role::Importer,
    'Bán hàng' => Role::Sale,
]);

it('Khoá nhân viên cắt ngay phiên đang mở', function () {
    $admin = staffMember(Role::Admin);
    $seller = staffMember(Role::Sale);
    $panelUrl = Filament::getPanel('admin')->getUrl();

    $this->actingAs($seller)->get($panelUrl)->assertOk();

    $this->staff->deactivate($admin, $seller);

    // Platform vẫn cho vào; kho chặn vì Khoá nhân viên là của kho.
    $this->get($panelUrl)->assertForbidden();
});

it('chặn Khoá Quản trị đang hoạt động cuối cùng', function () {
    $admin = staffMember(Role::Admin);

    expect(fn () => $this->staff->deactivate($admin, $admin))
        ->toThrow(LastActiveAdmin::class);

    expect($admin->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffDeactivated)->exists())->toBeFalse();
});

it('Quản trị đã bị khoá không tính là Quản trị đang hoạt động', function () {
    $owner = staffMember(Role::Admin);
    $other = staffMember(Role::Admin);
    $this->staff->deactivate($owner, $other);

    expect(fn () => $this->staff->deactivate($owner, $owner))
        ->toThrow(LastActiveAdmin::class);
});

it('Quản trị đang mất quyền theo platform không tính là Quản trị đang hoạt động', function () {
    $admin = staffMember(Role::Admin);
    staffMember(Role::Admin)->forceFill(['platform_revoked_at' => now(), 'platform_revoked_reason' => PlatformRevocation::Suspended])->save();

    expect(fn () => $this->staff->deactivate($admin, $admin))
        ->toThrow(LastActiveAdmin::class);
});

it('Quản trị mở khoá nhân viên và ghi Nhật ký bảo mật', function () {
    $admin = staffMember(Role::Admin);
    $seller = staffMember(Role::Sale);
    $this->staff->deactivate($admin, $seller);

    $this->staff->reactivate($admin, $seller);

    expect($seller->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffReactivated)->sole())
        ->user_id->toBe($seller->id)
        ->actor_id->toBe($admin->id);
});

it('chỉ Quản trị mở khoá được nhân viên', function (Role $role) {
    $admin = staffMember(Role::Admin);
    $seller = staffMember(Role::Sale);
    $this->staff->deactivate($admin, $seller);

    expect(fn () => $this->staff->reactivate(staffMember($role), $seller))
        ->toThrow(MissingRole::class);

    expect($seller->fresh()->isDeactivated())->toBeTrue();
})->with([
    'Nhập kho' => Role::Importer,
    'Bán hàng' => Role::Sale,
]);
