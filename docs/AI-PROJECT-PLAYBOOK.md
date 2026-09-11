# AI Project Playbook — Cái Tiệm Neo

Tài liệu này là bản đồ ngắn gọn cho AI/agent làm việc tiếp trong dự án. Đọc tài liệu này trước khi trace source. Quy ước và ràng buộc chi tiết của Laravel vẫn nằm tại [`CLAUDE.md`](../CLAUDE.md).

## 1. Mục tiêu và nền tảng

- **Sản phẩm:** quản trị chuỗi tiệm nail Cái Tiệm Neo: lịch hẹn, hóa đơn, thu chi, kho, nhân sự, chấm công, lương và báo cáo.
- **Stack:** Laravel, PHP 8.3, MariaDB, Blade, Vite, Alpine, PHPUnit.
- **Local URL:** `http://caitiemneo.local`.
- **Phong cách giao diện:** `Be Vietnam Pro` cho nội dung; `DM Serif Display` cho heading/brand. CSS cũ ở [`resources/css/app.css`](../resources/css/app.css); admin mới ở [`resources/scss/admin.scss`](../resources/scss/admin.scss).
- **Không thêm dependency hoặc thư mục gốc mới nếu chưa có yêu cầu rõ ràng.**

## 2. Bản đồ luồng HTTP

Tất cả route nghiệp vụ được khai báo tại [`routes/web.php`](../routes/web.php).

| Nhóm | URL/route name | Controller/điểm vào | Ghi chú |
|---|---|---|---|
| Public | `/`, `booking.store` | [`HomeController`](../app/Http/Controllers/HomeController.php), [`BookingController`](../app/Http/Controllers/BookingController.php) | Khách đặt lịch |
| Admin | `/admin` | [`DashboardController`](../app/Http/Controllers/Admin/DashboardController.php) | Cần `auth`, `verified`, `role:*`, `branch.context` |
| Lịch hẹn | `admin.appointments.*` | [`AppointmentController`](../app/Http/Controllers/Admin/AppointmentController.php) | Lưu bằng [`SaveAppointmentAction`](../app/Actions/Appointments/SaveAppointmentAction.php) |
| Hóa đơn | `admin.invoices.*` | [`InvoiceController`](../app/Http/Controllers/Admin/InvoiceController.php), [`InvoicePaymentController`](../app/Http/Controllers/Admin/InvoicePaymentController.php) | Xem workflow mục 5 |
| Quỹ tiền | `admin.cash.*` | [`CashTransactionController`](../app/Http/Controllers/Admin/CashTransactionController.php) | Dùng action thu/huỷ riêng |
| Kho | `admin.products.*`, `admin.inventory.*` | [`ProductController`](../app/Http/Controllers/Admin/ProductController.php), [`InventoryMovementController`](../app/Http/Controllers/Admin/InventoryMovementController.php) | Điều chỉnh tồn qua action, không sửa số tồn trực tiếp |
| Chuyển kho | `admin.stock-transfers.*` | [`StockTransferController`](../app/Http/Controllers/Admin/StockTransferController.php) | Complete/cancel là workflow riêng |
| Nhân sự | `admin.employees.*`, `admin.attendance.*` | [`EmployeeController`](../app/Http/Controllers/Admin/EmployeeController.php), [`AttendanceController`](../app/Http/Controllers/Admin/AttendanceController.php) | Profile lương và phân công chi nhánh |
| Lương | `admin.payrolls.*` | [`PayrollController`](../app/Http/Controllers/Admin/PayrollController.php), [`PayrollStatusController`](../app/Http/Controllers/Admin/PayrollStatusController.php) | Snapshot và trạng thái bất biến |
| Báo cáo | `admin.reports.*` | [`ReportController`](../app/Http/Controllers/Admin/ReportController.php), [`ReportExportController`](../app/Http/Controllers/Admin/ReportExportController.php) | Read-side: [`ReportService`](../app/Services/ReportService.php) |

## 3. Phân quyền và chi nhánh — bắt buộc giữ nguyên

### Vai trò

Enum nguồn: [`UserRole`](../app/Enums/UserRole.php). Các policy cùng tên model đặt tại [`app/Policies`](../app/Policies).

- **Owner:** xem toàn công ty, cấu hình chi nhánh và có thể chọn `branch=all`.
- **Manager:** chỉ chi nhánh được phân công.
- **Employee:** chỉ dữ liệu/khả năng được policy cho phép ở chi nhánh được phân công.

Không chỉ dựa vào menu để bảo vệ dữ liệu: controller phải dùng authorize/policy theo convention sẵn có.

### Branch context

