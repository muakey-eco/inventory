<?php

namespace App\Inventory\Staff;

use App\Models\User;
use RuntimeException;

/**
 * Lỗi nghiệp vụ: thao tác sẽ khiến kho không còn ai đang hoạt động có Quyền quản Nhân viên, tức
 * không còn ai mở được Khoá nhân viên.
 */
class LastActiveStaffManager extends RuntimeException
{
    public function __construct(public readonly User $staff)
    {
        parent::__construct('Kho phải luôn còn ít nhất một người đang hoạt động có Quyền quản Nhân viên.');
    }
}
