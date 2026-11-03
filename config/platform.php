<?php

/*
| SDK `muakey/platform-laravel`: nhân viên đăng nhập kho qua platform và nhận Vai trò từ đó
| (ADR 0011). Route của SDK nằm dưới `/admin/auth`, cạnh panel; trang lỗi đăng nhập là
| `/admin/dang-nhap-loi`. Bí mật (`PLATFORM_CLIENT_SECRET`, `PLATFORM_REDIS_URL`,
| `PLATFORM_WEBHOOK_SECRET`) chỉ sống ở `.env` và Infisical.
*/

return [
    // Issuer OIDC của platform, dạng `<origin>/oidc`.
    'issuer' => env('PLATFORM_ISSUER'),

    // Slug của App khi đăng ký ở Admin console.
    'client_id' => env('PLATFORM_CLIENT_ID'),

    // Secret của OIDC client, platform chỉ hiện một lần lúc đăng ký App. Cũng là khóa ký cookie
    // đăng nhập dở, nên xoay secret thì các lượt đăng nhập đang dở bị hỏng.
    'client_secret' => env('PLATFORM_CLIENT_SECRET'),

    // URL tuyệt đối `<origin><route_prefix>/callback`, đúng như đã đăng ký với platform. Origin của
    // URL này là origin mà guard so với header `Origin` của request ghi.
    'redirect_uri' => env('PLATFORM_REDIRECT_URI'),

    // Prefix của các route `login`, `callback`, `logout` mà SDK mount.
    'route_prefix' => env('PLATFORM_ROUTE_PREFIX', '/admin/auth'),

    // Trang lỗi của App; SDK chuyển về đây kèm `?reason=` khi đăng nhập không thành.
    'error_path' => env('PLATFORM_ERROR_PATH', '/admin/dang-nhap-loi'),

    // Nơi platform đưa trình duyệt về sau khi logout; phải nằm trong `post_logout_redirect_uris` của App.
    'post_logout_redirect_uri' => env('PLATFORM_POST_LOGOUT_REDIRECT_URI'),

    // Permission vào cửa (ADR-0007). `null` thì user `active` nào cũng vào được App.
    'entry_permission' => env('PLATFORM_ENTRY_PERMISSION', 'access'),

    // Gốc của Platform API mà backend gọi, vd. `http://core.platform.svc`; mặc định là origin của issuer.
    'api_url' => env('PLATFORM_API_URL'),

    // Cột của model user trong App giữ id của user trên platform; guard `platform` tra theo cột này.
    'user_column' => 'platform_user_id',

    // Connection DB của bảng `platform_app_sessions`; `null` là connection mặc định của App.
    'session_connection' => env('PLATFORM_SESSION_CONNECTION'),

    // Cache store giữ quyền của user, discovery, JWKS và access token của App; `null` là store mặc
    // định. Các pod của App phải dùng chung store này thì đổi quyền mới có hiệu lực ngay trên mọi pod.
    'cache_store' => env('PLATFORM_CACHE_STORE'),

    // Secret webhook của App (`whsec_<base64>`) do platform sinh, CI lấy bằng Client CI. Có secret và
    // App bind `DirectorySink` thì Bản sao danh bạ bật: route `<route_prefix>/webhook` nhận webhook
    // `user.*` và lệnh `platform:directory-sync` quét bù được.
    'webhook_secret' => env('PLATFORM_WEBHOOK_SECRET'),

    // Nơi đọc Phiên bản quyền ở mỗi request: `redis` đọc `pv:{userId}` qua connection Redis `platform`
    // trong `config/database.php` (thiếu connection thì App báo lỗi lúc boot), khóa thiếu hay Redis lỗi
    // thì hỏi Platform API; `api` luôn hỏi Platform API.
    'version_source' => env('PLATFORM_VERSION_SOURCE', 'redis'),
];
