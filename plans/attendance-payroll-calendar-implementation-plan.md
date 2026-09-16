# Kế hoạch triển khai lịch chấm công và đối soát lương

## 1. Mục tiêu

Thiết kế lại trải nghiệm chấm công và đối soát lương theo dạng lịch tháng, tối ưu cho điện thoại nhưng vẫn sử dụng tốt trên desktop.

Phạm vi gồm bốn bề mặt:

1. Lịch chấm công cá nhân của nhân viên.
2. Lịch quản trị chấm công với hai chế độ Tổng quan và Nhân viên.
3. Lịch đối soát trong chi tiết bảng lương.
4. Điều hướng quản trị dạng nhóm, ưu tiên làm gọn menu offcanvas trên mobile nhưng vẫn đồng bộ với sidebar desktop.

Mục tiêu quan trọng nhất là cải thiện cách xem dữ liệu mà không thay đổi công thức lương, luồng GPS, quy tắc phân quyền, audit trail, khóa kỳ lương hoặc dữ liệu tài chính hiện hành.

## 2. Nguyên tắc an toàn

### 2.1. Server là nguồn sự thật duy nhất

- Không tính tiền lương, hệ số ca, tăng ca, KPI hoặc hoa hồng bằng JavaScript.
- Không dùng màu hiển thị làm căn cứ nghiệp vụ.
- Không nhận quyền sửa từ tham số do trình duyệt gửi lên.
- Mọi thao tác ghi phải tiếp tục qua Form Request, Policy, Action và PayrollLockGuard hiện có.

### 2.2. Không thay đổi luồng nghiệp vụ hiện hành trong đợt này

Giữ nguyên:

- CheckInAction và CheckOutAction.
- SaveManualAttendanceAction.
- CalculatePayrollAction.
- Luồng duyệt tăng ca.
- Luồng audit chấm công.
- Quy tắc khóa bảng lương finalized và paid.
- Quy tắc chống quản lý tự tạo, sửa hoặc xóa công của mình.
- BranchContext và phạm vi chi nhánh.
- Password confirmation với thao tác tài chính nhạy cảm.

### 2.3. Progressive enhancement

- Trang phải đọc được khi JavaScript không chạy.
- Điều hướng tháng, bộ lọc và liên kết chi tiết phải hoạt động bằng request server thông thường.
- Alpine chỉ hỗ trợ chọn ngày, mở panel và cải thiện trải nghiệm.
- Không fetch ngầm dữ liệu nhạy cảm từ client trong phiên bản đầu.

### 2.4. Không migration phá hủy

- Ưu tiên không đổi schema database.
- Không sửa dữ liệu lịch sử.
- Không tạo bản sao dữ liệu lương chỉ để phục vụ giao diện.
- Nếu phát hiện bắt buộc phải bổ sung schema, phải tách thành quyết định riêng và đánh giá migration/rollback trước khi thực hiện.

## 3. Phạm vi chức năng

## 3.1. Lịch cá nhân của nhân viên

Giữ nguyên khối hành động hiện tại:

- Ca hôm nay.
- Vào ca.
- Ra ca.
- Lấy GPS.
- Thông báo đi muộn.
- Thông báo tăng ca chờ duyệt.

Bổ sung lịch tháng:

- Header tháng và năm.
- Nút tháng trước, tháng sau và về tháng hiện tại.
- Tuần bắt đầu từ thứ Hai.
- Mỗi ngày hiển thị tối đa hai dòng giờ vào và giờ ra.
- Nếu có nhiều ca, hiển thị ca đầu tiên và chỉ báo số ca còn lại.
- Chạm ngày mở chi tiết tất cả ca của chính nhân viên.
- Không có nút sửa/xóa đối với nhân viên thường.
- Ngày tương lai có phân ca được hiển thị khác với bản ghi đã chấm.
- Ngày ngoài tháng vẫn hiện mờ để giữ lưới đầy đủ.

## 3.2. Lịch quản trị chấm công

Có hai chế độ:

### Tổng quan

- Mỗi ô ngày hiển thị tổng số bản ghi hoặc số nhân viên có dữ liệu.
- Hiển thị chỉ báo cảnh báo cho các trường hợp cần chú ý.
- Chạm ngày mở danh sách tất cả nhân viên và tất cả ca trong ngày thuộc phạm vi chi nhánh hiện hành.
- Cảnh báo gồm tối thiểu: thiếu check-out, đi muộn, vắng, GPS cần xem lại, tăng ca chờ duyệt và bản ghi self-recorded.

