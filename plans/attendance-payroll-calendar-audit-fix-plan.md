# Kế hoạch audit và hoàn thiện lịch chấm công, payroll, navigation

## 1. Mục đích

Tài liệu này là kế hoạch khắc phục sau khi đối chiếu implementation hiện tại với `plans/attendance-payroll-calendar-implementation-plan.md`.

Không sửa lại lịch sử của plan gốc. Implementation hiện tại đã có nền tảng calendar/menu và bộ test mục tiêu đang xanh, nhưng chưa đạt đầy đủ tiêu chí bảo mật, nghiệp vụ, progressive enhancement, accessibility và hiệu năng của plan.

## 2. Bằng chứng audit

Đã chạy thành công:

```powershell
php artisan test tests/Unit/Support/CalendarMonthTest.php tests/Feature/Calendar/CalendarComponentRenderTest.php tests/Feature/Attendance/PersonalCalendarTest.php tests/Feature/Admin/AdminCalendarTest.php tests/Feature/Payroll/PayrollCalendarTest.php tests/Feature/Shifts/ShiftRequestNavigationTest.php tests/Feature/Admin/AdminAuthorizationTest.php
```

Kết quả: 71 test pass, 375 assertions.

Đã chạy thành công:

```powershell
npm run build
```

Kết quả: Vite production build thành công.

Kết luận: baseline hiện tại không lỗi theo các test đã có, nhưng test coverage còn nông và bỏ sót nhiều tình huống quan trọng dưới đây.

## 3. Ràng buộc an toàn

1. Không deploy production trong đợt khắc phục nếu chưa được phê duyệt riêng.
2. Không thay đổi công thức lương, GPS check-in/check-out, audit trail, payroll lock hoặc action nghiệp vụ hiện hành trừ khi một test chứng minh regression cần sửa.
3. Không hoàn tác hay ghi đè các thay đổi Gallery/Home đang có trong working tree.
4. Các file dùng chung như `app/Support/AdminNavigation.php`, `resources/scss/_neo-admin.scss`, `resources/js/admin.js`, `routes/web.php` phải được merge có chủ đích.
5. Viết test tái hiện lỗi trước hoặc cùng commit với bản sửa.
6. Mọi scope dữ liệu và quyền phải được cưỡng chế ở server; không dựa vào việc ẩn UI.

## 4. Kết quả audit theo mức ưu tiên

### P0 — Bảo mật và cách ly dữ liệu

#### 4.1. Chống IDOR ở chế độ lịch theo nhân viên

Hiện controller nhận `employee` từ query string bằng lookup toàn cục, sau đó service truy vấn attendance/assignment theo employee mà không tự cưỡng chế branch scope.

Nguy cơ: manager có thể truyền ID nhân viên ngoài chi nhánh để xem dữ liệu chấm công.

Yêu cầu sửa:

- Resolve nhân viên từ query đã giới hạn bởi actor, policy và `BranchContext`.
- Owner chỉ được thấy phạm vi mà policy hiện hành cho phép; manager chỉ thấy nhân viên thuộc chi nhánh được cấp.
- Service vẫn phải có defense-in-depth: không chấp nhận một employee ngoài scope.
- Attendance records và shift assignments phải được giới hạn phạm vi nhất quán.
- Với ID ngoài scope, trả 404 hoặc 403 nhất quán; không fallback sang dữ liệu khác.
- Không render URL xem/sửa nếu actor không được policy cho phép.

Test bắt buộc:

- Manager không đọc được employee ngoài branch qua query string.
- Manager đọc được employee trong branch.
- Owner đúng scope.
- Employee không truy cập màn admin.
- Không rò rỉ record ID hoặc edit URL ngoài quyền.

#### 4.2. Chống self-dealing và policy-gated actions

Yêu cầu sửa:

- Mọi nút tạo/sửa/xóa/review overtime trong panel lịch phải dựa trên `AttendanceRecordPolicy` hoặc Gate phía server.
- Manager không được tạo/sửa/xóa công của chính mình.
- Không chỉ ẩn nút; endpoint hiện hành vẫn phải tiếp tục authorize.
- Owner self-recorded phải giữ cơ chế review/audit hiện hành.

Test bắt buộc:

- Manager không thấy action tự sửa công của mình.
- Manager không gọi được endpoint trực tiếp.
- Action hợp lệ của employee khác trong scope vẫn hoạt động.
- Payroll finalized/paid vẫn chặn thay đổi ảnh hưởng tiền.

### P1 — Lỗi chức năng quan sát được

#### 4.3. Sửa panel chi tiết payroll không mở

Hiện `canInteract=false` đồng thời bị dùng để chặn event mở panel. Read-only không đồng nghĩa không được xem chi tiết.

Yêu cầu sửa:

