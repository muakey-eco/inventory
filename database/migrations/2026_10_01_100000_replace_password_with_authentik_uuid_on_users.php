<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhân viên đăng nhập qua Authentik (ADR 0008): bỏ mật khẩu và TOTP của kho, nhận diện bằng
 * `sub` của Authentik. Lúc viết, kho chưa chạy production nên không backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password', 'app_authentication_secret', 'app_authentication_recovery_codes']);
            // Email chỉ là bản sao từ Authentik, không còn là danh tính: Authentik cho phép
            // email trùng hoặc để trống, và một email cũ có thể sang tay người khác.
            $table->dropUnique(['email']);
            $table->string('email')->nullable()->change();
            $table->uuid('authentik_uuid')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('authentik_uuid');
            $table->string('email')->nullable(false)->unique()->change();
            $table->string('password');
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }
};