### Nhân viên

- Chọn một nhân viên trong phạm vi chi nhánh.
- Mỗi ô ngày hiển thị giờ vào/ra và trạng thái của người đó.
- Nếu một ngày có nhiều ca, hiển thị chỉ báo và mở đầy đủ trong panel.
- Nút sửa/xóa chỉ xuất hiện khi Policy cho phép.
- Nút thêm ca mở biểu mẫu hiện có, không tạo endpoint ghi mới.

Bộ lọc:

- Tháng.
- Chế độ Tổng quan/Nhân viên.
- Nhân viên.
- Trạng thái.
- Nguồn bản ghi.
- Chi nhánh tiếp tục theo BranchContext hiện hành.

Khi chuyển tháng hoặc chế độ, các bộ lọc hợp lệ phải được giữ lại.

## 3.3. Lịch đối soát bảng lương

- Hiển thị trong trang chi tiết payroll.
- Chỉ đọc ở mọi trạng thái payroll.
- Phạm vi ngày lấy đúng period_start và period_end, không tự mở rộng thành tháng nếu kỳ lương không trùng tháng dương lịch.
- Chỉ lấy attendance của đúng employee_id thuộc payroll.
- Đánh dấu ngày có ca được tính lương, đi muộn, nghỉ, vắng, tăng ca và KPI ngày nếu có.
- Panel ngày giải thích dữ liệu nguồn đóng góp vào kỳ lương.
- Không cho sửa chấm công trực tiếp trong lịch payroll.
- Payroll draft hiển thị cảnh báo dữ liệu có thể thay đổi sau khi tính lại.
- Payroll finalized hoặc paid hiển thị trạng thái đã khóa.
- Không gọi CalculatePayrollAction chỉ vì người dùng mở lịch.

## 3.4. Gom nhóm menu quản trị

Mục tiêu là giảm 12 liên kết cấp cao đang hiển thị liên tục trong offcanvas, giúp người dùng quét nhanh hơn mà không xóa hoặc đổi quyền truy cập của bất kỳ chức năng nào.

Cấu trúc nhóm dự kiến:

1. **Truy cập nhanh** — Tổng quan, Chấm công của tôi, Lịch hẹn. Nhóm này luôn mở vì chứa các tác vụ dùng hằng ngày.
2. **Bán hàng & tài chính** — Hóa đơn & thu chi, Báo cáo.
3. **Nhân sự & ca làm** — Chấm công & lương hoặc Lương của tôi, Đơn ca & nghỉ, Nhân sự.
4. **Vận hành cửa hàng** — Kho vật tư, Dịch vụ, Chi nhánh.
5. **Kiểm soát** — Cảnh báo. Chỉ render khi có ít nhất một mục được phép xem.

Cấu trúc cuối cùng được sinh từ AdminNavigation, không hard-code một danh sách riêng trong mobile menu:

- Bổ sung metadata group/order cho từng destination nhưng giữ nguyên route, pattern, icon, primary và badge hiện hữu.
- Sau khi lọc visibility theo Policy/Gate, loại mọi nhóm rỗng; không render tiêu đề hoặc nút nhóm mà người dùng không có child hợp lệ.
- Nhóm chứa route đang active phải tự mở và parent phải có trạng thái active rõ ràng.
- Các nhóm còn lại dùng Bootstrap Collapse; không cần endpoint, quyền hoặc JavaScript nghiệp vụ mới.
- Badge Đơn ca & nghỉ tiếp tục nằm cạnh child; parent Nhân sự & ca làm có thể hiển thị tổng badge để người dùng thấy việc cần xử lý ngay cả khi nhóm đang đóng.
- Badge tổng chỉ cộng từ các child đã được phép render, không làm phát sinh query badge lần hai.
- Bottom tab bar tiếp tục lấy bốn mục primary phẳng từ cùng collection và không bị biến thành menu nhóm.
- Sidebar desktop và offcanvas mobile dùng chung component nhóm để không lệch active state, quyền hoặc thứ tự; desktop có thể mở sẵn toàn bộ nhóm nếu trải nghiệm thực tế phù hợp hơn.
- Profile, xem website và đăng xuất giữ ở footer riêng, không trộn vào nhóm nghiệp vụ.
- Không đổi route hoặc nhãn nghiệp vụ trong đợt gom nhóm, ngoại trừ tiêu đề nhóm mới.

