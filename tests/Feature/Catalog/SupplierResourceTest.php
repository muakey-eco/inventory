<?php

use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Inventory\Catalog\SupplierDirectory;
use App\Models\Supplier;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('Quản trị và Nhập kho vào được trang Nhà cung cấp, Bán hàng không thấy', function (string $role, int $status) {
    $this->actingAs(staffInRole($role))
        ->get(SupplierResource::getUrl('index'))
        ->assertStatus($status);
})->with([
    'Quản trị' => ['admin', 200],
    'Nhập kho' => ['importer', 200],
    'Bán hàng' => ['sale', 403],
]);

it('Nhập kho tạo, sửa và xoá Nhà cung cấp từ panel', function () {
    $this->actingAs(staffInRole('importer'));

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
    $this->actingAs($stocker = staffInRole('importer'));
    app(SupplierDirectory::class)->create($stocker, 'Kinguin');

    Livewire::test(ManageSuppliers::class)
        ->callAction(CreateAction::class, data: ['name' => 'kinguin'])
        ->assertNotified('Nhà cung cấp "kinguin" đã có.');

    expect(Supplier::count())->toBe(1);
});
