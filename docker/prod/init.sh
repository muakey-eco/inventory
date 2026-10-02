#!/bin/sh
# initContainer chung của mọi pod (`app`, `queue`, `scheduler`), xem ADR 0009.
#
# Ba pod cùng chạy script này một lúc. `--isolated` giữ một khoá trên cache store (CACHE_STORE=database,
# bảng `cache_locks`): pod lấy được khoá chạy migrate, pod kia thoát ngay với mã 0 rồi chờ ở bước 2.
# Vì vậy bảng `cache_locks` phải có sẵn từ trước: lần dựng đầu migrate bằng tay.
#
# Pod giữ khoá chết giữa chừng thì khoá còn tới một giờ, và các pod khác hết hạn chờ rồi khởi động lại
# liên tục. Gỡ bằng tay: DELETE FROM cache_locks WHERE key LIKE '%framework/command-migrate%';
set -eu

wait_seconds="${INVENTORY_MIGRATE_WAIT_SECONDS:-600}"

php artisan migrate --force --isolated

# Pod không lấy được khoá tới đây trong khi pod kia còn đang migrate. `migrate:status --pending=1`
# thoát mã 1 khi còn migration pending, và cũng thoát khác 0 khi chưa tới được DB: cả hai đều là "chờ".
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
