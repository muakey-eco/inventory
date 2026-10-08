# Kho chạy trên k3s nhiều replica, chỉ `web` rolling, migration phải tương thích ngược

Thay ADR 0005. Kho chạy trên cụm k3s dùng chung của tổ chức, deploy bằng Argo CD từ `muakey-eco/k3s-ops`, với Postgres và S3-compatible nằm ngoài pod. Bản gốc của manifest nằm ở `deploy/k3s/` của repo này và được chép nguyên thư mục sang `k3s-ops/applications/inventory/`, theo cùng khuôn với `paygate` và `muakey-account`. `web` chạy nhiều replica và cập nhật kiểu RollingUpdate. `queue` và `scheduler` cập nhật kiểu `Recreate`, và `scheduler` luôn chỉ có một replica. Chúng tôi chọn để `web` không còn gián đoạn khi deploy. Cái giá là từ nay mọi migration phải để code của phiên bản trước vẫn chạy được trên schema mới.

Bốn chỗ hỏng mà ADR 0005 cảnh báo được xử lý như sau:

- **Livewire temporary upload.** `livewire-tmp` chuyển sang S3.
- **State trên đĩa cục bộ.** Disk `intake` và ảnh Báo lỗi chuyển sang S3. Pod không còn state cục bộ nào.
- **Scheduler.** Không dùng `onOneServer()`. Thay vào đó, `scheduler` cố định một replica và dùng `Recreate`, nên không bao giờ có hai bản cùng chạy.
- **Khoá mã hoá.** Xem ADR 0010.

## Consequences

- **Migration theo expand/contract.** Xoá cột tách thành hai release, đổi tên cột thành ba. Cột `NOT NULL` mới phải có default, hoặc đi qua nullable và backfill rồi mới siết. Trigger chỉ-ghi-thêm và CHECK constraint phải nới trước, siết sau.
- **Migrate chạy trong một Job, mọi pod chỉ chờ.** Job `migrate` là hook `Sync` của Argo CD (`BeforeHookCreation`), chạy `migrate --force` rồi `RoleSeeder`. Mọi Deployment có initContainer `inventory-init` chờ tới khi không còn migration nào pending, rồi chạy `inventory:keys:verify`. Lần dựng đầu chỉ còn `inventory:keys:register` làm bằng tay. Nó cố ý không tự chạy, vì tự đăng ký thì `keys:verify` sẽ nhận luôn một khoá sai trên Infisical thay vì chặn nó.
  Bản đầu của ADR này để mọi pod tự chạy `migrate --isolated`. Cách đó bị bỏ trước khi lên production, vì ba lý do. Khoá `--isolated` nằm trong bảng `cache_locks` do chính migration tạo, nên lần dựng đầu phải migrate tay. Pod giữ khoá mà chết thì khoá kẹt tới một giờ. Và cách đó lệch khuôn của các app khác trong cụm.
- **Queue không chạy hai phiên bản cùng lúc, nhưng job vẫn phải tương thích ngược.** `queue` dùng `Recreate`, nên không có hai worker khác phiên bản. Tuy vậy, pod `web` cũ vẫn dispatch job trong lúc rolling, sau khi worker mới đã lên, nên worker mới phải chạy được job theo class và payload của release trước.
- **Lỗi khi rolling được chấp nhận.** Trong lúc rolling, trang đã tải từ phiên bản cũ có thể gặp 404 asset Vite hoặc lỗi snapshot Livewire. Người dùng tải lại trang là hết.
- **IP client có thể bị giả trong cụm.** `trustProxies('*')` vẫn được giữ. NetworkPolicy chỉ cho dải pod `10.42.0.0/16` vào `web`, vì ingress-nginx chạy `hostNetwork` và traffic của nó mang IP nguồn nằm trong dải đó. Policy này chặn được những gì từ ngoài dải pod, nhưng **không** chặn được pod khác. Hệ quả là workload khác trong cụm, vốn đều của tổ chức, gọi thẳng vào Service có thể giả IP trong Nhật ký bảo mật và lách giới hạn thử khoá API theo IP. Thu hẹp về IP node ở issue #132.
- **Truy cập chỉ trong mạng nội bộ.** Panel nằm ở `kho.muakeyoffice.net` và chỉ mở cho mạng nội bộ (Tailscale/LAN). Authentik trong cụm gọi back-channel logout qua Service nội bộ `web`.