## 4. Thiết kế lớp dữ liệu

## 4.1. Calendar Range

Tạo lớp hỗ trợ khoảng lịch dùng chung:

- Nhận tháng hoặc khoảng ngày hợp lệ.
- Chuẩn hóa theo múi giờ nghiệp vụ trong config ứng dụng.
- Tuần bắt đầu thứ Hai.
- Với lịch tháng, tạo lưới đủ tuần chứa tháng đang xem, thường là 35 hoặc 42 ô.
- Mỗi ô biết ngày ISO, số ngày, thuộc tháng chính hay ngoài tháng, là hôm nay hay không.
- Không parse tự do chuỗi tháng bằng cách có thể gây exception 500.
- Tháng sai định dạng phải được validate và trả lỗi/fallback có kiểm soát.

## 4.2. ViewModel chỉ đọc

Tạo ViewModel/DTO thay vì đặt logic phân loại trong Blade.

Dữ liệu cấp lịch:

- Tiêu đề khoảng thời gian.
- URL tháng trước, tháng sau và tháng hiện tại.
- Danh sách nhãn thứ trong tuần.
- Danh sách CalendarDay.
- Legend trạng thái.
- Chế độ hiển thị.

Dữ liệu cấp ngày:

- Ngày ISO và nhãn hỗ trợ screen reader.
- Thuộc tháng chính hay không.
- Danh sách item trong ngày.
- Tổng số bản ghi.
- Tổng số cảnh báo.
- Tone hiển thị ưu tiên.
- Có được mở panel hay không.

Dữ liệu cấp item:

- ID bản ghi khi người dùng có quyền biết.
- Nhân viên.
- Tên ca.
- Giờ dự kiến nếu là phân ca tương lai.
- Giờ vào/ra thực tế.
- Trạng thái attendance.
- Phút đi muộn.
- Phút tăng ca và trạng thái duyệt.
- Nguồn GPS/manual.
- Cờ thiếu check-out.
- Cờ bị khóa bởi payroll.
- URL xem/sửa chỉ khi server cấp quyền.

Không đưa vào ViewModel:

- Quyền được tin tưởng từ client.
- Phép tính tổng lương mới.
- Dữ liệu của nhân viên/chi nhánh ngoài scope.
- Tọa độ GPS trong payload lịch tổng quan nếu không thật sự cần.

## 4.3. Query lịch cá nhân

- Luôn ràng buộc employee_id bằng user đang đăng nhập.
- Bỏ qua employee_id từ query string.
- Lấy attendance trong khoảng lịch.
- Lấy shift assignments cần thiết để hiển thị lịch tương lai.
- Eager-load branch và shift assignment theo nhu cầu.
- Không query từng ngày.
- Không query từng ô lịch.

## 4.4. Query lịch quản trị

- Luôn áp dụng BranchContext trước filter do request cung cấp.
- Chỉ chấp nhận employee_id thuộc actor scope.
- Chế độ Tổng quan dùng aggregate theo ngày để giảm dữ liệu tải.
- Chi tiết ngày dùng collection đã load hoặc một endpoint server-rendered được policy bảo vệ nếu payload tháng quá lớn.
- Chế độ Nhân viên lấy đầy đủ bản ghi trong tháng của một người hợp lệ.
- Eager-load employee, branch và shiftAssignment theo nhu cầu.
- Giữ query budget không tăng tuyến tính theo số ngày hoặc số nhân viên.

## 4.5. Query lịch payroll

- Authorize view payroll trước khi query attendance.
- Ràng buộc employee_id từ payroll model, không từ request.
- Ràng buộc period_start và period_end từ payroll model.
- Load KPI ngày bằng quan hệ hiện có.
- Không dùng dữ liệu ngoài kỳ để giải thích tổng lương.
- Với payroll finalized/paid, không tạo link sửa chấm công.

## 5. Thiết kế giao diện dùng chung

## 5.1. Calendar component

Tạo Blade component dùng chung gồm:

- Toolbar tháng/kỳ.
- Hàng nhãn thứ.
- Lưới ngày.
- Legend.
- Empty state.
- Slot cho nội dung tóm tắt theo chế độ.

Yêu cầu semantic/accessibility:

- Nút ngày có accessible name đầy đủ.
- Trạng thái không chỉ thể hiện bằng màu; phải có text, icon hoặc aria-label.
- Focus keyboard rõ ràng.
- Thứ tự tab hợp lý.
- Ngày ngoài tháng có mô tả phù hợp.
- Touch target đủ lớn.

