# PROMPT TRIỂN KHAI LỊCH CHẤM CÔNG, ĐỐI SOÁT LƯƠNG VÀ MENU QUẢN TRỊ DẠNG NHÓM

Triển khai đầy đủ kế hoạch trong `plans/attendance-payroll-calendar-implementation-plan.md` cho Laravel project Cái Tiệm Neo hiện tại.

Mục tiêu gồm:

1. Gom menu quản trị thành các nhóm gọn, dễ dùng trên mobile và đồng bộ với desktop.
2. Tạo lịch chấm công tháng cho nhân viên.
3. Tạo lịch chấm công quản trị với chế độ Tổng quan và Nhân viên.
4. Tạo lịch đối soát chỉ đọc trong chi tiết bảng lương.
5. Không làm thay đổi công thức lương, GPS, phân quyền, branch scope, audit, payroll lock hoặc các luồng nghiệp vụ đang hoạt động.

## 1. Quy trình bắt buộc

1. Đọc và tuân thủ `AGENTS.md` trước khi sửa code.
2. Đọc toàn bộ `plans/attendance-payroll-calendar-implementation-plan.md`; coi đây là nguồn yêu cầu chính và checklist nghiệm thu.
3. Kiểm tra PHP, Composer, trạng thái Git và các thay đổi cục bộ. Không ghi đè hoặc hoàn tác thay đổi không thuộc nhiệm vụ.
4. Khảo sát lại code hiện tại trước khi sửa, đặc biệt navigation, attendance, payroll, policy, branch scope, audit, frontend và test.
5. Tạo todo list chi tiết theo các giai đoạn trong plan và cập nhật trạng thái trong quá trình làm.
6. Không chỉ phân tích hoặc lập kế hoạch: trực tiếp triển khai code, test, style và tích hợp cho đến khi hoàn thành.
7. Làm theo từng giai đoạn nhỏ; sau mỗi giai đoạn chạy test mục tiêu trước khi chuyển tiếp.
8. Ưu tiên test-first hoặc characterization test trước khi refactor các phần đang hoạt động.
9. Không deploy production nếu chưa được người dùng phê duyệt rõ ràng. Hoàn tất local, báo cáo kết quả và hướng dẫn deploy trước.

## 2. Phạm vi thay đổi

### 2.1. Menu quản trị dạng nhóm

Khảo sát và mở rộng cấu trúc hiện có tại tối thiểu:

- `app/Support/AdminNavigation.php`
- `resources/views/components/admin/nav-items.blade.php`
- `resources/views/admin/partials/mobile-menu.blade.php`
- `resources/views/components/layouts/admin.blade.php`
- `resources/scss/_neo-admin.scss`
- Các test authorization/navigation/badge hiện hữu.

Cấu trúc nhóm dự kiến:

1. **Truy cập nhanh**
   - Tổng quan
   - Chấm công của tôi
   - Lịch hẹn
2. **Bán hàng & tài chính**
   - Hóa đơn & thu chi
   - Báo cáo
3. **Nhân sự & ca làm**
   - Chấm công & lương hoặc Lương của tôi tùy quyền
   - Đơn ca & nghỉ
   - Nhân sự
4. **Vận hành cửa hàng**
   - Kho vật tư
   - Dịch vụ
   - Chi nhánh
5. **Kiểm soát**
   - Cảnh báo

Yêu cầu:

- Dữ liệu nhóm phải xuất phát từ `AdminNavigation`; không hard-code danh sách độc lập trong từng Blade.
- Giữ nguyên route, route pattern, icon, primary state, nhãn theo role, visibility và badge hiện có.
- Chỉ group sau khi đã lọc Policy/Gate; không render nhóm rỗng.
- Nhóm chứa route active phải tự mở khi server render.
- Parent có active state dễ nhận biết; chỉ child hiện tại dùng `aria-current="page"`.
- Dùng Bootstrap Collapse hiện có; không thêm thư viện frontend nếu không thật sự cần.
- Nút nhóm phải là button thật với `aria-expanded`, `aria-controls` và nhãn truy cập phù hợp.
- Badge “Đơn ca & nghỉ” phải tiếp tục đúng branch scope. Khi nhóm đóng, parent có thể hiển thị tổng badge của các child được phép xem.
- Không chạy lại truy vấn badge chỉ để tạo group hoặc render nhiều bề mặt.
- Sidebar desktop và offcanvas mobile dùng chung component/nguồn dữ liệu.
- Bottom tab bar vẫn lấy tối đa bốn mục `primary` dạng phẳng và giữ nút “Thêm”.
- Profile, xem website và đăng xuất vẫn nằm ở footer riêng.
- Có progressive enhancement: nếu JavaScript không chạy, người dùng vẫn nhìn thấy và truy cập được các child link.
- Offcanvas cuộn độc lập, không bị footer hoặc safe-area che nội dung.
- Touch target tối thiểu 44px, focus rõ ràng, không chỉ dựa vào màu, hỗ trợ `prefers-reduced-motion`.

