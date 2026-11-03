<?php

use App\Inventory\Access\Permission;
use App\Inventory\Security\SecurityEvent;
use App\Models\SecurityLogEntry;

/*
 * Kho giữ Quyền lần cuối kho thấy của mỗi nhân viên (ADR 0011, ADR 0012): request vào panel thấy
 * Permission khác bản chụp thì cập nhật bản chụp và ghi Nhật ký bảo mật.
 */

it('request thấy Quyền đổi thì cập nhật bản chụp và ghi Quyền được thêm, bị bớt', function () {
    $staff = staffMember(Permission::StockView, Permission::Dispatch);

    $this->actingAsPlatformUser($staff, permissions: ['access', 'stock.view', 'intake', 'cost.view'])
        ->get('/admin')
        ->assertOk();

    $entry = SecurityLogEntry::sole();

    expect($staff->fresh()->permissions->all())->toBe([Permission::StockView, Permission::CostView, Permission::Intake])
        ->and($entry->event)->toBe(SecurityEvent::PermissionsChanged)
        ->and($entry->event->label())->toBe('Quyền đổi theo platform')
        ->and($entry->user_id)->toBe($staff->id)
        ->and($entry->details)->toBe(['added' => ['cost.view', 'intake'], 'removed' => ['dispatch']]);
});

it('Quyền không đổi thì không ghi gì, kể cả khi platform trả theo thứ tự khác', function () {
    $staff = staffMember(Permission::StockView, Permission::Dispatch);

    $this->actingAsPlatformUser($staff, permissions: ['dispatch', 'access', 'stock.view'])
        ->get('/admin')
        ->assertOk();

    expect(SecurityLogEntry::count())->toBe(0);
});

it('thu Quyền trên platform có hiệu lực ở request kế tiếp', function () {
    $manager = staffMember(Permission::StaffManage);
    $this->actingAsPlatformUser($manager, permissions: ['access', 'staff.manage'])
        ->get('/admin/nhan-vien')
        ->assertOk();

    $this->actingAsPlatformUser($manager, permissions: ['access', 'dispatch'])
        ->get('/admin/nhan-vien')
        ->assertForbidden();

    expect($manager->fresh()->permissions->all())->toBe([Permission::Dispatch]);
});
