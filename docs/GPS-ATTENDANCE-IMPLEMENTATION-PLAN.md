# Kế hoạch triển khai chấm công GPS

## 1. Mục tiêu

Phát triển MVP chấm công GPS trên website Laravel hiện tại.

Trong giai đoạn đầu:

- GPS là hình thức chấm công tự động duy nhất.
- Chưa triển khai QR, vân tay, nhận diện khuôn mặt, selfie, ràng buộc thiết bị hoặc ứng dụng native.
- Nhân viên đăng nhập bằng điện thoại, bấm **Vào ca** khi đến cửa hàng và **Ra ca** khi kết thúc công việc.
- Form quản trị nhập thủ công hiện tại được giữ lại để xử lý ngoại lệ, không phải hình thức chấm công chính của nhân viên.

## 2. Nghiệp vụ đã thống nhất

### 2.1. Quy trình làm việc

- Nhân viên được quản lý phân ca trước, thường theo tuần.
- Các khung ca phổ biến:
  - 09:00–19:00.
  - 10:00–20:00.
  - 11:00–21:00.
  - Ca dài 09:00–20:00.
- Một nhân viên thông thường làm một ca trong ngày.
- Nhân viên không được đi muộn.
- Nghỉ phải báo trước khoảng 2–3 ngày.
- Tăng ca có thể phát sinh khi khách đông hoặc dịch vụ kéo dài.
- Tăng ca đôi khi chỉ xác định được sau khi hoàn thành khách.
- Tăng ca phải được quản lý duyệt trước khi ảnh hưởng đến lương.

### 2.2. Trải nghiệm nhân viên

1. Nhân viên mở website trên điện thoại và đăng nhập tài khoản cá nhân.
2. Mở trang chấm công.
3. Xem ca được phân trong ngày.
4. Cấp quyền truy cập vị trí cho trình duyệt.
5. Bấm **Vào ca**.
6. Hệ thống tự kiểm tra tài khoản, lịch ca, chi nhánh, thời gian và GPS.
7. Cuối ca, nhân viên bấm **Ra ca**.
8. Hệ thống tự ghi nhận đi trễ và thời gian vượt ca.
9. Chỉ tăng ca đã được duyệt mới được sử dụng cho nghiệp vụ lương.

Nhân viên không tự nhập ngày, giờ, tên ca, trạng thái, tọa độ hoặc mã nhân viên. Các dữ liệu có thể xác định được phải do máy chủ lấy từ tài khoản đăng nhập, lịch phân ca và thời gian máy chủ.

## 3. Hiện trạng hệ thống

Hệ thống đã có:

- Tài khoản và vai trò owner, manager, employee trong `app/Models/User.php` và `app/Enums/UserRole.php`.
- Phân công nhân viên theo chi nhánh qua employee branch assignments.
- Model chấm công `app/Models/AttendanceRecord.php`.
- Trạng thái đi làm, đi trễ, nghỉ phép và vắng trong `app/Enums/AttendanceStatus.php`.
- Các trường ngày làm, tên ca, hệ số ca, giờ vào và giờ ra trong bảng `attendance_records`.
- CRUD quản lý chấm công trong `app/Http/Controllers/Admin/AttendanceController.php`.
- Form quản lý nhập chấm công thủ công.
- Cách ly dữ liệu theo chi nhánh.
- Cơ chế khóa dữ liệu chấm công thuộc kỳ lương đã finalized hoặc paid.
- Chỉ trạng thái present và late được tính là ca payable.

Hệ thống chưa có:

- Tọa độ và bán kính GPS của chi nhánh.
- Danh mục ca có giờ bắt đầu và kết thúc chuẩn.
- Lịch phân ca theoអ? Wait typo. Need fix before call sent? already composing JSON. Correct content. Continue no typo. Need ensure complete. 
- Trang nhân viên tự chấm công.
- Bằng chứng GPS cho giờ vào và giờ ra.
- Tự xác định/đi trễ và tính thời gian vượt ca.
- Quy trình duyệt tăng ca và điều chỉnh ngoại lệ có audit log.

> Lưu ý: `shift_name` hiện chỉ là chuỗi tự nhập. Muốn tự xác định đi trễ và tăng ca chính xác thì phải bổ sung danh mục ca và lịch phân ca trước hoặc đồng thời với GPS.

