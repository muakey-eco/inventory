<?php

namespace App\Models;

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
        ];
    }

    /**
     * Nhân viên đã bị Khoá nhân viên: không đăng nhập được, không qua kiểm tra Vai trò nào.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return ! $this->isDeactivated();
    }
}