- Tách capability `canOpenDetails` khỏi `canMutate` hoặc thiết kế tên tương đương rõ nghĩa.
- Payroll calendar cho phép mở panel read-only nhưng không render mutation action.
- Ngày rỗng không phải button tương tác giả.
- Test render phải chứng minh ngày có dữ liệu phát event/mở panel và không có edit/delete/create link.

#### 4.4. Loại bỏ duplicate ID trong navigation

Component nhóm menu đang được render ở desktop sidebar và mobile offcanvas với cùng ID collapse.

Yêu cầu sửa:

- Component nhận namespace/prefix bắt buộc hoặc sinh ID ổn định theo vị trí render.
- `aria-controls` và `data-bs-target` phải trỏ đúng instance.
- Không có duplicate DOM ID trên layout hoàn chỉnh.
- Nhóm chứa route hiện tại tự mở ở đúng sidebar/offcanvas.

#### 4.5. Progressive enhancement/no-JS

Yêu cầu sửa:

- Khi JavaScript không chạy, navigation vẫn phải truy cập được; không để các nhóm không active bị ẩn vĩnh viễn.
- Calendar phải có fallback server-rendered để xem chi tiết ngày, hoặc toàn bộ chi tiết cần thiết hiện sẵn bằng semantic HTML.
- Không dùng `x-cloak` để làm dữ liệu duy nhất biến mất khi không có JS.
- JavaScript chỉ nâng cấp trải nghiệm thành collapse/bottom sheet, không là điều kiện để đọc dữ liệu.

#### 4.6. Validate tháng an toàn

Hiện admin controller parse chuỗi query trước khi helper calendar có cơ hội fallback, nên input không hợp lệ có thể gây exception.

Yêu cầu sửa:

- Dùng Form Request hoặc validator/shared parser cho định dạng `Y-m` và khoảng năm hợp lệ.
- Chọn hành vi nhất quán: fallback có kiểm soát hoặc redirect kèm validation error.
- Không gọi parse tự do trên input chưa validate.
- Áp dụng cùng contract cho admin và personal calendar.

Test bắt buộc: empty, garbage, sai format, tháng 00/13 và năm ngoài giới hạn không gây 500.

#### 4.7. Toolbar payroll không tạo link rỗng

Yêu cầu sửa:

- Grid toolbar phải hỗ trợ mode không điều hướng tháng, hoặc payroll cung cấp URL hợp lệ theo kỳ.
- Không render anchor với `href=""`.
- Nhãn giữa toolbar phải đúng nghĩa kỳ lương, không gọi URL payroll là “Về tháng hiện tại”.

### P1 — Hoàn thiện yêu cầu nghiệp vụ

#### 4.8. Admin overview phải có danh sách từng nhân viên/ca

Hiện overview tạo một synthetic item mỗi ngày nên panel chỉ có summary, không đáp ứng plan.

Yêu cầu sửa:

- Giữ aggregate theo ngày cho ô lịch.
- Cung cấp detail rows theo ngày gồm employee, ca, giờ vào/ra, status và warning cần thiết.
- Chạm ngày phải xem được tất cả nhân viên/ca trong scope.
- Tránh N+1 và không query theo từng ô ngày.
- Nếu dùng hai query (aggregate + detail tháng), phải có query budget và dữ liệu giới hạn đúng tháng/scope.
- Số record và số warning phải là số thực, không suy ra từ một synthetic item.

Severity tối thiểu phải nhất quán, ví dụ: absent/missing checkout/GPS review/pending overtime/late/present; ghi rõ thứ tự và test.

#### 4.9. Đồng bộ filter và navigation tháng

Yêu cầu sửa:

- `employee`, `status`, `source` và các filter hợp lệ phải tác động nhất quán lên calendar hoặc UI ghi rõ filter nào chỉ áp dụng bảng.
- Ưu tiên calendar và table dùng cùng filter contract.
- Link tháng trước/sau/current và chuyển overview/employee phải bảo toàn filter phù hợp.
- Không bảo toàn giá trị employee ngoài scope.

#### 4.10. Nội dung ô ngày và panel

Yêu cầu sửa:

- Ô ngày đủ ngắn gọn cho mobile nhưng phải biểu đạt số ca/bản ghi, tone và cảnh báo chính.
- Panel hiển thị giờ vào/ra, ca dự kiến, thiếu checkout, đi muộn, nguồn, GPS review, overtime, self-recorded và lock khi dữ liệu có trường tương ứng.
- `accessibleSummary` phải đủ ngữ cảnh, gồm nhân viên khi ở overview và trạng thái quan trọng.
- `isSelfRecorded` phải được tính trong warning nếu plan nghiệp vụ coi đây là mục cần review.

### P1 — Payroll reconciliation

#### 4.11. Làm rõ snapshot và dữ liệu live

