# Khoá mã hoá lấy từ Infisical qua External Secrets; Người vận hành server mở rộng theo

Thay phần "khoá nằm trong `.env` trên VPS" của ADR 0001. Phần còn lại của ADR 0001 giữ nguyên: mã hoá ở tầng ứng dụng, không dùng KMS/envelope encryption, và chấp nhận Người vận hành server là lỗ hổng audit.

Trên k3s, `APP_KEY` và các khoá `INVENTORY_*` nằm trong project Infisical dùng chung của tổ chức, ở path `/inventory/*`. External Secrets đồng bộ chúng về một Secret trong namespace `inventory`, và app đọc khoá qua biến môi trường như trước. App không gọi ra ngoài khi giải mã, nên lý do ADR 0001 loại Vault (KMS sập là không giao hàng được) không áp dụng ở đây.

## Consequences

- **Người vận hành server mở rộng.** Từ nay gồm cả người có quyền vào project Infisical (kể cả người chỉ quản secret của app khác), chính Infisical, và người có quyền `get secret` trong namespace `inventory`. Đây là đánh đổi có chủ ý để dùng chung một đường quản lý secret với các app khác. Nếu số người này tăng tới mức không còn tin nhau, thì tách kho sang một project Infisical riêng.
- **Nhà cung cấp S3 đọc được bản rõ trong một khoảng thời gian.** Upload tạm của Livewire đi thẳng từ trình duyệt lên bucket S3-compatible (versitygw, phía sau là Vietnix), và file nhập hàng ở đó là **bản rõ**. Nó nằm đó tới khi lệnh purge hằng giờ xoá đi, tức tối đa khoảng 2 giờ, không có SSE nào bảo đảm. Phương án giữ upload tạm trong pod (sticky session cộng tmpfs) bị loại vì phải thêm sidecar dọn file và sticky session.
- **Quy trình khoá giữ nguyên.** Xoay khoá vẫn chỉ làm bằng dòng lệnh. Khoá vẫn phải có bản sao tách khỏi backup Postgres và bucket S3.
