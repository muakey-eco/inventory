<?php

use App\Inventory\Access\Permission;

/*
 * `platform.yaml` là Manifest platform của kho (ADR 0011, ADR 0012). Guard của SDK đòi Permission
 * vào cửa `platform.entry_permission`, và PermissionGate đọc Quyền theo tên Permission: Quyền code
 * kiểm mà manifest không khai thì không ai có, Permission thiếu trong role `admin` thì chủ shop mất
 * việc đó.
 */

it('khai Permission vào cửa và mọi Quyền code kiểm, kèm mô tả', function () {
    $declared = platformManifest()['permissions'];

    expect(config('platform.entry_permission'))->toBe('access')
        ->and($declared)->toHaveKey('access');

    foreach (Permission::cases() as $permission) {
        expect($declared)->toHaveKey($permission->value)
            ->and($declared[$permission->value]['description'])->not->toBeEmpty();
    }
});

it('role admin cấp mọi Permission khai trong manifest', function () {
    $manifest = platformManifest();

    expect($manifest['roles']['admin']['permissions'])->toEqualCanonicalizing(array_keys($manifest['permissions']));
});

it('role importer và sale cấp đúng phạm vi của Vai trò Nhập kho và Bán hàng cũ', function () {
    $roles = platformManifest()['roles'];

    expect($roles['importer']['permissions'])->toEqualCanonicalizing(['access', 'stock.view', 'cost.view', 'intake', 'supplier.manage', 'supplier_claim'])
        ->and($roles['sale']['permissions'])->toEqualCanonicalizing(['access', 'stock.view', 'dispatch', 'defect_report']);
});

it('manifest không khai Permission nào mà kho không kiểm', function () {
    $known = ['access', ...array_map(fn (Permission $permission): string => $permission->value, Permission::cases())];

    expect(array_keys(platformManifest()['permissions']))->toEqualCanonicalizing($known);
});
