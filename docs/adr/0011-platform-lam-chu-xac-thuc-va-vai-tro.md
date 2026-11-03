# Platform Muakey làm chủ xác thực và Vai trò; kho giữ idle timeout của mình

> Phần Vai trò của ADR này (Vai trò ứng với một Permission, Quản trị qua mọi cửa, option "Permission theo từng việc" bị bác) đã được thay bởi ADR 0012.

Thay ADR 0008. Tổ chức đã chuyển các app nội bộ khỏi Authentik sang platform Muakey (`id.muakeyoffice.net/oidc`); helpcenter đi trước. Kho đăng nhập qua platform bằng SDK `muakey/platform-laravel` và nhận **Vai trò** từ đó. Mục tiêu của ADR 0008 giữ nguyên: shop quản nhân viên ở một chỗ cho mọi công cụ nội bộ. Kho chưa lên production nên chuyển trong một lượt, không cầu nối hai IdP.

Mỗi **Vai trò** ứng với đúng một Permission trên platform (`admin`, `importer`, `sale`), cộng Permission vào cửa `access`. Platform chỉ trả Permission, không trả role, nên kho không gán quyền lẻ theo việc: giữ một Vai trò là giữ một Permission. **Khoá nhân viên** vẫn nằm ở kho, độc lập với platform.

## Considered Options

- **Permission theo từng việc** (`dispatch.create`, `reveal.view`…). Linh hoạt hơn, nhưng phải viết lại mọi Policy và phá định nghĩa "không gán quyền lẻ" của Vai trò. Với shop 1–10 người thì không đáng.
- **Giữ spatie, đồng bộ role từ Permission.** Thêm một bản sao phải giữ khớp, trong khi SDK đã cache Permission theo Phiên bản quyền.
- **Nhận phiên 7 ngày tuyệt đối của SDK.** Bị loại vì kho có nội dung mã và máy để mở là rủi ro thật; chỉ hạ `SESSION_LIFETIME` thì người dùng tải lại là vào tiếp.

## Consequences

- Thu quyền trên platform có hiệu lực ở request kế tiếp nhờ Phiên bản quyền; không còn đối soát mỗi phút.
- Kho tự kết thúc Phiên App sau 2 giờ không thao tác, lệch so với SDK. Request tự động như poll không gia hạn.
- Đăng nhập bị từ chối và bằng chứng MFA không còn tới kho: ID token của platform không có `amr`, và SDK từ chối trước khi gọi kho. Muốn kho kiểm MFA thì platform phải đưa `amr` vào token.
- Trang Nhân viên chỉ biết Vai trò theo lần cuối kho thấy, và chỉ thấy **Mất quyền theo platform** khi người dùng bị đình chỉ hoặc bị xoá (qua Bản sao danh bạ), vì danh bạ không mang Permission.
- Kho không chặn được việc mất Quản trị cuối cùng; bất biến này nằm ở người quản platform.
- Platform sập thì không ai đăng nhập được vào kho; Người vận hành server vẫn bật được **Tạm dừng xuất kho** qua dòng lệnh. Dev cần VPN và App thử `inventory-dev`, không còn đăng nhập giả.
