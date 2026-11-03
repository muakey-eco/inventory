<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Nhân viên đăng nhập qua platform (ADR 0011): không mật khẩu, không TOTP, nhận diện bằng
        // id trên platform. Lúc viết, kho chưa chạy production nên sửa thẳng migration này.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('platform_user_id')->unique();
            $table->string('name');
            // Email chỉ là bản sao từ platform, không phải danh tính: một email cũ có thể sang
            // tay người khác.
            $table->string('email')->nullable();
            // Bản chụp Vai trò theo lần cuối kho thấy nhân viên, danh sách giá trị của `Role`.
            $table->jsonb('roles')->default('[]');
            // Mất quyền theo platform (bị đình chỉ hoặc bị xoá), theo Bản sao danh bạ. Cột riêng,
            // không dùng chung `deactivated_at`: nó tự hết khi platform cấp lại quyền, còn Khoá
            // nhân viên thì chỉ Quản trị mở được.
            $table->timestampTz('platform_revoked_at')->nullable();
            $table->string('platform_revoked_reason')->nullable();
            // `seq` của bản danh bạ mới nhất đã nhận, để bỏ bản tới trễ hoặc tới lặp.
            $table->unsignedBigInteger('directory_seq')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_platform_revoked_reason_with_time CHECK ((platform_revoked_at IS NULL) = (platform_revoked_reason IS NULL))');

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
