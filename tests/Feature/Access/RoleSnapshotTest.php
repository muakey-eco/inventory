<?php

use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Models\SecurityLogEntry;

/*
 * Kho giữ Vai trò lần cuối kho thấy của mỗi nhân viên (ADR 0011): request vào panel thấy Permission
 * khác bản chụp thì cập nhật bản chụp và ghi Nhật ký bảo mật.
 */

it('request thấy Vai trò đổi thì cập nhật bản chụp và ghi nhật ký', function () {
    $staff = staffMember(Role::Sale);

    $this->actingAsPlatformUser($staff, permissions: ['access', 'importer', 'sale'])
        ->get('/admin')
        ->assertOk();

    $entry = SecurityLogEntry::sole();

    expect($staff->fresh()->roles->all())->toBe([Role::Importer, Role::Sale])
        ->and($entry->event)->toBe(SecurityEvent::RolesChanged)
        ->and($entry->user_id)->toBe($staff->id)
        ->and($entry->details)->toBe(['to' => ['importer', 'sale'], 'from' => ['sale']]);
});

it('Vai trò không đổi thì không ghi gì', function () {
    $staff = staffMember(Role::Importer, Role::Sale);

    $this->actingAsPlatformUser($staff, permissions: ['access', 'sale', 'importer'])
        ->get('/admin')
        ->assertOk();

    expect(SecurityLogEntry::count())->toBe(0);
});

it('thu Vai trò trên platform có hiệu lực ở request kế tiếp', function () {
    $admin = staffMember(Role::Admin);
    $this->actingAsPlatformUser($admin, permissions: ['access', 'admin'])
        ->get('/admin/nhan-vien')
        ->assertOk();

    $this->actingAsPlatformUser($admin, permissions: ['access', 'sale'])
        ->get('/admin/nhan-vien')
        ->assertForbidden();

    expect($admin->fresh()->roles->all())->toBe([Role::Sale]);
});
