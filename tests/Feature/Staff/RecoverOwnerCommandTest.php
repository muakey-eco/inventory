<?php

use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Models\SecurityLogEntry;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function lockedOutOwner(string $email = 'chu@shop.test'): User
{
    return tap(User::factory()->create([
        'email' => $email,
        'deactivated_at' => now(),
    ]))->assignRole(Role::Owner);
}

it('mở khoá Quản trị từ server và ghi Nhật ký bảo mật', function () {
    $admin = lockedOutOwner();

    $this->artisan('staff:recover-owner', ['email' => 'chu@shop.test', '--unlock' => true])
        ->assertSuccessful();

    expect($admin->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::sole())
        ->event->toBe(SecurityEvent::StaffReactivated)
        ->user_id->toBe($admin->id)
        ->actor_id->toBeNull()
        ->details->toBe(['via' => 'artisan']);
});

it('không còn tuỳ chọn reset 2FA', function () {
    lockedOutOwner();

    expect(fn () => $this->artisan('staff:recover-owner', ['email' => 'chu@shop.test', '--reset-2fa' => true]))
        ->toThrow(InvalidArgumentException::class);
});

it('từ chối khôi phục cho nhân viên không mang Vai trò Quản trị', function () {
    $seller = tap(User::factory()->create([
        'email' => 'binh@shop.test',
        'deactivated_at' => now(),
    ]))->assignRole(Role::BanHang);

    $this->artisan('staff:recover-owner', ['email' => 'binh@shop.test', '--unlock' => true])
        ->assertFailed();

    expect($seller->fresh()->isDeactivated())->toBeTrue()
        ->and(SecurityLogEntry::count())->toBe(0);
});

it('không đoán khi nhiều nhân viên cùng email', function () {
    $first = lockedOutOwner();
    $second = lockedOutOwner();

    $this->artisan('staff:recover-owner', ['email' => 'chu@shop.test', '--unlock' => true])
        ->expectsOutputToContain('Có nhiều nhân viên cùng email này.')
        ->assertFailed();

    expect($first->fresh()->isDeactivated())->toBeTrue()
        ->and($second->fresh()->isDeactivated())->toBeTrue()
        ->and(SecurityLogEntry::count())->toBe(0);
});

it('báo lỗi khi email không tồn tại hoặc không chọn thao tác nào', function (array $arguments) {
    lockedOutOwner();

    $this->artisan('staff:recover-owner', $arguments)->assertFailed();

    expect(SecurityLogEntry::count())->toBe(0);
})->with([
    'email không tồn tại' => [['email' => 'la@shop.test', '--unlock' => true]],
    'không chọn thao tác' => [['email' => 'chu@shop.test']],
]);