[`BranchContext`](../app/Support/BranchContext.php) là nguồn chân lý của chi nhánh trong request:

- Middleware `branch.context` resolve lựa chọn từ query `branch`, session, rồi chi nhánh được phép đầu tiên.
- Owner duy nhất được dùng `branch=all`.
- `scopeIds()` luôn trả về danh sách branch hợp lệ; nếu rỗng, query phải không trả về dữ liệu.
- Mọi **write/create** phải gọi `requireWritableBranchId()`. Giá trị `null` nghĩa là đang ở chế độ `all`, phải bắt người dùng chọn một chi nhánh cụ thể.
- Không tạo global Eloquent scope. Dùng query objects hoặc trait [`BranchScope`](../app/Queries/BranchScope.php) để scope một cách tường minh.

Các test chống rò dữ liệu chi nhánh nằm trong [`tests/Feature/Branch`](../tests/Feature/Branch).

## 4. Điểm vào theo domain

| Domain | Đọc dữ liệu / query | Thay đổi dữ liệu / action | Test chính |
|---|---|---|---|
| Appointment | [`AppointmentController`](../app/Http/Controllers/Admin/AppointmentController.php) | [`SaveAppointmentAction`](../app/Actions/Appointments/SaveAppointmentAction.php) | [`AppointmentOverlapTest`](../tests/Feature/Admin/AppointmentOverlapTest.php), [`BranchAppointmentTest`](../tests/Feature/Branch/BranchAppointmentTest.php) |
| Invoice | [`InvoiceQuery`](../app/Queries/InvoiceQuery.php) | [`ConvertAppointmentToInvoiceAction`](../app/Actions/Invoices/ConvertAppointmentToInvoiceAction.php), [`UpdateInvoiceAction`](../app/Actions/Invoices/UpdateInvoiceAction.php), [`PayInvoiceAction`](../app/Actions/Invoices/PayInvoiceAction.php), [`CancelInvoiceAction`](../app/Actions/Invoices/CancelInvoiceAction.php) | [`InvoiceWorkflowTest`](../tests/Feature/Admin/InvoiceWorkflowTest.php), [`InvoiceEditorTest`](../tests/Feature/Admin/InvoiceEditorTest.php) |
| Cash | [`CashTransactionQuery`](../app/Queries/CashTransactionQuery.php) | [`RecordCashTransactionAction`](../app/Actions/Cash/RecordCashTransactionAction.php), [`VoidCashTransactionAction`](../app/Actions/Cash/VoidCashTransactionAction.php) | [`CashBookTest`](../tests/Feature/Admin/CashBookTest.php) |
| Inventory | [`InventoryMovementQuery`](../app/Queries/InventoryMovementQuery.php) | [`RecordInventoryMovementAction`](../app/Actions/Inventory/RecordInventoryMovementAction.php), [`CompleteStockTransferAction`](../app/Actions/Inventory/CompleteStockTransferAction.php) | [`InventoryTest`](../tests/Feature/Admin/InventoryTest.php), [`StockTransferTest`](../tests/Feature/Branch/StockTransferTest.php) |
| Payroll | [`PayrollQuery`](../app/Queries/PayrollQuery.php) | [`CalculatePayrollAction`](../app/Actions/Payrolls/CalculatePayrollAction.php), [`FinalizePayrollAction`](../app/Actions/Payrolls/FinalizePayrollAction.php), [`PayPayrollAction`](../app/Actions/Payrolls/PayPayrollAction.php), [`CancelPayrollAction`](../app/Actions/Payrolls/CancelPayrollAction.php) | [`PayrollTest`](../tests/Feature/Admin/PayrollTest.php), [`BillKpiAndAdjustmentTest`](../tests/Feature/Payroll/BillKpiAndAdjustmentTest.php) |
| Report | [`ReportService`](../app/Services/ReportService.php) | Không sửa dữ liệu | [`ReportTest`](../tests/Feature/Admin/ReportTest.php), [`BranchReportTest`](../tests/Feature/Branch/BranchReportTest.php) |

## 5. Workflow và bất biến quan trọng

### Lịch hẹn → hóa đơn

