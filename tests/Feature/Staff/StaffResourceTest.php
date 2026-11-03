<?php

use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Resources\Staff\StaffResource;
use App\Inventory\Access\Permission;
use App\Inventory\Security\SecurityEvent;
use App\Models\SecurityLogEntry;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('chỉ Quản trị vào được trang Nhân viên', function (string $role, int $status) {
    $this->actingAs(staffInRole($role))
        ->get(StaffResource::getUrl('index'))
        ->assertStatus($status);
})->with([
    'Quản trị' => ['admin', 200],
    'Nhập kho' => ['importer', 403],
    'Bán hàng' => ['sale', 403],
]);

it('không có thao tác tạo nhân viên, đổi Vai trò hay reset 2FA', function () {
    $this->actingAs(staffInRole('admin'));
    $seller = staffInRole('sale');

    Livewire::test(ManageStaff::class)
        ->assertActionDoesNotExist(CreateAction::class)
        ->assertActionDoesNotExist(TestAction::make('changeRoles')->table($seller))
        ->assertActionDoesNotExist(TestAction::make('resetTwoFactor')->table($seller));
});

it('Quyền hiện chỉ đọc trên danh sách, gom theo nhóm, theo lần cuối kho thấy', function () {
    $this->actingAs(staffInRole('admin'));
    $seller = staffMember(Permission::DefectReport, Permission::StockView, Permission::Dispatch, Permission::ReplacementApprove, Permission::LogView);

    Livewire::test(ManageStaff::class)
        ->assertTableColumnStateSet('permissions', [
            'Xem chung: Xem hàng và báo cáo tồn',
            'Xuất hàng: Xuất hàng, Báo lỗi và Đổi hàng, Duyệt Đổi hàng',
            'Quản trị kho: Xem nhật ký',
        ], $seller);
});

it('Quản trị Khoá rồi mở khoá nhân viên từ panel', function () {
    $this->actingAs(staffInRole('admin'));
    $seller = staffInRole('sale');

    Livewire::test(ManageStaff::class)
        ->callAction(TestAction::make('deactivate')->table($seller))
        ->assertActionHidden(TestAction::make('deactivate')->table($seller))
        ->callAction(TestAction::make('reactivate')->table($seller));

    expect($seller->fresh()->isDeactivated())->toBeFalse()
        ->and(SecurityLogEntry::pluck('event')->all())->toEqualCanonicalizing([
            SecurityEvent::StaffDeactivated,
            SecurityEvent::StaffReactivated,
        ]);
});

it('panel báo lỗi thay vì Khoá Quản trị đang hoạt động cuối cùng', function () {
    $this->actingAs($admin = staffInRole('admin'));

    Livewire::test(ManageStaff::class)
        ->callAction(TestAction::make('deactivate')->table($admin))
        ->assertNotified('Kho phải luôn còn ít nhất một người đang hoạt động có Quyền quản Nhân viên.');

    expect($admin->fresh()->isDeactivated())->toBeFalse();
});

it('không có thao tác xoá nhân viên, kể cả với Quản trị', function () {
    $this->actingAs($admin = staffInRole('admin'));
    $seller = staffInRole('sale');

    Livewire::test(ManageStaff::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table($seller))
        ->assertActionDoesNotExist(TestAction::make('delete')->table()->bulk());

    expect($admin->can('delete', $seller))->toBeFalse()
        ->and($admin->can('deleteAny', User::class))->toBeFalse()
        ->and($admin->can('forceDelete', $seller))->toBeFalse();
});