### 2.2. Lịch chấm công cá nhân

Giữ nguyên khối thao tác hiện tại:

- Ca hôm nay.
- Vào ca/Ra ca.
- GPS và payload tọa độ.
- Cảnh báo đi muộn.
- Tăng ca chờ duyệt.

Bổ sung lịch tháng:

- Tuần bắt đầu thứ Hai.
- Lưới đủ tuần chứa tháng, thường 35 hoặc 42 ô.
- Điều hướng tháng trước, tháng sau và tháng hiện tại.
- Hiển thị ngày ngoài tháng với tone mờ.
- Hiển thị giờ vào/ra, trạng thái, cảnh báo và chỉ báo nhiều ca.
- Chạm/chọn ngày để xem đầy đủ các ca của chính nhân viên.
- Nhân viên thường không có nút sửa/xóa.
- Không nhận `employee_id` từ request để quyết định người được xem; luôn ràng buộc bằng tài khoản đăng nhập.

### 2.3. Lịch quản trị chấm công

Có hai chế độ:

#### Tổng quan

- Mặc định khi chưa chọn nhân viên.
- Mỗi ngày hiển thị số người hoặc số bản ghi và số cảnh báo.
- Chọn ngày để xem tất cả nhân viên và tất cả ca trong phạm vi `BranchContext`.
- Cảnh báo tối thiểu: thiếu check-out, đi muộn, vắng, GPS cần xem lại, tăng ca chờ duyệt và self-recorded.

#### Nhân viên

- Chỉ cho chọn nhân viên thuộc phạm vi actor/chi nhánh.
- Hiển thị chi tiết giờ vào/ra và các ca của người đã chọn.
- Nút tạo/sửa/xóa chỉ render và thực thi khi `AttendanceRecordPolicy` cho phép.
- Manager không được tạo/sửa/xóa công của chính mình.
- Owner self-record tiếp tục đi qua behavior review/audit hiện có.

Giữ nguyên:

- Quick manual attendance form.
- Shortcut tăng ca chờ duyệt.
- Route và action ghi hiện hữu.
- Filter tháng, trạng thái, nguồn và branch context.

### 2.4. Lịch đối soát bảng lương

- Tích hợp vào trang chi tiết payroll.
- Chỉ đọc ở mọi trạng thái.
- Lấy đúng `employee_id`, `period_start` và `period_end` từ model payroll, không lấy từ query string.
- Hiển thị nguồn attendance, ca tính lương, nghỉ, vắng, đi muộn, tăng ca và KPI ngày nếu có.
- Draft hiển thị cảnh báo dữ liệu có thể thay đổi sau khi tính lại.
- Finalized/paid hiển thị trạng thái khóa và không có link sửa attendance.
- Không tự gọi `CalculatePayrollAction` khi chỉ mở trang.
- Không thay các partial payslip, allocation, KPI, adjustment và action tài chính hiện có ngoài phần tích hợp cần thiết.

## 3. Kiến trúc lịch bắt buộc

Tạo lớp dùng chung, tách biệt trách nhiệm:

1. Calendar range builder:
   - Parse tháng an toàn.
   - Business timezone.
   - Tuần bắt đầu thứ Hai.
   - Lưới 35/42 ô.
   - Ranh giới năm và ngày ngoài tháng.
2. DTO/ViewModel chỉ đọc:
   - Calendar.
   - CalendarDay.
   - CalendarItem.
   - Không chứa phép tính lương mới hoặc quyền do client quyết định.
3. Query/service riêng cho:
   - Lịch cá nhân.
   - Lịch quản trị.
   - Lịch payroll.
4. Blade component dùng chung cho:
   - Toolbar.
   - Week header.
   - Day cell.
   - Legend.
   - Empty state.
   - Detail panel.
5. Alpine chỉ hỗ trợ chọn ngày và mở panel; không tính nghiệp vụ hoặc fetch ngầm dữ liệu nhạy cảm trong phiên bản đầu.

Không query theo từng ô, từng ngày hoặc từng nhân viên trong vòng lặp Blade. Dùng eager loading và aggregate phù hợp.

## 4. Ràng buộc nghiệp vụ tuyệt đối không được phá vỡ

Giữ nguyên và tái sử dụng:

- `CheckInAction`.
- `CheckOutAction`.
- `SaveManualAttendanceAction`.
- `CalculatePayrollAction`.
- `AttendanceRecordPolicy`.
- `PayrollPolicy`.
- `PayrollLockGuard`.
- `BranchContext`.
- Audit trail hiện có.
- Luồng duyệt tăng ca.
- Password confirmation cho thao tác tài chính nhạy cảm.