UI hiện tuyên bố payroll đã khóa dùng snapshot nhưng calendar lại đọc attendance records hiện tại.

Yêu cầu:

- Xác định nguồn snapshot thật hiện có trong domain.
- Nếu có snapshot attendance/payroll allocation phù hợp, calendar finalized/paid phải đọc snapshot.
- Nếu chưa có snapshot đủ chi tiết, không được tuyên bố sai; đổi wording thành dữ liệu đối chiếu hiện tại và nêu rõ payroll amount vẫn là snapshot/đã khóa.
- Không tạo migration phá hủy hoặc âm thầm thay đổi cách tính lương.
- Viết characterization test chứng minh việc mở calendar không gọi calculate và không mutate payroll/attendance.

#### 4.12. KPI nhiều branch/ngày

Hiện `keyBy(date)` có thể ghi đè nếu một ngày có nhiều KPI result.

Yêu cầu sửa:

- Group theo ngày và tổng hợp/hiển thị từng branch/tier theo semantics domain.
- Không lặp cùng một KPI note trên mọi attendance item nếu gây nhiễu.
- Tận dụng quan hệ đã eager-load hoặc bỏ eager-load trùng; không query KPI hai lần.

### P2 — Hiệu năng và kiến trúc

#### 4.13. Xóa N+1 assignment-attendance

Yêu cầu sửa:

- Eager-load hoặc dùng query loại trừ assignment đã có attendance.
- Không truy cập lazy relation trong vòng lặp.
- Làm rõ việc assignment cũ chưa có record có được hiển thị hay chỉ hôm nay/tương lai; code, comment và test phải thống nhất.

#### 4.14. Aggregate overview tại database

- Query count/count distinct/warnings theo ngày tại database khi phù hợp.
- Không tải toàn bộ employee model chỉ để tạo summary.
- Detail query được phép tải rows của tháng khi cần panel, nhưng phải chọn cột/eager-load có kiểm soát.
- Đặt query budget cho personal, admin overview, admin employee, payroll và navigation.

#### 4.15. ViewModel rõ semantics

- Không dùng một boolean cho cả quyền sửa và khả năng mở detail.
- Không dựa vào `recordId !== null` để tính count cho aggregate item.
- Bổ sung explicit aggregate counters/detail collection hoặc kiểu day summary riêng.
- Hạn chế magic property nếu làm yếu static analysis; nếu giữ phải có PHPDoc rõ.
- Tránh thay đổi locale Carbon global trong constructor; dùng locale ứng dụng/instance.

#### 4.16. Quy tắc số ô lịch

Implementation/test hiện chấp nhận tháng 28 ô, trong khi plan gốc định hướng lưới ổn định 35/42 ô.

Quyết định remediation: chuẩn hóa tối thiểu 35 ô, tối đa 42 ô để tránh chiều cao UI nhảy mạnh; vẫn bắt đầu thứ Hai và kết thúc Chủ nhật. Cập nhật unit test tương ứng.

Nếu team chủ động muốn giữ 28 ô, phải cập nhật plan gốc và có kiểm thử responsive chứng minh không gây layout shift. Không để comment, test và yêu cầu mâu thuẫn.

### P2 — Accessibility và UX

#### 4.17. Semantic calendar

- Không dùng enabled button cho ngày không có hành động.
- Nếu dùng `role="grid"`, hoàn thiện `row`, `columnheader`, `gridcell`, accessible name và keyboard behavior; hoặc bỏ ARIA grid để dùng cấu trúc semantic đơn giản, đúng thực tế.
- Không đặt nhãn ARIA hữu ích bên trong ancestor `aria-hidden="true"`.
- Weekday headers phải được screen reader nhận biết.
- Có visible focus, target size phù hợp và reduced-motion.

#### 4.18. Navigation accessibility

- ID duy nhất.
- Trigger có accessible name, `aria-expanded`, `aria-controls` chính xác.
- Child active dùng `aria-current="page"`.
- Parent badge có label theo domain, không hard-code “đơn cần xử lý” cho mọi nhóm.
- Test keyboard/focus ở mức markup và manual smoke test thực tế.

## 5. Kế hoạch triển khai theo pha

### Pha 0 — Characterization và security tests

1. Thêm test IDOR/branch scope.
2. Thêm test manager self-dealing và policy-gated URLs/actions.
3. Thêm test invalid month không 500.
4. Thêm test duplicate navigation IDs và no-JS markup.
5. Thêm test payroll detail trigger/read-only.

Không sửa UI lớn trước khi các test lỗi quan trọng tái hiện đúng vấn đề.

### Pha 1 — Security fixes

1. Scope employee resolver ở controller/query layer.
2. Defense-in-depth trong `AdminCalendarQuery`.
3. Policy-gate tất cả action URLs.
4. Chạy toàn bộ authorization, attendance và branch isolation tests.

