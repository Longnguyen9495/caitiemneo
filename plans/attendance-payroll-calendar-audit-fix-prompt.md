# Prompt triển khai phần khắc phục calendar/payroll/navigation

Bạn đang làm việc trong repository Laravel hiện tại. Hãy audit-fix implementation lịch chấm công, lịch payroll và navigation theo đúng tài liệu:

- `AGENTS.md`
- `plans/attendance-payroll-calendar-implementation-plan.md`
- `plans/attendance-payroll-calendar-audit-fix-plan.md`

## Mục tiêu

Hoàn thiện implementation hiện có thay vì viết lại từ đầu. Ưu tiên theo thứ tự:

1. Chặn IDOR và sai branch scope ở admin employee calendar.
2. Bảo vệ manager self-dealing và chỉ render action URL theo policy.
3. Sửa payroll day detail mở được ở chế độ read-only.
4. Loại bỏ duplicate collapse IDs giữa desktop sidebar và mobile offcanvas.
5. Đảm bảo navigation/calendar có no-JS fallback.
6. Validate query `month` an toàn, không gây 500.
7. Sửa admin overview để panel ngày liệt kê từng nhân viên và từng ca.
8. Áp dụng và bảo toàn filters khi đổi tháng/chế độ.
9. Sửa toolbar payroll không có link rỗng.
10. Xử lý N+1, aggregate/query budget, KPI nhiều branch/ngày và query trùng.
11. Làm rõ datasource/snapshot wording của payroll.
12. Hoàn thiện accessibility, semantics và lưới 35/42 ô.
13. Bổ sung test còn thiếu và chạy toàn bộ quality gate.

## Ràng buộc bắt buộc

- Đọc `AGENTS.md` trước khi sửa và tuân thủ toàn bộ guideline hiện tại.
- Kiểm tra `git status`, `git diff --stat` và `git diff --name-only` trước khi sửa.
- Working tree đang có thay đổi Gallery/Home không thuộc nhiệm vụ. Không reset, checkout, stash, xóa, ghi đè hoặc hoàn tác chúng.
- Khi sửa file dùng chung như `app/Support/AdminNavigation.php`, `resources/scss/_neo-admin.scss`, `resources/js/admin.js`, `routes/web.php`, phải merge có chủ đích và bảo toàn chức năng Gallery/Home.
- Không deploy production.
- Không thay đổi công thức lương.
- Không thay đổi contract GPS check-in/check-out.
- Không bỏ policy, audit trail, payroll lock hoặc branch isolation hiện có.
- Không đưa logic nghiệp vụ/tính lương sang Blade hoặc JavaScript.
- Không tạo migration phá hủy. Tránh migration mới nếu có thể giải quyết bằng query/DTO/view.
- Không coi việc test baseline đang xanh là bằng chứng hoàn thành; phải thêm test tái hiện các gap trong audit plan.
- Không sửa test chỉ để hợp thức hóa hành vi sai.

## Baseline đã xác minh

Các test mục tiêu cũ đang pass: 71 tests, 375 assertions.

Vite production build đang pass.

Baseline này thiếu coverage cho IDOR, duplicate IDs, no-JS, payroll panel, policy links, filters, query budget và detail overview.

## Cách triển khai bắt buộc

### Pha 0 — Characterization/security tests trước

Viết test fail cho ít nhất các trường hợp:

- Manager truyền employee ID ngoài branch không đọc được calendar/detail.
- Manager trong branch vẫn đọc đúng dữ liệu được phép.
- Manager không có action tự sửa/xóa/tạo công cho chính mình.
- Invalid `month` không gây 500.
- Layout hoàn chỉnh không có duplicate navigation collapse IDs.
- Navigation markup vẫn truy cập được khi không có JS.
- Payroll ngày có dữ liệu mở được detail read-only, không có mutation link/action.
- Payroll toolbar không render `href=""`.

Sau đó mới sửa implementation.

### Pha 1 — Security

- Scope employee resolver theo actor, policy và `BranchContext`.
- Thêm defense-in-depth trong calendar query/service; service không được tin một `User` lấy từ query string là hợp lệ.
- Scope nhất quán attendance records và shift assignments.
- Gate `viewUrl`, `editUrl`, create/delete/review actions bằng policy phía server.
- Không rò rỉ record ID/link ngoài quyền.
- Giữ authorization ở endpoint hiện hành.

### Pha 2 — Interaction/navigation/no-JS

- Tách rõ khả năng mở detail khỏi khả năng mutate; không tiếp tục dùng một boolean mơ hồ cho cả hai.
- Payroll được mở detail nhưng luôn read-only.
- Ngày không có action không là enabled button giả.
- Namespace ID của menu theo vị trí render, ví dụ desktop/mobile, và nối đúng `aria-controls`/`data-bs-target`.
- No-JS: menu items và calendar detail quan trọng vẫn đọc/truy cập được; JS chỉ enhancement.
- Grid toolbar hỗ trợ mode không có prev/next; không render anchor rỗng.

### Pha 3 — Admin overview/filter

- Tạo cấu trúc day summary và day detail rõ ràng; không suy count từ synthetic item có `recordId=null`.
- Ô overview hiển thị aggregate thực.
- Panel ngày liệt kê từng nhân viên/ca trong scope, gồm giờ vào/ra và warning cần thiết.
- Aggregate tại database khi phù hợp; không eager-load employee vô ích chỉ để tính summary.
- Không query từng ngày/cell.
- Áp dụng/preserve `employee`, `status`, `source` và các filter hợp lệ khi đổi tháng/chế độ.
- Giá trị employee ngoài scope phải bị từ chối, không được preserve.
- Chốt và test severity order nhất quán.

