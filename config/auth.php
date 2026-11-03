<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Nhân viên chỉ vào kho qua platform (ADR 0011): guard `platform` của SDK là guard duy nhất.
    | Không có mật khẩu, nên không có password broker.
    |
    */

    'defaults' => [
        'guard' => 'platform',
    ],

    'guards' => [
        'platform' => [
            'driver' => 'platform',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

];
