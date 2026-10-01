<?php

namespace App\Inventory\Staff;

use App\Inventory\SignIn\AuthentikIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Đối soát nhân viên với Authentik mỗi phút (ADR 0008): ai bị tắt, bị xoá hoặc không còn group
 * `kho-*` nào bên Authentik thì mất quyền vào kho, phiên đang mở bị chặn ở request kế tiếp; ai
 * được cấp lại thì có lại quyền. Chỉ đối soát nhân viên đã có trong kho. Không bao giờ đụng Khoá
 * nhân viên. Authentik lỗi thì giữ nguyên quyền hiện có thay vì khoá cả kho.
 */
class StaffSync
{
    private const VIA = 'authentik_sync';

    public function __construct(
        private AuthentikDirectory $directory,
        private StaffMirror $mirror,
        private StaffSyncHealth $health,
    ) {}

    /**
     * @return int số nhân viên đã đối soát
     *
     * @throws AuthentikUnavailable
     */
    public function run(): int
    {
        /** @var list<string> $uuids */
        $uuids = User::query()->pluck('authentik_uuid')->all();

        try {
            $found = $uuids === [] ? [] : $this->directory->find($uuids);
        } catch (AuthentikUnavailable $exception) {
            Log::warning('Đối soát nhân viên với Authentik lỗi, giữ nguyên quyền hiện có.', ['error' => $exception->getMessage()]);
            $this->health->failed($exception->getMessage());

            throw $exception;
        }

        foreach ($uuids as $uuid) {
            DB::transaction(fn () => $this->reconcile($uuid, $found[$uuid] ?? null));
        }

        $this->health->succeeded();

        return count($uuids);
    }

    private function reconcile(string $uuid, ?AuthentikUser $remote): void
    {
        // Đọc lại và khoá hàng: lời gọi API vừa rồi có thể chậm, trong lúc đó nhân viên có thể
        // đã đăng nhập hoặc bị Khoá nhân viên.
        $staff = User::query()->where('authentik_uuid', $uuid)->lockForUpdate()->sole();

        if ($remote === null) {
            $this->mirror->grantOrRevoke($staff, AuthentikRevocation::NotFound, self::VIA);

            return;
        }

        $roles = AuthentikIdentity::rolesFor($remote->groups);
        // Tên trống thì giữ tên cũ: đăng nhập cũng không bao giờ ghi tên trống.
        $this->mirror->sync($staff, $remote->name !== '' ? $remote->name : $staff->name, $remote->email, $roles, self::VIA);

        $revocation = match (true) {
            ! $remote->isActive => AuthentikRevocation::Inactive,
            $roles === [] => AuthentikRevocation::NoRole,
            default => null,
        };

        $this->mirror->grantOrRevoke($staff, $revocation, self::VIA);
    }
}
