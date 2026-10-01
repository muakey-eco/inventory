<?php

namespace App\Inventory\SignIn;

use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Inventory\Staff\AuthentikRevocation;
use App\Inventory\Staff\StaffMirror;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Nhận một danh tính Authentik đã xác minh và quyết định ai được vào kho (ADR 0008). Nhân
 * viên khớp theo `sub`, chưa có thì tạo mới; mỗi lần đăng nhập đồng bộ tên, email, bản sao
 * Vai trò và quyền vào kho qua {@see StaffMirror}. Authentik làm chủ Vai trò nên ở đây không
 * chặn mất Quản trị cuối cùng; Khoá nhân viên thì là của kho, và đăng nhập lại ở Authentik
 * không mở khoá.
 */
class StaffSignIn
{
    private const VIA = 'authentik';

    public function __construct(private SecurityLog $log, private StaffMirror $mirror) {}

    /**
     * @throws SignInRefused
     */
    public function signIn(AuthentikIdentity $identity): User
    {
        $staff = User::firstWhere('authentik_uuid', $identity->uuid);

        if ($staff?->isDeactivated()) {
            throw new SignInRefused(Refusal::Deactivated, $staff);
        }

        $roles = $identity->roles();

        if ($staff === null && $roles === []) {
            throw new SignInRefused(Refusal::NoRole, email: $identity->email, details: ['authentik_uuid' => $identity->uuid]);
        }

        // Đồng bộ cả khi sắp từ chối vì hết Vai trò: bản sao trong kho phải theo Authentik.
        $staff = DB::transaction(function () use ($staff, $identity, $roles): User {
            if ($staff === null) {
                return $this->firstSeen($identity, $roles);
            }

            $this->mirror->sync($staff, $identity->name, $identity->email, $roles, self::VIA);

            // Authentik vừa xác thực được họ nên người dùng còn active; còn quyền hay không chỉ
            // tuỳ group. Phiên kho đang mở ở máy khác cũng theo đó mà mất hoặc có lại quyền.
            $this->mirror->grantOrRevoke($staff, $roles === [] ? AuthentikRevocation::NoRole : null, self::VIA);

            return $staff;
        });

        if ($roles === []) {
            throw new SignInRefused(Refusal::NoRole, $staff);
        }

        if (! $identity->hasMfa()) {
            $this->log->record(SecurityEvent::LoginWithoutMfa, $staff, details: ['amr' => $identity->amr]);
        }

        return $staff;
    }

    /**
     * @param  list<Role>  $roles
     */
    private function firstSeen(AuthentikIdentity $identity, array $roles): User
    {
        $staff = User::create([
            'authentik_uuid' => $identity->uuid,
            'name' => $identity->name,
            'email' => $identity->email,
        ]);
        $staff->syncRoles($roles);

        $this->log->record(SecurityEvent::StaffFirstSeen, $staff, details: [
            'roles' => StaffMirror::roleNames($roles),
            'via' => self::VIA,
        ]);

        return $staff;
    }
}
