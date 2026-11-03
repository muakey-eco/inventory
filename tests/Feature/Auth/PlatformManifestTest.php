<?php

use App\Inventory\Access\Role;

/*
 * `platform.yaml` là Manifest platform của kho (ADR 0011). Guard của SDK đòi Permission vào cửa
 * `platform.entry_permission`, và RoleGate đọc Vai trò theo tên Permission: Manifest lệch enum
 * `Role` là cả kho mất Vai trò đó.
 */

it('khai Permission vào cửa và đúng một Permission cho mỗi Vai trò, mỗi role cấp kèm access', function () {
    $yaml = (string) file_get_contents(base_path('platform.yaml'));
    $entry = (string) config('platform.entry_permission');

    expect($entry)->toBe('access')
        ->and($yaml)->toMatch('/^permissions:\n(?:  .*\n)*?  '.$entry.':/m');

    foreach (Role::cases() as $role) {
        expect($yaml)
            ->toMatch('/^permissions:\n(?:  .*\n)*?  '.$role->value.':/m')
            ->toMatch('/^roles:\n(?:  .*\n)*?  '.$role->value.":\n(?:    .*\n)*?    permissions: \\[{$entry}, {$role->value}\\]/m");
    }
});