## 4. Nguyên tắc GPS

- Chỉ lấy vị trí khi nhân viên chủ động bấm Vào ca hoặc Ra ca.
- Không theo dõi vị trí liên tục hoặc chạy nền.
- Website production phải sử dụng HTTPS.
- Tọa độ và accuracy được nhận từ trình duyệt, nhưng khoảng cách phải tính lại tại máy chủ.
- Không tin thời gian, employee ID, branch ID, khoảng cách hoặc trạng thái do client gửi.
- Bán kính mặc định đề xuất: 100 mét.
- Ngưỡng accuracy mặc định đề xuất: 150 mét.
- Khu vực GPS kém có thể cấu hình bán kính 150–200 mét theo từng chi nhánh.
- Ngoài bán kính hoặc accuracy vượt ngưỡng phải bị từ chối rõ ràng hoặc chuyển thành yêu cầu ngoại lệ theo thiết kế được chọn.
- GPS không chống gian lận tuyệt đối; MVP kết hợp tài khoản cá nhân, lịch ca, thời gian máy chủ, branch assignment, IP, user agent và audit log.

## 5. Kế hoạch triển khai

### Giai đoạn 1 — Danh mục ca, lịch phân ca và GPS chi nhánh

- Tạo danh mục ca làm việc.
- Hỗ trợ tên ca, giờ bắt đầu, giờ kết thúc, hệ số ca, số phút ân hạn và trạng thái hoạt động.
- Hỗ trợ ca qua ngày.
- Tạo lịch phân ca theo nhân viên, ngày và chi nhánh.
- Lưu snapshot thời gian dự kiến để việc sửa danh mục ca không làm thay đổi lịch sử.
- Tạo giao diện quản lý ca và phân ca theo tuần.
- Bổ sung latitude, longitude, bán kính, ngưỡng accuracy và cờ bật GPS cho chi nhánh.

### Giai đoạn 2 — Nhân viên tự Vào ca/Ra ca

- Tạo trang chấm công mobile-first dành riêng cho nhân viên.
- Hiển thị ca hôm nay, chi nhánh, khung giờ, trạng thái và giờ đã ghi nhận.
- Dùng Geolocation API lấy vị trí khi bấm nút.
- Tạo endpoint Vào ca và Ra ca.
- Tách nghiệp vụ sang action/service, giữ controller mỏng.
- Tính khoảng cách tại server bằng Haversine.
- Dùng transaction, unique constraint và row lock khi cần để chống gửi lặp/race condition.

### Giai đoạn 3 — Đi trễ, tăng ca và ngoại lệ

- Tự xác định present hoặc late từ lịch và thời gian máy chủ.
- Tính late minutes.
- Tính thời gian vượt planned end.
- Overtime mới phát sinh mặc định ở trạng thái pending.
- Owner/manager được duyệt, từ chối hoặc điều chỉnh overtime.
- Quên chấm công do quản lý bổ sung kèm lý do.
- Mọi thay đổi thủ công phải có audit log trước/sau, người sửa và thời gian sửa.

### Giai đoạn 4 — Tích hợp lương và báo cáo

- Giữ nguyên nguyên tắc chỉ present và late được tính lương ca.
- Chỉ overtime đã duyệt mới được sử dụng cho nghiệp vụ lương.
- Nếu chưa có công thức tiền tăng ca, chỉ lưu và duyệt số phút; không tự suy đoán mức tiền.
- Bảo vệ bản ghi thuộc kỳ lương finalized/paid.
- Báo cáo đi trễ, ngoài vùng, thiếu giờ ra, overtime pending và chỉnh sửa thủ công.

### Giai đoạn 5 — Kiểm thử và bảo mật

- Feature test authorization và branch isolation.
- Unit test tính khoảng cách.
- Test Vào ca/Ra ca, gửi lặp, ca qua ngày, đi trễ và overtime.
- Rate limit endpoint chấm công.
- Không ghi tọa độ nhạy cảm vào application log.
- Chạy formatter và toàn bộ test suite.

## 6. Tiêu chí hoàn thành MVP

