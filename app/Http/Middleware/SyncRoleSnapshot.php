<?php

namespace App\Http\Middleware;

use App\Inventory\Access\RoleGate;
use App\Inventory\Staff\StaffMirror;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đối chiếu Permission platform vừa trả cho nhân viên đang đăng nhập với bản chụp Vai trò của kho
 * (ADR 0011): khác thì cập nhật bản chụp và ghi Nhật ký bảo mật. Bản chụp là thứ trang Nhân viên
 * hiện và RoleGate dùng ngoài request của chính nhân viên đó.
 */
class SyncRoleSnapshot
{
    public function __construct(private RoleGate $roles, private StaffMirror $mirror) {}

    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user();
        $roles = $staff instanceof User ? $this->roles->platformRolesOf($staff) : null;

        if ($staff instanceof User && $roles !== null) {
            $this->mirror->syncRoles($staff, $roles);
        }

        return $next($request);
    }
}
