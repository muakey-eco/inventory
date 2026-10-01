<?php

namespace App\Inventory\Staff;

/**
 * Một người dùng như API Authentik `GET /api/v3/core/users/` trả về, chỉ phần đối soát cần.
 */
final readonly class AuthentikUser
{
    /**
     * @param  list<string>  $groups  tên mọi group của người dùng, kể cả group không phải `kho-*`
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $email,
        public bool $isActive,
        public array $groups,
    ) {}
}
