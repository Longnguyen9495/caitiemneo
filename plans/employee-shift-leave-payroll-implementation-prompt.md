# PROMPT TRIỂN KHAI CA CỐ ĐỊNH, NGÀY NGHỈ, ĐỔI CA VÀ BẢNG LƯƠNG

Triển khai đầy đủ module ca làm cố định, ngày nghỉ có lương, đơn nghỉ/đổi ca, phân người thay và tính lương theo ngày công cho Laravel project hiện tại.

## Yêu cầu thực hiện

1. Đọc và tuân thủ `AGENTS.md` trước khi sửa code.
2. Kiểm tra trạng thái Git và không ghi đè thay đổi cục bộ.
3. Khảo sát code hiện có liên quan đến nhân viên, lịch phân ca, chấm công, bảng lương, policy, audit và notification.
4. Tạo todo list chi tiết và triển khai tuần tự; cập nhật trạng thái sau từng nhóm công việc.
5. Không chỉ lập kế hoạch: trực tiếp viết migration, model, enum, action/service, request, policy, controller, route, Blade, notification và test cho đến khi hoàn tất.
6. Sau mỗi nhóm chức năng, chạy test liên quan. Cuối cùng chạy formatter và toàn bộ test suite.

## 1. Ca làm cố định

- Mỗi nhân viên có một ca làm cố định: ca sáng hoặc ca chiều.
- Ca cố định phải tham chiếu danh mục ca hiện có, không hard-code giờ làm.
- Cấu hình ca có ngày bắt đầu và ngày kết thúc hiệu lực để không làm sai lịch sử.
- Owner/admin cấu hình ca cố định trong màn hình tạo/sửa nhân viên.
- Hệ thống sinh lịch từng tháng từ ca cố định.
- Việc sinh lịch phải idempotent, không tạo trùng, không tạo ca chồng giờ và không ghi đè lịch đã có chấm công.

## 2. Hai ngày nghỉ hưởng lương mỗi tháng

- Mỗi nhân viên được đúng 2 ngày nghỉ hưởng lương trong từng tháng.
- Admin chủ động xếp 2 ngày nghỉ cụ thể trên lịch tháng; đây không phải ngày nghỉ cố định hằng tuần.
- Giao diện phải cảnh báo nhân viên chưa được xếp đủ 2 ngày hoặc bị xếp quá 2 ngày.
- Số ngày cần làm để hưởng đủ lương cứng bằng số ngày của tháng trừ 2:
  - Tháng 31 ngày: làm 29 ngày.
  - Tháng 30 ngày: làm 28 ngày.
  - Tháng 29 ngày: làm 27 ngày.
  - Tháng 28 ngày: làm 26 ngày.
- Hai ngày nghỉ đã xếp vẫn hưởng nguyên lương cứng.

## 3. Đi làm vào ngày nghỉ

- Chỉ ngày đã được admin xếp là một trong 2 ngày nghỉ hưởng lương nhưng nhân viên thực tế vẫn đi làm mới được thưởng.
- Mỗi ngày như vậy cộng cố định 200.000 đồng, không phụ thuộc mức lương cứng.
- Dùng đủ 2 ngày nghỉ: nhận nguyên lương cứng, không có khoản cộng này.
- Nghỉ 1 ngày và đi làm ngày nghỉ còn lại: nguyên lương cứng cộng 200.000 đồng.
- Không nghỉ ngày nào và đi làm cả 2 ngày nghỉ đã xếp: nguyên lương cứng cộng 400.000 đồng.
- Ngày làm bình thường không được cộng 200.000 đồng.

## 4. Nghỉ vượt quyền lợi và vắng không phép

- Ngày nghỉ thứ 3 trở đi trong tháng hoặc ngày vắng không phép là ngày nghỉ không lương.
- Tiền trừ mỗi ngày bằng lương cứng chia đúng số ngày của tháng:
  - Tháng 31: lương cứng chia 31.
  - Tháng 30: lương cứng chia 30.
  - Tháng 29: lương cứng chia 29.
  - Tháng 28: lương cứng chia 28.
- Sử dụng cơ chế tiền tệ và rounding hiện có của project, không dùng phép toán float tùy tiện.
- Công thức phần lương cố định:

  lương cố định thực nhận = lương cứng - tiền nghỉ không lương + thưởng đi làm ngày nghỉ

  tiền nghỉ không lương = số ngày nghỉ không lương × lương cứng / số ngày của tháng

  thưởng đi làm ngày nghỉ = số ngày nghỉ đã xếp nhưng vẫn đi làm × 200.000

- Các khoản lương ca, hoa hồng, KPI, thưởng/phạt và điều chỉnh hiện có vẫn phải hoạt động.
- Không được tính trùng khoản 200.000 với lương ca hiện tại.

## 5. Đơn xin nghỉ

