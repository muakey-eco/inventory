<?php

use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Models\SecurityLogEntry;
use App\Models\User;

function lockedOutAdmin(string $email = 'chu@shop.test'): User
{
    return User::factory()->create([
        'email' => $email,
        'deactivated_at' => now(),
        'roles' => [Role::Admin],
    ]);
}

it('mở khoá Quản trị từ server và ghi Nhật ký bảo mật', function () {
    $admin = lockedOutAdmin();

    $this->artisan('staff:recover-admin', ['email' => 'chu@shop.test', '--unlock' => true])
        ->assertSuccessful();

    expect($admin->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::sole())
        ->event->toBe(SecurityEvent::StaffReactivated)
        ->user_id->toBe($admin->id)
        ->actor_id->toBeNull()
        ->details->toBe(['via' => 'artisan']);
});

it('không còn tuỳ chọn reset 2FA', function () {
    lockedOutAdmin();

    expect(fn () => $this->artisan('staff:recover-admin', ['email' => 'chu@shop.test', '--reset-2fa' => true]))
        ->toThrow(InvalidArgumentException::class);
});

it('từ chối khôi phục cho nhân viên không mang Vai trò Quản trị', function () {
    $seller = User::factory()->create([
        'email' => 'binh@shop.test',
        'deactivated_at' => now(),
        'roles' => [Role::Sale],
    ]);

    $this->artisan('staff:recover-admin', ['email' => 'binh@shop.test', '--unlock' => true])
        ->assertFailed();

    expect($seller->fresh()->isDeactivated())->toBeTrue()
        ->and(SecurityLogEntry::count())->toBe(0);
});

it('không đoán khi nhiều nhân viên cùng email', function () {
    $first = lockedOutAdmin();
    $second = lockedOutAdmin();

    $this->artisan('staff:recover-admin', ['email' => 'chu@shop.test', '--unlock' => true])
        ->expectsOutputToContain('Có nhiều nhân viên cùng email này.')
        ->assertFailed();

    expect($first->fresh()->isDeactivated())->toBeTrue()
        ->and($second->fresh()->isDeactivated())->toBeTrue()
        ->and(SecurityLogEntry::count())->toBe(0);
});

it('báo lỗi khi email không tồn tại hoặc không chọn thao tác nào', function (array $arguments) {
    lockedOutAdmin();

    $this->artisan('staff:recover-admin', $arguments)->assertFailed();

    expect(SecurityLogEntry::count())->toBe(0);
})->with([
    'email không tồn tại' => [['email' => 'la@shop.test', '--unlock' => true]],
    'không chọn thao tác' => [['email' => 'chu@shop.test']],
]);
