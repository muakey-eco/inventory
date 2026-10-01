<?php

namespace App\Inventory\SignIn;

use App\Inventory\Access\Role;

/**
 * Danh tính một nhân viên như Authentik vừa xác nhận trong id_token.
 */
final readonly class AuthentikIdentity
{
    /**
     * Mỗi Vai trò ứng với một group trên Authentik (ADR 0008). Group khác bị bỏ qua.
     */
    public const GROUPS = [
        'kho-quan-tri' => Role::Owner,
        'kho-nhap-kho' => Role::NhapKho,
        'kho-ban-hang' => Role::BanHang,
    ];

    /**
     * @param  string  $uuid  `sub` của id_token, tức `uuid` của người dùng bên Authentik
     * @param  list<string>  $groups  claim `kho_groups`
     * @param  list<string>  $amr  cách Authentik đã xác thực người dùng, ví dụ `pwd`, `mfa`
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $email,
        public array $groups,
        public array $amr,
    ) {}

    /**
     * @return list<Role>
     */
    public function roles(): array
    {
        return Role::inOrder(array_map(
            fn (string $group): Role => self::GROUPS[$group],
            array_filter($this->groups, fn (string $group): bool => isset(self::GROUPS[$group])),
        ));
    }

    /**
     * Authentik bỏ `mfa` khỏi `amr` cả khi stage MFA được bỏ qua nhờ cookie, nên đây chỉ là
     * tín hiệu để ghi cảnh báo, không phải căn cứ để chặn.
     */
    public function hasMfa(): bool
    {
        return in_array('mfa', $this->amr, true);
    }
}