- Nhân viên tạo đơn nghỉ từ một ca đã được phân cho chính mình.
- Đơn lưu ca, ngày, chi nhánh, lý do, người gửi, trạng thái và lịch sử xử lý.
- Hiển thị số ngày nghỉ hưởng lương còn lại của tháng.
- Admin/manager đúng phạm vi chi nhánh được duyệt hoặc từ chối.
- Khi duyệt, xác định rõ ngày này là nghỉ hưởng lương hay nghỉ không lương dựa trên quyền lợi tháng và lịch nghỉ đã xếp.
- Đơn nghỉ được duyệt phải tạo trạng thái ca cần người thay để admin xử lý.
- Nhân viên chỉ được hủy khi đơn chưa được xử lý.
- Mọi chuyển trạng thái phải được audit.

## 6. Đơn đổi ca

- Nhân viên chọn ca của chính mình và một nhân viên/ca đối ứng hợp lệ.
- Người được đề nghị đổi hoặc nhận ca phải xác nhận hoặc từ chối trước.
- Chỉ sau khi người nhận xác nhận, đơn mới chuyển sang chờ admin duyệt.
- Admin/manager đúng phạm vi chi nhánh duyệt hoặc từ chối.
- Khi admin duyệt, cập nhật các bản ghi lịch liên quan trong transaction.
- Trước khi duyệt phải khóa và kiểm tra lại dữ liệu: ca còn tồn tại, chưa chấm công, chưa nằm trong bảng lương khóa, nhân viên còn thuộc chi nhánh, không chồng giờ và chưa được thay đổi bởi đơn khác.
- Đổi ca hợp lệ không làm mất ngày công của hai nhân viên.

## 7. Phân người làm thay

- Có hàng đợi các ca nghỉ đã duyệt nhưng chưa có người thay.
- Admin thấy danh sách nhân viên rảnh và phù hợp tại chi nhánh, ngày và khung giờ đó.
- Không đề xuất người bị chồng ca, không thuộc chi nhánh hoặc không hoạt động.
- Khi admin phân người thay, cập nhật lịch trong transaction và lưu liên kết giữa ca gốc, đơn nghỉ và người thay.
- Hiển thị rõ ca nào vẫn đang thiếu người.

## 8. Thông báo

- Dùng Laravel database notification và email nếu mail được cấu hình.
- Thông báo cho admin/manager đúng chi nhánh khi có đơn nghỉ mới.
- Thông báo cho người được đề nghị nhận hoặc đổi ca.
- Thông báo cho admin khi người nhận đã xác nhận và đơn sẵn sàng duyệt.
- Thông báo kết quả duyệt, từ chối hoặc hủy cho người gửi và người liên quan.
- Thêm badge số lượng đơn chờ duyệt và ca chưa có người thay trên điều hướng admin.
- Lỗi gửi email không được làm transaction nghiệp vụ thất bại; ưu tiên queue theo convention của project.

## 9. Dữ liệu và kiến trúc

- Tạo migration mới, không sửa migration cũ đã tồn tại.
- Thiết kế model phù hợp cho:
  - Cấu hình ca cố định theo khoảng hiệu lực.
  - Hai ngày nghỉ hưởng lương theo tháng.
  - Yêu cầu nghỉ/đổi ca.
  - Người nhận thay và người duyệt.
  - Lịch sử chuyển trạng thái hoặc tích hợp audit hiện có.
- Dùng enum PHP cho loại đơn và trạng thái.
- Có unique constraint và index phù hợp để chống trùng ngày nghỉ, đơn đang mở và lịch ca.
- Dùng foreign key với quy tắc xóa phù hợp; dữ liệu lịch sử/audit không được mất khi tài khoản bị vô hiệu hóa.
- Các thao tác duyệt, đổi ca và phân người thay phải dùng database transaction và khóa hàng khi cần.
- Tận dụng `ShiftAssignment`, `WorkShift`, `AttendanceRecord`, `EmployeeCompensationProfile` và audit infrastructure hiện có thay vì tạo luồng song song không cần thiết.
- Bảo toàn branch isolation và policy hiện tại.

## 10. Tích hợp bảng lương

Điều chỉnh `CalculatePayrollAction::refresh()` và các thành phần liên quan để:

1. Xác định số ngày của tháng/kỳ.
2. Xác định số ngày cần làm bằng số ngày tháng trừ 2.
3. Đếm ngày nghỉ hưởng lương đã dùng.
4. Đếm ngày nghỉ vượt mức và vắng không phép.
5. Đếm ngày đã được xếp nghỉ nhưng nhân viên vẫn đi làm.
6. Tính tiền nghỉ không lương theo lương cứng chia số ngày tháng.
7. Tính thưởng đi làm ngày nghỉ theo 200.000 đồng/ngày.
8. Không làm hỏng lương ca, hoa hồng, KPI và adjustment hiện có.
9. Snapshot vào bảng lương tối thiểu:
   - Số ngày trong tháng.
   - Số ngày cần làm để đủ lương.
   - Số ngày nghỉ hưởng lương.
   - Số ngày nghỉ không lương/vắng không phép.
   - Đơn giá lương ngày.
   - Khoản lương bị trừ.
   - Số ngày nghỉ vẫn đi làm.
   - Mức thưởng ngày nghỉ.
   - Tổng thưởng đi làm ngày nghỉ.
   - Lương cứng thực nhận.
