<?php

namespace App\Inventory\Staff;

use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Muakey\Platform\Contracts\PlatformUserResolver;
use Muakey\Platform\PlatformIdentity;

/**
 * Callback đăng nhập của SDK platform gọi ở đây sau khi platform đã kiểm nhân viên `active` và có
 * `access` (ADR 0011). Nhân viên khớp chỉ theo id trên platform, không bao giờ theo email: email
 * chỉ là bản sao và một email cũ có thể sang tay người khác. Chưa có thì tạo mới, với bản chụp
 * Vai trò rỗng: request đầu vào panel điền nó ({@see StaffMirror::syncRoles()}). Tên và email đồng
 * bộ lại mỗi lần. Khoá nhân viên là của kho nên đăng nhập lại không mở khoá; panel chặn họ.
 */
class StaffResolver implements PlatformUserResolver
{
    public function __construct(private SecurityLog $log, private StaffMirror $mirror) {}

    public function resolve(PlatformIdentity $identity): User
    {
        return DB::transaction(function () use ($identity): User {
            $profile = [
                'name' => $identity->name ?: ($identity->email ?? $identity->sub),
                'email' => $identity->email,
            ];

            $staff = User::query()->where('platform_user_id', $identity->sub)->lockForUpdate()->first();

            if ($staff === null) {
                $staff = User::create(['platform_user_id' => $identity->sub, ...$profile]);

                $this->log->record(SecurityEvent::StaffFirstSeen, $staff);
            } else {
                $staff->fill($profile)->save();

                // SDK chỉ gọi tới đây khi platform nói nhân viên `active`: tài khoản đã được mở
                // lại, không chờ webhook hay lượt quét danh bạ (App thử không nhận webhook).
                $this->mirror->grantOrRevoke($staff, null);
            }

            $this->log->record(SecurityEvent::LoginSucceeded, $staff);

            return $staff;
        });
    }
}
