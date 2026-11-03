<?php

use App\Inventory\Staff\PlatformRevocation;
use Muakey\Platform\Facades\Platform;

/*
 * Nhân viên vào kho qua platform (ADR 0011): plugin Filament của SDK gác panel, guard `platform`
 * kiểm Permission vào cửa `access` và Trạng thái user ở mỗi request.
 */

it('chưa đăng nhập thì về trang đăng nhập của platform, rồi quay lại đúng trang đã mở', function () {
    Platform::fake();

    $this->get('/admin/nhan-vien?trang=2')
        ->assertRedirect('/admin/auth/login?returnTo='.rawurlencode('/admin/nhan-vien?trang=2'));
});

it('nhân viên có access và vài Quyền vào được kho', function () {
    $this->actingAsPlatformUser(staffMember(), permissions: ['access', 'stock.view', 'dispatch', 'defect_report'])
        ->get('/admin')
        ->assertOk();
});

it('thiếu access thì bị chặn, kể cả khi còn Quyền khác', function () {
    $this->actingAsPlatformUser(staffInRole('sale'), permissions: ['stock.view', 'dispatch', 'defect_report'])
        ->get('/admin')
        ->assertForbidden();
});

it('bị đình chỉ trên platform thì mất Phiên App ở request kế tiếp', function () {
    $staff = staffInRole('sale');
    $this->actingAsPlatformUser($staff, permissions: ['access', 'stock.view', 'dispatch', 'defect_report'])->get('/admin')->assertOk();

    $this->actingAsPlatformUser($staff, permissions: ['access', 'stock.view', 'dispatch', 'defect_report'], status: 'suspended')
        ->getJson('/admin')
        ->assertUnauthorized();
});

it('bị Khoá nhân viên thì bị chặn ở request kế tiếp dù platform vẫn cho vào', function () {
    $staff = staffInRole('sale');
    $this->actingAsPlatformUser($staff, permissions: ['access', 'stock.view', 'dispatch', 'defect_report'])->get('/admin')->assertOk();

    $staff->forceFill(['deactivated_at' => now()])->save();

    $this->get('/admin')->assertForbidden();
});

it('mất quyền theo platform thì bị chặn ở request kế tiếp', function () {
    $staff = staffInRole('sale');
    $this->actingAsPlatformUser($staff, permissions: ['access', 'stock.view', 'dispatch', 'defect_report'])->get('/admin')->assertOk();

    $staff->forceFill(['platform_revoked_at' => now(), 'platform_revoked_reason' => PlatformRevocation::Suspended])->save();

    $this->get('/admin')->assertForbidden();
});

it('đăng xuất đi qua platform để kết thúc cả Phiên SSO', function () {
    $this->actingAsPlatformUser(staffInRole('sale'), permissions: ['access', 'stock.view', 'dispatch', 'defect_report'])
        ->get('/admin')
        ->assertSee('action="'.url('/admin/auth/logout').'"', escape: false);
});

it('back-channel logout của platform đưa nhân viên về đăng nhập', function () {
    $this->actingAsPlatformUser(staffInRole('sale'), permissions: ['access', 'stock.view', 'dispatch', 'defect_report'], sid: 'sid-1')
        ->get('/admin')
        ->assertOk();

    Platform::endSession('sid-1');

    $this->get('/admin')->assertRedirect('/admin/auth/login?returnTo=%2Fadmin');
});
