#!/usr/bin/env bash
# Build image production và đẩy lên registry.
#
# Build thủ công không có CI đứng sau kiểm, nên rào chắn nằm ở đây: chỉ build từ một commit sạch,
# và tag theo commit sha để rollback được bằng cách đổi tag.
set -euo pipefail

# Registry private của tổ chức; cụm k3s kéo image bằng imagePullSecret. Source vẫn ở repo cá nhân,
# nên nhãn source không suy ra từ tên image được.
IMAGE="${IMAGE:-ghcr.io/muakey-eco/inventory}"
SOURCE_REPO="${SOURCE_REPO:-nghianb/inventory}"

cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
    echo "Cây làm việc không sạch — image sẽ không khớp với commit nào cả:" >&2
    git status --short >&2
    exit 1
fi

sha="$(git rev-parse --short HEAD)"
tag="git-${sha}"

docker build \
    --file docker/php/Dockerfile \
    --target prod \
    --tag "${IMAGE}:${tag}" \
    --tag "${IMAGE}:latest" \
    --label "org.opencontainers.image.revision=$(git rev-parse HEAD)" \
    --label "org.opencontainers.image.source=https://github.com/${SOURCE_REPO}" \
    .

docker push "${IMAGE}:${tag}"
docker push "${IMAGE}:latest"

cat <<EOF

Xong. Trên k3s: đổi tag thành ${tag} trong muakey-eco/k3s-ops, Argo CD tự đồng bộ.
Trên VPS:

    INVENTORY_IMAGE=${IMAGE}:${tag} docker compose -f compose.prod.yaml up -d --wait

Rollback: đặt lại tag cũ ở k3s-ops, hoặc chạy lại lệnh trên VPS với tag cũ. Migration theo
expand/contract (AGENTS.md) nên chỉ lùi an toàn được một release.
EOF
