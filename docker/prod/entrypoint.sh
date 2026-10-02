#!/bin/sh
set -e

# Volume gắn vào /app/storage/app có thể rỗng ở lần chạy đầu, và trên k3s với readOnlyRootFilesystem
# thì /app/storage là một emptyDir rỗng. `mkdir -p` không ghi gì khi thư mục đã có, nên dòng này vẫn
# qua được khi storage nằm trên root filesystem chỉ-đọc.
mkdir -p storage/app/intake \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# Cache cấu hình lúc khởi động, KHÔNG lúc build: cache lúc build sẽ đóng băng env của máy build
# — kể cả khoá mã hoá — vào image rồi đẩy lên registry, và đổi secret trên server sẽ vô tác dụng.
#
# Với readOnlyRootFilesystem, bootstrap/cache không ghi được (route cache và cache Filament nướng sẵn
# trong đó vẫn đọc được), nên cache cấu hình chuyển sang storage. Lệnh exec vào container sau này
# không mang biến này và tự đọc cấu hình từ env, cùng giá trị.
if [ -z "${APP_CONFIG_CACHE:-}" ] && [ ! -w bootstrap/cache ]; then
    if [ ! -w storage/framework ]; then
        echo "bootstrap/cache và storage/framework đều chỉ-đọc: gắn emptyDir vào /app/storage." >&2
        exit 1
    fi
    export APP_CONFIG_CACHE=/app/storage/framework/config.php
fi

php artisan config:cache

exec "$@"