1. Lưu lịch bằng [`SaveAppointmentAction`](../app/Actions/Appointments/SaveAppointmentAction.php); phải kiểm tra trùng lịch nhân viên và `branch_id`.
2. Chuyển lịch hẹn thành draft invoice bằng [`ConvertAppointmentToInvoiceAction`](../app/Actions/Invoices/ConvertAppointmentToInvoiceAction.php).
3. Sửa dòng hóa đơn và tính lại qua [`UpdateInvoiceAction`](../app/Actions/Invoices/UpdateInvoiceAction.php) + [`RecalculateInvoiceAction`](../app/Actions/Invoices/RecalculateInvoiceAction.php).
4. Thanh toán chỉ qua [`PayInvoiceAction`](../app/Actions/Invoices/PayInvoiceAction.php): lock row, chỉ draft được trả, sinh đúng một cash income có idempotency key `invoice-payment:{id}`.
5. Huỷ hóa đơn qua [`CancelInvoiceAction`](../app/Actions/Invoices/CancelInvoiceAction.php); không tự update status trong controller.

### Thu chi

- Hóa đơn thanh toán tạo dòng thu tự động thuộc `CashTransactionCategory::ServiceRevenue`.
- Huỷ thanh toán/thu chi phải dùng action void để lưu dấu vết, không xoá row vật lý.
- **Doanh thu** báo cáo đo theo `invoices.paid_at`; **dòng tiền** đo theo `cash_transactions.occurred_at`. Không trộn hai mốc thời gian.

### Tồn kho và chuyển kho

- Tồn kho là kết quả ledger theo `inventory_movements`, không được ghi trực tiếp vào `products`/`branch_products` để “sửa số”.
- Phiếu chuyển kho là state machine. Chỉ action complete mới tạo ledger xuất/nhập; complete phải idempotent.
- Luôn truyền đúng `branch_id` nguồn/đích và kiểm thử cách ly chi nhánh.

### Lương

- Quy tắc lương/policy và profile nhân viên được resolve bằng [`CompensationResolver`](../app/Services/CompensationResolver.php).
- Hóa đơn đã trả tiền mới tạo cơ sở doanh thu/hoa hồng.
- Payroll đã finalize/pay không được tính lại hoặc sửa trực tiếp; dùng action và luồng correction/adjustment hiện có.
- Lương trả tiền phải phản ánh vào quỹ tiền bằng workflow có transaction.

### Tiền tệ và số liệu

- Không dùng `float` cho tiền. Dùng [`Money`](../app/Support/Money.php) để đổi minor unit/decimal và giữ độ chính xác.
- Số document dùng [`DocumentNumber`](../app/Support/DocumentNumber.php).
- Báo cáo phải aggregate bằng database như [`ReportService`](../app/Services/ReportService.php), không load collection lớn rồi group trong PHP.

## 6. Mẫu xử lý yêu cầu mới

1. Xác định domain qua bảng mục 4.
2. Đọc controller + action + model + test tương ứng, không grep toàn repo trước.
3. Nếu là thay đổi dữ liệu: kiểm tra branch context, policy, enum status và transaction/idempotency.
4. Bổ sung/sửa feature test gần nhất theo domain.
5. Với PHP đã thay đổi: chạy `vendor/bin/pint --dirty --format agent`.
6. Chạy test hẹp trước; chỉ chạy full suite khi yêu cầu liên quan nhiều domain.
7. Nếu thay đổi frontend: build bằng `npm run build`.

## 7. Lệnh xác minh nhanh

```bat
:: Route liên quan
php artisan route:list --path=admin

:: Lương
php artisan test --compact tests/Feature/Admin/PayrollTest.php tests/Feature/Payroll/BillKpiAndAdjustmentTest.php

:: Hóa đơn và quỹ
php artisan test --compact tests/Feature/Admin/InvoiceWorkflowTest.php tests/Feature/Admin/InvoiceEditorTest.php tests/Feature/Admin/CashBookTest.php

:: Kho và chuyển kho
php artisan test --compact tests/Feature/Admin/InventoryTest.php tests/Feature/Branch/StockTransferTest.php

:: Cách ly chi nhánh
php artisan test --compact tests/Feature/Branch

:: Báo cáo/export
php artisan test --compact tests/Feature/Admin/ReportTest.php tests/Feature/Branch/BranchReportTest.php tests/Feature/Admin/CsvExportTest.php

:: Format PHP sau khi sửa
vendor/bin/pint --dirty --format agent

:: Build giao diện
npm run build
```

## 8. Checklist review trước khi hoàn tất

- [ ] Đã áp branch scope cho list/detail/export và branch ID cho mọi write.
- [ ] Đã authorize ở controller/action theo policy hiện hữu.
- [ ] Không bỏ qua action workflow bằng `Model::update()` trực tiếp.
- [ ] Không phá enum state, snapshot payroll/invoice, cash ledger hoặc idempotency key.
- [ ] Đã dùng `Money` cho phép tính tiền.
- [ ] Đã bổ sung/chạy test hẹp phù hợp.
- [ ] Đã chạy Pint khi sửa PHP và Vite build khi sửa frontend.