## 5.2. Quy tắc hiển thị ô ngày

Ưu tiên trạng thái nghiêm trọng nhất:

1. Vắng hoặc lỗi cần xử lý.
2. Thiếu check-out.
3. Tăng ca chờ duyệt hoặc GPS cần xem lại.
4. Đi muộn.
5. Nghỉ phép.
6. Đi làm hợp lệ.
7. Chỉ có lịch phân ca tương lai.
8. Không có dữ liệu.

Quy tắc này chỉ điều khiển tone hiển thị, không thay đổi trạng thái nghiệp vụ trong database.

Ô ngày cá nhân:

- Số ngày.
- Giờ vào.
- Giờ ra.
- Badge nhỏ nếu nhiều ca.
- Dấu cảnh báo có nhãn.

Ô ngày tổng quan:

- Số ngày.
- Số người hoặc số bản ghi.
- Số cảnh báo.
- Không nhồi tên từng nhân viên vào ô.

Ô ngày payroll:

- Số ngày.
- Tổng hệ số ca hoặc số ca nguồn.
- Dấu KPI/tăng ca nếu có.
- Tone trạng thái.

## 5.3. Panel chi tiết ngày

- Mobile: bottom sheet/offcanvas từ dưới hoặc modal căn dưới.
- Desktop: modal/panel có chiều rộng phù hợp.
- Nội dung được server render để tránh tái tạo logic nghiệp vụ ở client.
- Có tiêu đề ngày đầy đủ.
- Liệt kê từng ca riêng, không gộp giờ của hai ca.
- Hiển thị trạng thái, nguồn, giờ vào/ra, đi muộn, tăng ca và cảnh báo.
- Nút thao tác chỉ render sau khi server kiểm tra Policy.
- Có fallback link tới trang hiện hữu khi JavaScript không chạy.

## 5.4. Component menu nhóm

- Mở rộng nav-items hoặc tạo component nav-groups nhận collection đã được lọc quyền từ server.
- Nút mở nhóm là button thật, có aria-expanded, aria-controls và tên nhóm rõ ràng.
- Child dùng anchor hiện hữu, giữ aria-current="page" trên route active.
- Có focus ring, touch target tối thiểu 44px và icon chỉ báo đóng/mở không phụ thuộc màu.
- Nhóm active tự mở khi render server-side, do đó trang hiện tại luôn nhìn thấy được kể cả trước khi Bootstrap khởi tạo.
- Khi JavaScript bị tắt, dùng chiến lược progressive enhancement để các liên kết vẫn nhìn thấy/truy cập được; không để collapse mặc định che toàn bộ child.
- Không lồng collapse quá một cấp.
- Offcanvas phải cuộn độc lập, footer tài khoản không che child cuối và tương thích safe-area.
- Không đóng offcanvas khi chỉ mở/đóng nhóm; đóng theo hành vi điều hướng chuẩn khi chọn child.
- Trạng thái mở tùy chọn không cần lưu vào database; ưu tiên deterministic theo active route thay vì localStorage trong phiên bản đầu.

## 6. Tích hợp từng màn

## 6.1. Màn nhân viên

- Giữ khối chấm công hôm nay ở vị trí ưu tiên đầu trang.
- Thay bảng 14 ngày hoặc bổ sung bên dưới bằng lịch tháng.
- Không làm Alpine calendar ảnh hưởng Alpine clockButton.
- Không đổi tên route check-in/check-out.
- Không đổi payload GPS.
- Không tự refresh hoặc gửi form khi người dùng chọn ngày.

## 6.2. Màn quản trị

- Giữ page header, shortcut tăng ca chờ duyệt và quick form.
- Thay bảng chính bằng calendar nhưng giữ đường truy cập tới danh sách/fallback nếu cần trong giai đoạn rollout.
- Tổng quan là mặc định khi chưa chọn nhân viên.
- Khi chọn nhân viên, chuyển sang chế độ chi tiết.
- Các filter status/source phải có định nghĩa rõ: áp dụng vào bản ghi hiển thị, không làm sai summary toàn tháng mà không có nhãn.
- Tạo/sửa/xóa vẫn dùng route hiện tại.

## 6.3. Màn payroll