- Quản lý cấu hình được tọa độ và bán kính cho chi nhánh.
- Quản lý tạo được ca và phân ca theo ngày/tuần.
- Nhân viên xem được ca hôm nay trên điện thoại.
- Nhân viên Vào ca và Ra ca bằng GPS.
- Không thể chấm thay tài khoản khác hoặc đổi branch ID tùy ý.
- Không thể chấm từ ngoài vùng hoặc với GPS không đủ chính xác.
- Hệ thống xác định đúng present/late và late minutes.
- Thời gian vượt ca được ghi nhận ở trạng thái chờ duyệt.
- Employee không thể tự duyệt overtime hoặc sửa giờ.
- Quản lý xử lý được ngoại lệ và có audit log.
- Dữ liệu thuộc bảng lương đã chốt được bảo vệ.
- Test mới và toàn bộ test cũ chạy thành công.

---

# Prompt triển khai code

Sao chép nguyên prompt sau để chạy bằng code agent:

```text
Hãy triển khai MVP chấm công GPS cho ứng dụng Laravel hiện tại.

Trước khi sửa code:
1. Đọc kỹ AGENTS.md và thực hiện đầy đủ hướng dẫn dự án.
2. Khảo sát composer.json, routes/web.php, migrations, models, policies, requests, controllers, views, factories và tests liên quan.
3. Xác nhận hiện trạng thực tế trước khi thiết kế; không giả định dự án đã có lịch phân ca.
4. Lập TODO theo từng giai đoạn và triển khai tuần tự.
5. Tuân thủ kiến trúc, coding convention, authorization, branch isolation và quy tắc bảng lương hiện hữu.

BỐI CẢNH NGHIỆP VỤ

Cửa hàng hiện chưa có hình thức chấm công tự động. Giai đoạn đầu chỉ sử dụng GPS trên website, chưa triển khai QR, vân tay, nhận diện khuôn mặt, selfie, device binding hoặc ứng dụng native.

Nhân viên đăng nhập bằng điện thoại, đến cửa hàng bấm Vào ca và cuối ngày bấm Ra ca. Các ca phổ biến là 09:00–19:00, 10:00–20:00, 11:00–21:00 và 09:00–20:00. Nhân viên được phân ca trước, thông thường một ca mỗi ngày. Hệ thống tự phát hiện đi trễ. Tăng ca có thể phát sinh khi làm khách kéo dài và phải được quản lý duyệt trước khi ảnh hưởng đến lương. Nghỉ/vắng vẫn do quản lý quản trị.

HIỆN TRẠNG PHẢI BẢO TOÀN

- Model chấm công hiện có: app/Models/AttendanceRecord.php.
- Enum trạng thái: app/Enums/AttendanceStatus.php.
- CRUD thủ công: app/Http/Controllers/Admin/AttendanceController.php.
- Form thủ công phải được giữ để xử lý ngoại lệ.
- Bản ghi thuộc kỳ lương finalized/paid không được sửa/xóa theo cơ chế hiện tại.
- Quyền chi nhánh đang dựa trên User::accessibleBranchIds() và branch assignments.
- Chỉ AttendanceStatus::payableValues() được tính lương ca.
- Không làm hỏng test chấm công, chi nhánh và bảng lương hiện có.

MỤC TIÊU 1 — DANH MỤC CA VÀ LỊCH PHÂN CA

Thiết kế model/bảng danh mục ca làm việc với tối thiểu:
- branch_id hoặc khả năng dùng chung toàn hệ thống theo thiết kế phù hợp hiện trạng.
- name.
- starts_at dạng giờ.
- ends_at dạng giờ.
- shift_value.
- grace_minutes.
- is_active.

Thiết kế model/bảng lịch phân ca với tối thiểu:
- branch_id.
- employee_id.
- work_shift_id.
- work_date.
- planned_start_at và planned_end_at dạng datetime snapshot.
- Ràng buộc chống phân trùng không hợp lệ.

Hỗ trợ ca qua ngày khi ends_at nhỏ hơn hoặc bằng starts_at. Snapshot thời gian dự kiến phải bảo đảm sửa danh mục ca không làm thay đổi lịch sử.

Tạo giao diện quản lý ca và phân ca theo tuần phù hợp UI hiện tại. Owner/manager được quản lý; employee chỉ xem lịch của chính mình.

MỤC TIÊU 2 — CẤU HÌNH GPS CHI NHÁNH

Mở rộng Branch và form chi nhánh với:
- latitude dạng decimal đủ chính xác.
- longitude dạng decimal đủ chính xác.
- attendance_radius_meters, mặc định 100.
- attendance_accuracy_limit_meters, mặc định 150.
- gps_attendance_enabled.

Validation:
- latitude từ -90 đến 90.
- longitude từ -180 đến 180.
- bán kính và ngưỡng accuracy phải nằm trong giới hạn hợp lý.
- Tránh dùng float để lưu tọa độ nếu có thể.

MỤC TIÊU 3 — BẰNG CHỨNG CHẤM CÔNG GPS

Mở rộng attendance_records hoặc thiết kế attendance events riêng để lưu tối thiểu:
- shift assignment liên quan.
- nguồn tạo: GPS hoặc admin/manual.
- check-in latitude, longitude, accuracy, calculated distance, IP, user agent.
- check-out latitude, longitude, accuracy, calculated distance, IP, user agent.
- trạng thái xác minh GPS cho lúc vào và ra.
- late_minutes.
- overtime_minutes.
- approved_overtime_minutes.
- overtime approval status.
- approved_by, approved_at và approval_note.

Thiết kế phải tương thích ngược với bản ghi hiện tại. Dùng enum cho nguồn chấm công, trạng thái GPS và trạng thái duyệt overtime nếu phù hợp.

MỤC TIÊU 4 — TRANG CHẤM CÔNG NHÂN VIÊN

Tạo route/controller riêng ngoài CRUD quản trị, có auth, verified và role phù hợp.

Trang mobile-first hiển thị:
- Nhân viên đang đăng nhập.
- Ngày/giờ hiện tại.
- Chi nhánh và ca hôm nay.
- Giờ bắt đầu/kết thúc dự kiến.
- Trạng thái chưa vào ca/đang làm/đã ra ca.
- Giờ vào/ra đã ghi nhận.
- Nút Vào ca hoặc Ra ca tương ứng.
- Thông báo tiếng Việt rõ ràng khi từ chối quyền GPS, GPS tắt, timeout, accuracy kém hoặc ngoài bán kính.

Dùng browser Geolocation API với enableHighAccuracy và timeout hợp lý. Chỉ lấy vị trí khi người dùng bấm, không watchPosition và không theo dõi nền.

MỤC TIÊU 5 — ENDPOINT VÀO CA/RA CA

Tạo Form Request, action/service và controller mỏng.

Khi Vào ca:
- Luôn lấy employee_id từ auth user; không nhận hoặc tin employee_id từ request.
- Lấy thời gian từ server.
- Tìm lịch hợp lệ của nhân viên và đúng chi nhánh.
- Xác nhận branch assignment có hiệu lực trong ngày.
- Kiểm tra chi nhánh đã bật và cấu hình GPS.
- Validate latitude, longitude, accuracy.
- Tính khoảng cách phía server bằng Haversine qua service/value object có unit test.
- Không tin distance client gửi.
- Từ chối nếu accuracy vượt ngưỡng hoặc khoảng cách vượt bán kính, với thông báo rõ ràng.
- Cho phép vào sớm tối đa 30 phút; đưa thành config hoặc thuộc tính ca để dễ thay đổi.
- Tính trạng thái present/late và late_minutes từ grace_minutes.
- Tạo/cập nhật AttendanceRecord bằng transaction, chống gửi lặp và race condition.

Khi Ra ca:
- Chỉ cho ra khi có phiên đang mở của chính người đăng nhập.
- Kiểm tra GPS giống lúc vào.
- checked_out_at lấy từ server và phải sau checked_in_at.
- Tính overtime_minutes so với planned_end_at.
- Overtime phát sinh ở trạng thái pending, không tự động tính lương.
- Chống ra ca hai lần và race condition.

Dùng transaction, unique constraint, row lock khi cần, CSRF và rate limiting.

MỤC TIÊU 6 — NGOẠI LỆ, DUYỆT OVERTIME VÀ AUDIT

Tạo màn hình owner/manager để:
- Xem bản ghi ngoài vùng hoặc yêu cầu ngoại lệ nếu thiết kế có lưu yêu cầu.
- Xem bản ghi thiếu giờ vào/ra.
- Xem overtime pending.
- Duyệt, từ chối hoặc điều chỉnh approved_overtime_minutes.
- Bổ sung chấm công bị quên kèm lý do.

Mọi chỉnh sửa thủ công phải lưu audit log gồm người sửa, thời điểm, lý do, giá trị trước và sau. Employee không được tự sửa giờ hoặc duyệt overtime. Manager chỉ xử lý chi nhánh được phép; owner có quyền toàn hệ thống. Chặn thay đổi ảnh hưởng lương nếu payroll đã finalized/paid.

MỤC TIÊU 7 — TÍCH HỢP LƯƠNG

- Không thay đổi ngoài ý muốn cách tính lương hiện tại.
- Chỉ status thuộc AttendanceStatus::payableValues() được tính lương ca.
- Chỉ overtime đã duyệt mới được dùng cho nghiệp vụ lương.
- Nếu chưa có công thức tiền tăng ca, chỉ lưu và duyệt số phút; không tự đặt mức tiền hoặc tự cộng payroll.
- Thiết kế dữ liệu để sau này có thể bổ sung chính sách tiền tăng ca.

MỤC TIÊU 8 — AUTHORIZATION VÀ BRANCH ISOLATION

- Employee chỉ xem lịch và chấm công của chính mình.
- Manager chỉ quản lý dữ liệu thuộc chi nhánh được phép.
- Owner giữ quyền toàn hệ thống như hiện tại.
- Không cho client đổi employee_id hoặc branch_id để chấm hộ/chấm sai chi nhánh.
- Dùng policy, Form Request và service/action phù hợp convention dự án.

TESTS BẮT BUỘC

Viết unit/feature tests cho ít nhất:
- Guest không truy cập được trang/endpoint chấm công.
- Employee chỉ thấy lịch và bản ghi của mình.
- Không có lịch thì không được Vào ca.
- Chưa cấu hình GPS thì báo lỗi rõ ràng.
- GPS trong bán kính và đủ accuracy thì Vào ca thành công.
- GPS ngoài bán kính bị từ chối.
- Accuracy vượt ngưỡng bị từ chối.
- Không thể giả employee_id hoặc branch_id.
- Đúng giờ tạo present.
- Đi trễ tạo late và đúng late_minutes.
- Không thể Vào ca hai lần.
- Không thể Ra ca khi chưa Vào ca.
- Ra ca hợp lệ lưu GPS và giờ server.
- Không thể Ra ca hai lần.
- Ca qua ngày xử lý đúng.
- Vượt giờ tạo overtime pending.
- Employee không được duyệt overtime.
- Manager sai chi nhánh không được duyệt.
- Manager đúng chi nhánh và owner được duyệt.
- Payroll finalized/paid khóa thay đổi ảnh hưởng lương.
- Admin vẫn nhập thủ công được với audit log và lý do.
- Test cũ vẫn thành công.

Dùng factories, không phụ thuộc seed data. Dùng freeze/time travel để test thời gian. Test Haversine với tọa độ biết trước và các điểm sát ranh bán kính.

YÊU CẦU CHẤT LƯỢNG

- Controller mỏng; nghiệp vụ nằm trong action/service.
- Dùng enum, casts, indexes, foreign keys và database constraints hợp lý.
- Không lưu GPS nhiều hơn mức cần thiết.
- Không ghi tọa độ vào application log.
- Giao diện và thông báo bằng tiếng Việt, đồng nhất UI hiện tại.
- Không thêm package không cần thiết.
- Không tạo public API nếu web routes và POST forms đáp ứng MVP.
- Không triển khai tracking nền, QR, selfie, face recognition hoặc device binding.

TRÌNH TỰ THỰC HIỆN

1. Khảo sát và trình bày thiết kế ngắn gọn.
2. Viết migrations, models, enums và factories.
3. Viết distance service và actions check-in/check-out.
4. Viết authorization và validation.
5. Viết UI cấu hình chi nhánh, ca, lịch tuần và trang nhân viên.
6. Viết UI duyệt overtime/ngoại lệ và audit log.
7. Viết tests song song từng phần.
8. Chạy formatter và toàn bộ test suite.
9. Báo cáo file đã đổi, quyết định thiết kế, test đã chạy và phần cố ý chưa triển khai.

Ưu tiên MVP an toàn, có test, tương thích ngược và chạy được. Nếu thiếu nghiệp vụ về công thức tiền tăng ca thì không tự suy đoán; chỉ hoàn thiện ghi nhận và duyệt thời lượng.
```
