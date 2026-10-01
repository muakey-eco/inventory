<?php

namespace App\Inventory\SignIn;

use RuntimeException;

/**
 * Một token mang danh Authentik nhưng không qua được xác minh: chữ ký, iss, aud, thời hạn hay
 * claim bắt buộc. Message là mã lỗi ngắn, không nhạy cảm, đủ để ghi log.
 */
class InvalidAuthentikToken extends RuntimeException {}
