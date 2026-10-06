<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/*
 * Trang ngoài panel (trang chủ, bị từ chối, đã đăng xuất) đứng trong layout simple của Filament,
 * nên mang đủ Khung Muakey như trang đăng nhập của panel: CSS của Theme, logo lockup, font Inter
 * và Màu nhấn của app.
 */

dataset('trang ngoài panel', [
    'trang chủ' => fn () => test()->get('/')->assertOk(),
    'đã đăng xuất' => fn () => test()->get(route('filament.admin.auth.signed-out'))->assertOk(),
    'bị từ chối' => function () {
        config(['services.authentik.issuer' => 'https://auth-sap.shop.test/application/o/kho/']);
        Http::fake(['auth-sap.shop.test/*' => Http::response('Bad Gateway', 502)]);

        return test()->get(Filament::getLoginUrl())->assertForbidden();
    },
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
