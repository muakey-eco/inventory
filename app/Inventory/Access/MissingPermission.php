<?php

namespace App\Inventory\Access;

use App\Models\User;
use RuntimeException;

/**
 * Lỗi nghiệp vụ: nhân viên không có Quyền cần cho thao tác.
 */
class MissingPermission extends RuntimeException
{
    public function __construct(public readonly User $user, public readonly Permission $required)
    {
        parent::__construct("Thao tác cần Quyền: {$required->label()}.");
    }
}