- Thêm partial đối soát vào trang chi tiết.
- Không thay các partial payslip, allocation, KPI, adjustment và action hiện hữu.
- Lịch là phần giải thích dữ liệu nguồn, không phải bảng tính thứ hai.
- Không hiển thị số tạm tính phía client.
- Nếu có khác biệt giữa dữ liệu attendance hiện tại và payroll đã khóa, phải ghi rõ payroll là snapshot đã khóa; không âm thầm thay số.

## 7. Ma trận case bắt buộc

## 7.1. Lịch và ngày tháng

- Tháng 28 ngày.
- Tháng nhuận 29 ngày.
- Tháng 30 ngày.
- Tháng 31 ngày.
- Tháng bắt đầu vào thứ Hai.
- Tháng bắt đầu vào Chủ nhật.
- Chuyển tháng 12 sang tháng 1 và ngược lại.
- Ngày ngoài tháng ở đầu/cuối lưới.
- Hôm nay theo business timezone.
- Query month rỗng, sai định dạng hoặc ngoài phạm vi hợp lý.

## 7.2. Dữ liệu chấm công

- Không có ca.
- Có phân ca nhưng chưa đến ngày.
- Có phân ca hôm nay nhưng chưa chấm.
- Một ca hoàn chỉnh.
- Nhiều ca cùng ngày.
- Ca qua đêm nhưng work_date thuộc ngày bắt đầu.
- Có check-in chưa check-out.
- Đi muộn.
- Nghỉ phép.
- Vắng.
- Tăng ca none, pending, approved và rejected.
- GPS verified và needs review.
- Manual record có reason/audit.
- Owner self-recorded.
- Bản ghi có note dài.
- Nhân viên đã ngừng hoạt động nhưng có lịch sử trong kỳ.

## 7.3. Phân quyền và scope

- Guest bị chuyển login.
- Employee chỉ thấy attendance của mình.
- Employee không thể truyền employee_id để xem người khác.
- Manager chỉ thấy branch được cấp.
- Manager không thể truyền branch/employee ngoài scope.
- Manager không được tạo/sửa/xóa công của chính mình.
- Owner được xử lý trường hợp self-record nhưng vẫn có risk/review behavior hiện hành.
- Payroll manager chỉ thấy payroll theo quyền hiện hành.
- Employee chỉ xem payroll của mình.
- Không lộ dữ liệu trong HTML, JSON, data attribute hoặc panel ẩn.

## 7.4. Khóa kỳ lương

- Draft cho phép cập nhật attendance qua action hiện hành.
- Finalized chặn tạo/sửa/xóa attendance trong kỳ.
- Paid chặn tạo/sửa/xóa attendance trong kỳ.
- Chặn di chuyển bản ghi vào hoặc ra kỳ đã khóa.
- Chặn duyệt tăng ca ảnh hưởng kỳ đã khóa.
- Lịch payroll luôn chỉ đọc.
- Mở lịch không tự tính lại payroll.
- Recalculate draft vẫn lấy dữ liệu attendance mới đúng như trước.

## 7.5. Regression lương

- Tổng shift_count.
- Shift pay.
- Attendance bonus.
- Paid leave và unpaid leave.
- Worked paid leave bonus.
- Approved overtime.
- Regular/overtime commission.
- Daily KPI.
- Bill KPI.
- Manual adjustment.
- Automatic adjustment không bị nhân bản.
- Rounding.
- Variance approval.
- Finalize/pay/cancel authorization.
- Thanh toán payroll chỉ tạo một cash transaction.

## 7.6. Frontend và thiết bị

- iPhone màn nhỏ.
- Android màn nhỏ.
- Tablet portrait/landscape.
- Desktop.
- Safe-area bottom navigation.
- Text zoom.
- Keyboard navigation.
- Screen reader label cơ bản.
- prefers-reduced-motion.
- JavaScript bị tắt.
- Alpine component load đúng, không có lỗi console/browser log.
- Không xung đột clockButton, submitGuard, confirm modal và offcanvas hiện hữu.

## 7.7. Menu và phân quyền điều hướng

- Owner, manager, employee và các role hiện hữu chỉ thấy đúng destination như trước khi gom nhóm.
- Employee vẫn đi tới Lương của tôi, không bị dẫn vào màn attendance quản trị.
- Nhóm không có child hợp lệ không xuất hiện trong HTML.
- Route active mở đúng nhóm, đánh dấu đúng parent/child và chỉ có child mang aria-current="page".
- Badge Đơn ca & nghỉ giữ đúng branch scope, đúng số lượng và vẫn nhận biết được khi nhóm đóng.
- Bottom tab bar vẫn đủ tối đa bốn primary item và nút Thêm.
- Sidebar desktop và offcanvas mobile không lệch route, nhãn, badge hoặc quyền.
- Profile, website và logout vẫn hoạt động.
- Không tăng số query do render cùng navigation ở sidebar, offcanvas và tab bar.

