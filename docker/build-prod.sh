#!/usr/bin/env bash
# Build image production và đẩy lên registry.
#
# Build thủ công không có CI đứng sau kiểm, nên rào chắn nằm ở đây: chỉ build từ một commit sạch,
# và tag theo commit sha để rollback được bằng cách đổi tag.
set -euo pipefail

# Registry nội bộ của Muakey (Bizfly CR). Cụm k3s kéo cùng image này dưới bí danh `muakey/inventory`,
# node tự ánh xạ sang đây (ADR 0001 của muakey-eco/k3s-ops). Tên image không mang org của repo
# source, nên nhãn source không suy ra từ tên image được.
IMAGE="${IMAGE:-cr-hn-1.bizflycloud.vn/7cc21c55e13e43b992d6498e54de2661/inventory}"
SOURCE_REPO="${SOURCE_REPO:-muakey-eco/inventory}"

cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
    echo "Cây làm việc không sạch — image sẽ không khớp với commit nào cả:" >&2
    git status --short >&2
    exit 1
fi

sha="$(git rev-parse --short HEAD)"
tag="git-${sha}"

# Composer cần token GitHub đọc được repo private muakey-eco/filament-muakey-theme.
if [ -z "${COMPOSER_AUTH:-}" ]; then
    if ! token="$(gh auth token 2>/dev/null)" || [ -z "$token" ]; then
        echo "Thiếu token GitHub để đọc muakey-eco/filament-muakey-theme: đặt COMPOSER_AUTH hoặc chạy \`gh auth login\`." >&2
        exit 1
    fi
    export COMPOSER_AUTH="{\"github-oauth\": {\"github.com\": \"${token}\"}}"
fi

docker build \
    --file docker/php/Dockerfile \
    --secret id=composer_auth,env=COMPOSER_AUTH \
    --target prod \
    --tag "${IMAGE}:${tag}" \
    --tag "${IMAGE}:latest" \
    --label "org.opencontainers.image.revision=$(git rev-parse HEAD)" \
    --label "org.opencontainers.image.source=https://github.com/${SOURCE_REPO}" \
    .

docker push "${IMAGE}:${tag}"
docker push "${IMAGE}:latest"

cat <<EOF

Xong. Đổi newTag thành ${tag} ở applications/inventory/kustomization.yaml của
muakey-eco/k3s-ops rồi push, Argo CD tự đồng bộ.

Rollback: đặt lại tag cũ ở k3s-ops. Migration theo expand/contract (AGENTS.md) nên chỉ lùi
an toàn được một release.
EOF
