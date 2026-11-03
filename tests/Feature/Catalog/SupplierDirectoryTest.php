<?php

use App\Inventory\Access\MissingPermission;
use App\Inventory\Catalog\InvalidSupplier;
use App\Inventory\Catalog\SupplierDirectory;
use App\Models\Supplier;

beforeEach(function () {
    $this->suppliers = app(SupplierDirectory::class);
});

it('Quản trị và Nhập kho tạo được Nhà cung cấp', function (string $role) {
    $supplier = $this->suppliers->create(staffInRole($role), '  Kinguin  ', 'Liên hệ qua Telegram @kinguin');

    expect($supplier->fresh())
        ->name->toBe('Kinguin')
        ->note->toBe('Liên hệ qua Telegram @kinguin');
})->with([
    'Quản trị' => 'admin',
    'Nhập kho' => 'importer',
]);

it('Bán hàng không tạo được Nhà cung cấp', function () {
    expect(fn () => $this->suppliers->create(staffInRole('sale'), 'Kinguin'))
        ->toThrow(MissingPermission::class);

    expect(Supplier::count())->toBe(0);
});

it('Quản trị và Nhập kho sửa và xoá được Nhà cung cấp', function (string $role) {
    $actor = staffInRole($role);
    $supplier = $this->suppliers->create($actor, 'Kinguin');

    $this->suppliers->update($actor, $supplier, 'Kinguin Business', null);

    expect($supplier->fresh())
        ->name->toBe('Kinguin Business')
        ->note->toBeNull();

    $this->suppliers->delete($actor, $supplier);

    expect(Supplier::find($supplier->id))->toBeNull();
})->with([
    'Quản trị' => 'admin',
    'Nhập kho' => 'importer',
]);

it('Bán hàng không sửa, không xoá được Nhà cung cấp', function () {
    $supplier = $this->suppliers->create(staffInRole('importer'), 'Kinguin');
    $seller = staffInRole('sale');

    expect(fn () => $this->suppliers->update($seller, $supplier, 'Đổi tên', null))->toThrow(MissingPermission::class)
        ->and(fn () => $this->suppliers->delete($seller, $supplier))->toThrow(MissingPermission::class);

    expect($supplier->fresh()->name)->toBe('Kinguin');
});

it('tên Nhà cung cấp không được trống và không trùng, không phân biệt hoa thường', function () {
    $actor = staffInRole('importer');
    $kinguin = $this->suppliers->create($actor, 'Kinguin');
    $g2a = $this->suppliers->create($actor, 'G2A');

    expect(fn () => $this->suppliers->create($actor, '   '))
        ->toThrow(InvalidSupplier::class, 'Tên Nhà cung cấp không được để trống.')
        ->and(fn () => $this->suppliers->create($actor, ' kinguin '))
        ->toThrow(InvalidSupplier::class, 'Nhà cung cấp "kinguin" đã có.')
        ->and(fn () => $this->suppliers->update($actor, $g2a, 'KINGUIN', null))
        ->toThrow(InvalidSupplier::class);

    $this->suppliers->update($actor, $kinguin, 'KINGUIN', 'Đổi cách viết tên của chính nó');

    expect(Supplier::orderBy('id')->pluck('name')->all())->toBe(['KINGUIN', 'G2A']);
});
