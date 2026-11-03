<?php

namespace App\Http\Middleware;

use App\Inventory\Access\PermissionGate;
use App\Inventory\Staff\StaffMirror;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đối chiếu Permission platform vừa trả cho nhân viên đang đăng nhập với bản chụp Quyền của kho
 * (ADR 0011, ADR 0012): khác thì cập nhật bản chụp và ghi Nhật ký bảo mật. Bản chụp là thứ trang
 * Nhân viên hiện và PermissionGate dùng ngoài request của chính nhân viên đó.
 */
class SyncPermissionSnapshot
{
    public function __construct(private PermissionGate $gate, private StaffMirror $mirror) {}

    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user();
        $permissions = $staff instanceof User ? $this->gate->platformPermissionsOf($staff) : null;

        if ($staff instanceof User && $permissions !== null) {
            $this->mirror->syncPermissions($staff, $permissions);
        }

        return $next($request);
    }
}
