<?php

use App\Inventory\Access\MissingRole;
use App\Inventory\Access\Role;
use App\Inventory\Access\RoleGate;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Staff\LastActiveOwner;
use App\Inventory\Staff\StaffManager;
use App\Models\SecurityLogEntry;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->staff = app(StaffManager::class);
});

it('Quản trị Khoá nhân viên và ghi Nhật ký bảo mật', function () {
    $admin = staffMember(Role::Owner);
    $seller = staffMember(Role::BanHang);

    $this->staff->deactivate($admin, $seller);

    expect($seller->fresh()->isDeactivated())->toBeTrue()
        ->and(app(RoleGate::class)->allows($seller->fresh(), Role::BanHang))->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffDeactivated)->sole())
        ->user_id->toBe($seller->id)
        ->actor_id->toBe($admin->id);
});

it('chỉ Quản trị Khoá được nhân viên', function (Role $role) {
    $seller = staffMember(Role::BanHang);

    expect(fn () => $this->staff->deactivate(staffMember($role), $seller))
        ->toThrow(MissingRole::class);

    expect($seller->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffDeactivated)->exists())->toBeFalse();
})->with([
    'Nhập kho' => Role::NhapKho,
    'Bán hàng' => Role::BanHang,
]);

it('Khoá nhân viên cắt ngay phiên đang mở', function () {
    $admin = staffMember(Role::Owner);
    $seller = staffMember(Role::BanHang);
    $panelUrl = Filament::getPanel('admin')->getUrl();

    // Đăng nhập qua session thật để request sau nạp lại nhân viên từ DB như trên production.
    Filament::auth()->login($seller);
    $this->get($panelUrl)->assertOk();

    $this->staff->deactivate($admin, $seller);
    $this->app['auth']->forgetGuards();

    $this->get($panelUrl)->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
    $this->assertGuest();
});

it('chặn Khoá Quản trị đang hoạt động cuối cùng', function () {
    $admin = staffMember(Role::Owner);

    expect(fn () => $this->staff->deactivate($admin, $admin))
        ->toThrow(LastActiveOwner::class);

    expect($admin->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffDeactivated)->exists())->toBeFalse();
});

it('Quản trị đã bị khoá không tính là Quản trị đang hoạt động', function () {
    $owner = staffMember(Role::Owner);
    $other = staffMember(Role::Owner);
    $this->staff->deactivate($owner, $other);

    expect(fn () => $this->staff->deactivate($owner, $owner))
        ->toThrow(LastActiveOwner::class);
});

it('Quản trị mở khoá nhân viên và ghi Nhật ký bảo mật', function () {
    $admin = staffMember(Role::Owner);
    $seller = staffMember(Role::BanHang);
    $this->staff->deactivate($admin, $seller);

    $this->staff->reactivate($admin, $seller);

    expect($seller->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::where('event', SecurityEvent::StaffReactivated)->sole())
        ->user_id->toBe($seller->id)
        ->actor_id->toBe($admin->id);
});

it('chỉ Quản trị mở khoá được nhân viên', function (Role $role) {
    $admin = staffMember(Role::Owner);
    $seller = staffMember(Role::BanHang);
    $this->staff->deactivate($admin, $seller);

    expect(fn () => $this->staff->reactivate(staffMember($role), $seller))
        ->toThrow(MissingRole::class);

    expect($seller->fresh()->isDeactivated())->toBeTrue();
})->with([
    'Nhập kho' => Role::NhapKho,
    'Bán hàng' => Role::BanHang,
]);
