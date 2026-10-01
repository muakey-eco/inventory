<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đối soát nhân viên với Authentik mỗi phút (ADR 0008).
 *
 * "Mất quyền theo Authentik" là cột riêng, không dùng chung `deactivated_at`: nó tự hết khi
 * Authentik cấp lại quyền, còn Khoá nhân viên thì chỉ Quản trị mở được.
 *
 * `staff_sync_health` có đúng một hàng, giữ tình trạng lần đối soát gần nhất để trang Tổng quan
 * cảnh báo khi đối soát hỏng lâu. Lịch sử từng lần chạy nằm trong log ứng dụng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('authentik_revoked_at')->nullable();
            $table->string('authentik_revoked_reason')->nullable();
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_authentik_revoked_reason_with_time CHECK ((authentik_revoked_at IS NULL) = (authentik_revoked_reason IS NULL))');

        Schema::create('staff_sync_health', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->timestampTz('last_succeeded_at')->nullable();
            // Lần hỏng đầu tiên của chuỗi hỏng hiện tại; null khi lần gần nhất chạy được.
            $table->timestampTz('failing_since')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('updated_at');
        });

        DB::statement('ALTER TABLE staff_sync_health ADD CONSTRAINT staff_sync_health_single_row CHECK (id = 1)');

        DB::table('staff_sync_health')->insert(['id' => 1, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_sync_health');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['authentik_revoked_at', 'authentik_revoked_reason']);
        });
    }
};
