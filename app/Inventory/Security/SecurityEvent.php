<?php

namespace App\Inventory\Security;

/**
 * Loại sự kiện ghi vào Nhật ký bảo mật. Các sự kiện của đường mật khẩu + TOTP cũ đã xoá hẳn
 * cùng đường đó (ADR 0008): lúc xoá, kho chưa chạy production nên không có dòng cũ nào phải đọc lại.
 */
enum SecurityEvent: string
{
    case LoginSucceeded = 'login_succeeded';
    case LoginRefused = 'login_refused';
    case LoginWithoutMfa = 'login_without_mfa';
    case StaffFirstSeen = 'staff_first_seen';
    case RolesChanged = 'roles_changed';
    case StaffDeactivated = 'staff_deactivated';
    case StaffReactivated = 'staff_reactivated';
    case AuthentikAccessRevoked = 'authentik_access_revoked';
    case AuthentikAccessRestored = 'authentik_access_restored';
    case AuthentikSessionEnded = 'authentik_session_ended';
    case KeyFingerprintRegistered = 'key_fingerprint_registered';
    case KeyRotationStarted = 'key_rotation_started';
    case KeyRotationFinished = 'key_rotation_finished';
    case ApiKeyCreated = 'api_key_created';
    case ApiKeyRotated = 'api_key_rotated';
    case ApiKeyRevoked = 'api_key_revoked';
    case DispatchFrozen = 'dispatch_frozen';
    case DispatchUnfrozen = 'dispatch_unfrozen';

    public function label(): string
    {
        return match ($this) {
            self::LoginSucceeded => 'Đăng nhập thành công',
            self::LoginRefused => 'Đăng nhập bị từ chối',
            self::LoginWithoutMfa => 'Đăng nhập thiếu bằng chứng MFA',
            self::StaffFirstSeen => 'Nhân viên xuất hiện lần đầu',
            self::RolesChanged => 'Vai trò đổi theo Authentik',
            self::StaffDeactivated => 'Khoá nhân viên',
            self::StaffReactivated => 'Mở khoá nhân viên',
            self::AuthentikAccessRevoked => 'Mất quyền theo Authentik',
            self::AuthentikAccessRestored => 'Có lại quyền theo Authentik',
            self::AuthentikSessionEnded => 'Authentik huỷ phiên kho',
            self::KeyFingerprintRegistered => 'Đăng ký dấu vân tay khoá mã hoá',
            self::KeyRotationStarted => 'Bắt đầu xoay khoá mã hoá',
            self::KeyRotationFinished => 'Xoay xong khoá mã hoá',
            self::ApiKeyCreated => 'Tạo Khoá API',
            self::ApiKeyRotated => 'Xoay Khoá API',
            self::ApiKeyRevoked => 'Thu hồi Khoá API',
            self::DispatchFrozen => 'Bật Tạm dừng xuất kho',
            self::DispatchUnfrozen => 'Tắt Tạm dừng xuất kho',
        };
    }
}
