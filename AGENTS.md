## Agent skills

### Issue tracker

Issue được quản lý trên GitHub Issues của `nghianb/inventory` (dùng `gh` CLI). See `docs/agents/issue-tracker.md`.

### Triage labels

Dùng 5 nhãn mặc định: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: một `CONTEXT.md` và `docs/adr/` ở gốc repo. See `docs/agents/domain.md`.

## Migration tương thích ngược một phiên bản

Production chạy trên k3s, nên luật này áp dụng cho mọi migration mới. Migration chạy trong initContainer **trước** khi pod mới nhận traffic, và pod `app` của release trước vẫn phục vụ trên schema mới tới khi rolling xong (ADR 0009). Mọi migration đi theo expand/contract, mỗi bước là một release:

- **Xoá cột:** release 1 bỏ mọi chỗ đọc và ghi cột; release 2 xoá cột.
- **Đổi tên cột**, ví dụ `note` thành `remark`: release 1 thêm `remark` và ghi cả hai cột; release 2 backfill `remark` từ `note`, chuyển đọc sang `remark` nhưng vẫn ghi cả hai; release 3 bỏ ghi và xoá `note`.
- **Cột `NOT NULL` mới:** có default, hoặc thêm nullable, backfill, rồi siết `NOT NULL` ở release sau.
- **Trigger chỉ-ghi-thêm và CHECK constraint:** nới trước, siết ở release sau, khi code cũ không còn ghi thứ bị cấm. Nới thì làm được ngay trong release cần nó, như `2026_09_17_110000_allow_sale_price_on_recorded_lines.php` thêm `'recorded'` vào `dispatch_lines_sale_price_kind`. Siết CHECK thì `ADD CONSTRAINT ... NOT VALID` rồi `VALIDATE CONSTRAINT`, để không khoá ghi cả bảng trong lúc quét.
- **Tên bảng và class job:** giữ nguyên trong cùng release. Pod `app` cũ vẫn ghi vào bảng theo tên cũ và dispatch job theo class cũ, và worker mới phải chạy được job đó.
