<?php

namespace App\Models;

use App\Inventory\Staff\AuthentikRevocation;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Nhân viên của shop. Danh tính, tên, email và Vai trò lấy từ Authentik (ADR 0008).
 *
 * @property string $authentik_uuid
 * @property ?string $email
 * @property ?CarbonImmutable $deactivated_at
 * @property ?CarbonImmutable $authentik_revoked_at
 * @property ?AuthentikRevocation $authentik_revoked_reason
 */
#[Fillable(['authentik_uuid', 'name', 'email'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'immutable_datetime',
            'authentik_revoked_at' => 'immutable_datetime',
            'authentik_revoked_reason' => AuthentikRevocation::class,
        ];
    }

    /**
     * Nhân viên đã bị Khoá nhân viên: không đăng nhập được, không qua kiểm tra Vai trò nào.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Nhân viên mất quyền vì Authentik đã tắt họ, xoá họ hoặc gỡ họ khỏi mọi group `kho-*`. Tự hết
     * khi Authentik cấp lại quyền, khác Khoá nhân viên.
     */
    public function isRevokedByAuthentik(): bool
    {
        return $this->authentik_revoked_at !== null;
    }

    /**
     * Không vào được kho, vì Khoá nhân viên hoặc vì mất quyền theo Authentik. Middleware
     * Authenticate của panel hỏi lại ở mỗi request, nên phiên đang mở bị cắt ở request kế tiếp.
     */
    public function isLockedOut(): bool
    {
        return $this->isDeactivated() || $this->isRevokedByAuthentik();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return ! $this->isLockedOut();
    }
}
