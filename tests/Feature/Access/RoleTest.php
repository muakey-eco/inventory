<?php

use App\Inventory\Access\MissingRole;
use App\Inventory\Access\Role;
use App\Inventory\Access\RoleGate;

it('ba Vai trò mang nhãn tiếng Việt', function () {
    expect(Role::Admin->label())->toBe('Quản trị')
        ->and(Role::Importer->label())->toBe('Nhập kho')
        ->and(Role::Sale->label())->toBe('Bán hàng');
});

it('Vai trò lấy từ Permission platform, bỏ qua Permission không phải Vai trò', function () {
    expect(Role::fromPermissions(['sale', 'access', 'reports.view', 'admin']))->toBe([Role::Admin, Role::Sale]);
});

it('một nhân viên mang được nhiều Vai trò', function () {
    $user = staffMember(Role::Importer, Role::Sale);

    $gate = app(RoleGate::class);

    expect($gate->allows($user, Role::Importer))->toBeTrue()
        ->and($gate->allows($user, Role::Sale))->toBeTrue()
        ->and($gate->allows($user, Role::Admin))->toBeFalse();
});

it('Quản trị luôn qua mọi kiểm tra Vai trò', function () {
    $admin = staffMember(Role::Admin);

    $gate = app(RoleGate::class);

    expect($gate->allows($admin, Role::Importer))->toBeTrue()
        ->and($gate->allows($admin, Role::Sale))->toBeTrue();
});

it('nhân viên không có Vai trò được yêu cầu bị từ chối bằng lỗi thiếu quyền', function () {
    $seller = staffMember(Role::Sale);

    app(RoleGate::class)->authorize($seller, Role::Importer);
})->throws(MissingRole::class);

it('cho qua khi nhân viên có một trong các Vai trò được yêu cầu', function () {
    $stocker = staffMember(Role::Importer);

    expect(fn () => app(RoleGate::class)->authorize($stocker, Role::Importer, Role::Sale))
        ->not->toThrow(MissingRole::class);
});
