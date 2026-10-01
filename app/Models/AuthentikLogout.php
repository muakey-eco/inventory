<?php

namespace App\Models;

use App\Inventory\SignIn\AuthentikSessions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Một logout_token đã xác minh mà Authentik gửi qua back-channel (xem migration).
 *
 * @property string $jti
 * @property ?string $sid
 * @property ?string $authentik_uuid
 * @property CarbonImmutable $received_at
 */
#[Fillable(['jti', 'sid', 'authentik_uuid', 'received_at'])]
class AuthentikLogout extends Model
{
    public $timestamps = false;

    /**
     * Giữ micro giây của `received_at`, xem {@see AuthentikSessions::hasEnded()}.
     */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_datetime',
        ];
    }
}
