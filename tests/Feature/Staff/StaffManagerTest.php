<?php

use App\Inventory\Access\MissingPermission;
use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Staff\LastActiveStaffManager;
use App\Inventory\Staff\PlatformRevocation;
use App\Inventory\Staff\StaffManager;
use App\Models\SecurityLogEntry;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->staff = app(StaffManager::class);
});

it('Quản trị Khoá nhân viên và ghi Nhật ký bảo mật', function () {
    $admin = staffInRole('admin');
    $seller = staffInRole('sale');

    $this->staff->deactivate($admin, $seller);

    expect($seller->fresh()->isDeactivated())->toBeTrue()
        ->and(app(PermissionGate::class)->allows($seller->fresh(), Permission::Dispatch))->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffDeactivated)->sole())
        ->user_id->toBe($seller->id)
        ->actor_id->toBe($admin->id);
});

it('chỉ Quản trị Khoá được nhân viên', function (string $role) {
    $seller = staffInRole('sale');

    expect(fn () => $this->staff->deactivate(staffInRole($role), $seller))
        ->toThrow(MissingPermission::class);

    expect($seller->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffDeactivated)->exists())->toBeFalse();
})->with([
    'Nhập kho' => 'importer',
    'Bán hàng' => 'sale',
]);

it('Khoá nhân viên cắt ngay phiên đang mở', function () {
    $admin = staffInRole('admin');
    $seller = staffInRole('sale');
    $panelUrl = Filament::getPanel('admin')->getUrl();

    $this->actingAs($seller)->get($panelUrl)->assertOk();

    $this->staff->deactivate($admin, $seller);

    // Platform vẫn cho vào; kho chặn vì Khoá nhân viên là của kho.
    $this->get($panelUrl)->assertForbidden();
});

it('chặn Khoá Quản trị đang hoạt động cuối cùng', function () {
    $admin = staffInRole('admin');

    expect(fn () => $this->staff->deactivate($admin, $admin))
        ->toThrow(LastActiveStaffManager::class);

    expect($admin->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffDeactivated)->exists())->toBeFalse();
});

it('người cuối cùng có Quyền quản Nhân viên không bị khoá, dù còn người khác mang mọi Quyền còn lại', function () {
    $manager = staffMember(Permission::StaffManage);
    staffMember(...array_values(array_filter(Permission::cases(), fn (Permission $permission): bool => $permission !== Permission::StaffManage)));

    expect(fn () => $this->staff->deactivate($manager, $manager))
        ->toThrow(LastActiveStaffManager::class);
});

it('khoá được người có Quyền quản Nhân viên khi còn người khác đang hoạt động có Quyền đó', function () {
    $manager = staffMember(Permission::StaffManage);
    staffMember(Permission::StaffManage);

    $this->staff->deactivate($manager, $manager);

    expect($manager->fresh()->isDeactivated())->toBeTrue();
});

it('Quản trị đã bị khoá không tính là Quản trị đang hoạt động', function () {
    $owner = staffInRole('admin');
    $other = staffInRole('admin');
    $this->staff->deactivate($owner, $other);

    expect(fn () => $this->staff->deactivate($owner, $owner))
        ->toThrow(LastActiveStaffManager::class);
});

it('Quản trị đang mất quyền theo platform không tính là Quản trị đang hoạt động', function () {
    $admin = staffInRole('admin');
    staffInRole('admin')->forceFill(['platform_revoked_at' => now(), 'platform_revoked_reason' => PlatformRevocation::Suspended])->save();

    expect(fn () => $this->staff->deactivate($admin, $admin))
        ->toThrow(LastActiveStaffManager::class);
});

it('Quản trị mở khoá nhân viên và ghi Nhật ký bảo mật', function () {
    $admin = staffInRole('admin');
    $seller = staffInRole('sale');
    $this->staff->deactivate($admin, $seller);

    $this->staff->reactivate($admin, $seller);

    expect($seller->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffReactivated)->sole())
        ->user_id->toBe($seller->id)
        ->actor_id->toBe($admin->id);
});

it('chỉ Quản trị mở khoá được nhân viên', function (string $role) {
    $admin = staffInRole('admin');
    $seller = staffInRole('sale');
    $this->staff->deactivate($admin, $seller);

    expect(fn () => $this->staff->reactivate(staffInRole($role), $seller))
        ->toThrow(MissingPermission::class);

    expect($seller->fresh()->isDeactivated())->toBeTrue();
})->with([
    'Nhập kho' => 'importer',
    'Bán hàng' => 'sale',
]);
