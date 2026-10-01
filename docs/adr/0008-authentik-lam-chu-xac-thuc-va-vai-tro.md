# Authentik làm chủ cả xác thực lẫn Vai trò, kho chỉ nhận theo

Kho không còn tự quản đăng nhập hay phân quyền. Nhân viên đăng nhập qua Authentik bằng OIDC, đó là đường duy nhất: không còn mật khẩu, không còn TOTP trong app, không có đường phá kính. Mỗi **Vai trò** ứng với một group trên Authentik. Kho giữ bản sao Vai trò để Policy/Gate chạy như cũ, và cập nhật bản sao này mỗi lần đăng nhập, cộng thêm một lần đối soát mỗi phút qua API Authentik chỉ đọc. Mục tiêu là shop quản nhân viên ở một chỗ cho mọi công cụ nội bộ.

## Considered Options

- **Kho làm chủ Vai trò, Authentik chỉ xác thực.** Kho giữ được bất biến "luôn còn ít nhất một Quản trị" và biết ai đổi quyền của ai. Bị loại vì chủ shop muốn tuyển hay cho nghỉ việc chỉ phải làm ở một chỗ.
- **Giữ đường mật khẩu + TOTP cho Quản trị khi Authentik sập.** Bị loại vì nó giữ nguyên bề mặt tấn công cũ. Khi Authentik sập, **Người vận hành server** vẫn bật được **Tạm dừng xuất kho** qua SSH.
- **SCIM (Authentik đẩy sang).** Nhanh hơn đối soát chừng vài chục giây, nhưng kho phải dựng một API ghi đi vào, mà ai cầm token của API đó là đổi được quyền. Với shop 1–10 người thì không đáng.
- **Forward auth qua reverse proxy.** Kho phải tin header do proxy chèn vào, cấu hình proxy lệch một chút là ai cũng giả được danh tính.

## Consequences

- Kho không chặn được việc mất Quản trị cuối cùng. Bất biến này chuyển sang người quản Authentik.
- **Nhật ký bảo mật** chỉ ghi được rằng kho *nhận thấy* Vai trò đổi. Ai bấm thay đổi thì phải tra trong nhật ký của Authentik.
- Thu quyền bên Authentik có độ trễ tới một chu kỳ đối soát. Phiên đang mở thì back-channel logout của Authentik (issue #108) huỷ ngay khi người dùng bị tắt hay bị xoá phiên, nhưng endpoint đó chỉ huỷ phiên, không đổi quyền, và tính năng còn Preview bên Authentik nên đối soát vẫn là đường chính. Cần chặn ngay thì Quản trị dùng **Khoá nhân viên** trong kho; đối soát không bao giờ mở khoá do Quản trị đặt.
- Authentik sập thì không ai đăng nhập được vào kho.
