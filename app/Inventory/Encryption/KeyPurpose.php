<?php

namespace App\Inventory\Encryption;

/**
 * Các khoá mã hoá của kho mà ứng dụng cầm. APP_KEY không thuộc module này.
 *
 * Từng có khoá `backup` nhưng chưa mã hoá gì nên đã bỏ (#87). Dấu vân tay `purpose = 'backup'`
 * đã đăng ký vẫn nằm trong DB (bảng chỉ-ghi-thêm) và bị bỏ qua.
 */
enum KeyPurpose: string
{
    case Content = 'content';
    case Hmac = 'hmac';

    public function label(): string
    {
        return match ($this) {
            self::Content => 'khoá mã hoá nội dung',
            self::Hmac => 'khoá mã hoá HMAC',
        };
    }

    /**
     * Chỉ khoá nội dung giữ khoá cũ để giải mã; xoay khoá HMAC tính lại mọi hash, không
     * giữ hai khoá song song.
     */
    public function keepsPreviousKeys(): bool
    {
        return $this === self::Content;
    }

    /**
     * Cột trên `stock_units` ghi phiên bản khoá đã dùng cho bản ghi, để lệnh xoay khoá biết bản
     * ghi nào còn ở khoá cũ.
     */
    public function versionColumn(): string
    {
        return match ($this) {
            self::Content => 'secret_key_version',
            self::Hmac => 'dedupe_hmac_version',
        };
    }
}
