<?php

use App\Inventory\Access\Permission;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
 * Kho tự kết thúc Phiên App sau 2 giờ không thao tác (ADR 0011), dù Phiên App của SDK sống 7
 * ngày: kho có nội dung mã, máy để mở là rủi ro thật. Request tự động như `wire:poll` không gia
 * hạn.
 */

beforeEach(function () {
    $staff = staffInRole('admin');
    $this->actingAsPlatformUser($staff, permissions: ['access', ...Permission::names($staff->permissions)]);
});

/**
 * Snapshot thật của trang Nhân viên, để gửi request Livewire update như trình duyệt gửi. Mở trang
 * là một lần thao tác.
 */
function staffPageSnapshot(): string
{
    preg_match('/wire:snapshot="([^"]+)"/', test()->get('/admin/nhan-vien')->assertOk()->getContent(), $match);

    return html_entity_decode($match[1]);
}

/**
 * Request Livewire update không cập nhật ô nào, chỉ một lời gọi; `poll` là metadata JS của
 * Livewire gắn khi `wire:poll` chạy.
 *
 * @param  array<string, string>  $metadata
 */
function livewireRefresh(string $snapshot, array $metadata): TestResponse
{
    // Đúng header fetch của Livewire: body JSON, có `X-Livewire`, `Accept` mặc định của trình duyệt
    // chứ không phải `application/json`.
    return test()->json('POST', Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['method' => '$refresh', 'params' => [], 'metadata' => $metadata]],
        ]],
    ], ['Accept' => '*/*', 'X-Livewire' => '1']);
}

it('không thao tác quá 2 giờ thì phải đăng nhập lại', function () {
    $this->get('/admin')->assertOk();

    $this->travel(2)->hours();

    $this->get('/admin/nhan-vien')->assertRedirect('/admin/auth/login?returnTo=%2Fadmin%2Fnhan-vien');
    $this->get('/admin')->assertRedirect('/admin/auth/login?returnTo=%2Fadmin');
});

it('còn thao tác thì Phiên App sống tiếp quá 2 giờ', function () {
    $this->get('/admin')->assertOk();

    $this->travel(110)->minutes();
    $this->get('/admin')->assertOk();
    $this->travel(110)->minutes();

    $this->get('/admin')->assertOk();
});

it('wire:poll không giữ phiên sống', function () {
    $snapshot = staffPageSnapshot();

    $this->travel(110)->minutes();
    livewireRefresh($snapshot, ['type' => 'poll'])->assertOk();
    $this->travel(10)->minutes();

    $this->get('/admin')->assertRedirect('/admin/auth/login?returnTo=%2Fadmin');
});

it('thao tác qua Livewire thì gia hạn', function () {
    $snapshot = staffPageSnapshot();

    $this->travel(110)->minutes();
    livewireRefresh($snapshot, [])->assertOk();
    $this->travel(10)->minutes();

    $this->get('/admin')->assertOk();
});

it('request Livewire sau hạn nhận 401 để panel hiện hộp thoại hết phiên', function () {
    $snapshot = staffPageSnapshot();

    $this->travel(2)->hours();

    livewireRefresh($snapshot, [])->assertUnauthorized();
});
