<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Muakey\Platform\PlatformConfig;

// Phiên App của muakey/platform-laravel (ADR-0009, ADR-0017).
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return PlatformConfig::nullableString(config('platform.session_connection'));
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('platform_app_sessions', function (Blueprint $table) {
            // SHA-256 (base64url) của token ngẫu nhiên trong cookie; token gốc không nằm trong DB.
            $table->string('token_hash', 64)->primary();
            // `sid` của Phiên SSO trong ID token: back-channel logout xóa mọi Phiên App theo cột này.
            $table->string('sid')->index();
            $table->string('sub');
            // Chỉ để làm `id_token_hint` khi logout.
            $table->text('id_token');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('platform_app_sessions');
    }
};