Không được:

- Tạo endpoint ghi mới để bypass action/policy hiện tại.
- Tin `employee_id`, `branch_id`, quyền sửa hoặc trạng thái khóa do client gửi.
- Cho sửa attendance thuộc payroll finalized/paid.
- Làm manager tự xử lý attendance của mình.
- Tính lương, KPI, hoa hồng hoặc overtime bằng JavaScript/Blade.
- Sửa công thức lương trong đợt này.
- Làm thay đổi payroll finalized/paid.
- Reset database hoặc xóa dữ liệu ngoài test environment.
- Sửa hoặc bỏ test chỉ để suite xanh.
- Tạo migration nếu không thật sự cần. Nếu phát hiện cần đổi schema, dừng phần đó, phân tích backward compatibility và báo rõ trước khi thực hiện migration phá vỡ giả định của plan.

## 5. UX và accessibility

- Mobile-first nhưng desktop phải sử dụng tốt.
- Lịch có semantic HTML và accessible name đầy đủ cho từng ngày.
- Trạng thái không chỉ thể hiện bằng màu; cần text, icon hoặc ARIA label.
- Focus keyboard rõ ràng và thứ tự tab hợp lý.
- Touch target đủ lớn.
- Panel ngày là bottom sheet/modal phù hợp trên mobile và panel/modal hợp lý trên desktop.
- Có fallback server-rendered khi JavaScript không chạy.
- Hỗ trợ màn hình nhỏ, text zoom, safe-area và `prefers-reduced-motion`.
- Không xung đột với `clockButton`, `submitGuard`, confirm modal, notice modal hoặc offcanvas hiện hữu.

## 6. Test bắt buộc

### 6.1. Navigation

- Characterization test cho visibility, route, pattern, primary và badge trước khi refactor.
- Role matrix cho owner, manager, employee và các role hiện hữu.
- Nhóm rỗng không xuất hiện.
- Nhóm active tự mở; parent/child active đúng.
- ARIA của button nhóm đúng.
- Badge “Đơn ca & nghỉ” đúng số và branch scope ở child/parent.
- Employee vẫn thấy “Lương của tôi” và không bị dẫn vào attendance quản trị.
- Bottom tab bar vẫn có tối đa bốn mục primary và nút “Thêm”.
- Sidebar và offcanvas không lệch route, nhãn, badge hoặc quyền.
- Không tăng query do render navigation nhiều nơi.

### 6.2. Calendar range

- Tháng 28, 29, 30 và 31 ngày.
- Tháng bắt đầu thứ Hai và Chủ nhật.
- Ranh giới tháng 12/tháng 1.
- Ngày ngoài tháng.
- Hôm nay theo business timezone.
- Query tháng rỗng, sai định dạng hoặc ngoài phạm vi hợp lý.

### 6.3. Attendance data

- Không có ca.
- Phân ca tương lai.
- Một ca hoàn chỉnh.
- Nhiều ca cùng ngày.
- Ca qua đêm.
- Thiếu check-out.
- Đi muộn.
- Nghỉ phép và vắng.
- Overtime none/pending/approved/rejected.
- GPS verified/needs review.
- Manual record và audit.
- Owner self-recorded.
- Nhân viên ngừng hoạt động nhưng có dữ liệu lịch sử.

### 6.4. Security và scope

- Employee chỉ thấy dữ liệu mình.
- Chống IDOR qua `employee_id`, `branch_id`, date/day detail.
- Manager chỉ thấy chi nhánh được giao.
- Manager không tự tạo/sửa/xóa attendance của mình.
- Không lộ dữ liệu ngoài scope trong HTML, JSON, data attribute hoặc panel ẩn.
- Payroll chỉ hiển thị cho actor được phép và đúng employee/kỳ.

### 6.5. Payroll lock và regression

- Draft cho phép thay đổi qua action hiện hành.
- Finalized/paid chặn mọi thay đổi attendance ảnh hưởng kỳ.
- Lịch payroll luôn chỉ đọc.
- Mở lịch không tự recalculate.
- Giữ nguyên tổng ca, lương ca, chuyên cần, nghỉ hưởng/không hưởng lương, overtime đã duyệt, KPI, hoa hồng, adjustment, rounding và variance.
- Cash transaction khi trả lương vẫn idempotent.
- GPS check-in/check-out và chống gửi hai lần vẫn hoạt động như trước.

### 6.6. Render, accessibility và hiệu năng

- Keyboard, focus, accessible labels và legend không phụ thuộc màu.
- Fallback khi JavaScript tắt.
- Mobile markup và responsive behavior.
- Không có lỗi Alpine/JavaScript trong browser console.
- Không có N+1.
- Query budget không tăng tuyến tính theo số ngày, nhân viên hoặc số lần render navigation.

