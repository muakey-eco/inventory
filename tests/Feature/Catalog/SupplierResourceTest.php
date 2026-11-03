<?php

use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Inventory\Access\Role;
use App\Inventory\Catalog\SupplierDirectory;
use App\Models\Supplier;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('Quản trị và Nhập kho vào được trang Nhà cung cấp, Bán hàng không thấy', function (Role $role, int $status) {
    $this->actingAs(staffMember($role))
        ->get(SupplierResource::getUrl('index'))
        ->assertStatus($status);
})->with([
    'Quản trị' => [Role::Admin, 200],
    'Nhập kho' => [Role::Importer, 200],
    'Bán hàng' => [Role::Sale, 403],
]);

it('Nhập kho tạo, sửa và xoá Nhà cung cấp từ panel', function () {
    $this->actingAs(staffMember(Role::Importer));

    Livewire::test(ManageSuppliers::class)
        ->callAction(CreateAction::class, data: ['name' => 'Kinguin', 'note' => 'Telegram @kinguin'])
        ->assertHasNoFormErrors();

    $supplier = Supplier::sole();

    Livewire::test(ManageSuppliers::class)
        ->callAction(TestAction::make('edit')->table($supplier), data: ['name' => 'Kinguin Business', 'note' => null])
        ->assertHasNoFormErrors();

    expect($supplier->fresh())->name->toBe('Kinguin Business')->note->toBeNull();

    Livewire::test(ManageSuppliers::class)
        ->callAction(TestAction::make('delete')->table($supplier))
        ->assertNotified('Đã xoá Nhà cung cấp.');

    expect(Supplier::count())->toBe(0);
});

it('panel báo lỗi khi tạo Nhà cung cấp trùng tên', function () {
    $this->actingAs($stocker = staffMember(Role::Importer));
    app(SupplierDirectory::class)->create($stocker, 'Kinguin');

    Livewire::test(ManageSuppliers::class)
        ->callAction(CreateAction::class, data: ['name' => 'kinguin'])
        ->assertNotified('Nhà cung cấp "kinguin" đã có.');

    expect(Supplier::count())->toBe(1);
});
