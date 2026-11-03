<?php

namespace App\Inventory\Staff;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Muakey\Platform\Api\UserStatus;
use Muakey\Platform\Contracts\DirectorySink;
use Muakey\Platform\Directory\DirectoryUser;

/**
 * Bản sao danh bạ của kho (ADR 0011): SDK gọi `upsert` cho mỗi webhook `user.*` và mỗi user của
 * lượt quét bù `platform:directory-sync`. Kho chỉ cập nhật nhân viên đã biết, khớp theo id trên
 * platform: người chưa từng vào kho không có hàng nào, và danh bạ không bao giờ tạo nhân viên.
 * Snapshot tới lặp hoặc lệch thứ tự nên bản có `seq` không mới hơn bản đang giữ bị bỏ qua.
 *
 * Danh bạ không mang Permission, nên ở đây chỉ thấy nhân viên bị đình chỉ hay bị xoá (Mất quyền
 * theo platform); mất mỗi quyền vào kho thì guard chặn mà danh bạ không báo.
 */
class StaffDirectory implements DirectorySink
{
    public function __construct(private StaffMirror $mirror) {}

    public function upsert(DirectoryUser $user, int $seq): void
    {
        DB::transaction(function () use ($user, $seq): void {
            $staff = User::query()->where('platform_user_id', $user->id)->lockForUpdate()->first();

            if ($staff === null || ($staff->directory_seq !== null && $staff->directory_seq >= $seq)) {
                return;
            }

            $staff->fill(['name' => $user->name !== '' ? $user->name : $user->email, 'email' => $user->email]);
            $staff->forceFill(['directory_seq' => $seq])->save();

            $this->mirror->grantOrRevoke($staff, match ($user->status) {
                UserStatus::Active => null,
                UserStatus::Suspended => PlatformRevocation::Suspended,
                UserStatus::Deactivated => PlatformRevocation::Deactivated,
            });
        });
    }
}
