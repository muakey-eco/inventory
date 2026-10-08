#!/bin/sh
# initContainer `wait-for-migrate` của mọi Deployment (`web`, `queue`, `scheduler`), xem ADR 0009 và
# `deploy/k3s/wait-for-migrate.yaml`.
#
# Script này KHÔNG migrate: chỉ Job `migrate` làm việc đó. Ở đây pod chờ tới khi database hết migration
# pending, rồi kiểm khoá mã hoá. k8s không có quan hệ "chờ Job khác", nên pod tự hỏi chính database.
set -eu

wait_seconds="${INVENTORY_MIGRATE_WAIT_SECONDS:-600}"

# `migrate:status --pending=1` thoát mã 1 khi còn migration pending, và mã khác 0 khi bảng `migrations`
# chưa tồn tại (Job chưa chạy lần nào).
deadline=$(( $(date +%s) + wait_seconds ))
until status="$(php artisan migrate:status --pending=1 2>&1)"; do
    if [ "$(date +%s)" -ge "$deadline" ]; then
        echo "Hết ${wait_seconds} giây mà vẫn còn migration pending. Xem log Job \`migrate\`. Lần kiểm tra cuối:" >&2
        echo "$status" >&2
        exit 1
    fi
    echo "Còn migration pending, chờ Job migrate..."
    sleep 3
done

# Lần dựng đầu thất bại ở đây cho tới khi `inventory:keys:register` chạy tay (README).
php artisan inventory:keys:verify