## 8. Chiến lược kiểm thử tự động

## 8.1. Unit test

- Calendar range builder.
- Nhóm item theo ngày.
- Ưu tiên tone trạng thái.
- Format giờ và fallback thiếu dữ liệu.
- URL chuyển tháng bảo toàn filter.

## 8.2. Feature test lịch cá nhân

- Render đúng tháng.
- Chỉ thấy dữ liệu chính mình.
- Hiện assignment tương lai.
- Nhiều ca cùng ngày.
- Không ảnh hưởng check-in/check-out GPS.

## 8.3. Feature test lịch quản trị

- Tổng quan chỉ thuộc branch scope.
- Chế độ nhân viên từ chối ID ngoài scope.
- Filter month/status/source.
- Panel chi tiết không lộ dữ liệu ngoài scope.
- Nút sửa/xóa theo Policy.
- Quick form vẫn hoạt động.

## 8.4. Feature test lịch payroll

- Chỉ lấy đúng employee và period.
- Employee không xem payroll người khác.
- Finalized/paid không có link sửa.
- Draft không bị tự recalculate khi xem.
- KPI ngày khớp quan hệ hiện có.

## 8.5. Regression suite

Chạy tối thiểu:

- Toàn bộ tests/Feature/Attendance.
- Toàn bộ tests/Feature/Payroll.
- BranchAttendanceTest.
- BranchPayrollVisibilityTest.
- AdminAuthorizationTest.
- ScopedForeignIdTest.
- QueryBudgetTest.
- StepUpAuthenticationTest.
- AlpineComponentContractTest.
- Toàn bộ test suite trước deploy.

## 8.6. Navigation test

- Unit test cấu trúc nhóm, thứ tự và loại nhóm rỗng sau khi lọc visibility.
- Feature test role matrix cho owner/manager/employee và các role hiện hữu.
- Mở rộng ShiftRequestNavigationTest để kiểm tra badge child, badge parent và branch scope.
- Render test active group/child, aria-expanded, aria-controls và aria-current.
- Contract test xác nhận bottom tab bar vẫn lấy collection primary phẳng.
- Test fallback bảo đảm child vẫn truy cập được khi JavaScript không chạy.

## 8.7. Query budget

- Không query trong vòng lặp Blade.
- Không gọi Policy gây query lặp nếu có thể chuẩn bị trạng thái quyền theo collection mà vẫn an toàn.
- Eager-load quan hệ dùng trong panel.
- Aggregate tổng quan theo database.
- Không gọi AdminNavigation::for nhiều lần trong cùng request và không chạy lại badge query khi group collection.
- Thêm test query budget cho ba màn lịch và navigation.
- So sánh trước/sau bằng dữ liệu nhiều nhân viên, nhiều ca.

## 9. Trình tự triển khai

### Giai đoạn 0: gom nhóm điều hướng

1. Viết test characterization cho menu phẳng hiện tại: visibility, route, active state, primary và badge.
2. Bổ sung metadata group vào AdminNavigation và API group collection không làm chạy lại query.
3. Tạo component menu nhóm dùng chung cho sidebar/offcanvas, giữ tab bar phẳng.
4. Bổ sung SCSS collapse, active parent/child, badge và safe-area.
5. Chạy navigation authorization, badge, accessibility, query budget và responsive test.
6. Giữ thay đổi này trong commit độc lập để có thể rollback menu mà không rollback calendar.

### Giai đoạn 1: nền tảng dữ liệu

1. Viết test calendar range trước.
2. Tạo range builder.
3. Tạo DTO/ViewModel.
4. Tạo query/service riêng cho ba ngữ cảnh.
5. Viết test scope và query budget.

### Giai đoạn 2: component giao diện

1. Tạo Blade calendar component.
2. Tạo day-cell component.
3. Tạo detail panel component.
4. Tạo SCSS responsive.
5. Tạo Alpine component tối thiểu.
6. Viết render/accessibility test.

### Giai đoạn 3: lịch cá nhân

