<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mỗi logout_token Authentik gửi qua back-channel đã xác minh là một hàng. Middleware Authenticate
 * đối chiếu phiên kho với bảng này ở mỗi request: khớp `sid` thì huỷ đúng phiên đó, hàng không có
 * `sid` thì huỷ mọi phiên của `authentik_uuid` mở trước `received_at`. Không xoá thẳng hàng trong
 * `sessions`, để chạy được với mọi session driver và không lệch khi session id đổi.
 *
 * `jti` unique để chống phát lại. Hàng cũ hơn thời hạn phiên kho được dọn khi có token mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authentik_logouts', function (Blueprint $table) {
            $table->id();
            $table->string('jti')->unique();
            $table->string('sid')->nullable()->index();
            $table->uuid('authentik_uuid')->nullable()->index();
            // Micro giây: so với giờ đăng nhập lưu trong phiên, để đăng nhập lại ngay sau đó không bị huỷ oan.
            $table->timestampTz('received_at', 6)->index();
        });

        DB::statement('ALTER TABLE authentik_logouts ADD CONSTRAINT authentik_logouts_sid_or_uuid CHECK (sid IS NOT NULL OR authentik_uuid IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('authentik_logouts');
    }
};
