<?php

use App\Filament\Resources\SecurityLogEntries\SecurityLogEntryResource;
use App\Inventory\Security\AppendOnlyViolation;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Models\SecurityLogEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('ghi đúng thời điểm tuyệt đối của sự kiện', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 09:00:00', 'Asia/Ho_Chi_Minh'));

    $entry = app(SecurityLog::class)->record(SecurityEvent::LoginSucceeded, email: 'la@shop.test');

    expect($entry->fresh()->occurred_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-09-15 02:00:00');
});

it('chỉ Quản trị xem được Nhật ký bảo mật trong Filament', function (string $role, int $status) {
    app(SecurityLog::class)->record(SecurityEvent::LoginSucceeded, email: 'la@shop.test');

    $this->actingAs(staffInRole($role))
        ->get(SecurityLogEntryResource::getUrl('index'))
        ->assertStatus($status);
})->with([
    'Quản trị' => ['admin', 200],
    'Nhập kho' => ['importer', 403],
    'Bán hàng' => ['sale', 403],
]);

it('Quản trị thấy các dòng nhật ký trên trang danh sách', function () {
    app(SecurityLog::class)->record(SecurityEvent::PlatformAccessRevoked, email: 'la@shop.test');

    $this->actingAs(staffInRole('admin'))
        ->get(SecurityLogEntryResource::getUrl('index'))
        ->assertSee('Mất quyền theo platform')
        ->assertSee('la@shop.test');
});

it('không ai tạo, sửa hay xoá được dòng Nhật ký bảo mật qua Filament, kể cả Quản trị', function () {
    $admin = staffInRole('admin');
    $entry = app(SecurityLog::class)->record(SecurityEvent::LoginSucceeded, $admin);

    expect($admin->can('create', SecurityLogEntry::class))->toBeFalse()
        ->and($admin->can('update', $entry))->toBeFalse()
        ->and($admin->can('delete', $entry))->toBeFalse()
        ->and($admin->can('deleteAny', SecurityLogEntry::class))->toBeFalse();
});

it('ứng dụng không sửa được dòng Nhật ký bảo mật', function () {
    $entry = app(SecurityLog::class)->record(SecurityEvent::LoginSucceeded, email: 'la@shop.test');

    $entry->update(['email' => 'khac@shop.test']);
})->throws(AppendOnlyViolation::class);

it('ứng dụng không xoá được dòng Nhật ký bảo mật', function () {
    $entry = app(SecurityLog::class)->record(SecurityEvent::LoginSucceeded, email: 'la@shop.test');

    $entry->delete();
})->throws(AppendOnlyViolation::class);

it('PostgreSQL chặn sửa, xoá và truncate Nhật ký bảo mật kể cả khi đi vòng qua model', function (string $sql) {
    app(SecurityLog::class)->record(SecurityEvent::LoginSucceeded, email: 'la@shop.test');

    // Savepoint riêng để lỗi không làm hỏng transaction của RefreshDatabase.
    expect(fn () => DB::transaction(fn () => DB::statement($sql)))
        ->toThrow(QueryException::class, 'chỉ-ghi-thêm');
})->with([
    'update' => "UPDATE security_log_entries SET email = 'khac@shop.test'",
    'delete' => 'DELETE FROM security_log_entries',
    'truncate' => 'TRUNCATE security_log_entries',
]);