1. Tích hợp dữ liệu lịch vào ShiftBoard hoặc query chuyên biệt.
2. Giữ nguyên clock panel.
3. Thêm lịch tháng.
4. Chạy GpsCheckInTest, GpsCheckOutTest và AttendancePagesRenderTest.
5. Test thủ công GPS trên thiết bị thật.

### Giai đoạn 4: lịch quản trị

1. Bổ sung validate month/mode/employee.
2. Tạo chế độ Tổng quan.
3. Tạo chế độ Nhân viên.
4. Gắn panel chi tiết và quyền thao tác.
5. Giữ quick form và review shortcut.
6. Chạy test branch scope, self-dealing, audit, payroll lock và query budget.

### Giai đoạn 5: lịch payroll

1. Query attendance theo payroll period.
2. Tạo partial reconciliation calendar.
3. Gắn KPI ngày.
4. Thêm trạng thái draft/locked.
5. Chạy toàn bộ test payroll và payslip.

### Giai đoạn 6: hardening

1. Chạy Pint.
2. Chạy test mục tiêu.
3. Chạy toàn bộ test suite.
4. Chạy npm build.
5. Kiểm tra browser console/log.
6. Test responsive và accessibility.
7. Kiểm tra query budget.
8. Sửa mọi regression trước deploy.

## 10. Quality gate trước deploy

Không deploy nếu một trong các điều kiện sau chưa đạt:

- Có test attendance/payroll/navigation thất bại.
- Có destination bị mất, sai route, sai quyền hoặc nhóm rỗng xuất hiện sau khi gom menu.
- Badge Đơn ca & nghỉ sai scope/số lượng hoặc không còn nhận biết được khi nhóm đóng.
- Có lỗi npm build.
- Có lỗi Alpine/JavaScript trong browser log.
- Có N+1 hoặc vượt query budget đã thống nhất.
- Employee hoặc manager nhìn thấy dữ liệu ngoài scope.
- Có đường thao tác vượt AttendanceRecordPolicy.
- Có thể sửa dữ liệu trong kỳ finalized/paid.
- Việc mở lịch làm thay đổi payroll hoặc attendance.
- GPS check-in/check-out bị thay đổi hành vi.
- Giao diện mobile không thao tác được ở màn nhỏ.
- Không có phương án rollback xác minh được.

## 11. Deploy production

1. Không deploy trực tiếp từ working tree chưa commit.
2. Chạy quality gate local.
3. Commit/push thay đổi đã review.
4. Pull bằng user deploy theo tài liệu server.
5. Đồng bộ release nhưng bảo toàn .env, database và runtime files.
6. Composer install production.
7. Chỉ chạy migration nếu thực sự có migration đã được duyệt; kế hoạch mặc định không cần migration.
8. Build frontend.
9. Xóa và tạo lại Laravel caches.
10. Kiểm tra service.
11. Smoke test ba màn bằng các role phù hợp.
12. Theo dõi Laravel log, Nginx log và browser log.
13. Kiểm tra PageSeed không bị ảnh hưởng.

## 12. Smoke test sau deploy

### Điều hướng

- Kiểm tra owner, manager và employee trên mobile offcanvas lẫn desktop sidebar.
- Mở/đóng từng nhóm, chuyển trang và xác minh nhóm chứa trang hiện tại tự mở.
- Xác minh mọi destination được phép vẫn truy cập được và destination bị cấm không xuất hiện trong HTML.
- Kiểm tra badge Đơn ca & nghỉ ở child/parent với đúng branch scope.
- Kiểm tra bốn tab nhanh và nút Thêm không thay đổi hành vi.
- Kiểm tra keyboard, screen reader label cơ bản, text zoom, JavaScript tắt và màn hình có safe-area.

### Nhân viên

- Đăng nhập.
- Mở chấm công.
- Chuyển tháng.
- Mở chi tiết một ngày.
- Vào ca hoặc ra ca bằng GPS trong điều kiện test hợp lệ.
- Xác minh không gửi hai lần.

### Quản lý

- Mở tổng quan tháng.
- Chuyển chi nhánh trong phạm vi.
- Mở một ngày có nhiều người.
- Chọn một nhân viên.
- Sửa bản ghi được phép.
- Xác minh không sửa được bản ghi của chính quản lý.
- Xác minh kỳ khóa bị chặn.

### Payroll

- Employee xem payroll của mình.
- Manager có quyền xem payroll thuộc branch.
- Owner xem draft/finalized/paid.
- Mở ngày đối soát.
- Xác minh không có nút sửa trong lịch payroll.
- Xác minh số payslip không đổi chỉ vì mở lịch.

