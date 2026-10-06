<?php

use App\Inventory\Access\Role;
use App\Inventory\Staff\AuthentikRevocation;
use App\Models\User;
use Database\Seeders\DevStaffSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

/**
 * Route của panel đăng ký lúc boot theo APP_ENV, nên muốn thử môi trường khác phải dựng lại
 * app. App mới mở kết nối DB mới, ngoài transaction của RefreshDatabase: mở lại cho nó.
 */
function bootAppIn(string $environment): void
{
    putenv("APP_ENV={$environment}");
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $environment;

    test()->refreshApplication();
    test()->beginDatabaseTransaction();
    app(RoleSeeder::class)->run();
}

afterEach(function () {
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
});

it('không có route đăng nhập dev ngoài local', function (string $environment) {
    bootAppIn($environment);

    expect(app()->environment())->toBe($environment)
        ->and(Route::has('filament.admin.auth.dev'))->toBeFalse()
        ->and(Route::has('filament.admin.auth.dev.store'))->toBeFalse();

    $this->get(Filament::getUrl().'/auth/dev')->assertNotFound();
})->with(['production', 'testing']);

it('ở local chọn một nhân viên rồi vào thẳng panel', function () {
    bootAppIn('local');
    $seller = tap(User::factory()->create(['name' => 'Bình bán hàng']))->assignRole(Role::BanHang);

    $this->get(route('filament.admin.auth.dev'))
        ->assertOk()
        ->assertSee('fi-simple-layout', escape: false)
        ->assertSee('Bình bán hàng');

    // Ngoài APP_ENV=testing thì CSRF có hiệu lực thật.
    $this->withSession(['_token' => 'csrf'])
        ->post(route('filament.admin.auth.dev.store', $seller), ['_token' => 'csrf'])
        ->assertRedirect(Filament::getUrl());

    expect(Filament::auth()->id())->toBe($seller->id);
});

it('đăng nhập dev cũng không cho nhân viên đang bị khoá vào', function () {
    bootAppIn('local');
    $seller = tap(User::factory()->create(['deactivated_at' => now()]))->assignRole(Role::BanHang);

    $this->withSession(['_token' => 'csrf'])
        ->post(route('filament.admin.auth.dev.store', $seller), ['_token' => 'csrf'])
        ->assertForbidden();

    expect(Filament::auth()->check())->toBeFalse();
});

it('seeder tạo sẵn một nhân viên mỗi Vai trò để đăng nhập dev, chạy lại vẫn an toàn', function () {
    app(RoleSeeder::class)->run();
    app(DevStaffSeeder::class)->run();
    app(DevStaffSeeder::class)->run();

    expect(User::count())->toBe(3)
        ->and(collect(Role::cases())->every(fn (Role $role): bool => User::role($role->value)->count() === 1))->toBeTrue();
});

it('chạy lại seeder trả quyền cho nhân viên dev mà đối soát với Authentik dev đã thu', function () {
    // uuid giả không có trên Authentik của profile `sso`, nên đối soát thu quyền cả ba người.
    app(RoleSeeder::class)->run();
    app(DevStaffSeeder::class)->run();
    User::query()->update(['authentik_revoked_at' => now(), 'authentik_revoked_reason' => AuthentikRevocation::NotFound]);

    app(DevStaffSeeder::class)->run();

    expect(User::whereNotNull('authentik_revoked_at')->orWhereNotNull('authentik_revoked_reason')->count())->toBe(0);
});