10. Phiếu lương đã finalize/paid không thay đổi khi lịch, mức lương hoặc policy sau đó bị sửa.
11. Cập nhật trang bảng lương, phiếu lương và CSV để hiển thị đầy đủ breakdown.

## 11. Phân quyền

- Employee: xem lịch của mình, tạo/xem/hủy đơn hợp lệ của mình, xác nhận/từ chối lời mời đổi hoặc nhận ca dành cho mình.
- Manager: xem và xử lý trong các chi nhánh được giao; không truy cập dữ liệu chi nhánh khác.
- Owner: toàn quyền.
- Không tin ID từ request; validate mọi employee, shift và branch theo phạm vi actor.
- Ca đã có chấm công hoặc thuộc bảng lương khóa không được sửa, xóa hoặc đổi trái quy tắc.

## 12. Giao diện

Giữ convention Blade/component và giao diện hiện có. Bổ sung:

- Cấu hình ca cố định trong form nhân viên.
- Lịch tháng để admin xếp 2 ngày nghỉ.
- Trang nhân viên gửi và theo dõi đơn.
- Trang người nhận xác nhận đổi/nhận ca.
- Trang admin duyệt đơn và xử lý ca thiếu người.
- Badge trạng thái và số lượng chờ xử lý.
- Breakdown lương rõ ràng bằng tiếng Việt.
- Form phải giữ input cũ khi validation lỗi và hiển thị thông báo dễ hiểu.

## 13. Test bắt buộc

Viết đầy đủ feature/unit test cho:

1. Cấu hình ca sáng/chiều cố định có ngày hiệu lực.
2. Sinh lịch idempotent cho tháng 28, 29, 30 và 31 ngày.
3. Admin xếp đúng 2 ngày nghỉ; cảnh báo thiếu/thừa.
4. Nhân viên không xem/sửa dữ liệu người khác.
5. Manager không truy cập chi nhánh ngoài phạm vi.
6. Đơn nghỉ: tạo, hủy, duyệt, từ chối và trạng thái ca cần người thay.
7. Đơn đổi ca: người nhận xác nhận/từ chối, admin duyệt/từ chối.
8. Chặn đổi ca chồng giờ, ca đã chấm công, ca đã thay đổi và kỳ lương đã khóa.
9. Chống xử lý đồng thời: hai người nhận cùng một ca hoặc hai admin duyệt cùng lúc.
10. Phân người thay và danh sách ứng viên hợp lệ.
11. Database notification và recipient đúng phạm vi.
12. Lương tháng 28, 29, 30 và 31 ngày.
13. Nghỉ đủ 2 ngày vẫn nguyên lương cứng.
14. Nghỉ 1 ngày và đi làm ngày nghỉ còn lại: cộng 200.000 đồng.
15. Không nghỉ và đi làm cả 2 ngày nghỉ: cộng 400.000 đồng.
16. Nghỉ vượt 2 ngày hoặc vắng không phép: trừ đúng lương cứng chia số ngày tháng cho mỗi ngày.
17. Không cộng 200.000 đồng cho ngày làm bình thường.
18. Đổi ca hợp lệ không làm mất công.
19. Không tính trùng thưởng ngày nghỉ với lương ca.
20. Snapshot bảng lương không đổi sau khi finalize/paid.
21. Toàn bộ test cũ vẫn chạy thành công.

## 14. Trình tự triển khai

1. Migration và enum.
2. Model, relation và factory.
3. Policy và validation.
4. Service sinh lịch và xếp ngày nghỉ.
5. Luồng đơn nghỉ.
6. Luồng đổi ca và xác nhận người nhận.
7. Admin duyệt và phân người thay.
8. Notification và badge.
9. Tích hợp bảng lương và snapshot.
10. Giao diện và CSV.
11. Audit trail.
12. Test từng module.
13. Formatter và toàn bộ test suite.

## 15. Ràng buộc triển khai

- Không bỏ qua hoặc sửa test lỗi chỉ để làm suite xanh.
- Không reset database hoặc xóa dữ liệu ngoài test environment.
- Không làm thay đổi kết quả bảng lương đã finalize hoặc paid.
- Nếu phát hiện xung đột với schema hiện tại, ưu tiên bảo toàn dữ liệu và backward compatibility, đồng thời ghi rõ quyết định kỹ thuật.
- Khi hoàn tất, báo cáo:
  - Danh sách file thay đổi.
  - Migration cần chạy.
  - Luồng sử dụng mới cho employee/manager/owner.
  - Test và formatter đã chạy.
  - Mọi lưu ý khi deploy.
