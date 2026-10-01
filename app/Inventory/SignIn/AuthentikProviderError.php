<?php

namespace App\Inventory\SignIn;

use RuntimeException;

/**
 * Không dùng được Authentik: không tới được, trả lỗi, trả sai dạng, hoặc discovery lệch với cấu
 * hình. Message là mã lỗi ngắn, không nhạy cảm, đủ để ghi log.
 */
class AuthentikProviderError extends RuntimeException {}