### Pha 2 — Interaction và progressive enhancement

1. Tách read-only detail capability khỏi mutation capability.
2. Sửa payroll panel.
3. Namespace navigation collapse IDs.
4. Thêm no-JS fallback cho menu và calendar.
5. Sửa payroll toolbar.

### Pha 3 — Admin overview và filters

1. Thiết kế day aggregate DTO và day detail rows.
2. Aggregate bằng database.
3. Hiển thị từng employee/shift trong panel.
4. Áp dụng/preserve filter contract.
5. Thêm severity và warning count đúng.

### Pha 4 — Payroll reconciliation

1. Xác nhận snapshot source.
2. Sửa wording hoặc datasource.
3. Group KPI nhiều branch/ngày.
4. Xóa query KPI trùng.
5. Test calendar không mutate/recalculate.

### Pha 5 — Performance, accessibility và hardening

1. Xóa N+1 assignment relation.
2. Thêm query budgets.
3. Chuẩn hóa 35/42 cells.
4. Hoàn thiện semantic HTML/ARIA/focus/reduced-motion.
5. Chạy regression suite và kiểm thử thủ công responsive.

## 6. Test matrix bắt buộc

### Unit

- Calendar tháng 28/29/30/31 ngày, đầu tuần thứ Hai, ranh giới năm, tối thiểu 35 và tối đa 42 ô.
- Invalid month contract.
- Severity/warning/count aggregation.
- URL tháng bảo toàn query hợp lệ.
- Navigation grouping, active parent, aggregate badge.

### Feature — Personal

- Chỉ thấy dữ liệu bản thân.
- Nhiều ca/ngày, ca qua đêm, missing checkout, late, absent, leave, pending overtime, GPS review.
- Assignment cũ/tương lai theo contract đã chốt.
- Check-in/out và chống gửi hai lần không regression.

### Feature — Admin

- Overview liệt kê từng employee/shift theo ngày.
- Scope theo branch và chống IDOR employee query.
- Filter status/source/employee và preserve khi đổi tháng.
- Policy action và self-dealing.
- Invalid month không 500.

### Feature — Payroll

- Chỉ đúng employee và period.
- Ngày ngoài kỳ và employee khác không xuất hiện.
- Panel mở được nhưng hoàn toàn read-only.
- Draft/finalized/paid wording đúng datasource.
- Không recalculate hoặc mutate khi render.
- KPI nhiều branch/ngày không bị overwrite.
- Không có empty navigation links.

### Feature — Navigation

- Role/policy visibility.
- Không render group rỗng.
- Badge child/parent đúng và không query lặp.
- Unique IDs giữa sidebar/offcanvas.
- Active group tự mở.
- No-JS content vẫn truy cập được.

### Query budget

Đặt ngưỡng dựa trên baseline đo được, sau đó test số query không tăng theo số ngày/cell/assignment. Không hard-code ngưỡng tùy ý trước khi đo.

## 7. Quality gate

Chỉ coi hoàn thành khi:

1. Không còn IDOR/branch scope issue.
2. Payroll detail mở được và không có mutation action.
3. Navigation không duplicate ID và dùng được khi JS tắt.
4. Invalid month không gây 500.
5. Admin overview hiển thị đúng từng nhân viên/ca.
6. Filter được áp dụng/bảo toàn nhất quán.
7. Không có N+1 đã xác định.
8. Snapshot wording/datasource không mâu thuẫn.
9. Accessibility markup qua test và manual smoke test.
10. `vendor/bin/pint --test` pass.
11. Targeted tests pass.
12. Full test suite pass.
13. `npm run build` pass.
14. Git diff chỉ chứa thay đổi có chủ đích; các thay đổi Gallery/Home được bảo toàn.

## 8. Lệnh xác minh cuối

Chạy riêng từng lệnh; không nối bằng dấu chấm phẩy trong shell hiện tại.

```powershell
vendor/bin/pint --test
```

```powershell
php artisan test tests/Unit/Support/CalendarMonthTest.php tests/Feature/Calendar tests/Feature/Attendance tests/Feature/Admin/AdminCalendarTest.php tests/Feature/Admin/AdminAuthorizationTest.php tests/Feature/Payroll tests/Feature/Shifts/ShiftRequestNavigationTest.php
```

```powershell
php artisan test
```

```powershell
npm run build
```

## 9. Rollback

- Tách commit theo security, navigation, calendar interaction, admin overview và payroll để rollback độc lập.
- Không rollback bằng cách reset toàn bộ working tree.
- Không đụng migration/dữ liệu nếu remediation không thực sự cần schema mới.
- Nếu UI mới cần rollback, giữ nguyên backend authorization/security fixes.