## 13. Rollback

- Giữ commit menu grouping tách biệt với commit calendar để có thể rollback độc lập.
- Giữ commit thay đổi giao diện/query tách biệt với thay đổi khác.
- Không thay schema và không mutate dữ liệu trong rollout mặc định.
- Nếu menu grouping thất bại, phục hồi component menu phẳng và metadata navigation trước đó mà không ảnh hưởng route/quyền.
- Nếu smoke test calendar thất bại, rollback release/commit calendar và build lại asset.
- Xóa cache ứng dụng sau rollback.
- Xác minh lại màn chấm công cũ, payroll, GPS và service health.
- Không rollback database nếu kế hoạch không tạo migration.

## 14. Tiêu chí hoàn thành

Tính năng chỉ được coi là hoàn thành khi:

- Cả ba màn lịch hoạt động đúng phạm vi.
- Menu mobile được gom nhóm gọn, không mất destination, badge, quyền hoặc active state; sidebar desktop và tab bar vẫn đồng bộ.
- Mobile thể hiện gần với mẫu đã duyệt nhưng phù hợp dữ liệu nhiều ca.
- Mọi quyền và branch scope được giữ nguyên.
- Không thay công thức hoặc số payroll hiện hành.
- Kỳ finalized/paid không thể bị thay đổi qua lịch.
- GPS vào/ra ca hoạt động như trước.
- Audit trail và review queue hoạt động như trước.
- Toàn bộ test suite xanh.
- npm build thành công.
- Query budget đạt yêu cầu.
- Smoke test production đạt.
- Không có lỗi mới trong log sau triển khai.

## 15. Danh sách công việc thực thi

- [ ] Viết navigation characterization test cho visibility, route, active state, primary item và badge hiện tại.
- [ ] Bổ sung metadata nhóm vào AdminNavigation, lọc nhóm rỗng và không lặp badge query.
- [ ] Tạo component nhóm dùng chung cho mobile offcanvas và desktop sidebar; giữ bottom tab bar phẳng.
- [ ] Bổ sung active parent/child, auto-open nhóm hiện tại, badge child/parent và progressive fallback.
- [ ] Bổ sung SCSS responsive, touch target, safe-area, focus và prefers-reduced-motion cho menu nhóm.
- [ ] Viết navigation role/authorization, branch badge, accessibility, no-JS và query budget test.
- [ ] Thiết kế lớp dữ liệu lịch dùng chung: khoảng ngày, lưới 35/42 ô, ngày ngoài tháng và múi giờ nghiệp vụ.
- [ ] Tạo DTO/ViewModel chỉ đọc cho lịch, ngày và item.
- [ ] Xây query lịch cá nhân giới hạn theo tài khoản đăng nhập.
- [ ] Xây query lịch quản trị theo BranchContext và actor scope.
- [ ] Xây query lịch payroll theo employee_id và period của payroll.
- [ ] Giữ nguyên toàn bộ action ghi dữ liệu hiện có.
- [ ] Validate month/mode/employee và điều hướng tháng an toàn.
- [ ] Tạo Blade calendar component dùng chung.
- [ ] Tạo day-cell component cho từng chế độ.
- [ ] Tạo detail panel responsive và fallback không JavaScript.
- [ ] Tích hợp lịch cá nhân mà không thay clock panel GPS.
- [ ] Tích hợp lịch quản trị Tổng quan/Nhân viên.
- [ ] Tích hợp lịch đối soát payroll chỉ đọc.
- [ ] Bổ sung SCSS responsive/accessibility.
- [ ] Bổ sung Alpine component tối thiểu.
- [ ] Viết unit test calendar range và mapping trạng thái.
- [ ] Viết feature test lịch cá nhân.
- [ ] Viết feature test lịch quản trị.
- [ ] Viết feature test lịch payroll.
- [ ] Viết security test scope, IDOR và self-dealing.
- [ ] Viết payroll lock regression test.
- [ ] Chạy GPS regression test.
- [ ] Chạy payroll calculation regression test.
- [ ] Bổ sung query budget test.
- [ ] Chạy Pint, test suite và npm build.
- [ ] Kiểm thử menu nhóm và calendar trên thiết bị thật.
- [ ] Deploy, smoke test điều hướng cùng ba màn lịch, theo dõi log và xác minh rollback độc lập.
