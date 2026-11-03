<?php

namespace App\Models;

use App\Inventory\Access\Role;
use App\Inventory\Staff\PlatformRevocation;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * Nhân viên của shop. Danh tính, tên, email và Vai trò lấy từ platform (ADR 0011).
 *
 * @property string $platform_user_id
 * @property ?string $email
 * @property Collection<int, Role> $roles Bản chụp Vai trò theo lần cuối kho thấy nhân viên này.
 * @property ?CarbonImmutable $deactivated_at
 * @property ?CarbonImmutable $platform_revoked_at
 * @property ?PlatformRevocation $platform_revoked_reason
 * @property ?int $directory_seq `seq` của bản danh bạ mới nhất kho đã nhận về nhân viên này.
 */
#[Fillable(['platform_user_id', 'name', 'email'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Nhân viên mới chưa có bản chụp Vai trò nào, như default của cột.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'roles' => '[]',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles' => AsEnumCollection::of(Role::class),
            'deactivated_at' => 'immutable_datetime',
            'platform_revoked_at' => 'immutable_datetime',
            'platform_revoked_reason' => PlatformRevocation::class,
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
     * Nhân viên mất quyền vì platform đã đình chỉ hoặc xoá họ, theo Bản sao danh bạ. Tự hết khi
     * platform cấp lại quyền, khác Khoá nhân viên.
     */
    public function isRevokedByPlatform(): bool
    {
        return $this->platform_revoked_at !== null;
    }

    /**
     * Không vào được kho, vì Khoá nhân viên hoặc vì mất quyền theo platform. Panel hỏi lại ở mỗi
     * request qua {@see canAccessPanel()}, nên phiên đang mở bị chặn ở request kế tiếp.
     */
    public function isLockedOut(): bool
    {
        return $this->isDeactivated() || $this->isRevokedByPlatform();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return ! $this->isLockedOut();
    }
}
