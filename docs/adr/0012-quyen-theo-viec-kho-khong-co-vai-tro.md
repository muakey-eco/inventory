# Quyền theo việc; kho không có Vai trò

Thay phần Vai trò của ADR 0011; phần xác thực và idle timeout của ADR 0011 giữ nguyên. Platform chỉ trả Permission, nên ba Vai trò của kho (`admin`, `importer`, `sale`) vốn chỉ là ba Permission thô mang tên nhóm, còn nhóm thật nằm ở `roles` của `platform.yaml`. Kho giữ một bản sao của khái niệm nhóm, và một Quản trị được qua mọi cửa một cách ngầm định.

Kho chỉ biết **Quyền**: mỗi việc nghiệp vụ mà shop có thể muốn giao riêng có một Permission (`intake`, `dispatch`, `replacement.approve`, `deadline.override`, `staff.manage`…), và mỗi thao tác kiểm đúng Quyền của nó. Không Quyền nào bao trùm Quyền khác, kể cả với chủ shop. Gom Quyền thành nhóm, và có cho gán lẻ cho một người hay không, là chính sách của người quản platform. Các ngoại lệ trước đây dành cho Quản trị (khỏi duyệt Đổi hàng từ lần thứ 3, Báo lỗi ngoài Hạn bảo hành, Giao thay quá hạn) gắn vào Quyền của chính việc vượt đó. Bất biến "còn người mở được **Khoá nhân viên**" chuyển sang theo `staff.manage`.

## Considered Options

- **Chỉ đổi tên Vai trò thành Quyền, giữ ba quyền thô.** Từ vựng khớp hơn nhưng hành vi không đổi, và Quản trị vẫn là một nhóm trá hình trong kho; không đáng đụng mọi Policy.
- **Giữ một quyền bao trùm (`admin` hay `*`).** Đỡ phải liệt kê mọi Quyền cho chủ shop, nhưng dựng lại Vai trò Quản trị ngầm, và trang Nhân viên không nói thật một người làm được gì.
- **Quyền theo từng nút CRUD** (`batch.create`, `batch.view`…). Mịn hơn mức shop 1–10 người cần giao việc, và danh sách Quyền dài tới mức người quản platform không đọc nổi.

## Consequences

- Thêm một Quyền mà quên thêm vào role `admin` trong `platform.yaml` thì chủ shop mất việc đó; một test soát role `admin` chứa mọi Permission khai trong manifest.
- Ba role `admin`, `importer`, `sale` trên platform gom Quyền đúng theo phạm vi của ba Vai trò cũ, nên lúc chuyển không ai được thêm hay bị bớt việc.
- Trang Nhân viên hiện bản chụp Quyền theo nhóm; Nhật ký bảo mật ghi Quyền được thêm và bị bớt thay cho Vai trò đổi.
- Đổi tên một Permission trong `platform.yaml` là mọi người mất việc đó tới khi platform sync lại, giống ADR 0011 nhưng nay xảy ra với nhiều Permission hơn.
