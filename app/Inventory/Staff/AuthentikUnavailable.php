<?php

namespace App\Inventory\Staff;

use RuntimeException;

/**
 * Không đọc được danh sách người dùng từ Authentik: mạng, 5xx, token sai hoặc thiếu quyền, hay
 * câu trả lời không đúng dạng. Đối soát gặp lỗi này thì giữ nguyên quyền hiện có.
 */
class AuthentikUnavailable extends RuntimeException {}
