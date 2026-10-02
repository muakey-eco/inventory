## Agent skills

### Issue tracker

Issue được quản lý trên GitHub Issues của `nghianb/inventory` (dùng `gh` CLI). See `docs/agents/issue-tracker.md`.

### Triage labels

Dùng 5 nhãn mặc định: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: một `CONTEXT.md` và `docs/adr/` ở gốc repo. See `docs/agents/domain.md`.

## Migration tương thích ngược một phiên bản

Áp dụng từ khi production trên k3s chạy (trước đó kho chưa có dữ liệu thật, migration cứ viết thẳng). `app` chạy RollingUpdate, nên code của release **trước** chạy trên schema **mới** trong lúc deploy (ADR 0009). Mọi migration đi theo expand/contract:

- **Xoá hoặc đổi tên cột:** tách hai release. Release 1 thêm cột mới và ghi song song cả hai cột; release 2 chuyển đọc sang cột mới rồi xoá cột cũ. Ví dụ đổi `note` thành `remark`: release 1 thêm `remark`, ghi cả `note` lẫn `remark`, backfill; release 2 xoá `note`.
- **Cột `NOT NULL` mới:** có default, hoặc đi ba bước: thêm nullable, backfill, siết `NOT NULL` ở release sau.
- **Trigger chỉ-ghi-thêm và CHECK constraint:** nới trước, siết ở release sau, khi code cũ không còn ghi giá trị bị cấm. Nới ở cùng release là an toàn, như `2026_09_17_110000_allow_sale_price_on_recorded_lines.php` thêm `'recorded'` vào `dispatch_lines_sale_price_kind`.
- **Tên bảng và class job:** giữ nguyên trong cùng release. Pod `app` cũ vẫn ghi vào tên bảng cũ và dispatch job theo tên class cũ, worker mới phải chạy được job đó.