## 7. Trình tự triển khai

### Giai đoạn 0: menu nhóm

1. Characterization test navigation hiện tại.
2. Metadata group và phương thức grouping trong `AdminNavigation`.
3. Component menu nhóm dùng chung.
4. Tích hợp sidebar và mobile offcanvas; giữ tab bar phẳng.
5. SCSS và accessibility.
6. Navigation test, badge test và query budget.
7. Giữ thay đổi menu trong commit logic độc lập để rollback riêng.

### Giai đoạn 1: nền tảng calendar

1. Test range builder.
2. Range builder.
3. DTO/ViewModel.
4. Ba query/service.
5. Scope test và query budget.

### Giai đoạn 2: component calendar

1. Calendar component.
2. Day-cell component.
3. Detail panel.
4. SCSS responsive.
5. Alpine tối thiểu.
6. Render/accessibility test.

### Giai đoạn 3: lịch cá nhân

1. Tích hợp dữ liệu.
2. Giữ nguyên clock/GPS panel.
3. Thêm lịch tháng.
4. Chạy attendance/GPS regression test.

### Giai đoạn 4: lịch quản trị

1. Validate month/mode/employee.
2. Chế độ Tổng quan.
3. Chế độ Nhân viên.
4. Panel chi tiết và quyền thao tác.
5. Giữ quick form/review shortcut.
6. Chạy scope, self-dealing, audit, lock và query budget test.

### Giai đoạn 5: lịch payroll

1. Query theo payroll period.
2. Partial reconciliation calendar.
3. KPI ngày.
4. Draft/locked state.
5. Chạy toàn bộ payroll/payslip regression test.

### Giai đoạn 6: hardening

1. Chạy formatter.
2. Chạy test mục tiêu.
3. Chạy toàn bộ test suite.
4. Chạy frontend build.
5. Kiểm tra browser console/log.
6. Kiểm tra responsive/accessibility.
7. Kiểm tra query budget.
8. Sửa mọi regression trước khi kết luận.

## 8. Các lệnh kiểm tra cuối

Dùng lệnh phù hợp với project và môi trường, tối thiểu gồm:

```powershell
vendor\bin\pint
php artisan test tests/Feature/Shifts/ShiftRequestNavigationTest.php
php artisan test tests/Feature/Attendance
php artisan test tests/Feature/Payroll
php artisan test tests/Feature/Branch/BranchAttendanceTest.php
php artisan test tests/Feature/Branch/BranchPayrollVisibilityTest.php
php artisan test tests/Feature/Admin/AdminAuthorizationTest.php
php artisan test tests/Feature/AlpineComponentContractTest.php
php artisan test
npm run build
```

Nếu tên/path test mới hoặc test hiện tại khác sau khi khảo sát, điều chỉnh lệnh cho đúng nhưng không bỏ qua các nhóm kiểm tra tương ứng.

## 9. Điều kiện hoàn thành

Chỉ kết luận hoàn thành khi:

- Menu mobile được gom nhóm gọn và desktop/sidebar/tab bar đồng bộ.
- Không mất destination, route, quyền, badge hoặc active state.
- Ba màn lịch hoạt động đúng phạm vi.
- Nhân viên chỉ thấy dữ liệu của mình.
- Manager chỉ thấy đúng branch scope và không tự xử lý công của mình.
- Finalized/paid không bị thay đổi qua lịch.
- GPS và các action ghi hoạt động như trước.
- Công thức và kết quả payroll không bị thay đổi ngoài dữ liệu hợp lệ vốn có.
- Không có N+1 hoặc query vượt budget hợp lý.
- Formatter, test mục tiêu, toàn bộ test suite và frontend build đều thành công.
- Không có lỗi browser console đáng kể.
- Không có migration phá hủy hoặc thay đổi production chưa được phê duyệt.

## 10. Báo cáo cuối cùng

Khi hoàn tất, báo cáo rõ:

1. Tóm tắt chức năng đã triển khai.
2. Danh sách file đã tạo/sửa.
3. Các quyết định kiến trúc quan trọng.
4. Cách dùng mới cho employee, manager và owner.
5. Test, formatter và build đã chạy cùng kết quả.
6. Query budget trước/sau nếu có đo.
7. Migration hoặc config cần deploy; nếu không có phải ghi rõ.
8. Rủi ro còn lại và giới hạn đã biết.
9. Checklist deploy và smoke test.
10. Cách rollback menu và calendar độc lập.

Không kết thúc bằng lời khẳng định chung chung. Phải nêu bằng chứng kiểm tra cụ thể và mọi phần chưa hoàn tất nếu còn.