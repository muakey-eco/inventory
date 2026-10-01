<?php

namespace App\Inventory\SignIn;

use App\Inventory\Access\Role;
use App\Inventory\Security\SecurityEvent;
use App\Inventory\Security\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Nhận một danh tính Authentik đã xác minh và quyết định ai được vào kho (ADR 0008). Nhân
 * viên khớp theo `sub`, chưa có thì tạo mới; mỗi lần đăng nhập đồng bộ tên, email và bản sao
 * Vai trò. Authentik làm chủ Vai trò nên ở đây không chặn mất Quản trị cuối cùng; Khoá nhân
 * viên thì là của kho, và đăng nhập lại ở Authentik không mở khoá.
 */
class StaffSignIn
{
    public function __construct(private SecurityLog $log) {}

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

        // Đồng bộ cả khi sắp từ chối vì hết Vai trò: bản sao trong kho phải theo Authentik,
        // để phiên đang mở của người vừa bị thu quyền cũng mất quyền theo.
        $staff = DB::transaction(fn (): User => $staff === null
            ? $this->firstSeen($identity, $roles)
            : $this->sync($staff, $identity, $roles));

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
            'roles' => self::roleNames($roles),
            'via' => 'authentik',
        ]);

        return $staff;
    }

    /**
     * @param  list<Role>  $roles
     */
    private function sync(User $staff, AuthentikIdentity $identity, array $roles): User
    {
        $staff->fill(['name' => $identity->name, 'email' => $identity->email])->save();

        $from = self::roleNames($staff->getRoleNames()->map(fn (string $name): Role => Role::from($name))->all());
        $to = self::roleNames($roles);

        if ($from !== $to) {
            $staff->syncRoles($roles);

            $this->log->record(SecurityEvent::RolesChanged, $staff, details: [
                'from' => $from,
                'to' => $to,
                'via' => 'authentik',
            ]);
        }

        return $staff;
    }

    /**
     * Tên Vai trò theo thứ tự khai báo, để so sánh và ghi nhật ký không phụ thuộc thứ tự group.
     *
     * @param  array<Role>  $roles
     * @return list<string>
     */
    private static function roleNames(array $roles): array
    {
        return array_map(fn (Role $role): string => $role->value, Role::inOrder($roles));
    }
}