### Pha 4 — Payroll reconciliation

- Xác định nguồn snapshot thật từ domain hiện có.
- Nếu calendar không có attendance snapshot chi tiết, sửa wording để không tuyên bố sai; phân biệt dữ liệu đối chiếu hiện tại với số tiền payroll đã khóa.
- Không âm thầm thay đổi calculation.
- Group KPI theo ngày và branch/tier; không dùng `keyBy(date)` làm mất record.
- Không lặp note KPI trên mọi attendance item một cách gây nhiễu.
- Tránh query lại quan hệ KPI đã load hoặc bỏ eager-load dư thừa.
- Test render calendar không gọi calculate và không mutate payroll/attendance.

### Pha 5 — Performance/accessibility/hardening

- Xóa lazy-loading `$assignment->attendanceRecord` trong loop bằng eager-load hoặc query phù hợp.
- Làm rõ contract assignment cũ chưa có attendance; code/comment/test phải thống nhất.
- Đo baseline query rồi đặt query budget hợp lý cho personal, admin overview, admin employee, payroll và navigation.
- Chuẩn hóa calendar thành 35 hoặc 42 cells, bắt đầu thứ Hai, tránh tháng 28 cells làm layout nhảy.
- Không gọi `CarbonImmutable::setLocale()` trong constructor gây global side effect; dùng app/instance locale.
- Sửa semantics calendar: weekday headers không bị ẩn khỏi screen reader; grid/gridcell hoặc semantic structure phải nhất quán; focus rõ; reduced-motion; accessible labels đủ ngữ cảnh.
- Warning label không nằm trong ancestor `aria-hidden=true`.
- Parent navigation badge phải có label theo ngữ cảnh, không hard-code một domain cho mọi group.

## Test coverage tối thiểu

### Calendar core

- Tháng 28/29/30/31 ngày.
- Ranh giới năm.
- Bắt đầu thứ Hai.
- 35/42 cells.
- Outside-month và today.
- Invalid month.
- URL preserve filters.

### Personal attendance

- Chỉ dữ liệu bản thân.
- Nhiều ca/ngày.
- Ca qua đêm.
- Missing checkout, late, absent, leave, pending overtime, GPS review.
- Assignment cũ/tương lai theo contract.
- GPS check-in/out và chống submit hai lần không regression.

### Admin attendance

- Branch scope và IDOR.
- Overview detail từng employee/shift.
- Employee mode giờ vào/ra.
- Filters và month navigation.
- Policy action và self-dealing.
- Warning/count/severity.

### Payroll

- Đúng employee và period.
- Không lẫn employee/ngày ngoài kỳ.
- Detail read-only mở được.
- Không action sửa công.
- Draft/finalized/paid wording đúng.
- Không calculate/mutation khi render.
- KPI nhiều branch/ngày.
- Không empty links.

### Navigation

- Role/policy visibility.
- Empty groups không render.
- Active parent/child.
- Child và aggregate badges.
- Unique IDs desktop/mobile.
- No-JS fallback.
- Badge query không chạy lại.

## Quy trình kiểm chứng

Chạy các lệnh riêng biệt. Không nối bằng dấu chấm phẩy vì shell hiện tại từng coi dấu đó là một phần argument.

1. Format/check style:

```powershell
vendor/bin/pint --test
```

Nếu fail do code mới, chạy Pint trên các file đã sửa rồi chạy lại `--test`. Không format hàng loạt các file ngoài phạm vi nếu làm nhiễu diff.

2. Targeted tests:

```powershell
php artisan test tests/Unit/Support/CalendarMonthTest.php tests/Feature/Calendar tests/Feature/Attendance tests/Feature/Admin/AdminCalendarTest.php tests/Feature/Admin/AdminAuthorizationTest.php tests/Feature/Payroll tests/Feature/Shifts/ShiftRequestNavigationTest.php
```

3. Full suite:

```powershell
php artisan test
```

4. Frontend build:

```powershell
npm run build
```

5. Kiểm tra cuối:

```powershell
git status --short
```

```powershell
git diff --stat
```

```powershell
git diff --check
```

## Tiêu chí bàn giao

Chỉ kết luận hoàn thành khi:

- P0 security tests pass.
- Payroll detail hoạt động read-only.
- Không duplicate DOM IDs.
- No-JS fallback tồn tại.
- Invalid month không 500.
- Admin overview đúng yêu cầu từng nhân viên/ca.
- Filters nhất quán.
- N+1 đã xử lý và query budgets pass.
- Payroll snapshot wording/datasource trung thực.
- Accessibility tests và manual markup review đạt.
- Pint, targeted tests, full suite và build đều pass.
- Không làm mất hoặc hoàn tác thay đổi Gallery/Home.

Khi hoàn tất, báo cáo theo cấu trúc:

1. Danh sách vấn đề đã sửa, chia P0/P1/P2.
2. Danh sách file đã thay đổi.
3. Test mới đã thêm và lỗi nào chúng bảo vệ.
4. Kết quả từng lệnh Pint/test/build.
5. Query count trước/sau.
6. Các quyết định về snapshot, assignment cũ và 35/42 cells.
7. Hạng mục còn lại nếu có, không che giấu test fail hoặc giới hạn chưa xử lý.
8. Xác nhận chưa deploy production và đã bảo toàn thay đổi Gallery/Home.
