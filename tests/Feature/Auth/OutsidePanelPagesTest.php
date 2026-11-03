<?php

use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;

/*
 * Trang ngoài panel (trang chủ, đăng nhập không thành) đứng trong layout simple của Filament,
 * nên mang đủ Khung Muakey như trang đăng nhập của panel: CSS của Theme, logo lockup, font Inter
 * và Màu nhấn của app.
 */

dataset('trang ngoài panel', [
    'trang chủ' => fn () => test()->get('/')->assertOk(),
    'đăng nhập không thành' => fn () => test()->get('/admin/dang-nhap-loi?reason=no-access')->assertOk(),
]);

it('đứng trong Khung Muakey', function (TestResponse $response) {
    $response
        ->assertSee('fi-simple-layout', escape: false)
        ->assertSee('muakey-lockup', escape: false)
        ->assertSee('Kho hàng số')
        ->assertSee("--font-family: 'Inter Variable'", escape: false)
        ->assertDontSee('--accent', escape: false);
})->with('trang ngoài panel');

it('trang chủ dẫn vào kho', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(Filament::getUrl())
        ->assertDontSee('laravel.com');
});

it('mỗi trang có tiêu đề riêng trên tab', function () {
    expect($this->get('/admin/dang-nhap-loi?reason=no-access')->getContent())
        ->toMatch('/<title>\s*Không vào được kho\s*-\s*Kho hàng số\s*<\/title>/u');
});

it('trang đăng nhập không thành nói rõ lý do và cho thử lại', function (string $reason, string $message) {
    $this->get('/admin/dang-nhap-loi?reason='.$reason)
        ->assertOk()
        ->assertSee($message)
        ->assertSee('/admin/auth/login?returnTo=%2Fadmin', escape: false);
})->with([
    'không có quyền' => ['no-access', 'Bạn chưa được cấp quyền vào kho, hoặc tài khoản đang bị đình chỉ trên platform.'],
    'platform không trả lời' => ['unavailable', 'Kho không hỏi được platform để kiểm quyền.'],
    'lượt đăng nhập hỏng' => ['login-failed', 'Lượt đăng nhập này hết hạn hoặc bị từ chối giữa chừng.'],
    'lý do lạ' => ['la-lam', 'Lượt đăng nhập này hết hạn hoặc bị từ chối giữa chừng.'],
]);
