#!/bin/sh
# initContainer chung của mọi pod (`app`, `queue`, `scheduler`), xem ADR 0009 và mục "Chạy trên k3s"
# của README (lần dựng đầu, gỡ khoá khi pod giữ khoá chết giữa chừng).
#
# Mọi pod cùng chạy script này một lúc. `--isolated` giữ một khoá trên cache store: pod lấy được khoá
# chạy migrate, các pod còn lại thoát ngay với mã 0 rồi chờ ở vòng lặp bên dưới. Khoá chỉ loại trừ được
# giữa các pod khi cache store dùng chung, nên store khác bị từ chối ở đây.
set -eu

case "${CACHE_STORE:-database}" in
    database | redis) ;;
    *)
        echo "CACHE_STORE=${CACHE_STORE} không dùng chung giữa các pod: mọi pod sẽ cùng migrate." >&2
        exit 1
        ;;
esac

wait_seconds="${INVENTORY_MIGRATE_WAIT_SECONDS:-600}"

php artisan migrate --force --isolated

# `migrate:status --pending=1` thoát mã 1 khi còn migration pending.
deadline=$(( $(date +%s) + wait_seconds ))
until status="$(php artisan migrate:status --pending=1 2>&1)"; do
    if [ "$(date +%s)" -ge "$deadline" ]; then
        echo "Hết ${wait_seconds} giây mà vẫn còn migration pending. Lần kiểm tra cuối:" >&2
        echo "$status" >&2
        exit 1
    fi
    echo "Còn migration pending, chờ pod đang giữ khoá migrate xong..."
    sleep 3
done

php artisan inventory:keys:verify
