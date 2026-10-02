# Kho chạy trên k3s nhiều replica, chỉ `app` rolling, migration phải tương thích ngược

Thay ADR 0005. Kho chạy trên cụm k3s dùng chung của tổ chức, deploy bằng Argo CD từ `muakey-eco/k3s-ops`, với Postgres và S3-compatible nằm ngoài pod. `app` chạy nhiều replica và cập nhật kiểu RollingUpdate. `queue` và `scheduler` cập nhật kiểu `Recreate`, và `scheduler` luôn chỉ có một replica. Chúng tôi chọn để `app` không còn gián đoạn khi deploy. Cái giá là từ nay mọi migration phải để code của phiên bản trước vẫn chạy được trên schema mới.

Bốn chỗ hỏng mà ADR 0005 cảnh báo được xử lý như sau:

- **Livewire temporary upload và state trên đĩa cục bộ.** Cả hai chuyển sang S3: disk `intake`, ảnh Báo lỗi và `livewire-tmp`. Pod không còn state cục bộ nào.
- **Scheduler.** Không dùng `onOneServer()`. Thay vào đó, `scheduler` cố định một replica và dùng `Recreate`, nên không bao giờ có hai bản cùng chạy.
- **Khoá mã hoá.** Xem ADR 0010.

## Consequences

- **Migration theo expand/contract.** Xoá hoặc đổi tên cột phải tách thành hai release. Cột `NOT NULL` mới phải có default. Trigger chỉ-ghi-thêm và CHECK constraint phải nới trước, siết sau.
- **Migrate chạy trong initContainer của mọi pod.** Script chạy `migrate --force --isolated`, sau đó chờ tới khi không còn migration nào pending, rồi chạy `inventory:keys:verify`. `--isolated` bảo đảm chỉ một pod thực sự chạy migrate. Lần dựng đầu (`migrate`, `inventory:keys:register`, `RoleSeeder`) làm bằng tay.
- **Queue không chạy hai phiên bản cùng lúc.** Vì `queue` dùng `Recreate`, payload của job không phải tương thích ngược. Job chỉ nằm chờ trong bảng `jobs` vài giây khi deploy.
- **Lỗi khi rolling được chấp nhận.** Trong lúc rolling, trang đã tải từ phiên bản cũ có thể gặp 404 asset Vite hoặc lỗi snapshot Livewire. Người dùng tải lại trang là hết.
- **IP client có thể bị giả trong cụm.** `trustProxies('*')` vẫn được giữ, và không có NetworkPolicy, vì ingress-nginx chạy `hostNetwork`. Policy chọn theo namespace không khớp được nguồn traffic đó. Hệ quả là workload khác trong cụm, vốn đều của tổ chức, gọi thẳng vào Service có thể giả IP trong Nhật ký bảo mật và lách giới hạn thử khoá API theo IP.
- **Truy cập chỉ trong mạng nội bộ.** Panel nằm ở `kho.muakeyoffice.net` và chỉ mở cho mạng nội bộ (Tailscale/LAN). Authentik trong cụm gọi back-channel logout qua Service nội bộ.
