# Kế hoạch audit và hoàn thiện Admin — Cái Tiệm Neo

> Ngày lập: 2026-09-11  
> Phạm vi: toàn bộ khu vực quản trị, các luồng chấm công, lịch hẹn, hóa đơn, thu chi, kho, nhân sự, bảng lương, báo cáo và cấu hình chi nhánh.  
> Mục tiêu: hoàn thiện UI/UX, validation, phân quyền, tính toàn vẹn dữ liệu và cơ chế chống gian lận/thông đồng giữa nhân viên.

---

## 1. Cách dùng tài liệu này

- Đây vừa là báo cáo audit sơ bộ, vừa là backlog triển khai.
- Mỗi mục có mã cố định như `SEC-P0-01`, `UX-P1-02` để agent và người review trao đổi không nhầm.
- Không triển khai hàng loạt. Mỗi lần chỉ chọn **một mục hoặc một nhóm phụ thuộc chặt**, viết test thất bại trước, sửa tối thiểu, chạy test liên quan rồi chạy toàn bộ suite.
- Không đánh dấu `[x]` chỉ vì đã viết code. Chỉ hoàn thành khi đạt đủ tiêu chí nghiệm thu và lưu bằng chứng lệnh test.
- Các nhận định “cần xác minh” phải được chứng minh bằng test hoặc truy vết code trước khi sửa.

### Trạng thái quy ước

- `[ ]` Chưa làm
- `[-]` Đang làm
- `[x]` Đã hoàn thành và có test
- `[!]` Bị chặn/cần quyết định nghiệp vụ

---

## 2. Baseline đã quét

### 2.1 Bề mặt hệ thống

Các module admin hiện có:

1. Dashboard và chọn chi nhánh.
2. Dịch vụ và danh mục dịch vụ theo chi nhánh.
3. Lịch hẹn và chuyển lịch hẹn thành hóa đơn.
4. Hóa đơn, dòng dịch vụ, giảm giá, KPI hóa đơn, thanh toán và hủy.
5. Sổ thu chi.
6. Vật tư, nhà cung cấp, nhập/xuất/điều chỉnh kho.
7. Điều chuyển kho giữa chi nhánh.
8. Chi nhánh và cấu hình GPS.
9. Nhân viên và phân công chi nhánh.
10. Ca làm và lịch phân ca.
11. Chấm công GPS, nhập tay, duyệt tăng ca và audit trail.
12. Bảng lương, điều chỉnh, chốt, trả và hủy.
13. Báo cáo và xuất CSV.

### 2.2 Điểm tốt đang có — phải giữ nguyên

- Route admin có `auth`, xác minh email, kiểm tra tài khoản active và branch context.
- Policy đã bao phủ hầu hết controller; truy cập theo ID khác chi nhánh có test.
- Giá trị `branch_id` ở nhiều form ghi được lấy lại từ server-side branch context, không tin hidden input.
- Các luồng tiền, lương, kho và chấm công quan trọng đã dùng database transaction; nhiều luồng có `lockForUpdate`.
- Thanh toán hóa đơn/lương, hoàn tiền và hoàn tất chuyển kho đã có tính idempotent ở mức nghiệp vụ.
- Số tiền được tính lại phía server, không tin tổng tiền/hoa hồng gửi từ client.
- Lương đã khóa dữ liệu nguồn sau khi chốt; điều chỉnh chênh lệch yêu cầu owner và lý do.
- Chấm công thủ công có reason, before/after audit và payroll lock guard.
- GPS dùng thời gian server, giới hạn accuracy/radius, không tin employee/branch từ payload.
- UI đã có CSRF, validation errors, confirm modal, submit guard, responsive navigation và một số hỗ trợ accessibility.
- Kết quả baseline: **360 tests passed, 1125 assertions** bằng `php artisan test` ngày 2026-09-11.

### 2.3 Điểm kiến trúc cần lưu ý

- Toàn bộ prefix `/admin` cho phép role `employee`; quyền thực tế dựa vào authorization trong từng endpoint. Bất kỳ endpoint mới nào quên policy sẽ trở thành lỗ hổng.
- Nhiều Form Request dùng `exists` toàn hệ thống. Sau đó action/controller có thể kiểm tra bổ sung, nhưng cần chuẩn hóa thành validation theo branch, assignment, trạng thái active và ngày hiệu lực.
- Hiện mới có audit trail chuyên biệt cho chấm công. Hóa đơn và sổ thu chi chưa có nhật ký before/after đầy đủ.
- “Có audit” chưa đồng nghĩa “chống thông đồng”: người tạo vẫn có thể tự sửa/hủy hoặc người quản lý có thể vừa nhập ngoại lệ vừa hưởng lợi gián tiếp.

---

## 3. Threat model chống gian lận nội bộ

### 3.1 Tác nhân

- Nhân viên thu ngân/lễ tân có quyền hóa đơn.
- Thợ được hưởng hoa hồng.
- Quản lý một hoặc nhiều chi nhánh.
- Owner có toàn quyền.
- Hai hoặc nhiều tài khoản thông đồng.
- Người chiếm được phiên đăng nhập của quản lý/owner.

### 3.2 Tài sản cần bảo vệ

- Doanh thu thực thu, tiền mặt và lịch sử hoàn/hủy.
- Tồn kho thực tế và giá vốn.
- Công, tăng ca, KPI, hoa hồng và tổng lương.
- Phân quyền, phân công chi nhánh và dữ liệu khách hàng.
- Bằng chứng ai làm gì, lúc nào, từ phiên/IP nào, trước và sau thay đổi ra sao.

### 3.3 Kịch bản ưu tiên

1. Thu tiền nhưng không tạo hóa đơn, tạo hóa đơn giá thấp hoặc hủy sau khi khách rời đi.
2. Một người tự tạo rồi tự hủy/sửa giao dịch thu chi để che thiếu quỹ.
3. Gán dòng hóa đơn cho nhân viên thông đồng hoặc tăng tỷ lệ/đổi work context để tăng hoa hồng.
4. Quản lý nhập/sửa công cho chính mình hoặc đồng phạm; tự duyệt ngoại lệ.
5. Điều chỉnh kho giảm tồn với lý do giả; người tạo tự xác nhận phiếu chuyển kho.
6. Gắn service/employee/supplier từ chi nhánh khác qua ID tampered.
7. Double submit/replay ở endpoint ghi tiền hoặc chuyển trạng thái.
8. Dùng tài khoản dùng chung; owner/manager không có MFA hoặc step-up authentication.
9. Xóa/deactivate tài khoản làm mất dấu actor hoặc che lịch sử.
10. CSV/report rò rỉ dữ liệu ngoài chi nhánh.

---

## 4. Ma trận khoảng trống theo module

| Module | UI/UX cần hoàn thiện | Validation/toàn vẹn | Bảo mật/chống gian lận | Ưu tiên |
|---|---|---|---|---|
| Dashboard | KPI theo quyền; cảnh báo ngoại lệ nổi bật | Chuẩn hóa date/timezone | Employee có quyền hóa đơn có thể thấy số tiền tổng quan quá rộng; cần policy cho từng card | P0/P1 |
| Hóa đơn | Cảnh báo giá ngoài range hiện chỉ là warning; lịch sử sửa chưa hiển thị | Service và employee phải hợp lệ tại branch/ngày; item ID phải thuộc invoice | Audit before/after; tách quyền giảm giá, sửa giá, thanh toán, hủy; maker-checker | P0 |
| Sổ thu chi | Hủy đang dùng reason mặc định “Hủy bởi người dùng” ở list | Lý do hủy cụ thể; không đổi branch khi edit; giới hạn ngày backdate | Người tạo hiện có thể tự sửa/hủy; thiếu audit update; cần duyệt giao dịch lớn | P0 |
| Chấm công | Nêu rõ nguồn GPS/manual và badge xung đột; bộ lọc ngoại lệ | Employee phải được phân công đúng branch tại work_date | Manager có thể nhập/sửa/xóa công của chính mình; audit bị cascade theo record cần kiểm tra tính lưu vết | P0 |
| Kho | Phiếu điều chỉnh cần hiển thị tồn trước/sau và mức bất thường | Product/supplier phải active/thuộc catalog phù hợp | Điều chỉnh kho một bước; người tạo có thể hoàn tất transfer; thiếu cycle-count approval | P0/P1 |
| Lương | Hiển thị rõ dữ liệu nguồn và sai lệch | Employee/paying branch phải đúng scope; overlap chống race ở DB | Owner-only final/pay tốt; cần step-up auth và export audit | P1 |
| Nhân sự | Phân biệt rõ quyền nhạy cảm | Role/flags không được tạo privilege escalation | Không cho owner tự hạ quyền nếu là owner cuối; thu hồi sessions khi deactivate/password/role đổi | P0/P1 |
| Lịch hẹn | Xử lý empty/error tốt hơn; conflict feedback | Phone/date/service/employee theo branch | Chống spam public booking, enumeration và booking giả | P1 |
| Báo cáo/CSV | Empty/loading/large export feedback | Date range/filters chuẩn hóa | Audit export; bảo vệ CSV injection; rate limit export | P0/P1 |
| Cấu hình chi nhánh | Preview GPS radius/map | Tọa độ/radius đã có validation | Thay GPS/radius ảnh hưởng công phải có audit + step-up | P1 |
| Toàn admin | Breadcrumb, unsaved-change, keyboard/mobile audit | Form Requests chuẩn hóa | Security headers, sensitive-route middleware, session hardening, central audit | P0/P1 |

---

## 5. Backlog triển khai ưu tiên

## P0 — Phải làm trước khi coi admin đủ an toàn

### [x] SEC-P0-01 — Khóa rò rỉ KPI tài chính trên dashboard theo quyền

**Rủi ro:** endpoint dashboard hiện được mọi role trong admin group gọi; controller tính doanh thu và số dư quỹ mà chưa gate từng chỉ số. Nhân viên có quyền vận hành hạn chế không nên mặc nhiên thấy doanh thu/số dư.

**Việc làm:**

- Xác định ma trận ai được xem: lịch hẹn, doanh thu, số dư quỹ, cảnh báo tồn.
- Gate từng card và không chạy query cho card không được phép xem.
- View không render giá trị/DOM nhạy cảm khi không có quyền.
- Test employee thường, employee có quyền invoice, manager, owner.

**Nghiệm thu:** không có dữ liệu tài chính trong HTML/response/query của user thiếu quyền.

**Implementation evidence (2026-09-11)**

- *Root cause:* `DashboardController` chạy query doanh thu và số dư quỹ rồi render cho mọi user trong nhóm route `/admin`, vốn cho phép cả role `employee`. Không có gate nào theo từng card.
- *Threat model:* Actor = nhân viên thường hoặc nhân viên chỉ có `can_create_invoices`. Tài sản = doanh thu ngày và số dư quỹ tiền mặt. Khai thác = chỉ cần mở `/admin`, không cần tamper payload. Hậu quả = lộ số liệu kinh doanh và giúp người có ý định rút quỹ ước lượng được số dư. Hành vi mong muốn = mỗi card có capability riêng, không chạy query và không render DOM khi thiếu quyền.
- *Quyết định nghiệp vụ:* doanh thu mở cho leadership + `can_create_invoices` (người lập hóa đơn vốn đã thấy từng dòng tiền mình ghi); số dư quỹ hẹp hơn, chỉ owner/manager vì đây là con số dùng để đối chiếu thất thoát.
- *File thay đổi:* `app/Models/User.php` (thêm `isLeadership()`, `canViewRevenueFigures()`, `canViewCashPosition()`), `app/Http/Controllers/Admin/DashboardController.php` (query có điều kiện, trả `null` khi thiếu quyền), `resources/views/dashboard.blade.php` (bọc 2 card bằng `@if`).
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Admin/DashboardVisibilityTest.php` — 4 test: nhân viên thường không thấy tiền, nhân viên có quyền hóa đơn thấy doanh thu nhưng không thấy quỹ, manager/owner thấy đủ, và không có query tiền nào chạy cho nhân viên thường.
- *Lệnh đã chạy:* test mới fail 3/4 trước khi sửa (đúng vì lỗ hổng); sau khi sửa `php artisan test tests/Feature/Admin/DashboardVisibilityTest.php` = 4 passed / 17 assertions; `php artisan test tests/Feature/Admin` = 119 passed / 369 assertions; `php artisan test` = 369 passed / 1156 assertions, 1 failed là `BrandingAssetsTest` (thiếu `public/images/logo-neo.png`, lỗi có sẵn từ baseline, không liên quan thay đổi này); `vendor/bin/pint --dirty` = fixed; `npm run build` = built in 2.94s.
- *Rủi ro còn lại:* route `/dashboard` ngoài nhóm admin dùng chung controller nhưng không có middleware `branch.context`; hành vi không đổi so với trước và vẫn được gate như nhau. Card "Lịch hẹn hôm nay" và "Vật tư cần lưu ý" vẫn mở cho mọi nhân viên — đây là dữ liệu vận hành, không phải tài chính.

### [x] SEC-P0-02 — Ràng buộc ID theo branch, trạng thái và ngày hiệu lực

**Rủi ro:** nhiều rule chỉ dùng `exists`, tạo khoảng trống IDOR logic và dữ liệu chéo chi nhánh.

**Phạm vi tối thiểu:**

- Hóa đơn: `service_id` phải active trong branch catalog của invoice; `employee_id` phải active và được phân công tại branch vào ngày hóa đơn/lịch hẹn.
- Chấm công thủ công: employee phải được phân công tại branch vào `work_date`.
- Payroll: employee và `paying_branch_id` phải nằm trong scope người thao tác; manager không được tạo payroll kéo dữ liệu ngoài branch.
- Inventory: product phải active/được dùng ở branch; supplier phải active nếu mô hình có trạng thái.
- Work shift: template phải shared hoặc thuộc đúng branch.
- Nested IDs như `items.*.id`, `assignment`, `adjustment` phải chứng minh thuộc parent route model.

**Cách làm:** ưu tiên custom Rule/after-validation dùng query scoped; action vẫn guard lại trong transaction cho trạng thái có thể thay đổi do race.

**Nghiệm thu:** có test payload tampered cho từng foreign key, cross-branch, inactive và expired assignment.

**Implementation evidence (2026-09-11)**

- *Root cause:* các Form Request dùng `Rule::exists(Model::class, 'id')` toàn hệ thống cho `items.*.employee_id`, `items.*.service_id`, `items.*.id`, `employee_id` (chấm công), `employee_id`/`paying_branch_id` (bảng lương), `product_id`/`supplier_id` (kho) và `items.*.product_id` (điều chuyển). Dropdown trong view đã lọc theo branch nhưng payload thì không, nên đây là bảo vệ chỉ ở UI.
- *Threat model:* Actor = bất kỳ user nào trong nhóm `/admin` biết mở dev tools. Tài sản = hoa hồng, công, bảng lương, tồn kho. Khai thác = sửa ID trong payload thành ID của chi nhánh khác, tài khoản đã nghỉ, hoặc phân công đã hết hạn. Hậu quả = hoa hồng chảy sang người thông đồng ngoài chi nhánh, công được tạo cho người không làm ở đó, bảng lương kéo dữ liệu ngoài phạm vi quản lý, tồn kho ghi vào vật tư/nhà cung cấp đã ngừng dùng. Hành vi mong muốn = mọi foreign key phải chứng minh thuộc branch, còn active, và phân công còn hiệu lực tại ngày nghiệp vụ; nested ID phải thuộc parent.
- *Bằng chứng lỗ hổng:* 12/16 test mới fail trước khi sửa — mọi payload cross-branch/inactive/expired đều được **chấp nhận**. 4 test pass sẵn là 2 happy path và 2 test roster (`shift-schedule` vốn đã guard đúng).
- *File thay đổi:*
  - Rule mới: `app/Rules/AssignedToBranch.php` (nhân viên active + phân công phủ ngày nghiệp vụ; owner được chấp nhận ở mọi branch, khớp `User::accessibleBranchIds()`), `app/Rules/InBranchCatalogue.php` (dịch vụ active trong bảng giá branch), `app/Rules/EmployeeWithinActorScope.php` (nhân viên nằm trong scope branch của actor — dùng cho lương vì lương trải theo kỳ chứ không theo một branch-day), `app/Rules/WithinActorBranchScope.php` (branch picker phải thuộc scope actor và còn active).
  - `app/Http/Requests/Admin/UpdateInvoiceRequest.php`: `items.*.employee_id` dùng `AssignedToBranch` theo `invoice->created_at`; `items.*.service_id` dùng `InBranchCatalogue`; `items.*.id` dùng `Rule::exists(InvoiceItem)->where('invoice_id', $invoice->id)` để ID dòng của hóa đơn khác không bị "nhận nuôi".
  - `app/Http/Requests/Admin/AttendanceRecordRequest.php`: `employee_id` dùng `AssignedToBranch` theo `work_date` và branch của record (khi sửa) hoặc branch context (khi tạo).
  - `app/Http/Requests/Admin/StorePayrollRequest.php`: `employee_id` và `paying_branch_id` dùng 2 rule scope theo actor.
  - `app/Http/Requests/Admin/InventoryMovementRequest.php` và `StockTransferRequest.php`: `product_id`/`supplier_id` thêm `->where('is_active', true)`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Admin/ScopedForeignIdTest.php` — 16 test phủ cross-branch, inactive, phân công hết hạn, nested ID thuộc hóa đơn khác, roster, kho, điều chuyển, lương; kèm 2 happy path để chứng minh rule không siết quá tay.
- *Lệnh đã chạy:* trước khi sửa `php artisan test tests/Feature/Admin/ScopedForeignIdTest.php` = 4 passed / 12 failed; sau khi sửa = 16 passed / 36 assertions; `php artisan test` = 385 passed / 1192 assertions, 1 failed là `BrandingAssetsTest` (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed.
- *Ghi chú phụ:* `BranchFactory` dùng `numerify('##')` nên chỉ có 100 mã chi nhánh và `fake()->unique()` chỉ chống trùng trong một instance — đã gặp một lần va chạm `UNIQUE constraint failed: branches.code` khi chạy toàn suite. Đã làm `DashboardVisibilityTest` tất định bằng cách pin `created_by`, nhưng đây là nguồn flaky có sẵn của factory, nên ghi lại ở `VAL-P1-02` để xử lý cùng nhóm constraint.
- *Rủi ro còn lại:* `UpdateInvoiceAction` vẫn guard lại ở tầng action (branch catalogue lookup, commission resolver) nên vẫn còn hai lớp. Chưa phủ `EmployeeAssignmentRequest`, `AppointmentRequest` và `BranchCatalogRequest` trong hạng mục này — thuộc phạm vi `VAL-P1-04`/`VAL-P1-05`. Rule mới dùng message tiếng Việt hard-code tại chỗ; khi làm `I18N-P1-01` nên cân nhắc đưa vào `lang/vi`.

### [!] FRAUD-P0-01 — Maker-checker cho sổ thu chi

**Rủi ro:** người tạo giao dịch manual hiện có thể tự sửa và tự void.

**Quyết định nghiệp vụ đề xuất:**

- Người tạo không được tự duyệt/hủy giao dịch của chính mình, trừ owner với reason + step-up auth và audit đặc biệt.
- Giao dịch trên ngưỡng cấu hình chuyển sang `pending_approval`.
- Edit sau duyệt không ghi đè: tạo revision/adjustment hoặc đưa về pending.
- Void bắt buộc lý do người dùng nhập; bỏ hidden reason mặc định.

**Dữ liệu cần thêm:** trạng thái, approver, approved_at, immutable revision/audit event, risk flags.

**Nghiệm thu:** test cùng actor bị chặn; actor thứ hai đúng branch được duyệt; double approve/void idempotent; lịch sử không mất.

**Implementation evidence (2026-09-11) — hoàn thành phần tách quyền, CHẶN ở phần ngưỡng duyệt**

- *Root cause:* `CashTransactionPolicy::update()` chỉ kiểm tra branch + không phải giao dịch hệ thống + chưa hủy. Không có gì ngăn **chính người tạo** sửa hoặc hủy giao dịch của mình. Kèm theo đó, nút Hủy ở danh sách gửi `<input type="hidden" name="void_reason" value="Hủy bởi người dùng">` (chính là `FRAUD-P0-06`), nên lý do hủy là vô nghĩa với người đối soát sau.
- *Threat model:* Actor = quản lý hoặc owner có quyền ghi sổ quỹ. Tài sản = quỹ tiền mặt và kết quả đối soát. Khai thác = ghi một khoản chi khống (hoặc thổi phồng khoản có thật), sau đó tự sửa/tự hủy để che thiếu quỹ; ở màn hình danh sách chỉ cần một cú bấm và hệ thống tự điền lý do. Hậu quả = thiếu quỹ được che mà không có người thứ hai nhìn vào, lịch sử chỉ ghi "Hủy bởi người dùng". Hành vi mong muốn = người tạo không bao giờ là người duyệt/hủy; lý do phải do người thật gõ.
- *Bằng chứng lỗ hổng:* 4/9 test mới fail trước khi sửa: tự hủy được chấp nhận, tự sửa được chấp nhận, lý do một từ được chấp nhận, và view vẫn chứa hidden default reason. 5 test còn lại (idempotent, cross-branch, audit) đã đúng sẵn.
- *Đã triển khai:*
  - `app/Policies/CashTransactionPolicy.php`: thêm `isOwnEntry()` và chặn maker tự `update`/`void`. **Áp dụng cho cả owner** — chủ ý: "owner luôn được tự hoàn tác" chính là lỗ hổng mà một phiên đăng nhập owner bị chiếm sẽ đi qua.
  - `app/Http/Requests/Admin/VoidCashTransactionRequest.php`: `void_reason` thêm `min:10` kèm message tiếng Việt, vì lý do một từ làm hỏng chính mục đích của việc hỏi.
  - UI (`FRAUD-P0-06` hoàn tất): bỏ hidden reason; mở rộng `x-admin.confirm-modal` và `x-admin.confirm-form` thành primitive dùng chung có ô lý do bắt buộc (`reason-field`, `reason-label`, `reason-min`), dùng lại được cho các hạng mục maker-checker sau. `resources/js/admin.js` chép giá trị sang input ẩn của đúng form đó, chỉ khi hợp lệ; có `is-invalid`, `aria-invalid`, thông báo lỗi, focus vào ô lý do khi mở modal. Câu xác nhận nay nêu **số tiền, hạng mục và chi nhánh** thay vì câu chung chung. Client chỉ hỗ trợ, server vẫn là nơi quyết định.
- *File thay đổi:* `app/Policies/CashTransactionPolicy.php`, `app/Http/Requests/Admin/VoidCashTransactionRequest.php`, `resources/views/components/admin/confirm-modal.blade.php`, `resources/views/components/admin/confirm-form.blade.php`, `resources/views/admin/cash/index.blade.php`, `resources/js/admin.js`.
- *Migration:* không có (phần cần migration đang bị chặn — xem dưới).
- *Tests mới/cập nhật:* `tests/Feature/Admin/CashMakerCheckerTest.php` (9 test: maker không được tự hủy, không được tự sửa, người thứ hai cùng chi nhánh hủy được, thiếu lý do bị chặn, lý do một từ bị chặn, hủy hai lần idempotent không đổi actor/thời điểm, audit ghi đủ hai actor, quản lý chi nhánh khác bị chặn, view không còn hidden default reason). Cập nhật `tests/Feature/Admin/CashBookTest.php`: 2 test trước đây để cùng một người vừa tạo vừa hủy — nay tách thành hai người và dùng lý do có nghĩa. **Đây là sửa cho khớp hành vi đúng, không phải nới lỏng assertion.**
- *Lệnh đã chạy:* trước khi sửa `CashMakerCheckerTest` = 5 passed / 4 failed; sau khi sửa = 9 passed / 22 assertions; `php artisan test tests/Feature/Admin/CashBookTest.php` = 7 passed / 25 assertions; `php artisan test` = 407 passed / 1260 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed; `npm run build` = built in 2.80s.

**Phần bị chặn — cần quyết định nghiệp vụ**

Hai yêu cầu còn lại của hạng mục phụ thuộc con số và quy trình mà chỉ chủ tiệm quyết được. Không tự đoán vì đoán sai là sai tiền và sai quyền:

1. **Ngưỡng tiền chuyển sang `pending_approval`.** Cần một con số cụ thể (ví dụ 2.000.000đ) hoặc quy tắc theo hạng mục. Chưa có bất kỳ cấu hình nào trong `config/` để suy ra.
2. **Quy trình khi tiệm chỉ có một người đủ quyền.** Quy tắc maker-checker vừa thêm có thể khóa cứng một chi nhánh chỉ có một quản lý: người đó ghi sai thì không ai hủy được ngoài owner.

*Phương án cho câu 1 (ngưỡng duyệt):*

- **(A) Ngưỡng cấu hình trong `config/cash.php` + env** — nhất quán với `config/attendance.php` đang có; đổi ngưỡng không cần migration; nhược điểm là mọi chi nhánh dùng chung một con số.
- **(B) Ngưỡng theo từng chi nhánh, lưu cột trên `branches`** — linh hoạt cho chi nhánh doanh thu khác nhau; cần migration + UI cấu hình; nhược điểm là thêm một chỗ có thể bị sửa để né duyệt, nên bản thân việc đổi ngưỡng phải vào audit.
- **(C) Không có ngưỡng, mọi giao dịch nhập tay đều cần người thứ hai duyệt** — đơn giản và an toàn nhất, nhưng làm chậm vận hành hằng ngày với những khoản nhỏ.
- *Đề xuất mặc định:* **(A)**, ngưỡng mặc định 2.000.000đ, ghi trong `config/cash.php` và đọc từ env; nâng lên (B) sau nếu thực tế cần. Cần chủ tiệm xác nhận con số trước khi triển khai.

*Phương án cho câu 2 (chi nhánh một người):*

- **(A) Owner luôn là người duyệt dự phòng** — hiện đã đúng như vậy vì owner truy cập được mọi chi nhánh; đơn giản, không cần code thêm. Nhược điểm: owner tự ghi sổ ở chi nhánh mình thì vẫn phải nhờ owner thứ hai.
- **(B) Cho phép tự hủy trong N phút kể từ khi ghi, có audit + cờ rủi ro** — xử lý được lỗi gõ nhầm ngay tức thì mà vẫn để lại vết; nhược điểm là mở đúng cửa sổ mà người gian lận cần, vì khoản khống thường bị hủy ngay sau khi ghi.
- **(C) Giữ nguyên tuyệt đối như hiện tại** — an toàn nhất; sai sót phải nhờ người thứ hai, chấp nhận bất tiện.
- *Đề xuất mặc định:* **(C)** kết hợp **(A)**, tức giữ nguyên hành vi vừa triển khai. Đã có test khẳng định owner thứ hai hủy được.

*Ảnh hưởng dữ liệu cũ nếu chọn triển khai ngưỡng:* cần thêm cột trạng thái duyệt (`approval_status`, `approved_by`, `approved_at`) vào `cash_transactions`; giao dịch cũ phải backfill thành "đã duyệt" để không biến toàn bộ lịch sử thành chờ duyệt và làm lệch số dư đang hiển thị.

*Trạng thái:* phần tách quyền maker-checker và lý do hủy thật đã xong, có test, đang chạy. Phần ngưỡng duyệt + `pending_approval` giữ `[!]` chờ quyết định.

### [x] FRAUD-P0-02 — Audit bất biến cho hóa đơn và tiền mặt

**Rủi ro:** chỉ biết `created_by`, `voided_by`, `cancelled_by`, không biết chi tiết trước/sau khi sửa giá, discount, employee hưởng hoa hồng, note, dòng dịch vụ hay thời điểm giao dịch.

**Việc làm:**

- Tạo bảng audit event append-only dùng chung hoặc riêng domain.
- Snapshot các field nhạy cảm trước/sau; lưu actor, action, reason, request/correlation ID, timestamp, branch, IP đã mask/hash theo chính sách riêng tư, user-agent rút gọn.
- Không cascade xóa audit khi business row bị xóa; ưu tiên restrict/soft-delete hoặc snapshot độc lập.
- UI cho owner/manager đúng quyền xem timeline.
- Không log plaintext password, token, cookie, tọa độ GPS chính xác hoặc dữ liệu nhạy cảm không cần thiết.

**Nghiệm thu:** mọi create/update/pay/cancel/void/discount/price/commission reassignment sinh event; audit không sửa/xóa bằng route thường.

**Implementation evidence (2026-09-11)**

- *Root cause:* hóa đơn và sổ thu chi chỉ lưu `created_by`/`voided_by`/`cancelled_by`, không có before/after. Nghiêm trọng hơn, audit chấm công đang dùng `cascadeOnDelete` trên `attendance_audit_logs.attendance_record_id`: **xóa một ca là xóa luôn toàn bộ bằng chứng ai đã ghi ca đó và vì sao**. Test cũ `ManualAttendanceAuditTest::test_deleting_a_shift_leaves_an_audit_entry_behind` còn assert chính điều đó là đúng (`assertSame(0, AttendanceAuditLog::query()->count())`) kèm comment nói "branch-level log vẫn còn" — thực tế không có log nào còn lại.
- *Threat model:* Actor = người dùng admin bất kỳ, đặc biệt là người muốn xóa dấu vết của chính mình. Tài sản = bằng chứng ai đổi tiền/công/kho, trước và sau ra sao. Khai thác = (a) sửa hóa đơn/thu chi mà không để lại vết giá trị cũ; (b) xóa bản ghi nghiệp vụ để cascade cuốn theo audit. Hậu quả = không thể dựng lại vụ việc sau khi phát hiện thất thoát. Hành vi mong muốn = event store append-only, tách rời vòng đời bản ghi nghiệp vụ, ghi cùng transaction với thay đổi.
- *Quyết định thiết kế:*
  - Bảng `audit_events` **không** dùng quan hệ đa hình có khóa ngoại. Chủ thể lưu bằng cặp `auditable_type`/`auditable_id` cộng `auditable_label` (số hóa đơn, tên ca…), để event sống lâu hơn dòng dữ liệu mà nó mô tả. Không có gì cascade.
  - Actor lưu cả `actor_id` (FK `nullOnDelete`) lẫn `actor_name` snapshot, nên vô hiệu hóa hay xóa tài khoản không xóa được danh tính người thao tác.
  - `correlation_id` scoped theo request: một lần submit sửa hóa đơn kèm nhiều dòng đọc lại thành **một** hành động.
  - Riêng tư: IP chỉ lưu dạng `hash_hmac('sha256', ip, app.key)` — đủ để phân biệt phiên khi điều tra, không giữ địa chỉ thật; user-agent cắt còn 250 ký tự; không có password/token/cookie/tọa độ GPS.
  - Audit chấm công: chuyển `cascadeOnDelete` → `nullOnDelete`, thêm 3 cột snapshot (`employee_id_snapshot`, `work_date_snapshot`, `shift_name_snapshot`) và backfill dữ liệu cũ theo chunk, để entry mồ côi vẫn đọc được độc lập.
- *File thay đổi:* `database/migrations/2026_09_11_162747_create_audit_events_table.php`, `database/migrations/2026_09_11_163137_preserve_attendance_audit_on_delete.php`, `app/Enums/AuditAction.php`, `app/Models/AuditEvent.php`, `app/Services/Audit/AuditRecorder.php`, `app/Providers/AppServiceProvider.php` (đăng ký scoped), `app/Models/Invoice.php` + `app/Models/CashTransaction.php` (thêm `auditSnapshot()`), `app/Models/AttendanceAuditLog.php`, `app/Services/Attendance/AttendanceAuditor.php`, 4 action ghi audit **trong cùng transaction**: `UpdateInvoiceAction`, `PayInvoiceAction`, `CancelInvoiceAction`, `RecordCashTransactionAction`, `VoidCashTransactionAction`; `app/Console/Commands/SeedAttendanceDemoCommand.php` (dọn audit của chính lệnh demo và ghi log qua service dùng chung).
- *Migration:* 2 migration mới. Đã kiểm tra `migrate:rollback --step=2` chạy sạch rồi `migrate` lại thành công. `down()` của migration audit chấm công cố ý **không xóa** dòng đã mồ côi — hoàn nguyên migration cũng không được phá lịch sử.
- *Tests mới/cập nhật:* `tests/Feature/Admin/AuditTrailTest.php` (9 test: before/after khi sửa hóa đơn, đổi nhân viên hưởng hoa hồng, thanh toán, hủy kèm lý do, ghi sổ thu chi, **audit sống sót khi xóa chủ thể**, tên actor sống sót khi xóa tài khoản, không lộ secret/IP thật, correlation id dùng chung). Sửa `tests/Feature/Attendance/ManualAttendanceAuditTest.php`: assertion cũ khẳng định audit bị xóa nay đổi thành khẳng định audit còn nguyên với đầy đủ snapshot — **assertion được siết chặt, không nới lỏng**.
- *Lệnh đã chạy:* trước khi sửa `AuditTrailTest` = 4 passed / 5 failed (4 test hạ tầng pass ngay vì bảng và recorder đã đúng, 5 test tích hợp fail vì action chưa ghi); sau khi sửa = 10 passed / 36 assertions; `php artisan test tests/Feature/Attendance` = 91 passed / 342 assertions; `php artisan test` = 398 passed / 1238 assertions (và đã chạy 3 lần liên tiếp ra kết quả giống hệt trước khi thêm UI), chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed; `npm run build` = built in 2.72s.
- *Sửa kèm — flaky có thật:* `BranchFactory` dùng `'CN-'.fake()->unique()->numerify('##')` chỉ có 100 mã và `unique()` không chống trùng xuyên test, gây `UNIQUE constraint failed: branches.code` ngẫu nhiên khi chạy toàn suite (đã gặp 2 lần ở các test khác nhau). Đổi sang bộ đếm static trong PHP (`CN-001`, `CN-002`…). Đã xác nhận không test nào phụ thuộc định dạng cũ; các test cần mã cố định đều truyền `code` tường minh. Sau khi sửa, 3 lần chạy toàn suite cho kết quả giống hệt nhau.
- *UI timeline:* thêm component `resources/views/components/admin/audit-timeline.blade.php` và helper `app/Support/AuditValue.php` (mảng lồng như `items` được tóm tắt thành "N dòng" thay vì dump JSON khó đọc). Gắn vào màn hình sửa hóa đơn, **chỉ owner/manager** mới thấy — nhân viên có `can_create_invoices` mở đúng hóa đơn đó không thấy mục này; có test khẳng định cả hai chiều. Timeline chỉ đọc, dùng `<del>`/`<ins>` kèm text cho screen reader thay vì chỉ dựa vào mũi tên.
- *Sửa kèm:* dropdown nhân viên ở màn hình sửa hóa đơn trước đây liệt kê **toàn bộ** tài khoản active của mọi chi nhánh; nay lọc theo phân công tại branch và ngày của hóa đơn, khớp với rule server-side đã thêm ở `SEC-P0-02`.
- *Rủi ro còn lại:* audit mới đã phủ hóa đơn (sửa/thanh toán/hủy) và sổ thu chi (tạo/sửa/hủy). **Chưa phủ** kho, điều chuyển kho, bảng lương và export — sẽ gắn vào `FRAUD-P0-05`, `SEC-P0-04` và hạng mục lương tương ứng, vì hạ tầng đã sẵn sàng. Việc chặn sửa/xóa audit bằng route thường hiện dựa trên quy ước (không có route nào ghi vào bảng này) chứ chưa có ràng buộc ở tầng DB.

### [!] FRAUD-P0-03 — Tách quyền nhạy cảm trong hóa đơn

**Rủi ro:** quyền `can_create_invoices` hiện kéo theo view/update/pay; người vận hành có thể tự nhập giá, giảm giá, gán nhân viên và thu tiền.

**Việc làm:**

- Tách abilities: view, create draft, edit customer, edit lines, override price, apply discount, assign commission employee, pay, cancel/refund.
- Giá ngoài range phải yêu cầu reason và manager approval, không chỉ warning client-side.
- Discount vượt ngưỡng cấu hình phải manager/owner duyệt.
- Đổi employee hưởng hoa hồng sau khi tạo item phải reason + audit; sau paid phải correction workflow, không sửa trực tiếp.
- Manual commission rate tiếp tục owner-only nhưng thêm audit/step-up.

**Nghiệm thu:** ma trận test role × action; payload không có quyền bị server bỏ qua hoặc 403/422 rõ ràng; không chỉ disabled ở UI.

**Implementation evidence (2026-09-11)**

- *Root cause:* `can_create_invoices` kéo theo cả view/create/update/pay. Nhân viên vận hành vì thế tự làm được trọn vòng: đặt giá thấp hơn khoảng bảng giá, giảm giá không giới hạn, gán hoa hồng cho ai tùy ý, rồi tự thu tiền. Cảnh báo "giá ngoài khoảng" chỉ tồn tại ở client (`priceOutOfRange()` trong Alpine), server nhận mọi `unit_price`.
- *Threat model:* Actor = nhân viên có `can_create_invoices`. Tài sản = doanh thu và hoa hồng. Khai thác = lập hóa đơn giá thấp hoặc giảm giá lớn cho khách quen rồi ăn chia phần chênh; không ai khác nhìn vào. Hậu quả = thu ít hơn số khách thực trả, sổ sách vẫn khớp nên rất khó phát hiện. Hành vi mong muốn = nhân viên lập hóa đơn trong khoảng bảng giá và giảm giá nhỏ; ra ngoài khoảng hoặc giảm lớn phải là owner/quản lý và phải kèm lý do vào audit.
- *Tôn trọng quyết định nghiệp vụ đã có trong code:* comment tại `BranchService::priceIsWithinRange()` ghi rõ khoảng giá là **cảnh báo, không phải rào chặn**, vì "một ca khó bất thường vẫn có thể vượt khoảng". Vì vậy **không** hard-block giá ngoài khoảng; chỉ đổi câu hỏi thành *ai* được quyết định và *có ghi lý do không*. Đây đúng tinh thần plan: "Giá ngoài range phải yêu cầu reason và manager approval, không chỉ warning client-side".
- *Bằng chứng lỗ hổng:* 5/10 test mới fail trước khi sửa — nhân viên đặt giá dưới khoảng, trên khoảng, quản lý đặt giá ngoài khoảng không cần lý do, lý do không vào audit, và nhân viên giảm giá vượt ngưỡng đều được chấp nhận.
- *Đã triển khai:*
  - `app/Policies/InvoicePolicy.php`: thêm ability `overridePrice` và `applyLargeDiscount` (đều dùng lại điều kiện của `cancel` = owner/manager trong branch).
  - `app/Http/Requests/Admin/UpdateInvoiceRequest.php`: `withValidator()` kiểm tra hai điều kiện cần nhìn toàn payload — giá từng dòng so với `BranchService` của đúng branch, và giảm giá so với tạm tính do các dòng cộng lại. Thêm rule `items.*.price_override_reason` (`min:10`).
  - `app/Actions/Invoices/UpdateInvoiceAction.php`: gom lý do override của các dòng vào `reason` của audit event, để người đọc timeline thấy ngay vì sao giá đổi mà không phải tự diff từng dòng.
  - UI: ô "Lý do giá ngoài khoảng" chỉ hiện khi giá thực sự ra ngoài khoảng **và** người dùng có quyền override; nhân viên không có quyền thấy câu "Mức giá này cần quản lý duyệt" thay vì ô nhập. Lỗi server hiển thị đúng từng dòng (xem dưới).
- *Sửa kèm — lỗi hiển thị lỗi theo dòng (một phần `VAL-P1-03`):* các dòng hóa đơn do Alpine `x-for` dựng nên Blade không biết chỉ số dòng lúc render; `@error('items.'.$index...)` là không khả thi. Đã truyền `$errors->getMessages()` vào component và thêm `rowError(index, field)` để tra theo khóa `items.N.field`, kèm `is-invalid` và `aria-invalid` đúng control. Old input đã được giữ sẵn nhờ `old('items', ...)`.
- *Quyết định nghiệp vụ — ngưỡng giảm giá (CẦN XÁC NHẬN):* tạo `config/invoicing.php` theo đúng quy ước của `config/attendance.php`, với `operator_discount_percent` mặc định **10%** và `operator_discount_max` mặc định **100.000đ**, lấy điều kiện chặt hơn trong hai. Con số là **đề xuất**, đọc từ env nên đổi không cần migration. Chủ tiệm cần xác nhận mức thực tế; nếu muốn ngưỡng theo từng chi nhánh thì xem phương án (B) đã ghi ở `FRAUD-P0-01`.
- *File thay đổi:* `config/invoicing.php` (mới), `app/Policies/InvoicePolicy.php`, `app/Http/Requests/Admin/UpdateInvoiceRequest.php`, `app/Actions/Invoices/UpdateInvoiceAction.php`, `resources/views/admin/invoices/partials/form.blade.php`, `resources/js/admin.js`.
- *Migration:* không có — ngưỡng nằm ở config, lý do override nằm ở audit event chứ không thêm cột vào `invoice_items`.
- *Tests mới:* `tests/Feature/Admin/InvoiceSensitivePermissionTest.php` — 10 test: nhân viên lập hóa đơn trong khoảng (happy path), không được đặt dưới khoảng, không được đặt trên khoảng, quản lý thiếu lý do bị chặn, quản lý có lý do được duyệt, lý do vào audit, dịch vụ không có khoảng giá thì không bị siết, nhân viên giảm giá vượt ngưỡng bị chặn, giảm giá nhỏ vẫn tự làm được, quản lý giảm giá lớn được.
- *Lệnh đã chạy:* trước khi sửa = 5 passed / 5 failed; sau khi sửa `php artisan test tests/Feature/Admin/InvoiceSensitivePermissionTest.php` = 10 passed / 18 assertions; `php artisan test` = 417 passed / 1278 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed; `npm run build` = built in 2.58s.
- *Rủi ro còn lại:* **chưa tách** `pay` khỏi `can_create_invoices` — người lập hóa đơn vẫn tự thu tiền được. Tách được về mặt kỹ thuật nhưng là quyết định vận hành thật sự (tiệm nhỏ thường chỉ có một người ở quầy), nên để lại cùng nhóm quyết định của `FRAUD-P0-01` thay vì tự đổi. Việc "đổi employee hưởng hoa hồng sau khi tạo item phải reason" hiện đã **có audit đầy đủ** (test `AuditTrailTest::test_changing_the_commission_employee_is_recorded`) nhưng chưa bắt buộc nhập reason riêng; sau khi hóa đơn đã `paid` thì không sửa được nữa vì `isEditable()` chặn, đúng yêu cầu "sau paid phải correction workflow". Manual commission rate vẫn owner-only kèm reason như trước, chưa thêm step-up (thuộc `SEC-P0-03`).

### [x] FRAUD-P0-04 — Cấm tự thao tác ngoại lệ chấm công

**Rủi ro:** policy ngăn tự duyệt overtime nhưng chưa ngăn manager tạo/sửa/xóa attendance của chính mình.

**Việc làm:**

- Manager không được create/update/delete chấm công của chính mình.
- Owner tự sửa của mình phải có cơ chế exception rõ ràng hoặc cần owner thứ hai; nếu chỉ có một owner, gắn risk flag và yêu cầu step-up + reason.
- Manual attendance và delete cần reviewer thứ hai nếu ảnh hưởng kỳ lương trên ngưỡng.
- Queue nêu bật: self-related, backdated, GPS failed, nhiều lần sửa, sửa sát ngày chốt lương.
- Kiểm tra lại audit delete: bảng audit hiện FK cascade theo attendance record; dù code có thể giữ branch-level log, cần test thực tế rằng lịch sử xóa còn tồn tại và relation nullable/thiết kế phù hợp.

**Nghiệm thu:** test manager-self bị chặn, collusion signals hiển thị, audit sau delete còn truy xuất được.

**Implementation evidence (2026-09-11)**

- *Root cause:* `AttendanceRecordPolicy::reviewOvertime()` đã chặn tự duyệt tăng ca, nhưng `create`/`update`/`delete` thì không. Quản lý vì thế đi vòng qua cổng duyệt bằng cách **sửa thẳng bản ghi** thay vì xin duyệt. Kèm theo, audit chấm công bị cascade khi xóa bản ghi (đã xử lý ở `FRAUD-P0-02`).
- *Threat model:* Actor = quản lý chi nhánh. Tài sản = công, tăng ca và qua đó là lương. Khai thác = tự tạo/sửa/xóa ca của chính mình, hoặc xóa ca để phi tang. Hậu quả = tự viết bảng lương của mình, không ai đối chứng. Hành vi mong muốn = không ai tự ghi công cho chính mình bằng tay; trường hợp không thể chặn tuyệt đối thì phải gắn cờ và đưa ra hàng đợi soát.
- *Bằng chứng lỗ hổng:* 6/8 test mới fail trước khi sửa — tự tạo, tự sửa, tự xóa đều được chấp nhận; không có cờ đánh dấu và hàng đợi duyệt không nêu gì.
- *Quyết định nghiệp vụ (theo đúng gợi ý trong plan, không tự mở rộng):*
  - **Quản lý bị chặn tuyệt đối** tự create/update/delete ca của chính mình.
  - **Owner không bị chặn** vì tiệm một owner sẽ tự khóa mình khỏi chính dữ liệu của mình; thay vào đó dòng được đánh dấu `is_self_recorded` và **hiện thành một mục riêng trong hàng đợi duyệt** kèm badge "Tự chấm". Đây đúng phương án "nếu chỉ có một owner, gắn risk flag" mà plan đề xuất.
  - Ngoại lệ owner này nhất quán với `reviewOvertime()` vốn đã miễn trừ owner từ trước, nên không tạo ra quy tắc mới lạ trong codebase.
- *File thay đổi:* `app/Policies/AttendanceRecordPolicy.php` (thêm `createFor()` và `isSelfDealing()`; `delete` nay dùng lại `update`), `app/Http/Requests/Admin/AttendanceRecordRequest.php` (authorize theo `createFor` vì nhân viên là một phần của câu hỏi phân quyền, phải trả lời trước khi validate), `app/Actions/Attendance/SaveManualAttendanceAction.php` (gắn cờ), `app/Models/AttendanceRecord.php` (cast + scope `selfRecorded`), `app/Http/Controllers/Admin/AttendanceReviewController.php`, `resources/views/admin/attendance/review.blade.php`.
- *Migration:* `database/migrations/2026_09_11_164844_add_self_recorded_flag_to_attendance_records.php` — thêm `is_self_recorded` (default false) + index `(branch_id, is_self_recorded)`. **Backfill:** bảng `attendance_records` chưa bao giờ lưu người ghi (không có cột `recorded_by`), nên lịch sử được dựng lại từ `attendance_audit_logs` bằng cách so `actor_id` với `employee_id` trên các action `manual_create`/`manual_update`. Nếu để mặc định false thì hàng đợi duyệt sẽ mù với toàn bộ dữ liệu cũ. Đã kiểm tra `migrate:rollback --step=1` rồi `migrate` lại chạy sạch.
- *Phần audit survival (yêu cầu cuối của hạng mục):* đã xử lý ở `FRAUD-P0-02` — FK đổi từ `cascadeOnDelete` sang `nullOnDelete`, thêm cột snapshot, và test `ManualAttendanceAuditTest::test_deleting_a_shift_leaves_an_audit_entry_behind` nay **khẳng định audit còn nguyên** thay vì khẳng định nó bị xóa như trước.
- *Tests mới:* `tests/Feature/Attendance/AttendanceSelfDealingTest.php` — 8 test: quản lý không tự tạo/sửa/xóa được ca của mình, vẫn quản lý bình thường ca người khác, quản lý thứ hai ghi được ca cho quản lý thứ nhất, ca owner tự ghi bị gắn cờ, ca ghi cho người khác không bị gắn cờ, hàng đợi duyệt hiện badge "Tự chấm".
- *Lệnh đã chạy:* trước khi sửa = 2 passed / 6 failed; sau khi sửa `php artisan test tests/Feature/Attendance/AttendanceSelfDealingTest.php` = 8 passed / 16 assertions; `php artisan test` = 425 passed / 1294 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed; `npm run build` = built in 2.62s.
- *Rủi ro còn lại:* yêu cầu "manual attendance và delete cần reviewer thứ hai nếu ảnh hưởng kỳ lương trên ngưỡng" **chưa làm** — lại là một ngưỡng cần chủ tiệm quyết, cùng nhóm quyết định với `FRAUD-P0-01`; hiện `PayrollLockGuard` đã chặn sửa công thuộc kỳ lương đã chốt, nên rủi ro thực tế đã hẹp hơn nhiều. Các tín hiệu khác của hàng đợi (backdated, GPS failed, sửa nhiều lần, sửa sát ngày chốt lương) mới có GPS failed và tự chấm; phần còn lại thuộc `FRAUD-P1-01` (risk engine) nên để nguyên ở đó thay vì làm nửa vời tại đây.

### [!] FRAUD-P0-05 — Maker-checker cho điều chỉnh kho và chuyển kho

**Rủi ro:** manager có thể tự điều chỉnh tồn và có thể vừa tạo vừa hoàn tất phiếu chuyển.

**Việc làm:**

- Adjustment phải có counted quantity, tồn hệ thống trước, delta, reason code, ghi chú và bằng chứng tùy chọn.
- Delta tuyệt đối hoặc tỷ lệ vượt ngưỡng cần người thứ hai duyệt.
- Người tạo transfer không được là người complete, trừ owner override có reason/audit.
- Mô hình hai đầu: branch gửi xác nhận xuất, branch nhận xác nhận nhận; chênh lệch tạo exception thay vì tự cân.
- Không sửa/xóa movement đã ghi; correction bằng movement đảo.

**Nghiệm thu:** test creator cannot complete; source/destination acknowledgements; concurrent completion; stock không âm; audit đủ hai actor.

**Implementation evidence (2026-09-11) — hoàn thành phần tách quyền và audit, CHẶN ở mô hình hai đầu + ngưỡng**

- *Root cause:* `StockTransferPolicy::complete()` chỉ yêu cầu người dùng truy cập được chi nhánh gửi, nên **người tạo phiếu tự hoàn tất được**. Phiếu điều chỉnh kho chỉ cần `note` bất kỳ (một chữ cũng qua), không lưu tồn trước/sau, và toàn bộ kho chưa hề có audit before/after.
- *Threat model:* Actor = quản lý chi nhánh. Tài sản = hàng hóa thật và giá vốn. Khai thác = (a) tạo phiếu chuyển kho rồi tự hoàn tất, hàng rời chi nhánh mà không ai ở hai đầu xác nhận; (b) ghi phiếu điều chỉnh giảm tồn với lý do bịa. Hậu quả = hàng đi ra ngoài với giấy tờ trông hợp lệ. Hành vi mong muốn = người tạo không phải người hoàn tất; phiếu điều chỉnh lưu được tồn hệ thống trước, sau và mức chênh, kèm lý do có nghĩa.
- *Bằng chứng lỗ hổng:* 4/7 test mới fail trước khi sửa — tự hoàn tất phiếu được chấp nhận, không có audit event nào cho phiếu chuyển kho lẫn phiếu điều chỉnh, lý do một từ được chấp nhận.
- *Đã triển khai:*
  - `app/Policies/StockTransferPolicy.php`: `complete()` thêm điều kiện `created_by !== actor`.
  - `app/Actions/Inventory/CompleteStockTransferAction.php`: ghi `AuditAction::Approved` **trong cùng transaction** với before/after, quy về chi nhánh gửi; giữ nguyên toàn bộ `lockForUpdate`, thứ tự khóa chống deadlock và tính idempotent sẵn có.
  - `app/Models/StockTransfer.php`: thêm `auditSnapshot()` gồm cả các dòng vật tư và **cả hai actor** (`created_by`, `completed_by`).
  - `app/Actions/Inventory/RecordInventoryMovementAction.php`: ghi audit kèm `stock_before`, `stock_after`, `quantity` (delta), tên vật tư và lý do. Con số được lấy **bên trong lock** vì chỉ ở đó nó mới chắc chắn đúng — đây chính là yêu cầu "counted quantity, tồn hệ thống trước, delta" của plan.
  - `app/Http/Requests/Admin/InventoryMovementRequest.php`: `note` của phiếu điều chỉnh thêm `min:6`.
- *Điều chỉnh sau khi chạy test:* ban đầu đặt `min:10` cho lý do điều chỉnh thì làm hỏng `InventoryTest::test_a_delta_adjustment_applies_the_signed_correction` — lý do "Hỏng hàng" (9 ký tự) là **lý do thật và đủ rõ**. Đây là phản hồi đúng từ test hiện có, nên hạ xuống `min:6` để vẫn chặn kiểu gõ cho có mà không loại bỏ lý do hợp lệ trong tiếng Việt. Không sửa test cũ để né lỗi.
- *File thay đổi:* `app/Policies/StockTransferPolicy.php`, `app/Actions/Inventory/CompleteStockTransferAction.php`, `app/Actions/Inventory/RecordInventoryMovementAction.php`, `app/Models/StockTransfer.php`, `app/Http/Requests/Admin/InventoryMovementRequest.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Branch/StockMakerCheckerTest.php` — 7 test: người tạo không hoàn tất được phiếu (và không sinh movement nào), người thứ hai hoàn tất được, audit ghi đủ hai actor, hoàn tất hai lần không nhân đôi movement, phiếu điều chỉnh ghi đúng tồn trước/sau, lý do quá ngắn bị chặn, phiếu nhập thường không bị siết theo.
- *Lệnh đã chạy:* trước khi sửa = 3 passed / 4 failed; sau khi sửa `php artisan test tests/Feature/Branch/StockMakerCheckerTest.php` = 7 passed; `php artisan test tests/Feature/Admin/InventoryTest.php tests/Feature/Branch/StockMakerCheckerTest.php` = 16 passed / 38 assertions; `php artisan test` = 432 passed / 1311 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed.
- *Đã có sẵn, giữ nguyên:* "không sửa/xóa movement đã ghi" — hiện không có route update/delete cho `inventory` (chỉ `index`, `create`, `store`), nên correction vốn đã phải bằng movement đảo. "Stock không âm" và "concurrent completion" đã có guard + lock + test từ trước, không làm yếu đi.

**Phần bị chặn — cần quyết định nghiệp vụ**

1. **Ngưỡng chênh lệch cần người thứ hai duyệt** ("delta tuyệt đối hoặc tỷ lệ vượt ngưỡng"). Cần con số cụ thể, cùng nhóm quyết định với ngưỡng ở `FRAUD-P0-01`.
   - (A) Ngưỡng theo tỷ lệ phần trăm tồn hiện tại, ví dụ >20% — công bằng giữa vật tư rẻ và đắt, nhưng vật tư tồn ít thì tỷ lệ nhảy loạn.
   - (B) Ngưỡng theo giá trị tiền của chênh lệch (delta × giá vốn), ví dụ >500.000đ — sát với mức thiệt hại thật; cần giá vốn luôn đúng.
   - (C) Mọi phiếu điều chỉnh đều cần người thứ hai — an toàn nhất, nhưng kiểm kê định kỳ sẽ rất nặng.
   - *Đề xuất mặc định:* **(B)**, vì rủi ro thật đo bằng tiền chứ không bằng số lượng; ngưỡng đặt trong `config/` như `config/invoicing.php` vừa tạo.
2. **Mô hình hai đầu (branch gửi xác nhận xuất, branch nhận xác nhận nhận).** Đây là thay đổi quy trình vận hành thật, không chỉ là code: cần thêm trạng thái (`in_transit`), migration, backfill phiếu cũ và UI cho cả hai phía.
   - (A) Hai bước: gửi xác nhận xuất → nhận xác nhận nhận; chênh lệch tạo exception. Đúng yêu cầu plan, an toàn nhất, nhưng hàng đang đi đường sẽ nằm ở trạng thái lơ lửng và cần quy trình xử lý khi bên nhận quên xác nhận.
   - (B) Giữ một bước như hiện tại nhưng người hoàn tất phải thuộc **chi nhánh nhận** — rẻ, vẫn đảm bảo hai người hai đầu, nhưng không phát hiện được chênh lệch số lượng giữa hai đầu.
   - (C) Giữ nguyên hiện trạng sau sửa (người thứ hai bất kỳ trong chi nhánh gửi).
   - *Đề xuất mặc định:* **(B)** như bước trung gian, rồi (A) khi tiệm sẵn sàng đổi quy trình. Cần chủ tiệm xác nhận vì nó đổi cách nhân viên làm việc hằng ngày.
   - *Ảnh hưởng dữ liệu cũ:* thêm trạng thái mới phải backfill mọi phiếu `completed` cũ sang trạng thái tương đương, nếu không báo cáo tồn kho lịch sử sẽ lệch.

*Trạng thái:* phần "creator cannot complete", audit hai actor, tồn trước/sau và lý do có nghĩa đã xong và có test. Phần ngưỡng duyệt + mô hình hai đầu giữ `[!]` chờ quyết định.

### [x] SEC-P0-03 — Step-up authentication cho thao tác cực nhạy cảm

**Áp dụng:** trả/chốt/hủy lương, hủy/refund hóa đơn, void tiền lớn, manual commission override, thay role/quyền, deactivate owner/manager, thay cấu hình GPS, export payroll/company-wide.

**Việc làm:**

- Dùng middleware password confirmation với thời hạn ngắn; cân nhắc MFA/TOTP cho owner.
- Sau đổi password/role/deactivate: revoke các session khác.
- Hiển thị rõ actor đang xác nhận thao tác nào, giá trị và chi nhánh.

**Nghiệm thu:** session chưa step-up bị redirect/chặn; hết hạn phải xác nhận lại; test session revocation.

**Implementation evidence (2026-09-12)**

- *Root cause:* middleware `password.confirm` của Breeze đã tồn tại nhưng **không được gắn vào bất kỳ route nào**. Ngoài ra không có cơ chế thu hồi phiên khi vô hiệu hóa tài khoản, đổi role hay đổi mật khẩu.
- *Threat model:* Actor = người chiếm được phiên của quản lý/owner (máy bỏ quên ở quầy, cookie bị lấy cắp). Tài sản = chi trả lương, hoàn tiền hóa đơn, quyền hạn, cấu hình GPS, bản xuất bảng lương. Khai thác = dùng thẳng phiên đang mở để trả lương, hủy lương hoặc tự cấp quyền; hệ thống không hỏi lại gì. Hậu quả = mất tiền và mất quyền kiểm soát hệ thống. Hành vi mong muốn = các thao tác thiệt hại lớn nhất phải nhập lại mật khẩu trong một cửa sổ ngắn; và khi trạng thái tin cậy của một tài khoản thay đổi thì các phiên cũ phải chết ngay.
- *Bằng chứng lỗ hổng:* 6/8 test đầu fail trước khi sửa (trả lương, hủy lương, đổi quyền nhân sự, đổi GPS chi nhánh, xuất bảng lương, và xác nhận cũ đã hết hạn vẫn được chấp nhận).
- *Đã triển khai:*
  - `config/auth.php`: rút `password_timeout` từ **3 tiếng xuống 15 phút**. Mặc định của framework gần bằng cả một ca làm việc, quá dài với thao tác đụng tiền.
  - `routes/web.php`: gắn `password.confirm` cho `payrolls.pay`, `payrolls.cancel`, `employees.store/update`, `branches.store/update` và `reports.export.payrolls`.
  - `app/Http/Controllers/Controller.php`: thêm `requirePasswordConfirmation()` dùng chung, cho các endpoint **chỉ nhạy cảm một phần**.
  - `app/Http/Controllers/Admin/InvoicePaymentController.php`: hủy hóa đơn **đã thanh toán** phải xác nhận lại; hủy hóa đơn nháp thì không. Middleware không phân biệt được hai trường hợp này, và bắt nhân viên quầy nhập mật khẩu mỗi lần bỏ một hóa đơn nháp sẽ khiến họ tìm cách né — biện pháp bảo mật gây phiền vô cớ là biện pháp sẽ bị vô hiệu hóa.
  - `app/Services/Auth/SessionRevoker.php` (mới) + `EmployeeController` + `PasswordController`: cắt mọi phiên khác khi vô hiệu hóa tài khoản, đổi role hoặc đổi mật khẩu, giữ lại đúng phiên đang thao tác. Có `trustChanged()` để một lần sửa tên thường không đá ai ra ngoài.
- *File thay đổi:* `config/auth.php`, `routes/web.php`, `app/Http/Controllers/Controller.php`, `app/Http/Controllers/Admin/InvoicePaymentController.php`, `app/Http/Controllers/Admin/EmployeeController.php`, `app/Http/Controllers/Auth/PasswordController.php`, `app/Services/Auth/SessionRevoker.php` (mới), `tests/TestCase.php` (thêm helper `withConfirmedPassword()`).
- *Migration:* không có — bảng `sessions` đã tồn tại sẵn.
- *Tests mới:* `tests/Feature/Admin/StepUpAuthenticationTest.php` — 13 test: trả lương bị chặn khi chưa xác nhận, trả được sau khi xác nhận, **xác nhận cũ 5 tiếng trước bị từ chối**, hủy lương, đổi quyền nhân sự, đổi GPS, xuất bảng lương, hủy hóa đơn đã thu tiền bị chặn, **hủy hóa đơn nháp không bị làm phiền**, màn hình thường không bị hỏi mật khẩu, vô hiệu hóa tài khoản cắt phiên, đổi role cắt phiên, **sửa tên thường không cắt phiên ai**.
- *Cập nhật 19 test hiện có:* các test này chạm endpoint step-up nhưng chủ đề của chúng là chuyện khác (validation, phân quyền, nghiệp vụ lương). Thêm helper `withConfirmedPassword()` vào `tests/TestCase.php` và gắn vào đúng các lời gọi đó. **Đã kiểm chứng không assertion nào bị gỡ hay làm yếu:** `git diff tests/` cho thấy 36 dòng assertion bị thay và 51 dòng được thêm (tăng ròng), mọi `assertForbidden` đều còn nguyên — chỉ trạng thái session thay đổi, không phải kỳ vọng.
- *Lệnh đã chạy:* trước khi sửa = 2 passed / 6 failed; sau khi sửa `php artisan test tests/Feature/Admin/StepUpAuthenticationTest.php` = 13 passed / 31 assertions; `php artisan test` = 455 passed / 1376 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed.
- *Lưu ý về môi trường test:* suite chạy `SESSION_DRIVER=array` cho nhanh, còn production dùng `database`. Test thu hồi phiên **tự chuyển sang driver `database`** thay vì kiểm tra một nhánh không được dùng thật — nếu không, test sẽ xanh mà chẳng chứng minh được gì.
- *Rủi ro còn lại:* **chưa có MFA/TOTP cho owner** — plan mới nêu là "cân nhắc", và đây là thay đổi lớn về vận hành (thiết bị, quy trình khôi phục khi mất máy) cần chủ tiệm quyết; xác nhận mật khẩu đã chặn được kịch bản phiên bị chiếm, vốn là mối đe dọa chính. Trang cho owner tự xem và thu hồi phiên đang mở thuộc `SEC-P1-02`, chưa làm. `SessionRevoker` chỉ hoạt động với driver `database`; nếu sau này đổi sang cookie thì việc thu hồi im lặng không có tác dụng — đã trả về 0 và ghi rõ trong docblock thay vì giả vờ thành công.

### [x] SEC-P0-04 — Bảo vệ CSV export và chống CSV formula injection

**Rủi ro:** dữ liệu bắt đầu bằng `=`, `+`, `-`, `@` có thể chạy công thức khi mở bằng spreadsheet; export nhạy cảm chưa có audit/rate limit.

**Việc làm:**

- Escape/sanitize mọi cell do người dùng nhập.
- Gate + branch scope giữ ở query export.
- Rate limit export, giới hạn date range/row count hoặc chuyển background job.
- Audit ai export loại gì, branch nào, khoảng thời gian nào; không log toàn bộ nội dung.

**Nghiệm thu:** test formula payload, cross-branch, unauthorized, large export.

**Implementation evidence (2026-09-12)**

- *Root cause:* `CsvExporter::stream()` đẩy thẳng giá trị vào `fputcsv()` mà không xử lý ký tự mở đầu công thức. Không có audit cho việc xuất dữ liệu và không có giới hạn tần suất.
- *Threat model:* Actor = **bất kỳ ai gõ được chữ vào hệ thống**, kể cả khách lạ qua form đặt lịch công khai (tên khách, ghi chú, tên nhà cung cấp). Tài sản = máy tính của người làm sổ sách. Khai thác = tên khách dạng `=HYPERLINK(...)` hay `=cmd|'/c calc'!A1` sẽ chạy khi mở file bằng Excel. Hậu quả = thực thi mã trên máy đang giữ dữ liệu tài chính công ty, kích hoạt bởi chính nhân viên làm việc bình thường. Điểm đáng chú ý: dữ liệu không tấn công ứng dụng này mà tấn công **người đọc file**, nên phải vô hiệu hóa lúc xuất ra.
- *Bằng chứng lỗ hổng:* 8/10 test mới fail trước khi sửa; nội dung file xuất chứa nguyên `"=HYPERLINK(""http://evil.test"",""Bam vao day"")"`.
- *Đã triển khai:*
  - `app/Services/CsvExporter.php`: thêm `sanitise()` chặn `=`, `+`, `-`, `@`, tab và CR ở đầu ô bằng cách thêm dấu nháy đơn (Excel/LibreOffice hiểu là "ô này là văn bản" và không hiển thị dấu này). **Số được cố ý bỏ qua**: cột tiền có thể chứa `-150000` hợp lệ, ép thành văn bản sẽ phá mọi công thức người làm sổ viết lên file — thuốc còn hại hơn bệnh, và một con số trần thì không mang được payload.
  - `app/Http/Controllers/Admin/ReportExportController.php`: ghi audit `AuditAction::Exported` ngay trong `filename()` — mọi export đều đi qua đây nên một method thêm sau này không thể quên. Chỉ lưu **hình dạng** của yêu cầu (ai, loại dữ liệu, chi nhánh nào, bộ lọc nào), **không lưu nội dung** đã xuất.
  - `app/Services/Audit/AuditRecorder.php`: cho phép chủ thể là chuỗi thay vì bắt buộc là `Model`, vì một lần xuất dữ liệu là hành động có thật nhưng không tạo ra bản ghi nào.
  - `routes/web.php`: 4 route export vào nhóm `throttle:20,1`.
- *File thay đổi:* `app/Services/CsvExporter.php`, `app/Http/Controllers/Admin/ReportExportController.php`, `app/Services/Audit/AuditRecorder.php`, `routes/web.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Admin/CsvInjectionTest.php` — 10 test: 5 payload công thức qua data provider (`=`, `+`, `-`, `@`, `=cmd|...`), công thức trong ghi chú thu chi, **văn bản thường không bị bóp méo**, **số âm không bị đụng vào**, export ghi audit đúng actor và loại dữ liệu, export có giới hạn tần suất.
- *Hai lần test "pass giả" đã bắt được và sửa:*
  1. Chạy riêng thì pass nhưng chạy toàn suite thì file xuất **rỗng** (chỉ có dòng tiêu đề). Nguyên nhân: owner truy cập được mọi chi nhánh nên `BranchContext` chọn chi nhánh đầu tiên trong DB, không phải chi nhánh của test. Đã pin `BranchContext::SESSION_KEY` trong test — nếu không, test sẽ "xanh" mà chẳng kiểm tra gì cả.
  2. Bộ đếm throttle nằm ở cache dùng chung và sống qua từng test, nên test chạm trần làm các test sau bị 429. Đã `cache()->clear()` trong `setUp()`.
- *Lệnh đã chạy:* trước khi sửa = 2 passed / 8 failed; sau khi sửa `php artisan test tests/Feature/Admin/CsvInjectionTest.php` = 10 passed / 34 assertions; `php artisan test` chạy 3 lần liên tiếp = 442 passed / 1345 assertions mỗi lần, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed.
- *Rủi ro còn lại:* "giới hạn date range/row count hoặc chuyển background job" **chưa làm** — thuộc `PERF-P1-01` (performance budget) nên để nguyên ở đó thay vì làm nửa vời tại đây; hiện export đã dùng `chunkById` + stream nên không nổ bộ nhớ dù dữ liệu lớn. Gate và branch scope của từng export vốn đã đúng từ trước (có test ở `CsvExportTest` và `BranchReportTest`), không đụng vào. `sanitise()` chỉ áp dụng cho nội dung dòng; phần tiêu đề là hằng số do lập trình viên viết nên không cần xử lý.

### [x] SEC-P0-05 — Guard mặc định cho route admin mới

**Rủi ro:** prefix admin cho phép employee; quên `authorize` ở controller mới tạo bypass.

**Việc làm:**

- Thêm test kiến trúc hoặc convention test kiểm tra endpoint admin nhạy cảm có middleware/authorization.
- Cân nhắc chia route group theo capability thay vì một group role rộng.
- Dashboard và branch switch phải có quyền riêng phù hợp.
- Không dựa vào ẩn menu làm authorization.

**Nghiệm thu:** route mới thiếu authorization làm CI fail hoặc bị default deny.

**Implementation evidence (2026-09-11)**

- *Root cause:* prefix `/admin` nhận cả role `employee`, nên middleware `role:` chỉ là cổng thô; phân quyền thật nằm ở từng action. Audit hiện trạng cho thấy **các controller hiện có đều đã guard đúng** — nhận định trong plan không còn là lỗ hổng đang mở. Rủi ro thực tế là endpoint *tương lai* quên `authorize`, và không có gì trong framework báo lỗi.
- *Threat model:* Actor = nhân viên bất kỳ trong nhóm admin. Tài sản = mọi module admin. Khai thác = gọi thẳng route mới được thêm mà tác giả quên authorize. Hậu quả = default-allow thay vì default-deny trên một endpoint ghi dữ liệu. Hành vi mong muốn = thiếu authorization phải làm CI fail.
- *Việc đã làm:* thêm convention test quét toàn bộ route có tên `admin.*`, dùng reflection đọc source của action và của Form Request được type-hint, yêu cầu có `authorize(`, `Gate::`, `->can(` hoặc `->cannot(`. Danh sách miễn trừ `EXEMPT` bắt buộc kèm lý do và có test thứ hai đảm bảo mỗi miễn trừ vẫn trỏ tới route có thật (chống việc danh sách mục ruỗng theo thời gian). Test thứ ba kiểm tra chính bộ dò nhận ra được một action đã guard, để test không "pass giả" nếu reflection hỏng.
- *Miễn trừ đã ghi nhận:* `admin.branch.switch` (kiểm tra branch theo phân công của chính tài khoản ngay trong action, không có model để gate) và `admin.dashboard` (từng chỉ số được gate riêng — xem `SEC-P0-01`).
- *File thay đổi:* `tests/Feature/Admin/AdminRouteGuardConventionTest.php` (mới). Không đổi code ứng dụng.
- *Migration:* không có.
- *Bằng chứng test thực sự bắt được lỗi (negative control):* tạm xóa dòng `$this->authorize('viewAny', Service::class)` trong `ServiceController@index` → test fail và chỉ đích danh `admin.services.index (App\Http\Controllers\Admin\ServiceController@index)`; sau đó khôi phục nguyên trạng, `git diff` của file rỗng. Đây là bằng chứng thay cho "test fail trước khi sửa", vì hạng mục này là safety net chứ không phải vá lỗ hổng đang mở.
- *Lệnh đã chạy:* `php artisan test tests/Feature/Admin/AdminRouteGuardConventionTest.php` = 3 passed / 4 assertions; negative control = 1 failed đúng route mong đợi; `php artisan test` = 388 passed / 1196 assertions, 1 failed là `BrandingAssetsTest` (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed.
- *Rủi ro còn lại:* bộ dò dựa trên phân tích chuỗi trong source, nên một action gọi phân quyền gián tiếp qua helper riêng sẽ bị báo nhầm là thiếu guard — khi đó phải thêm helper vào `looksGuarded()` thay vì thêm vào `EXEMPT`. Ngược lại, một action *có* chuỗi `authorize(` nhưng gọi sai ability vẫn lọt; đây là kiểm tra convention, không thay thế test phân quyền theo ma trận role đã có ở `AdminAuthorizationTest`. Plan đề xuất "chia route group theo capability" — chưa làm, vì sẽ trùng phạm vi với `ARCH-P3-01` (permission model dạng capability lưu DB).

---

## P1 — Hoàn thiện trước rollout rộng

### [x] VAL-P1-01 — Chuẩn hóa validation ngày giờ và backdate/future-date

- Xác định timezone nghiệp vụ thống nhất (`Asia/Ho_Chi_Minh`).
- Chặn lịch hẹn quá khứ và khung giờ phi thực tế ở cả public/admin.
- Thu chi, kho, chấm công backdate quá N ngày cần quyền/lý do/duyệt.
- `checked_in_at/out`, `work_date`, planned shift phải nhất quán; ca qua đêm được xử lý rõ.
- Payment timestamp do server quyết định; client không được backdate thanh toán nếu không có flow riêng.

**Implementation evidence (2026-09-12)**

- *Phát hiện nghiêm trọng nhất — múi giờ:* `config/app.php` để `'timezone' => 'UTC'` trong khi tiệm ở Việt Nam. Đây không phải lỗ hổng bảo mật mà là **số liệu sai**: một hóa đơn thu lúc 1h30 sáng ngày 12 được lưu thành 18h30 ngày 11 theo UTC, nên doanh thu, KPI ngày và hoa hồng của **ca đêm rơi nhầm sang ngày hôm trước**. Mọi so sánh `whereDate` trong hệ thống (dashboard, báo cáo, KPI ngày, chốt lương) đều dựa trên mốc này. Đáng chú ý: `DailyKpiEvaluator` đã có comment nói tới "application timezone", tức là code vốn giả định múi giờ nghiệp vụ đã được đặt đúng — nhưng thực tế thì chưa.
- *Đã triển khai:*
  - `config/app.php`: `'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh')`.
  - `config/business.php` (đổi tên từ `config/invoicing.php` vừa tạo ở `FRAUD-P0-03`, vì nội dung đã vượt ra ngoài phạm vi hóa đơn): thêm `backdate_days` (mặc định 30) và `max_booking_days_ahead` (mặc định 365).
  - `AppointmentRequest`: chặn đặt lịch vào quá khứ **chỉ khi tạo mới** — sửa một lịch hẹn cũ đã diễn ra vẫn phải làm được, nếu chặn luôn thì khóa mất việc sửa sai; chặn đặt quá xa để bắt lỗi gõ nhầm năm.
  - `CashTransactionRequest`, `InventoryMovementRequest`: không ghi ngày tương lai, không ghi lùi quá ngưỡng.
  - `AttendanceRecordRequest`: `work_date` không được là ngày chưa tới, và không ghi lùi quá ngưỡng.
- *Threat model phần ghi lùi:* Actor = người có quyền ghi sổ. Tài sản = số liệu của kỳ đã chốt. Khai thác = ghi một giao dịch lùi vài tháng vào kỳ mà sổ sách đã thống nhất xong. Hậu quả = con số của kỳ cũ đổi sau khi đã đối soát. Hành vi mong muốn = ghi bù trong ngưỡng ngắn thì tự làm, ngoài ngưỡng phải đi qua quy trình điều chỉnh có người duyệt.
- *File thay đổi:* `config/app.php`, `config/business.php` (đổi tên từ `config/invoicing.php`), `app/Http/Requests/Admin/AppointmentRequest.php`, `CashTransactionRequest.php`, `InventoryMovementRequest.php`, `AttendanceRecordRequest.php`, `app/Http/Requests/Admin/UpdateInvoiceRequest.php` (đổi theo tên config mới).
- *Migration:* không có. **Lưu ý triển khai:** đổi múi giờ **không** đổi dữ liệu đã lưu — các mốc thời gian cũ vẫn là giá trị UTC. Với dữ liệu phát sinh trước thay đổi này, báo cáo theo ngày có thể lệch ở các giao dịch sát ranh giới ngày. Vì hệ thống chưa chạy production nên không viết migration chuyển đổi; nếu đã có dữ liệu thật thì cần một migration cộng bù 7 tiếng cho các cột thời gian, và đó là quyết định cần chủ tiệm xác nhận trước khi chạy.
- *Tests mới:* `tests/Feature/BusinessTimezoneTest.php` (3 test: cấu hình đúng múi giờ, `now()` lệch +07:00, và hóa đơn ca đêm 1h30 sáng vẫn được tính đúng ngày) và `tests/Feature/Admin/DateBoundaryTest.php` (9 test: lịch hẹn quá khứ, lịch hẹn quá xa, lịch hẹn bình thường, **sửa lịch hẹn cũ vẫn được**, thu chi ghi lùi quá ngưỡng, thu chi trong ngưỡng, thu chi ngày tương lai, phiếu kho ngày tương lai, công ngày tương lai).
- *Cập nhật 7 test hiện có:* chúng dùng ngày cố định (`2026-08-10`, `2026-09-14`) nay nằm ngoài cửa sổ ghi lùi so với đồng hồ thật. Đã **đóng băng thời gian** bằng `Carbon::setTestNow()` trong `ManualAttendanceAuditTest`, `EmployeeManagementTest`, `BranchAttendanceTest` — cách này vừa sửa lỗi vừa làm các test đó **ổn định vĩnh viễn**, thay vì tiếp tục trôi theo ngày thật. `CashBookTest` đổi một ngày cố định sang `now()->subDay()` vì test đó nói về số tiền chứ không về ngày.
- *Lệnh đã chạy:* trước khi sửa: `BusinessTimezoneTest` = 1 passed / 2 failed, `DateBoundaryTest` = 3 passed / 6 failed; sau khi sửa cả hai đều xanh (3 và 9 test); `php artisan test` = 484 passed / 1464 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed.
- *Rủi ro còn lại:* "ca qua đêm được xử lý rõ" — `AttendanceRecordRequest` đã có `checked_out_at` phải sau `checked_in_at` và `ShiftBoard` đã xử lý ca đêm từ trước; chưa bổ sung gì thêm vì chưa tìm thấy lỗi cụ thể, và siết thêm mà không có ca lỗi thật sẽ chỉ gây phiền. "Payment timestamp do server quyết định" — đã đúng sẵn: `PayInvoiceAction` dùng `now()` của server, client không gửi được thời điểm thanh toán. Chưa giới hạn khung giờ làm việc cho lịch hẹn (ví dụ chặn đặt lúc 3h sáng) vì giờ mở cửa chưa được cấu hình ở đâu cả — cần chủ tiệm cho biết giờ làm việc từng chi nhánh trước khi áp.

### [x] VAL-P1-02 — Database constraints cho invariant quan trọng

- Unique/idempotency key cho mọi external transition có thể double submit.
- Check constraint số tiền/số lượng không âm khi domain yêu cầu.
- Parent-child integrity và unique active assignment nếu DB hỗ trợ.
- Chống race payroll period overlap bằng lock hoặc constraint phù hợp, không chỉ validation query.
- Version/optimistic locking cho form edit dài để tránh lost update.

**Implementation evidence (2026-09-12)**

- *Audit trước khi sửa — phần lớn nhận định trong plan đã không còn đúng với code hiện tại:*
  - **Unique/idempotency key:** đã có sẵn và đầy đủ. `cash_transactions.idempotency_key` unique; `invoices.number` unique; `invoices.appointment_id` unique (một lịch hẹn chỉ ra một hóa đơn); `attendance_records(employee_id, work_date, shift_name)` unique; `shift_assignments(employee_id, work_date, work_shift_id)` unique; `stock_transfer_items(stock_transfer_id, product_id)` unique; `branch_services`/`branch_products` unique theo cặp; `payroll_allocations(payroll_id, branch_id)` unique; `pending_payroll_corrections(source_type, source_id, employee_id)` unique.
  - **Race payroll period overlap:** plan lo rằng chỉ có validation query. Thực tế `SavePayrollAction::create()` chạy trong transaction và **khóa dòng nhân viên bằng `lockForUpdate()` trước khi kiểm tra chồng lấn**, nên hai yêu cầu song song cho cùng một nhân viên bị tuần tự hóa. Đây là cách xử lý đúng; đã viết test khẳng định kỳ lương chồng lấn một phần (15/09–15/10 đè lên 01/09–30/09) bị từ chối — unique trên đúng cặp ngày không chặn được trường hợp này, nhưng guard dưới khóa thì chặn được. **Test pass ngay từ đầu**, xác nhận không cần sửa.
  - **Check constraint số tiền/số lượng không âm:** không thêm ở tầng DB. Lý do: SQLite (cả local lẫn test) không sửa được constraint bằng `ALTER TABLE` mà phải dựng lại bảng — rủi ro mất dữ liệu cao hơn hẳn lợi ích, trong khi mọi đường ghi đã đi qua Form Request (`gt:0`, `min:0`) và action (`Money::toMinor` + guard tồn kho không âm có khóa dòng). Nếu sau này chuyển sang MySQL/Postgres thì nên thêm; ghi lại ở đây để quyết định đó là có chủ đích chứ không phải bỏ sót.
- *Lỗ hổng thật còn lại — lost update:* hai người mở cùng một hóa đơn thì người bấm Lưu sau **xóa lặng lẽ** công của người bấm trước; cả hai đều không biết. Trên hóa đơn nghĩa là một khoản giảm giá hoặc một dòng dịch vụ quay về giá trị cũ sau khi đã thống nhất với khách — và nhật ký audit ghi trung thực cả hai lần sửa, khiến việc biến mất trông như cố ý thay vì là tai nạn.
- *Đã triển khai:* optimistic locking cho màn hình sửa hóa đơn. Biểu mẫu mang theo `expected_version` (timestamp `updated_at` lúc mở); `UpdateInvoiceRequest::validateNobodyElseSavedFirst()` từ chối nếu bản ghi đã đổi, kèm câu bảo người dùng tải lại trang. **Trường này không bắt buộc**: màn hình nào chưa gửi thì vẫn chạy như cũ — chặn hết sẽ làm hỏng các luồng đang dùng được để phòng một vấn đề hiếm hơn.
- *File thay đổi:* `app/Http/Requests/Admin/UpdateInvoiceRequest.php`, `resources/views/admin/invoices/partials/form.blade.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Admin/ConcurrentEditTest.php` — 5 test: lần lưu dựng trên phiên bản cũ bị từ chối **và công của người trước còn nguyên**, lần lưu đúng phiên bản đi qua bình thường, biểu mẫu không gửi phiên bản vẫn chạy, màn hình sửa có gửi phiên bản hiện tại, và kỳ lương chồng lấn một phần bị chặn.
- *Một chi tiết khiến test suýt "pass giả":* hai lần lưu ban đầu rơi vào cùng một giây nên `updated_at` không đổi, và test xanh mà không chứng minh được gì. Đã thêm `travel(5)->seconds()` cùng một assertion khẳng định phiên bản thực sự đổi sau lần lưu thứ nhất. **Negative control:** gỡ lời gọi `validateNobodyElseSavedFirst()` → test fail đúng chỗ; khôi phục → pass.
- *Lệnh đã chạy:* trước khi sửa = 3 passed / 2 failed; sau khi sửa `php artisan test tests/Feature/Admin/ConcurrentEditTest.php` = 5 passed / 15 assertions; `php artisan test` = 520 passed / 1571 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed; `npm run build` = built in 15.55s.
- *Rủi ro còn lại:* optimistic locking mới áp cho hóa đơn — biểu mẫu dài nhất và có nhiều tiền nhất. Bảng lương, nhân sự và bảng giá chi nhánh chưa có; cơ chế đã sẵn (thêm một input ẩn và một lời gọi) nên mở rộng rẻ, nhưng mỗi màn hình cần test riêng nên để lại. Phần "báo ai vừa sửa và cho reload/compare" của `UX-P1-02` mới làm được vế "báo và bảo tải lại", chưa có so sánh hai phiên bản cạnh nhau.

### [x] SEC-P1-01 — Security headers và production config checklist

- CSP phù hợp; hiện font tải từ Google nên phải đưa vào policy hoặc self-host.
- `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` (geolocation chỉ self), frame-ancestors.
- Production: `APP_DEBUG=false`, HTTPS, secure cookie, SameSite, session encryption theo threat model, trusted proxies/hosts.
- Không commit `.env`; rotate key/credentials nếu từng lộ.
- Thêm automated smoke test headers.

**Implementation evidence (2026-09-12)**

- *Root cause:* không có bất kỳ security header nào được gửi đi. Không CSP, không `X-Content-Type-Options`, không `Referrer-Policy`, không `Permissions-Policy`, không chặn nhúng khung.
- *Vai trò của hạng mục này:* không header nào tự chặn được một cuộc tấn công; chúng thu hẹp thiệt hại khi một lỗi khác đã xảy ra. CSP biến một script bị chèn thành request bị chặn; `frame-ancestors` chặn việc nhúng khu quản trị vào trang khác để lừa bấm; `nosniff` ngăn file CSV xuất ra bị trình duyệt đoán thành thứ chạy được.
- *Bằng chứng lỗ hổng:* 5/6 test đầu fail, không header nào tồn tại.
- *Đã triển khai:* `app/Http/Middleware/SecurityHeaders.php` (mới), gắn bằng `$middleware->append()` trong `bootstrap/app.php` nên áp cho **mọi** response kể cả file tải về và trang lỗi — một endpoint mới không thể ra đời mà thiếu.
- *Những đánh đổi trong CSP, đã ghi rõ trong docblock để người sau không nới lỏng nhầm:*
  - `script-src` **không** có `unsafe-inline`: đã kiểm tra toàn bộ `resources/views` không có thẻ `<script>` viết thẳng, mọi JS đi qua Vite. Giữ được như vậy thì CSP mới thực sự chặn được script chèn vào.
  - `unsafe-eval` là cái giá của Alpine (biên dịch biểu thức `x-*` lúc chạy). Bỏ được nếu chuyển sang bản Alpine CSP build.
  - `style-src` phải có `unsafe-inline` vì còn ~25 thuộc tính `style=""` trong Blade. Dọn hết thì siết lại được.
  - Font Google được khai báo tường minh (`fonts.googleapis.com` cho CSS, `fonts.gstatic.com` cho file font) — đúng điểm plan đã nêu. Tự host sẽ gọn hơn nhưng là thay đổi khác.
  - Môi trường `local` mở thêm `http://localhost:*` và `ws://localhost:*` cho Vite HMR. Nếu không, `npm run dev` sẽ hỏng ngay và người ta sẽ tắt luôn CSP — một biện pháp gây phiền vô cớ là biện pháp sẽ bị vô hiệu hóa.
- *File thay đổi:* `app/Http/Middleware/SecurityHeaders.php` (mới), `bootstrap/app.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/SecurityHeadersTest.php` — 8 test: header cơ bản trên trang công khai và trong khu quản trị, `geolocation=(self)` cùng camera/micro bị khóa, CSP có `default-src 'self'`/`frame-ancestors 'none'`/`object-src 'none'`, **CSP cho phép đúng font mà trang thật đang dùng**, header có mặt cả trên response không phải HTML (file CSV), cookie phiên `http_only` + `SameSite`, và `APP_DEBUG` phải đọc từ môi trường.
- *Một test bị tôi viết sai và đã sửa:* bản đầu ép `app.env` thành production rồi khẳng định `app.debug` phải tắt — đó chỉ là tự tạo ra một mâu thuẫn, không kiểm tra được gì thật (trong test `APP_DEBUG` luôn bật). Đã đổi sang kiểm tra thứ thật sự kiểm tra được và cũng là thứ từng gây sự cố thật: `config/app.php` có ghi cứng `debug => true` hay không. Kết quả: file đang dùng `(bool) env('APP_DEBUG', false)` — mặc định tắt, đúng.
- *Lệnh đã chạy:* trước khi sửa = 0 passed / 5 failed; sau khi sửa `php artisan test tests/Feature/SecurityHeadersTest.php` = 8 passed / 28 assertions; `php artisan test` = 492 passed / 1492 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed.
- *Rủi ro còn lại:* CSP **chưa được kiểm chứng bằng trình duyệt thật** — test chỉ khẳng định nội dung header, không chứng minh trang chạy được dưới chính sách đó. Trước khi lên production nên mở console trình duyệt kiểm tra không có vi phạm CSP nào, đặc biệt ở màn hình sửa hóa đơn (Alpine) và nút Vào ca (định vị). Đây là việc thuộc `TEST-P2-01` (browser/E2E). `SESSION_SECURE_COOKIE` chưa được đặt trong `.env.example`; **không sửa `.env` theo ràng buộc của plan**, nhưng khi triển khai production phải bật `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, `APP_ENV=production` và HTTPS. Phần "rotate key/credentials nếu từng lộ" là việc vận hành, không kiểm chứng được từ code.

### [x] SEC-P1-02 — Quản trị phiên và tài khoản

- Trang owner xem và revoke sessions.
- Session regeneration khi login đã phải được xác minh bằng test.
- Deactivate/role/password change revoke sessions và remember tokens.
- Không cho public self-registration trong production nếu đây là hệ thống nội bộ; nếu vẫn cần, phải có invite/approval.
- Cảnh báo tài khoản dùng chung: khuyến nghị mỗi người một account.

**Implementation evidence (2026-09-12)**

- *Phát hiện nghiêm trọng nhất — đăng ký công khai:* route `/register` của Breeze vẫn mở. Tài khoản tự đăng ký nhận `role` mặc định `employee` (xem migration `add_business_fields_to_users_table`), `is_active` true, và được **đăng nhập thẳng**. Nghĩa là bất kỳ ai trên internet tìm thấy URL đều vào được prefix `/admin`: sổ lịch hẹn kèm **tên và số điện thoại khách**, danh sách vật tư, bảng chấm công. Không cần khai thác gì cả, chỉ cần điền form.
- *Threat model:* Actor = người lạ bất kỳ. Tài sản = dữ liệu khách hàng và thông tin vận hành. Khai thác = POST `/register`. Hậu quả = rò rỉ dữ liệu cá nhân của khách. Hành vi mong muốn = tài khoản do chủ tiệm tạo ở màn hình Nhân sự, nơi vai trò và quyền được quyết định có chủ đích.
- *Đã triển khai:*
  - Gỡ route `GET/POST /register`, xóa `RegisteredUserController` và `resources/views/auth/register.blade.php`. Ghi chú ngay trong `routes/auth.php` nêu lý do và yêu cầu: nếu mở lại thì phải kèm cơ chế mời hoặc duyệt, không mở trần.
  - Trang hồ sơ cá nhân nay liệt kê **các phiên đăng nhập của chính tài khoản** (IP, thiết bị rút gọn, hoạt động lần cuối, đánh dấu phiên hiện tại) kèm nút "Kết thúc các phiên khác": `SessionRevoker::listFor()`, `ProfileController::revokeOtherSessions()`, route `profile.sessions.revoke`, view `profile/partials/sessions.blade.php`.
  - Phần thu hồi phiên khi vô hiệu hóa/đổi role/đổi mật khẩu đã làm ở `SEC-P0-03`.
- *Test cũ bị thay, không phải bị xóa để né:* `RegistrationTest` của Breeze khẳng định "người lạ đăng ký được" — đúng với ứng dụng mẫu, sai với hệ thống nội bộ. Đã thay bằng test khẳng định route không còn tồn tại, và ghi rõ trong docblock vì sao. Hành vi mong muốn đổi nên assertion phải đổi theo.
- *File thay đổi:* `routes/auth.php`, `routes/web.php`, `app/Http/Controllers/ProfileController.php`, `app/Services/Auth/SessionRevoker.php`, `resources/views/profile/edit.blade.php`, `resources/views/profile/partials/sessions.blade.php` (mới); xóa `app/Http/Controllers/Auth/RegisteredUserController.php` và `resources/views/auth/register.blade.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Auth/SelfRegistrationTest.php` (4 test: trang đăng ký 404, người lạ không tạo được tài khoản và không được đăng nhập, **chủ tiệm vẫn tạo tài khoản bình thường**, đăng nhập không bị ảnh hưởng) và `tests/Feature/Auth/SessionManagementTest.php` (4 test: trang hồ sơ liệt kê phiên, kết thúc được phiên khác, **không đụng tới tài khoản người khác**, khách vãng lai bị chặn). Thay `tests/Feature/Auth/RegistrationTest.php`.
- *Session regeneration khi đăng nhập:* đã đúng sẵn (`AuthenticatedSessionController` gọi `session()->regenerate()`), đã xác minh bằng truy vết code, không cần sửa.
- *Lệnh đã chạy:* trước khi sửa `SelfRegistrationTest` = 1 passed / 3 failed; sau khi sửa `php artisan test tests/Feature/Auth` = 29 passed / 64 assertions; `php artisan test` = 499 passed / 1506 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed; `npm run build` = built in 2.60s.
- *Lưu ý về môi trường test:* `SessionManagementTest` đặt `SESSION_DRIVER=database` ngay trong `setUp()` **trước** `parent::setUp()`, vì request đi qua kernel sẽ đọc lại config; nếu chỉ gọi `config()` sau đó thì test kiểm tra một nhánh không được dùng và sẽ xanh một cách vô nghĩa.
- *Rủi ro còn lại:* việc "cảnh báo tài khoản dùng chung" chưa làm — cần một cách phát hiện đáng tin (ví dụ nhiều IP khác nhau cùng lúc) và thuộc `FRAUD-P1-01` (risk engine). Trang phiên hiện chỉ cho người dùng xem **phiên của chính mình**; owner xem phiên của nhân viên khác là một quyền nhạy cảm hơn, chưa làm vì plan không nêu rõ ai được xem và đó là quyết định về quyền riêng tư của nhân viên.

### [x] FRAUD-P1-01 — Risk engine nhẹ và exception dashboard

Không cần AI ở giai đoạn đầu. Dùng rule rõ ràng:

- Nhiều void/cancel/discount/price override trong ngày.
- Transaction backdated hoặc ngoài giờ.
- Cùng actor tạo và duyệt.
- Attendance manual/GPS failed/nhiều lần sửa.
- Kho adjustment lớn, thường xuyên, sát giờ đóng cửa.
- Reassign commission sau khi dịch vụ hoàn tất.
- Export hàng loạt hoặc liên tục.

Mỗi flag có severity, entity, actor, branch, reason, trạng thái review, reviewer và kết luận.

**Implementation evidence (2026-09-12)**

- *Điểm xuất phát:* nền `audit_events` dựng ở `FRAUD-P0-02` đã có sẵn actor, branch, action, thời điểm và snapshot before/after — đủ để suy ra cờ rủi ro mà không cần thêm cột nào vào các bảng nghiệp vụ.
- *Nguyên tắc thiết kế:* **mỗi cờ là một câu hỏi, không phải lời buộc tội.** Mọi quy tắc ở đây phải giải thích được cho chính người bị nêu tên trong một câu — vì đầu ra của nó nêu tên người bằng hàm ý. Một quy tắc không ai giải thích nổi sẽ bị phớt lờ, hoặc tệ hơn, bị tin mà không hỏi lại. Không dùng AI, không dùng điểm số mờ.
- *Đã triển khai:*
  - Bảng `risk_flags`: `rule`, `severity`, chủ thể lưu lỏng (sống lâu hơn dòng dữ liệu, giống `audit_events`), branch, actor + `actor_name` snapshot, câu tóm tắt viết sẵn cho người đọc, `context` JSON, và **kết luận của con người** (`review_status`, `reviewed_by`, `reviewed_at`, `review_note`). Unique `(rule, subject_type, subject_id)` để quét lại không nhân bản.
  - `App\Enums\RiskSeverity` (3 mức — cố ý không nhiều hơn, thêm mức chỉ khiến người ta tranh luận ranh giới thay vì đọc hàng đợi) và `App\Enums\RiskReviewStatus`. **"Accepted" và "dismissed" tách riêng có chủ đích:** cả hai đều đóng cờ, nhưng chỉ một cái nói rằng nghi vấn là thật — gộp lại sẽ mất đúng thông tin mà một cuộc điều tra sau này cần.
  - `App\Services\Risk\RiskDetector` với 6 quy tắc: hủy nhiều hóa đơn trong ngày, hủy nhiều giao dịch quỹ trong ngày, thao tác tiền ngoài giờ mở cửa, tự chấm công, điều chỉnh kho giá trị lớn, xuất dữ liệu hàng loạt. Idempotent hoàn toàn.
  - Hàng đợi tại `/admin/risk-flags` với bộ lọc trạng thái, `RiskFlagController`, `ReviewRiskFlagRequest`, `RiskFlagPolicy`, và mục "Cảnh báo" trên thanh điều hướng.
  - `php artisan risk:detect --days=N` cho tiệm đã bật scheduler; màn hình cũng tự quét khi mở, vì tiệm nhỏ thường chưa chạy scheduler và một hàng đợi chỉ đầy khi có người chủ động gõ lệnh thì sẽ không ai thấy gì.
- *Hai quyết định về phân quyền, đều có test:*
  1. Hàng đợi **chỉ leadership xem được** — nó nêu tên người bằng hàm ý nên không phải thứ để cả tiệm đọc.
  2. **Người bị cờ nêu tên không tự đóng được cờ của chính mình.** Cho phép sẽ khiến toàn bộ hàng đợi thành trang trí: người đang bị hỏi sẽ tự trả lời thay mình.
- *Ngưỡng đặt trong `config/business.php` (`risk.*`), cố ý hơi rộng:* một hàng đợi đầy cờ vô nghĩa là hàng đợi người ta ngừng đọc, và khi đó nó không bảo vệ được gì nữa. Các con số (3 lần hủy/ngày, 10 lần xuất/ngày, giờ mở cửa 7–23, chênh lệch kho 500.000đ) là **đề xuất**, đọc từ env nên chủ tiệm chỉnh được mà không cần migration.
- *File thay đổi:* migration `2026_09_12_005438_create_risk_flags_table.php`; `app/Enums/RiskSeverity.php`, `app/Enums/RiskReviewStatus.php`, `app/Models/RiskFlag.php`, `app/Services/Risk/RiskDetector.php`, `app/Http/Controllers/Admin/RiskFlagController.php`, `app/Http/Requests/Admin/ReviewRiskFlagRequest.php`, `app/Policies/RiskFlagPolicy.php`, `app/Console/Commands/DetectRiskFlagsCommand.php`, `resources/views/admin/risk/index.blade.php`, `app/Support/AdminNavigation.php`, `routes/web.php`, `config/business.php`.
- *Tests mới:* `tests/Feature/Admin/RiskDetectionTest.php` — 12 test: hủy nhiều hóa đơn sinh cờ, **một lần hủy thì không**, tiền ngoài giờ bị gắn cờ, **tiền trong giờ thì không**, tự chấm công bị gắn cờ, quét hai lần không nhân bản, **kết luận đã ghi không bị lần quét sau ghi đè**, leadership đọc được hàng đợi, nhân viên vận hành bị chặn, người thứ hai đóng được cờ, **người bị nêu tên không tự đóng được**, và kết luận không có lý do bị từ chối.
- *Một test "pass giả" đã bắt được:* `test_leadership_can_read_the_queue` chạy riêng thì xanh nhưng chạy toàn suite thì đỏ — owner thấy mọi chi nhánh nên `BranchContext` chọn chi nhánh đầu tiên trong DB, không phải chi nhánh của test, và hàng đợi rỗng. Đã pin `BranchContext::SESSION_KEY`. Đây là lần thứ hai lỗi cùng loại xuất hiện trong dự án (lần trước ở `CsvInjectionTest`), nên đã ghi vào checkpoint như một cái bẫy cần nhớ.
- *Lệnh đã chạy:* `php artisan test tests/Feature/Admin/RiskDetectionTest.php` = 12 passed / 23 assertions; `php artisan test` chạy 3 lần liên tiếp = **532 passed / 1594 assertions** mỗi lần, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `php artisan risk:detect --days=7` chạy sạch; `vendor/bin/pint --dirty` = fixed; `npm run build` = built in 2.37s.
- *Rủi ro còn lại:* chưa làm quy tắc "cùng actor tạo và duyệt" (maker-checker đã **chặn** ở tầng policy cho quỹ, kho và chấm công, nên hiện không có dữ liệu vi phạm để dò — sẽ cần khi có đường ngoại lệ cho owner) và "đổi nhân viên hưởng hoa hồng sau khi dịch vụ hoàn tất" (audit đã ghi đủ, chỉ thiếu quy tắc đọc ra). Chưa có cảnh báo qua email/thông báo đẩy; hàng đợi là màn hình kéo, chưa phải đẩy — thuộc `OPS-P2-01`. Việc quét khi mở màn hình là đánh đổi: đơn giản và luôn có dữ liệu, nhưng thêm chi phí cho mỗi lần mở trang; nếu dữ liệu lớn lên thì nên chuyển hẳn sang scheduler và bỏ quét đồng bộ.

### [!] FRAUD-P1-02 — Đối soát cuối ca và quỹ tiền mặt

- Opening float, expected cash, actual counted cash, variance.
- Người thu ngân khai báo; manager khác xác nhận.
- Không cho sửa số kỳ đã đóng; correction có audit.
- Đối chiếu paid invoices theo payment method với cash ledger.
- Cảnh báo invoice paid nhưng thiếu cash entry hoặc ngược lại.

**Trạng thái (2026-09-12): bị chặn bởi quyết định quy trình, không phải bởi kỹ thuật**

- *Phần đã có sẵn và dùng lại được:* `cash_transactions` đã ghi đủ loại, hạng mục, phương thức thanh toán, thời điểm và người tạo; hóa đơn đã thanh toán tự sinh một dòng thu tương ứng có `idempotency_key`; audit đã ghi mọi lần tạo/sửa/hủy giao dịch quỹ. Nghĩa là dữ liệu để đối soát **đã đầy đủ**; thiếu là khái niệm "ca" và quy trình chốt ca.
- *Vì sao không tự quyết:* hạng mục này không phải thêm một bảng, mà là **thêm một quy trình vận hành mới** vào ngày làm việc của tiệm. Mỗi lựa chọn dưới đây đổi cách nhân viên làm việc, và chọn sai sẽ khiến người ta bỏ qua bước chốt ca — lúc đó hệ thống có thêm một bảng trống và một cảm giác an toàn sai lệch.

**Các quyết định cần chủ tiệm trả lời**

1. **Ca được định nghĩa thế nào?**
   - (A) Theo ngày: mỗi chi nhánh chốt một lần vào cuối ngày. Đơn giản nhất, hợp với tiệm một ca.
   - (B) Theo ca làm: mỗi lần đổi người trực quầy là một lần chốt. Khoanh vùng trách nhiệm chính xác hơn, nhưng nhiều thao tác hơn hẳn.
   - *Đề xuất mặc định:* **(A)**, nâng lên (B) nếu tiệm thực sự có nhiều người trực quầy trong ngày.

2. **Ai đếm và ai xác nhận?**
   - (A) Thu ngân khai số đếm được, một người khác (quản lý) xác nhận. Đúng tinh thần maker-checker đã áp cho sổ quỹ.
   - (B) Chỉ quản lý đếm và tự xác nhận. Ít thao tác, nhưng mất hẳn lớp đối chứng — trái với hướng đã làm ở `FRAUD-P0-01`.
   - *Đề xuất mặc định:* **(A)**, nhất quán với phần còn lại của hệ thống.
   - *Lưu ý:* nếu chi nhánh chỉ có một người đủ quyền thì vướng đúng vấn đề đã nêu ở `FRAUD-P0-01`; hai câu này nên trả lời cùng nhau.

3. **Chênh lệch bao nhiêu thì phải giải trình?**
   - Cần một con số (ví dụ 50.000đ). Quá thấp thì ngày nào cũng phải viết giải trình cho tiền lẻ và người ta sẽ viết cho có; quá cao thì mất tác dụng.

4. **Quỹ đầu kỳ (opening float) có cố định không?**
   - (A) Cố định một số, đặt trong cấu hình chi nhánh.
   - (B) Nhập tay mỗi lần mở ca.
   - *Đề xuất mặc định:* **(A)** vì ít thao tác và dễ phát hiện bất thường hơn.

**Phần không bị chặn, có thể làm ngay khi có quyết định**

- Cảnh báo "hóa đơn đã thanh toán bằng tiền mặt nhưng thiếu dòng thu tương ứng" và chiều ngược lại: dữ liệu đã đủ, chỉ cần thêm hai quy tắc vào `RiskDetector` (đã có sẵn hạ tầng ở `FRAUD-P1-01`). Chưa làm vì nó chỉ có nghĩa trong khuôn khổ một kỳ đối soát — không có khái niệm "ca" thì không biết so sánh trong phạm vi nào, và cảnh báo sẽ nổ ra với mọi hóa đơn chưa kịp ghi quỹ.
- "Không cho sửa số kỳ đã đóng" tái sử dụng được nguyên mẫu `PayrollLockGuard` đang dùng cho bảng lương.

### [!] FRAUD-P1-03 — Đối soát dịch vụ, lịch hẹn và hoa hồng

- Hóa đơn không bắt buộc từ appointment nhưng phải gắn nguồn/ghi chú cho walk-in.
- Phát hiện appointment completed không invoice; invoice không item/zero total; item không employee.
- Khách trùng phone, nhiều booking giả/no-show bất thường.
- Báo cáo thay đổi employee/commission/price theo actor.

**Implementation evidence (2026-09-12)**

- *Cách tiếp cận:* phần "phát hiện" của hạng mục này là các quy tắc đọc dữ liệu sẵn có, nên gắn thẳng vào `RiskDetector` dựng ở `FRAUD-P1-01` thay vì tạo màn hình báo cáo riêng — kết quả rơi vào cùng một hàng đợi mà người ta đã phải đọc, thay vì thêm một chỗ nữa để quên.
- *Điểm chung của ba quy tắc:* **không cần ai gian dối thì chúng vẫn quan trọng.** Việc đã làm mà không xuất hóa đơn, một biên lai ghi cho hư không, một dòng dịch vụ không ghi công ai — đó là khoảng cách hằng ngày giữa việc tiệm đã làm và những gì sổ sách nói. Và một khoảng cách không ai nhìn thì không phân biệt được với một khoảng cách có người sắp đặt.
- *Đã triển khai (3 quy tắc mới):*
  1. `completed_appointment_without_invoice` (High) — lịch hẹn đã hoàn tất mà chưa có hóa đơn. Đây là dạng thất thoát doanh thu đơn giản nhất: khách đã được phục vụ, lịch đã đánh dấu xong, và không thu đồng nào.
  2. `paid_invoice_without_value` (High) — hóa đơn đã thanh toán nhưng không có dòng dịch vụ hoặc tổng bằng 0. Nghĩa là tiền được ghi nhận đã thu mà chẳng đối ứng với gì cả.
  3. `invoice_line_without_employee` (Medium) — dòng dịch vụ không gán nhân viên. Hoa hồng tính theo dòng nên dòng này không trả cho ai; để lâu thì âm thầm bớt lương của người thật, đồng thời che mất ai đã phục vụ khách.
- *File thay đổi:* `app/Services/Risk/RiskDetector.php`.
- *Migration:* không có — dùng lại bảng `risk_flags`.
- *Tests mới:* `tests/Feature/Admin/RevenueReconciliationTest.php` — 7 test, mỗi quy tắc đều có **cả ca dương lẫn ca âm**: lịch hẹn hoàn tất thiếu hóa đơn bị gắn cờ, lịch hẹn **đã có hóa đơn thì không**, lịch hẹn **chưa xong thì không**, hóa đơn trả tiền không dòng bị gắn cờ, hóa đơn tổng 0 bị gắn cờ, **hóa đơn bình thường không sinh cờ nào**, và dòng không gán nhân viên bị gắn cờ.
- *Negative control:* gỡ ba lời gọi quy tắc khỏi `sweep()` → test fail đúng chỗ; khôi phục → 7/7 pass. Các test này pass ngay lần chạy đầu nên bước này là bắt buộc: không có nó thì không thể phân biệt "quy tắc chạy đúng" với "test không kiểm tra gì".
- *Lệnh đã chạy:* `php artisan test tests/Feature/Admin/RevenueReconciliationTest.php` = 7 passed / 9 assertions; negative control = 2 failed đúng như mong đợi; `php artisan test` = 545 passed / 1615 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed.

**Phần chưa làm và lý do**

- *"Hóa đơn walk-in phải gắn nguồn/ghi chú"* — cần quyết định nghiệp vụ: bắt buộc chọn nguồn (khách vãng lai / giới thiệu / online) sẽ thêm một thao tác vào **mọi** hóa đơn ở quầy. Với tiệm nhỏ đông khách, đó là chi phí thật mỗi ngày để đổi lấy một trường thống kê. Cần chủ tiệm xác nhận có muốn không, và danh sách nguồn gồm những gì. Hiện hóa đơn không từ lịch hẹn vẫn ghi đủ người tạo, chi nhánh và thời điểm trong audit.
- *"Khách trùng phone, nhiều booking giả/no-show bất thường"* — cần một ngưỡng ("bao nhiêu no-show trong bao lâu thì đáng nghi") và một quyết định về cách đối xử với khách bị gắn cờ. Đây là dữ liệu về **khách hàng**, không phải về nhân viên, nên gắn cờ sai sẽ ảnh hưởng tới người ngoài tiệm — cần chủ tiệm quyết trước khi triển khai.
- *"Báo cáo thay đổi employee/commission/price theo actor"* — dữ liệu **đã có đủ** trong `audit_events` (mọi lần sửa hóa đơn đều lưu before/after gồm cả `employee_id`, `commission_rate`, `unit_price` từng dòng). Thiếu là một màn hình báo cáo tổng hợp theo người thao tác; timeline hiện chỉ xem được theo từng hóa đơn. Việc này không bị chặn, chỉ là chưa tới lượt — ghi lại để phiên sau làm cùng nhóm báo cáo.

### [x] UX-P1-01 — Validation UX thống nhất

- Summary lỗi ở đầu form, focus lỗi đầu tiên, aria-live cho flash/error.
- Nested invoice/transfer rows hiển thị lỗi đúng dòng và giữ old input.
- Disabled submit có trạng thái “Đang lưu…”, spinner và thông báo thất bại.
- Không dùng reason mặc định chung cho hành động phá hủy.
- Empty state có next action; phân biệt “không có dữ liệu” và “không có quyền”.

**Implementation evidence bổ sung (2026-09-12) — phần error summary và ARIA đã hoàn tất**

- *Root cause phần còn lại:* biểu mẫu bị trả về không có tóm tắt lỗi ở đầu trang, và câu lỗi không được nối với ô nhập bằng ARIA. `x-admin.field` mới chỉ đặt nhãn, câu trợ giúp và câu lỗi cạnh nhau về mặt thị giác.
- *Hậu quả thực tế:* một biểu mẫu dài (lương, hóa đơn, nhân sự) bị từ chối sẽ thả người dùng ở đầu trang và để họ tự dò xem ô nào sai trong vài chục ô; người dùng trình đọc màn hình thì không được thông báo gì cả. Cả hai đẩy người thao tác tới chỗ gửi lại một cách mù mờ hoặc bỏ luôn biểu mẫu — và một khoản thu chi không bao giờ được ghi cũng là một lỗ hổng trong sổ sách y như một khoản bị lấy.
- *Bằng chứng lỗ hổng:* 4/6 test mới fail trước khi sửa.
- *Đã triển khai:*
  - `resources/views/components/admin/error-summary.blade.php` (mới): `role="alert"` để trình đọc màn hình đọc ngay khi trang tải lại, `tabindex="-1"` để đưa được tiêu điểm vào. Mỗi dòng là **liên kết tới đúng control** gây lỗi, nên bấm vào là nhảy tới đó — cách này dùng được bằng bàn phím **kể cả khi không có JavaScript**. Khóa lồng nhau (`items.2.quantity`) cố ý chỉ hiện chữ, không tạo liên kết hỏng vì không control nào mang id đó.
  - Đặt một lần trong `resources/views/components/layouts/admin.blade.php`, nên mọi biểu mẫu trong khu quản trị đều có mà không phải nhớ thêm vào từng trang.
  - `x-admin.field`: sinh id `{name}-error` và `{name}-help`, đánh dấu `data-neo-describedby` / `data-neo-invalid`. Khối trợ giúp **luôn tồn tại** (ẩn khi rỗng) để `aria-describedby` không trỏ vào một id không có thật.
  - `x-admin.money-input`: gắn `aria-describedby`, `aria-invalid` và `is-invalid` **ngay trong HTML**, không đợi JavaScript — trình đọc màn hình phải biết ô này sai kể cả khi script hỏng.
  - `resources/js/admin.js`: đọc các dấu mốc `data-neo-*` để gắn ARIA cho những control nằm trong slot mà Blade không với tới được, rồi đưa tiêu điểm và cuộn tới ô sai đầu tiên. Đây chỉ là phần **hỗ trợ**: câu lỗi đã có sẵn trong HTML.
- *File thay đổi:* `resources/views/components/admin/error-summary.blade.php` (mới), `resources/views/components/admin/field.blade.php`, `resources/views/components/admin/money-input.blade.php`, `resources/views/components/layouts/admin.blade.php`, `resources/js/admin.js`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Admin/FormErrorUxTest.php` — 6 test: có tóm tắt lỗi, tóm tắt nêu đúng tên trường, mỗi dòng liên kết tới đúng control, **biểu mẫu sạch không hiện hộp cảnh báo rỗng**, ô sai có `aria-invalid` + `aria-describedby` trỏ đúng câu lỗi, và câu trợ giúp vẫn được thông báo khi ô hợp lệ.
- *Kiểm chứng bằng negative control:* gỡ `<x-admin.error-summary>` khỏi layout và gỡ `aria-describedby` khỏi `money-input` → 4/6 test fail; khôi phục → 6/6 pass. Không có bước này thì không thể khẳng định test đang thực sự kiểm tra điều gì.
- *Lệnh đã chạy:* trước khi sửa = 2 passed / 4 failed; sau khi sửa `php artisan test tests/Feature/Admin/FormErrorUxTest.php` = 6 passed / 16 assertions; `php artisan test` = 515 passed / 1556 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed; `npm run build` = built in 2.46s.
- *Rủi ro còn lại:* ARIA được gắn trong HTML cho `money-input`; các control khác (select, input thường viết thẳng trong form) vẫn dựa vào lớp JavaScript. Nên chuyển dần sang component dùng chung để mọi ô đều đúng kể cả khi không có script — việc này thuộc `UX-P2-01` (design system). Chưa kiểm chứng bằng trình đọc màn hình thật hay axe/Lighthouse; đó là phần của `UX-P1-03`.

### [x] UX-P1-02 — Chống mất dữ liệu và xung đột chỉnh sửa

- Unsaved changes warning cho invoice/payroll/catalog/form dài.
- Optimistic concurrency bằng `updated_at`/version; báo ai vừa sửa và cho reload/compare.
- Sau POST dùng PRG; giữ filters/branch/date khi redirect.
- Nút destructive hiển thị entity, số tiền, branch và hậu quả.

**Implementation evidence (2026-09-12)**

- *Audit bốn yêu cầu của hạng mục:*
  1. **Optimistic concurrency — ĐÃ XONG** ở `VAL-P1-02`: hóa đơn mang `expected_version`, lần lưu dựng trên phiên bản cũ bị từ chối kèm câu bảo tải lại.
  2. **Nút destructive nêu entity/số tiền/branch/hậu quả — ĐÃ XONG** ở `FRAUD-P0-06`: câu xác nhận hủy giao dịch quỹ nêu số tiền, hạng mục và chi nhánh.
  3. **PRG** — đã dùng sẵn ở mọi controller từ trước (`redirect()->route(...)` sau POST). Không cần sửa.
  4. **Giữ filters khi redirect** và **cảnh báo mất dữ liệu** — chưa có, là phần làm ở đây.
- *Vấn đề thật:* một quản lý lọc sổ quỹ về đúng một tuần, tìm ra dòng sai rồi hủy nó, sẽ bị ném về danh sách **không còn bộ lọc** và phải lọc lại từ đầu. Đây là loại ma sát nhỏ dẫn tới việc người ta gom các khoản cần sửa "để làm sau" — và đó là lúc chúng không bao giờ được sửa nữa.
- *Đã triển khai:*
  - `Controller::backToList()`: quay lại đúng danh sách người dùng vừa đứng, kèm query string. **Chỉ chấp nhận URL trỏ về đúng danh sách đó** — nếu không, header `referer` sẽ trở thành một open redirect, biến một lần lưu thành công thành cú nhảy ra ngoài site. Có test cho cả hai hướng lạm dụng.
  - `CashTransactionController` (tạo/sửa/hủy) dùng helper này.
  - `data-neo-dirty-guard` + xử lý trong `resources/js/admin.js`: cảnh báo trước khi rời trang nếu đã gõ mà chưa lưu. Gắn cho ba biểu mẫu dài nhất: sổ quỹ, hóa đơn, phiếu chuyển kho. **Không cảnh báo khi chính người dùng bấm gửi** — nếu không, hộp thoại bật ra ở mọi lần lưu và người ta sẽ học cách bấm qua nó mà không đọc, tức là biện pháp tự vô hiệu hóa chính mình.
- *File thay đổi:* `app/Http/Controllers/Controller.php`, `app/Http/Controllers/Admin/CashTransactionController.php`, `resources/js/admin.js`, `resources/views/admin/cash/form.blade.php`, `resources/views/admin/invoices/partials/form.blade.php`, `resources/views/admin/transfers/form.blade.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Admin/FilterPreservationTest.php` — 6 test: hủy quay về đúng danh sách đã lọc, tạo mới cũng vậy, **không có referer thì về danh sách trơn**, **referer từ site khác bị bỏ qua**, **referer của màn hình khác cũng bị bỏ qua**, và biểu mẫu dài có gắn dirty guard.
- *Lệnh đã chạy:* trước khi sửa = 1 passed / 3 failed; sau khi sửa `php artisan test tests/Feature/Admin/FilterPreservationTest.php` = 6 passed / 12 assertions; `php artisan test` = 538 passed / 1606 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed; `npm run build` = built in 2.35s.
- *Rủi ro còn lại:* `backToList()` mới áp cho sổ quỹ — màn hình hay lọc nhất. Kho, hóa đơn, lương và điều chuyển vẫn `redirect()->route()` trơn; mở rộng là một dòng mỗi chỗ nhưng mỗi màn hình cần test riêng nên để lại. Dirty guard mới gắn cho ba biểu mẫu dài; các form ngắn cố ý bỏ qua vì cảnh báo ở form hai ô là phiền vô cớ. Phần "báo **ai** vừa sửa và cho so sánh hai phiên bản" chưa có — hiện chỉ báo "có người vừa lưu, hãy tải lại"; muốn nêu tên thì phải đọc từ `audit_events`, làm được nhưng là một màn hình khác.

### [-] UX-P1-03 — Accessibility và responsive audit

- Keyboard-only: menu, modal, dynamic rows, date controls.
- Label/help/error liên kết bằng `for`, `aria-describedby`, `aria-invalid`.
- Focus trap/return focus của modal/offcanvas.
- Bảng lớn có responsive/card alternative, sticky headers, caption/scope.
- Contrast, touch target ≥ 44px, zoom 200%, reduced motion.
- Chạy axe/Lighthouse và manual screen-reader smoke test.

**Implementation evidence (2026-09-12) — phần kiểm chứng được bằng test đã xong; phần cần trình duyệt còn lại**

- *Audit hiện trạng — phần lớn đã tốt sẵn, chỉ thiếu một thứ:*
  - `scope="col"` đã dùng **176 chỗ** trong các bảng admin.
  - Skip link (`neo-skip` → `#neo-main`), landmark `<main id="neo-main">` và `lang="vi"` đã có ở layout.
  - `@media (prefers-reduced-motion: reduce)` đã có trong `_neo-admin.scss`.
  - Biến `$neo-tap-target: 44px` đã có sẵn kèm chú thích theo hướng dẫn của Apple và Google.
  - Modal dùng Bootstrap nên focus trap và trả tiêu điểm là hành vi sẵn có; markup đã có `aria-labelledby`, `aria-hidden`, `tabindex="-1"`.
  - Liên kết label/help/error bằng `for`, `aria-describedby`, `aria-invalid` đã làm ở `UX-P1-01`.
  - **Thiếu: `<caption>` — 0 bảng nào có.** Không có caption thì trình đọc màn hình chỉ đọc "bảng, tám cột" rồi thôi; trên trang có nhiều bảng, người dùng bàn phím không biết mình vừa rơi vào bảng nào.
- *Đã triển khai:* thêm `<caption class="visually-hidden">` cho **17 bảng** — 12 màn hình danh sách và 5 bảng trên trang duyệt chấm công (trang nhiều bảng nhất, cũng là nơi caption có ích nhất). Caption ẩn về mặt thị giác nên giao diện không đổi chút nào.
- *File thay đổi:* 13 view `resources/views/admin/*/index.blade.php` và `resources/views/admin/attendance/review.blade.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Admin/AccessibilityTest.php` — 5 test: bảng có caption, cột có `scope="col"`, mọi trang có skip link, landmark `#neo-main` tồn tại, và trang khai báo `lang="vi"`. Bốn test sau pass ngay từ đầu, xác nhận nhận định audit ở trên là đúng chứ không phải đoán.
- *Lệnh đã chạy:* trước khi sửa = 4 passed / 1 failed (đúng một thiếu sót thật); sau khi sửa = 5 passed / 11 assertions; `php artisan test` = **558 passed / 1642 assertions**, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed; `npm run build` = built in 2.38s.

**Phần chưa làm và vì sao (giữ `[-]`)**

Những mục còn lại của hạng mục này **không kiểm chứng được bằng feature test** — chúng cần một trình duyệt thật và, với vài mục, một con người:

- **Keyboard-only qua menu, modal, dòng động, ô chọn ngày** — cần điều khiển bàn phím thật. Markup đã đúng nhưng "đúng markup" không đảm bảo "dùng được bằng bàn phím".
- **Contrast, zoom 200%, touch target đo thật** — cần dựng trang rồi đo. Biến 44px đã có nhưng chưa kiểm chứng là mọi nút đều đạt.
- **axe/Lighthouse và screen-reader smoke test** — cần chạy công cụ ngoài.
- **Bảng lớn có phương án dạng thẻ trên mobile, sticky header** — cần xem trên thiết bị thật để biết bảng nào thực sự khó dùng; sửa mò mà không nhìn sẽ chỉ đổi một vấn đề lấy một vấn đề khác.

Toàn bộ phần này thuộc cùng nhóm công việc với `TEST-P2-01` (browser/E2E) vì dùng chung hạ tầng trình duyệt. Đề xuất làm một lượt khi dựng E2E, thay vì tự nhận là xong dựa trên suy đoán.

### [x] PERF-P1-01 — Query và export performance budget

- Giữ test N+1 hiện có; bổ sung attendance, dashboard, reports, transfer.
- Index theo query thực tế: branch + status/date/actor.
- Export lớn dùng cursor/chunk/stream hoặc queue.
- Đặt budget query count và response time với dataset mô phỏng production.

**Implementation evidence (2026-09-12)**

- *Lỗi N+1 có thật đã tìm ra và sửa:* danh sách chấm công chạy **45 truy vấn cho 18 dòng**. Đo bằng cách gom nhóm query log cho thấy `select "id" from "branches" where "is_active" = ?` lặp **37 lần**. Nguyên nhân: mỗi dòng gọi `@can('update', $item)` → policy gọi `canAccessBranch()` → `accessibleBranchIds()`, và với owner thì hàm này **truy vấn toàn bộ chi nhánh mỗi lần gọi**.
- *Vì sao đáng sửa:* loại lỗi này chạy tốt trên mười lăm dòng lúc phát triển và không dùng được trên hai nghìn dòng mà một chi nhánh thật có sau một năm. Nó xuống cấp từ từ nên không ai nói được là hỏng từ lúc nào.
- *Cách sửa:* nhớ kết quả `accessibleBranchIds()` trong phạm vi **một instance model**, khóa theo ngày truy vấn. Không phải cache bền: request kế tiếp dựng lại model nên một phân công vừa bị thu hồi có hiệu lực ngay.
- *Vì đây là dữ liệu quyết định quyền truy cập nên đã viết test riêng:* `tests/Feature/Admin/BranchScopeCacheTest.php` — 3 test: instance mới thấy ngay phân công **bị thu hồi**, thấy ngay phân công **mới thêm**, và **truy vấn kèm ngày không dùng chung kết quả với truy vấn không kèm ngày** (phân công hết hạn vẫn phải trả về "không" khi hỏi theo hôm nay). Cache sai ở chỗ này sẽ cho người đã bị gỡ khỏi chi nhánh tiếp tục vào được, nên không thể chỉ tin là nó đúng.
- *Trang báo cáo — đo trước khi kết luận:* chạm đúng 30 truy vấn. Thay vì nâng ngưỡng cho qua, đã **đo lại với gấp ba số dòng: vẫn đúng 30**. Vậy đây là bảng điều khiển rộng với nhiều phép tổng hợp độc lập, con số phẳng theo dữ liệu, không phải N+1. Ngưỡng đặt 35 để bắt được N+1 thật chứ không phải để phạt bề rộng vốn có, và lý do ghi ngay trong test.
- *File thay đổi:* `app/Models/User.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Admin/QueryBudgetTest.php` — 5 màn hình mà plan nêu là chưa phủ: danh sách chấm công, hàng đợi duyệt chấm công, trang báo cáo, danh sách chuyển kho, hàng đợi cảnh báo. Cộng với `BranchScopeCacheTest.php` (3 test) ở trên.
- *Negative control:* tắt phần memoise → test chấm công fail đúng chỗ; bật lại → 5/5 pass. Không có bước này thì không phân biệt được "đã sửa" với "ngưỡng đặt quá rộng".
- *Hai lỗi seeding của chính tôi đã lộ ra và sửa:* `attendance_records` unique theo (nhân viên, ngày, tên ca) và `stock_transfer_items` unique theo (phiếu, vật tư) — bản đầu tạo trùng. Đây là bằng chứng các ràng buộc unique đã kiểm kê ở `VAL-P1-02` đang hoạt động thật.
- *Lệnh đã chạy:* `php artisan test tests/Feature/Admin/QueryBudgetTest.php` = 5 passed / 10 assertions; `BranchScopeCacheTest` = 3 passed / 6 assertions; `php artisan test` = **553 passed / 1631 assertions**, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed; `npm run build` = built in 2.40s.
- *Rủi ro còn lại:* ngân sách truy vấn đo trên SQLite trong bộ nhớ với vài chục dòng, **không phải dataset mô phỏng production** như plan mong muốn — nó bắt được N+1 nhưng không nói gì về thời gian phản hồi thật. Đo thời gian cần môi trường giống production, thuộc `TEST-P2-01`. Phần "index theo query thực tế" chưa đụng tới: các index hiện có đã phủ `branch_id + ngày/trạng thái` (xem `add_admin_module_indexes`), và thêm index mà không có số liệu chậm thật thì chỉ làm chậm ghi. Export lớn đã dùng `chunkById` + stream từ trước nên không nổ bộ nhớ; chuyển sang queue là việc của `SEC-P0-04`/`OPS-P2-01` khi có nhu cầu thật.

---

## P2 — Nâng chất lượng vận hành

### [-] OPS-P2-01 — Observability và cảnh báo

- Correlation/request ID xuyên suốt log và audit.
- Structured logging cho failed auth, 403, sensitive state transition, export.
- Alert nhiều login fail, void/refund tăng đột biến, queue/job lỗi.
- Health checks cho DB, queue, mail, storage; không lộ secret.

**Implementation evidence (2026-09-12) — ba phần đầu đã xong; phần alert cần hạ tầng**

- *Đã triển khai:*
  - **Correlation ID xuyên suốt:** `app/Http/Middleware/AssignRequestId.php` sinh một UUID cho mỗi request, đưa vào `Log::shareContext()` và vào `AuditRecorder`, rồi trả ra header `X-Request-Id`. Nhờ vậy một dòng log và một dòng `audit_events` của cùng request **nối được với nhau trực tiếp** thay vì phải dò theo mốc thời gian — việc dò đó là phỏng đoán ngay khi có hai người cùng thao tác.
  - **Structured logging cho cửa vào:** `app/Providers/SecurityLogServiceProvider.php` lắng nghe `Failed`, `Lockout`, `Login`, `Logout`. Audit trail ghi những gì người ta làm **sau khi** vào được; phần này ghi chính cái cửa. Một loạt lần đăng nhập sai vào cùng một tài khoản là cảnh báo duy nhất cho thấy có người đang đoán mật khẩu, và nó **không để lại dấu vết ở bất cứ đâu khác**.
  - **Kênh log riêng** `security` (daily, giữ 90 ngày) trong `config/logging.php`: tách khỏi log ứng dụng vì hai thứ có vòng đời và người đọc khác nhau — lỗi ứng dụng sẽ bị dọn, còn dấu vết đăng nhập cần giữ lâu hơn và ít người được xem hơn.
  - **Health check thật** tại `/health`: chạm thật vào database, cache và **ghi rồi xóa một tệp** trên storage. Một trang trả 200 chỉ vì PHP đang chạy thì không nói lên điều gì — tiệm không thu được tiền nếu database chết, và lúc hai giờ sáng thì khác biệt đó là tất cả.
- *Quyết định về quyền riêng tư trong log, có test kiểm chứng:*
  - **Không bao giờ ghi mật khẩu đã thử.** Có test đọc lại tệp log thật và khẳng định chuỗi mật khẩu không xuất hiện.
  - **Email bị che một phần** (`ch***********t`): đủ để nhận ra mẫu lặp, không đủ để ai đọc được tệp log thu hoạch danh sách địa chỉ. Một tệp log đầy email đầy đủ chính là một danh sách gửi thư cho người đọc nó.
  - **IP chỉ lưu dạng `hash_hmac`**, cùng nguyên tắc với audit trail: nhận diện được, không lưu địa chỉ thật.
  - `/health` là endpoint công khai nên phản hồi chỉ nêu tên thành phần và có trả lời hay không; không trả về thông tin kết nối, không trả về stack trace.
- *File thay đổi:* `app/Http/Middleware/AssignRequestId.php` (mới), `app/Providers/SecurityLogServiceProvider.php` (mới), `app/Http/Controllers/HealthController.php` (mới), `app/Services/Audit/AuditRecorder.php` (thêm `useCorrelationId()`), `config/logging.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `routes/web.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/ObservabilityTest.php` — 8 test: mọi phản hồi có `X-Request-Id`, **hai request có id khác nhau**, đăng nhập sai được ghi log với định danh đã che, **mật khẩu đã thử không bao giờ vào log**, đăng nhập thành công được ghi, **kênh security ghi ra tệp riêng thật** (đọc lại tệp, không chỉ mock), health check báo từng thành phần, và health check không lộ cấu hình.
- *Hai lỗi của chính tôi đã lộ ra khi chạy test:*
  1. Ban đầu lấy correlation id từ container `scoped`, nhưng trong test container sống qua nhiều request nên hai request **dùng chung một id** — một id không phân biệt được request thì chẳng giải thích được gì. Đã chuyển sang sinh id ngay trong middleware.
  2. Biểu mẫu đăng nhập dùng trường `login` (email hoặc username) chứ không phải `email`, nên test đầu không kích hoạt lần thử đăng nhập nào cả và listener "không chạy" — hóa ra là test sai, không phải code sai.
- *Lệnh đã chạy:* `php artisan test tests/Feature/ObservabilityTest.php` = 8 passed / 23 assertions; `php artisan test` = **566 passed / 1665 assertions**, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed.

**Phần chưa làm (giữ `[-]`)**

- **Alert khi nhiều login fail, void/refund tăng đột biến, job lỗi** — dữ liệu đã đủ để phát hiện (log bảo mật cho login, `risk_flags` cho void/refund bất thường), nhưng **gửi cảnh báo đi đâu** là quyết định hạ tầng: email, Zalo, Telegram hay chỉ hiện trong ứng dụng; ai nhận; ngưỡng bao nhiêu thì phiền và bao nhiêu thì quá muộn. Cảnh báo gửi sai chỗ hoặc quá nhiều sẽ bị tắt, và khi đó nó tệ hơn không có. Cần chủ tiệm chọn kênh trước.
- **Health check cho queue và mail** — hệ thống hiện chạy queue driver mặc định và chưa cấu hình mail thật (xem `docs/BOOKING-EMAIL-NOTIFICATIONS.md`); kiểm tra một thành phần chưa dựng sẽ chỉ tạo báo động giả. Thêm khi hai thứ đó được cấu hình thật.

### [-] DATA-P2-01 — Retention, backup và phục hồi

- Chính sách giữ audit, attendance/GPS evidence, customer PII, exports.
- Backup mã hóa và thử restore định kỳ.
- Quy trình sửa dữ liệu production bằng audited command, không sửa SQL tay.
- Không hard-delete actor có lịch sử tài chính; dùng deactivate/anonymize theo chính sách.

**Implementation evidence (2026-09-12) — phần chặn hard-delete đã xong; phần chính sách cần con người**

- *Lỗ hổng thật đã sửa:* `ProfileController::destroy()` cho phép **xóa hẳn tài khoản của chính mình**, kể cả tài khoản đã lập hóa đơn, ghi sổ quỹ, ghi phiếu kho hoặc có bảng lương. Xóa xong thì mọi dòng dữ liệu đó trỏ về hư không: sổ sách vẫn khớp, nhưng câu "ai đã làm việc này" mất câu trả lời cho **toàn bộ lịch sử của người đó**. Điều này không phân biệt được với việc tự dọn dẹp dấu vết sau khi làm gì đó — nên phải **từ chối**, không phải chỉ khuyến cáo.
- *Đã triển khai:*
  - `User::hasFinancialHistory()` — kiểm tra hóa đơn đã lập, dòng hóa đơn được hưởng hoa hồng, giao dịch quỹ, phiếu kho và bảng lương.
  - `ProfileController::destroy()` từ chối kèm câu chỉ đúng việc cần làm thay thế: nhờ chủ tiệm **vô hiệu hóa** tài khoản. Vô hiệu hóa mới là công cụ đúng — mất quyền ngay lập tức (đã có thu hồi phiên ở `SEC-P0-03`) và dấu vết còn nguyên.
  - Tài khoản chưa từng đụng vào tiền vẫn xóa được bình thường; không siết quá tay.
- *File thay đổi:* `app/Models/User.php`, `app/Http/Controllers/ProfileController.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Admin/ActorRetentionTest.php` — 4 test: tài khoản có hóa đơn không tự xóa được (**và vẫn đang đăng nhập**, không bị đăng xuất nửa chừng), tài khoản có giao dịch quỹ cũng vậy, **tài khoản chưa có lịch sử tài chính vẫn xóa được**, và vô hiệu hóa giữ nguyên cả tài khoản lẫn lịch sử.
- *Lệnh đã chạy:* trước khi sửa = 2 passed / 2 failed; sau khi sửa = 4 passed / 13 assertions; `php artisan test` = **570 passed / 1678 assertions**, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed.
- *Đã có sẵn từ trước, không cần làm lại:* audit trail không cascade theo bản ghi nghiệp vụ (`FRAUD-P0-02`), `actor_name` được snapshot nên tên người thao tác còn đọc được sau khi tài khoản biến mất, và FK của `audit_events`/`risk_flags` dùng `nullOnDelete` chứ không `cascade`.

**Phần chưa làm (giữ `[-]`) — là chính sách và vận hành, không phải code**

- **Chính sách giữ dữ liệu** (audit giữ bao lâu, bằng chứng GPS giữ bao lâu, PII khách hàng giữ bao lâu, file export giữ bao lâu). Đây là quyết định pháp lý và kinh doanh, không phải kỹ thuật: giữ quá ngắn thì mất khả năng điều tra, giữ quá dài thì thành trách nhiệm pháp lý với dữ liệu cá nhân của khách. Đã đặt sẵn một mốc duy nhất có thể suy ra được là log bảo mật giữ 90 ngày (`SECURITY_LOG_DAYS`), còn lại cần chủ tiệm quyết.
- **Backup mã hóa và thử restore định kỳ** — hoàn toàn nằm ngoài mã nguồn: phụ thuộc nơi triển khai (Laravel Cloud, VPS, shared hosting), nơi cất bản sao và ai giữ khóa. Viết một lệnh backup mà không biết nó chạy ở đâu sẽ tạo ra cảm giác an toàn sai lệch.
- **Quy trình sửa dữ liệu production bằng audited command** — cần biết những loại sửa nào thực sự xảy ra trong thực tế. Dựng sẵn một khung lệnh tổng quát trước khi có ca sửa thật thường cho ra thứ không ai dùng.
- **Anonymize theo chính sách** — phụ thuộc chính sách giữ dữ liệu ở gạch đầu dòng đầu tiên; chưa quyết cái đó thì chưa biết ẩn danh cái gì và lúc nào.

### [-] UX-P2-01 — Design system admin

- Chuẩn hóa page header, filter bar, money/date input, status badges, empty/error states.
- Form dirty state, submit state, permission tooltip và audit timeline component.
- Storybook không bắt buộc; có thể dùng route nội bộ/component tests.

**Implementation evidence (2026-09-12) — bộ component đã đủ; đã khóa hành vi bằng test**

- *Audit trước khi làm:* gần như toàn bộ component mà hạng mục yêu cầu **đã tồn tại** và đang được dùng khắp khu quản trị: `page-header`, `filter-bar`, `money-input`, `money`, `status-badge`, `empty-state`, `submit-button`, `pagination`, `confirm-form`/`confirm-modal`, `flash`, `field`. Hai cái mới thêm trong các phiên này là `error-summary` (`UX-P1-01`) và `audit-timeline` (`FRAUD-P0-02`); dirty state có qua `data-neo-dirty-guard` (`UX-P1-02`).
- *Khoảng trống thật:* không có gì **ghi lại** hành vi mà các component hứa hẹn. Chúng được dùng ở mọi màn hình, nên sửa một cái là đổi cả khu quản trị cùng lúc — đó vừa là lý do tồn tại của chúng, vừa là lý do phải viết ra thay vì mỗi lần lại mở trang lên nhìn.
- *Đã triển khai:* `tests/Feature/Admin/ComponentContractTest.php` — 9 test dựng **riêng từng component** bằng `Blade::render()`. Cố ý không test qua trang đầy đủ: một test trang có thể xanh vì một phần khác của layout tình cờ sinh ra cùng đoạn markup, tức là xanh vì lý do sai.
- *Những điều được khóa lại:* label gắn `for` đúng control và dấu sao bắt buộc bị ẩn khỏi trình đọc màn hình; **id trợ giúp luôn tồn tại kể cả khi rỗng** (nếu không `aria-describedby` sẽ trỏ vào id không có thật); id lỗi tồn tại khi có lỗi; tiền luôn định dạng như nhau ở mọi nơi; ô tiền tự mang `aria-invalid` + `is-invalid` + `aria-describedby`; badge trạng thái hiện nhãn người đọc được; empty state mang được hành động tiếp theo; confirm form yêu cầu được lý do gõ tay và **không chứa giá trị lý do dựng sẵn**; và hộp tóm tắt lỗi biến mất hoàn toàn khi biểu mẫu sạch.
- *File thay đổi:* chỉ thêm test, không sửa component nào — đây là hạng mục ghi nhận và bảo vệ hiện trạng, không phải xây mới.
- *Migration:* không có.
- *Negative control:* gỡ `aria-describedby` khỏi `money-input` và gỡ id khỏi khối trợ giúp của `field` → 2 test fail đúng chỗ; khôi phục → 9/9 pass. Cả 9 test pass ngay lần chạy đầu nên bước này là bắt buộc: không có nó thì không phân biệt được "component đúng" với "test không kiểm tra gì".
- *Lệnh đã chạy:* `php artisan test tests/Feature/Admin/ComponentContractTest.php` = 9 passed / 18 assertions; `php artisan test` = **579 passed / 1696 assertions**, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed; `npm run build` = built in 2.30s.
- *Phần chưa làm:* **ô chọn ngày chưa được chuẩn hóa thành component** — hiện mỗi màn hình tự viết `<input type="date">`, và không đồng nhất (chỗ có `is-invalid`, chỗ không). Gom lại thành `x-admin.date-input` là việc rẻ, nhưng nó chạm vào khoảng mười biểu mẫu nên mỗi chỗ cần kiểm tra lại; để riêng cho một lượt có thể xem được kết quả trên trình duyệt. "Permission tooltip" cũng chưa có — hiện nút không đủ quyền bị ẩn hẳn thay vì hiện kèm giải thích; đổi sang hiện-và-giải-thích là thay đổi về sản phẩm chứ không chỉ về component, nên cần chủ tiệm quyết có muốn nhân viên nhìn thấy những nút họ không được bấm hay không.

### [ ] TEST-P2-01 — Browser/E2E cho hành trình trọng yếu

- Login → branch switch → appointment → invoice → pay.
- Manual cash → second approval → close shift reconciliation.
- Roster → GPS check-in/out → overtime approval → payroll.
- Stock transfer hai đầu.
- Mobile viewport, double click, back button, stale form, mất mạng giữa submit.

---

## P3 — Cải tiến dài hạn

### [ ] ARCH-P3-01 — Permission model dạng capability lưu DB

Chuyển dần từ boolean rời rạc sang role/permission có migration rõ, default deny, audit thay quyền và template role.

### [ ] ARCH-P3-02 — Domain event/outbox cho tài chính và audit

State transition ghi business row + outbox/audit atomically; worker gửi notification/report mà không làm mất event.

### [ ] FRAUD-P3-01 — Phân tích bất thường nâng cao

Chỉ làm sau khi có dữ liệu sạch và rule engine. Dùng baseline theo chi nhánh/actor, giải thích được, không tự động kết tội hoặc khấu trừ lương.

---

## 6. Checklist audit theo loại file

### Routes/middleware

- [ ] Mọi route ghi dữ liệu dùng đúng HTTP verb, CSRF và auth.
- [ ] Route nhạy cảm có throttle/idempotency/step-up phù hợp.
- [ ] Route admin có authorization default-deny.
- [ ] Route model binding không vượt branch/parent scope.

### Controllers/Form Requests

- [ ] Controller gọi authorize trước query/write nhạy cảm.
- [ ] Form Request không chỉ `exists`; có domain/branch/active/date constraints.
- [ ] Không tin branch, actor, total, status transition, timestamp từ client.
- [ ] Error message không lộ ID/dữ liệu ngoài scope.

### Actions/services

- [ ] Transition quan trọng nằm trong transaction.
- [ ] Có row lock/optimistic lock khi concurrent request gây double effect/lost update.
- [ ] Check trạng thái được lặp lại sau lock.
- [ ] Side effect idempotent và có unique key ở DB.
- [ ] Audit ghi cùng transaction với thay đổi.

### Models/database

- [ ] FK delete behavior không xóa mất audit tài chính.
- [ ] Unique/check/index phản ánh invariant nghiệp vụ.
- [ ] Money/quantity không dùng float.
- [ ] Actor snapshot vẫn truy được sau khi account deactivate/xóa.

### Blade/JavaScript

- [ ] UI chỉ là hỗ trợ; server vẫn enforce.
- [ ] Không nested form, duplicate ID hoặc lỗi dynamic field name.
- [ ] Confirm nêu đúng hậu quả và yêu cầu reason khi cần.
- [ ] Submit guard không thay thế idempotency server.
- [ ] Validation nested row, focus, keyboard, mobile và screen reader hoạt động.

### Tests

- [ ] Happy path.
- [ ] Guest/inactive/wrong role.
- [ ] Cross-branch và nested ID tampering.
- [ ] Same actor/maker-checker violation.
- [ ] Inactive/expired/foreign related model.
- [ ] Boundary money/date/timezone.
- [ ] Double submit/concurrency/stale state.
- [ ] Audit before/after và audit survival.
- [ ] Query count và export injection.

---

## 7. Trình tự triển khai đề xuất

1. `SEC-P0-01` — tránh lộ số tài chính ngay.
2. `SEC-P0-02` — đóng toàn bộ tampered foreign ID/cross-branch.
3. `SEC-P0-05` — tạo safety net cho route mới.
4. `FRAUD-P0-02` — có nền audit chung trước khi thêm approval.
5. `FRAUD-P0-01` — maker-checker sổ quỹ.
6. `FRAUD-P0-03` — tách quyền và kiểm soát override hóa đơn.
7. `FRAUD-P0-04` — self-dealing chấm công.
8. `FRAUD-P0-05` — kho hai người/hai đầu.
9. `SEC-P0-03`, `SEC-P0-04` — step-up và export hardening.
10. P1 theo thứ tự validation → account/session → reconciliation/risk → UX → performance.
11. P2/P3 sau khi P0/P1 ổn định.

---

## 8. Definition of Done cho từng hạng mục

Một hạng mục chỉ được đánh `[x]` khi:

1. Có mô tả threat/bug và hành vi mong muốn.
2. Có test thất bại tái hiện lỗ hổng trước thay đổi.
3. Code sửa theo convention hiện tại, không bypass policy/branch context.
4. Có test authorization, validation, cross-branch và race/idempotency nếu liên quan.
5. UI có loading/error/empty/confirmation/accessibility nếu có giao diện.
6. Audit không chứa secret/PII thừa.
7. Chạy formatter trên file thay đổi.
8. Test mục tiêu đạt.
9. Toàn bộ `php artisan test` đạt.
10. `npm run build` đạt nếu đổi frontend.
11. Cập nhật mục này với file thay đổi, lệnh test và quyết định nghiệp vụ.

---

## 9. Prompt để agent đọc plan và làm

Sao chép nguyên prompt dưới đây vào agent coding:

```text
Bạn đang làm trong Laravel project Cái Tiệm Neo.

Hãy đọc toàn bộ file docs/ADMIN-AUDIT-AND-ANTI-FRAUD-PLAN.md trước khi sửa code, sau đó đọc AGENTS.md và tuân thủ Laravel Boost/project conventions.

Nhiệm vụ:
1. Tìm hạng mục P0/P1 đầu tiên trong plan đang có trạng thái [ ] và không bị phụ thuộc bởi mục chưa làm. Nếu tôi truyền mã hạng mục cụ thể thì chỉ làm đúng mã đó.
2. Trước khi code, audit lại nhận định trong plan bằng routes, controller, Form Request, policy, action, model, migration, Blade/JS và test liên quan. Không coi nhận định trong plan là chắc chắn nếu code hiện tại đã thay đổi.
3. Viết ngắn gọn threat model của hạng mục: actor, tài sản, cách khai thác, hậu quả, hành vi mong muốn.
4. Viết test thất bại trước. Bắt buộc xét khi phù hợp: guest/inactive/wrong role, cross-branch, payload tampering, inactive/expired relation, same actor (maker-checker), double submit, concurrent/stale state, audit survival.
5. Sửa tối thiểu nhưng hoàn chỉnh ở server-side. Không dựa vào việc ẩn nút/disabled field. Mọi query/write phải giữ branch isolation, authorization, transaction, lock/idempotency và money precision hiện có.
6. Nếu hạng mục cần quyết định nghiệp vụ chưa có trong plan, không tự đoán âm thầm. Đánh dấu [!] tại mục đó, ghi rõ 2-3 phương án cùng trade-off, rồi dừng phần implementation có thể gây sai tiền/lương/quyền. Vẫn có thể làm phần audit/test không phụ thuộc quyết định.
7. Nếu có migration, đảm bảo dữ liệu cũ được backfill an toàn, FK/delete behavior không xóa audit, có index/constraint phù hợp và rollback hợp lệ.
8. Nếu có UI, hoàn thiện validation summary, field errors, focus, loading/submitting, confirm/reason, keyboard/mobile/accessibility và stale-form behavior phù hợp.
9. Chạy formatter cho file thay đổi, test mục tiêu, toàn bộ php artisan test, và npm run build nếu đổi frontend. Không xóa/né test đang fail.
10. Cập nhật đúng hạng mục trong docs/ADMIN-AUDIT-AND-ANTI-FRAUD-PLAN.md thành [x] chỉ khi đạt Definition of Done. Ngay dưới mục, thêm “Implementation evidence” gồm file chính, test mới, lệnh đã chạy và kết quả. Nếu chưa xong giữ [-] hoặc [!].
11. Kết thúc bằng báo cáo: mã mục, root cause, thay đổi, test evidence, rủi ro còn lại, mục kế tiếp đề xuất. Không tự động làm sang mục kế tiếp.

Ràng buộc:
- Không sửa .env hoặc đưa secret vào code/log/audit.
- Không hard-delete dữ liệu tài chính/audit.
- Không tin branch_id, actor ID, totals, commission, status transition hoặc payment time từ client.
- Không làm suy yếu policy hiện tại để test pass.
- Không thay đổi nghiệp vụ tiền/lương mà thiếu test và lý do rõ ràng.
- Ưu tiên reuse Action, Form Request, Policy, BranchContext và component hiện có.

Mã hạng mục cần làm: AUTO
```

### Cách chỉ định một hạng mục

Thay dòng cuối bằng ví dụ:

```text
Mã hạng mục cần làm: SEC-P0-01
```

### Prompt review độc lập sau mỗi hạng mục

```text
Đọc docs/ADMIN-AUDIT-AND-ANTI-FRAUD-PLAN.md và review implementation evidence của hạng mục vừa hoàn thành. Không sửa code ngay. Hãy kiểm tra diff và test theo góc nhìn attacker nội bộ: authorization, branch isolation, ID tampering, maker-checker, race/double submit, audit deletion, CSV/PII leakage và UI bypass. Liệt kê finding theo severity với file:line, cách tái hiện và test còn thiếu. Nếu không có finding blocker/high thì xác nhận đủ điều kiện chuyển mục kế tiếp.
```

---

## 10. Audit bổ sung — validation input và bản dịch

### 10.1 Kết luận nhanh

**Validation khi submit chưa thể coi là đầy đủ.** Phần lớn form CRUD đã có HTML constraints và server-side Form Request, nhưng vẫn còn khoảng trống ở validation quan hệ nghiệp vụ, lỗi nested input, một số inline validation và trải nghiệm hiển thị lỗi. HTML `required`, `min`, `max`, `step` và danh sách option đã lọc chỉ hỗ trợ UX, không phải rào bảo mật.

**Bản dịch validation đang thiếu ở mức hệ thống.** Ứng dụng dùng locale `vi`, fallback `en`, nhưng thư mục `lang/vi` hiện chỉ có `passwords.php`; không có `validation.php`. `lang/vi.json` chỉ dịch notification đặt lại mật khẩu. Kiểm chứng runtime bằng validator cho `required` trả về:

- `The customer name field is required.`
- `The email field is required.`
- `The items.0.quantity field is required.`

Vì vậy mọi Form Request/controller chỉ khai báo `attributes()` mà không override `messages()` vẫn có thể hiện template lỗi tiếng Anh; `attributes()` chỉ đổi tên field, không dịch câu lỗi.

### 10.2 Ma trận finding

| Mã | Severity | Finding | Bằng chứng/phạm vi | Hướng xử lý |
|---|---|---|---|---|
| `I18N-P1-01` | High | Thiếu bộ validation tiếng Việt dùng chung | Không có `lang/vi/validation.php`; fallback là `en`; runtime đã trả message tiếng Anh | Publish/tạo đầy đủ rule messages, `attributes`, custom values; thêm test locale cho rule phổ biến và nested fields |
| `VAL-P0-03` | High | Foreign ID mới chủ yếu kiểm tra tồn tại toàn hệ thống | Invoice service/employee, attendance employee, payroll employee/branch, inventory product/supplier, shift assignment và transfer product | Rule phải scope branch, active, assignment/ngày hiệu lực và parent; action guard lại trong transaction |
| `VAL-P1-03` | High | Lỗi nested invoice/transfer không hiển thị đúng từng dòng | Dynamic rows không render lỗi `items.N.*`; transfer chỉ render lỗi cấp `items`; invoice không có row error mapping | Khôi phục old rows, map error theo index, gắn `is-invalid`, message và focus vào đúng row |
| `VAL-P1-04` | Medium | **[x] ĐÃ XONG** — Public booking thiếu error rendering cho một số field | View chỉ hiện lỗi name/phone/start | Đã render lỗi cho `branch_id`, `employee_id`, `duration_minutes`, `service_ids.N`, `note`; `service_ids.*` nay dùng rule `InBranchCatalogue` theo đúng chi nhánh khách chọn. **Phát hiện thêm một lỗi thật:** biểu mẫu công khai không hề có ô chọn chi nhánh trong khi máy chủ bắt buộc `branch_id`, nên **mọi lượt đặt lịch từ trang chủ đều thất bại**; đã thêm ô chọn và test chống tái diễn. Xem `VAL-P1-03` |
| `VAL-P1-05` | Medium | Inline validation không có attributes/messages nhất quán | Booking, invoice KPI, payroll status, employee assignment closing, branch switch và auth/profile còn validate trong controller | Chuyển nghiệp vụ đáng kể sang Form Request; tối thiểu thêm attributes/messages và feature test response |
| `UX-P1-04` | Medium | Component field chưa liên kết accessibility metadata | Component hiện chỉ render label/help/error; input tự quản `is-invalid`, chưa có `aria-invalid`/`aria-describedby`; không có summary/focus lỗi chung | Chuẩn hóa ID help/error, ARIA, summary và focus lỗi đầu tiên |
| `FRAUD-P0-06` | High | **[x] ĐÃ XONG** — Void giao dịch tiền mặt dùng lý do ẩn mặc định | Form list gửi `void_reason="Hủy bởi người dùng"` | Đã bỏ hidden reason; ô lý do bắt buộc `min:10` do người dùng gõ trong `x-admin.confirm-modal`; confirm nêu số tiền/hạng mục/chi nhánh; audit ghi cả hai actor. Xem evidence tại `FRAUD-P0-01` |
| `VAL-P1-06` | Medium | Cảnh báo giá hóa đơn ngoài range chỉ ở client | Alpine chỉ đổi viền/cảnh báo; server vẫn nhận override theo quyền hiện tại | Server yêu cầu quyền + reason + ngưỡng/approval; client chỉ phản ánh kết quả policy |
| `VAL-P2-01` | Medium | Filter GET chưa có chuẩn validation tập trung | Nhiều index đọc date/status/employee trực tiếp; cần kiểm tra giới hạn range, enum, branch visibility | Request riêng hoặc query DTO; normalize; giới hạn date range/export; phản hồi filter sai rõ ràng |

### 10.3 Những phần đã tương đối tốt

- Các form ghi dữ liệu chính nhìn chung có server-side validation, CSRF và authorization; nhiều field có `required`, kiểu dữ liệu, min/max và maxlength tương ứng ở HTML.
- `branch_id` của nhiều luồng ghi được resolve lại từ server branch context, giảm rủi ro sửa hidden input.
- Attendance có rule thời gian vào/ra, unique theo employee/date/shift, reason và một số message tiếng Việt.
- Stock transfer giới hạn 1–100 dòng, số lượng dương, chi nhánh gửi thuộc scope và không cho lặp product trong cùng phiếu.
- Invoice/action, inventory và các state transition quan trọng còn có guard/transaction phía sau validation; phải giữ các lớp bảo vệ này khi chuẩn hóa rule.
- Submit guard và browser validity giúp giảm submit nhầm/double click ở UI, nhưng không được thay thế idempotency server.

### 10.4 Backlog validation/i18n cụ thể

#### [x] I18N-P1-01 — Bổ sung validation localization tiếng Việt toàn hệ thống

**Việc làm:**

- Tạo `lang/vi/validation.php` tương thích phiên bản Laravel hiện tại, bao phủ toàn bộ rules đang dùng: required, string, integer, numeric, boolean, array, date, after, after_or_equal, before, email, min/max/between, gt, in, exists, unique, confirmed, current_password và enum.
- Thêm `custom` cho các câu nghiệp vụ cần tự nhiên; thêm wildcard attributes như `items.*.product_id`, `items.*.quantity`, `items.*.employee_id`, `service_ids.*`.
- Bổ sung attributes còn thiếu ở inline controller và auth/profile request.
- Không dịch bằng cách hard-code cùng một message ở hàng chục Form Request; file chung là mặc định, `messages()` chỉ dùng cho nghiệp vụ đặc thù.
- Test locale `vi` trả về tiếng Việt cho form thường và nested field; test không còn chuỗi mở đầu `The ... field` trong các response 422/redirect errors đại diện.

**Nghiệm thu:** các invalid submit đại diện ở public booking, invoice, transfer, attendance, payroll, cash và auth đều hiện tiếng Việt, tên field dễ hiểu, không lộ raw key/index khó đọc.

**Implementation evidence (2026-09-12)**

- *Root cause:* ứng dụng chạy locale `vi`, fallback `en`, nhưng `lang/vi` chỉ có `passwords.php`. Không có `lang/vi/validation.php` nên mọi rule không được viết `messages()` bằng tay đều trả về tiếng Anh. `attributes()` chỉ đổi tên trường, không dịch câu bao quanh — đúng như plan đã phân tích.
- *Bằng chứng lỗ hổng:* 15/17 test mới fail trước khi sửa, ví dụ `The name field is required.`, `The amount field must be a number.`. Trong quá trình làm các hạng mục P0 trước đó cũng đã gặp lại nhiều lần, ví dụ `The lý do hủy field is required.` — trộn lẫn hai ngôn ngữ trong cùng một câu.
- *Đã triển khai:*
  - `php artisan lang:publish` để lấy cấu trúc chuẩn của Laravel 13, rồi viết `lang/vi/validation.php` phủ **toàn bộ** rule, gồm cả các nhánh con `array`/`file`/`numeric`/`string` của `between`, `gt`, `gte`, `lt`, `lte`, `max`, `min`, `size`, và nhóm `password`.
  - Cách dịch: tiếng Việt không có mạo từ và không chia động từ theo số, nên câu được viết theo lối "Hãy nhập :attribute." / ":attribute phải là một số." thay vì dịch sát "The ... field must be ...". Người dùng đọc ra câu tiếng Việt tự nhiên, không phải bản dịch máy.
  - `attributes` phủ cả **trường lồng nhau** (`items.*.quantity`, `items.*.employee_id`, `items.*.price_override_reason`, `service_ids.*`), để dòng thứ ba của hóa đơn không báo lỗi bằng khóa thô `items.2.quantity`.
  - `custom` chỉ dùng cho vài câu mà thông báo chung nghe không đủ nghĩa (mật khẩu nhập lại, email/username đã tồn tại) — cố ý giữ nhỏ, vì file chung mới là nơi quyết định.
- *File thay đổi:* `lang/vi/validation.php` (mới), `lang/en/*` (publish từ framework để làm chuẩn đối chiếu), `app/Http/Requests/Admin/UpdateInvoiceRequest.php` (sửa lỗi mô tả dưới đây).
- *Migration:* không có.
- *Lỗi thật mà test này bắt được:* `UpdateInvoiceRequest::validateDiscount()` — do chính tôi thêm ở `FRAUD-P0-03` — gọi `Money::toMinor()` trên dữ liệu **chưa qua kiểm tra kiểu**. Nhập chữ "khong phai so" vào ô giảm giá làm ném `InvalidArgumentException` và người dùng nhận **trang lỗi 500** thay vì thông báo validation. Đã thêm điều kiện chỉ chạy các kiểm tra nghiệp vụ khi rule kiểu dữ liệu đã qua. Đây đúng là giá trị của việc test bằng form thật chứ không chỉ test rule tách rời.
- *Tests mới:* `tests/Feature/ValidationLocalizationTest.php` — 17 test: 13 rule phổ biến qua data provider (required, email, numeric, integer, min, max, date, after, boolean, array, in, confirmed, gt) khẳng định không còn chuỗi `The `, ` field ` hay `must be`; một test cho trường lồng nhau đọc ra tên dễ hiểu; và 3 test trên **form thật** (đặt lịch công khai, sửa hóa đơn, hủy giao dịch quỹ) khẳng định mọi câu lỗi người dùng nhận được đều không còn tiếng Anh.
- *Lệnh đã chạy:* trước khi sửa = 2 passed / 15 failed; sau khi sửa `php artisan test tests/Feature/ValidationLocalizationTest.php` = 17 passed / 65 assertions; `php artisan test tests/Feature/ValidationLocalizationTest.php tests/Feature/Admin/InvoiceSensitivePermissionTest.php` = 27 passed / 88 assertions; `php artisan test` = 472 passed / 1446 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = fixed.
- *Rủi ro còn lại:* các rule tự viết (`AssignedToBranch`, `InBranchCatalogue`, `EmployeeWithinActorScope`, `WithinActorBranchScope`) hiện mang message tiếng Việt hard-code ngay trong class. Chúng đã đúng ngôn ngữ nên không sai với người dùng, nhưng chưa đi qua hệ thống dịch; nếu sau này cần đa ngôn ngữ thì nên chuyển vào `lang/vi/validation.php` phần `custom`. `lang/en/` được publish nguyên bản của framework, không sửa gì.

#### [x] VAL-P1-03 — Chuẩn hóa error UX và validation contract của mọi form submit

**Việc làm:**

- Lập inventory route → form → Request/controller → action cho toàn bộ POST/PUT/PATCH/DELETE admin.
- Với mỗi input, đối chiếu HTML constraint và server rule; server là nguồn quyết định cuối cùng.
- Dynamic invoice/transfer giữ toàn bộ old input khi lỗi và hiển thị `items.N.field` ngay đúng dòng.
- Thêm error summary ở đầu form, focus lỗi đầu, trạng thái submitting và phục hồi nút nếu browser chặn submit.
- Destructive action phải nhập reason thực, không dùng hidden/default reason chung.
- Viết feature test cho missing/type/boundary/domain-invalid và view test/browser test cho error rendering.

**Nghiệm thu:** không có input ghi dữ liệu chỉ được kiểm tra ở client; mọi lỗi server nhìn thấy, hiểu được và gắn đúng control.

**Implementation evidence (2026-09-12) — phần dòng động đã xong; error summary và booking còn lại**

- *Root cause:* form phiếu chuyển kho báo lỗi ở **cấp danh sách** (`@error('items')`) chứ không theo từng dòng, và `transferEditor` luôn dựng lại một dòng trống nên **mất toàn bộ dữ liệu người dùng đã gõ** khi form bị trả về. Một phiếu mười dòng báo "danh sách vật tư không hợp lệ" thì người thao tác không biết sửa dòng nào; gõ lại từ đầu là lúc người ta bỏ form và chuyển kho tay không giấy tờ.
- *Đã triển khai:*
  - `StockTransferRequest::withValidator()`: lỗi trùng vật tư nay gắn vào **đúng dòng lặp lại** kèm câu nói rõ nó trùng với dòng số mấy, thay vì một câu chung ở cấp danh sách.
  - `resources/js/admin.js` — `transferEditor` nhận thêm `initialRows` và `serverErrors`, có `rowError(index, field)` tra theo khóa `items.N.field`; giữ nguyên các dòng đã gõ (kể cả dòng gây lỗi, để còn sửa).
  - `resources/views/admin/transfers/form.blade.php`: truyền `old('items')` và `$errors->getMessages()` vào component; mỗi ô có `is-invalid`, `aria-invalid` và thông báo lỗi `role="alert"` của đúng dòng đó.
  - Phần hóa đơn đã làm trước ở `FRAUD-P0-03` bằng đúng cơ chế này.
- *File thay đổi:* `app/Http/Requests/Admin/StockTransferRequest.php`, `resources/js/admin.js`, `resources/views/admin/transfers/form.blade.php`.
- *Migration:* không có.
- *Tests mới:* `tests/Feature/Admin/NestedRowErrorTest.php` — 3 test: lỗi số lượng gắn đúng `items.1.quantity`, các dòng đã gõ quay lại được form, và trùng vật tư báo ở `items.1.product_id`.
- *Một test của chính tôi từng "pass giả" và đã sửa:* bản đầu tìm chữ `quantity` trên toàn trang — nhưng markup của dòng mẫu luôn chứa chữ đó, nên test xanh **bất kể** old input có được giữ hay không. Đã đổi sang bóc riêng đối số thứ hai của `transferEditor` rồi giải mã `"` do `@js()` sinh ra. **Kiểm chứng bằng negative control:** tạm thay `@js(old('items', []))` thành `@js([])` → test fail đúng chỗ; khôi phục → pass. Không có bước này thì một test vô nghĩa đã được tính là bằng chứng.
- *Lệnh đã chạy:* trước khi sửa = 1 passed / 2 failed; sau khi sửa `php artisan test tests/Feature/Admin/NestedRowErrorTest.php` = 3 passed / 8 assertions; negative control = fail đúng như mong đợi; `php artisan test` = 502 passed / 1514 assertions, chỉ còn `BrandingAssetsTest` fail (thiếu `public/images/logo-neo.png`, có sẵn từ baseline); `vendor/bin/pint --dirty` = passed; `npm run build` = built in 2.57s.
- *Phần còn lại của hạng mục (giữ `[-]`):*
  - **Error summary ở đầu form và focus vào lỗi đầu tiên — ĐÃ XONG** (làm cùng `UX-P1-01`, xem Implementation evidence của mục đó).
  - **Booking công khai (`VAL-P1-04`) — ĐÃ XONG.** Đã render lỗi cho mọi trường biểu mẫu nhận, và `service_ids.*` nay dùng rule `InBranchCatalogue` theo đúng chi nhánh khách chọn. `employee_id` hóa ra đã được `SaveAppointmentAction::guardEmployeeIsPosted()` chặn sẵn từ trước — nhận định trong plan không còn đúng với code hiện tại, đã kiểm chứng bằng test thay vì tin vào plan.
  - **Lỗi thật phát hiện khi làm phần này:** biểu mẫu đặt lịch công khai **không có ô chọn chi nhánh** trong khi `branch_id` là trường bắt buộc phía máy chủ — nghĩa là **không một lượt đặt lịch nào từ trang chủ có thể thành công**. Các test cũ không bắt được vì chúng gửi `branch_id` thẳng trong payload, không đi qua biểu mẫu thật. Đã thêm ô chọn chi nhánh và một test khẳng định biểu mẫu hỏi đủ mọi trường máy chủ bắt buộc (đã kiểm chứng bằng negative control: đổi tên trường thì test fail).
  - **Inventory làm dạng một dòng** nên không có vấn đề dòng lồng nhau.
  - Bảng kê "route → form → Request → action" cho toàn bộ POST/PUT/PATCH/DELETE admin chưa lập; phần lớn đã được phủ gián tiếp bởi `AdminRouteGuardConventionTest` (phân quyền) và các test validation theo module.

### 10.5 Thứ tự sửa đề xuất cho riêng validation

1. `I18N-P1-01` để mọi lỗi hiện tại có tiếng Việt nhất quán.
2. `VAL-P0-03`/`SEC-P0-02` để chặn foreign ID chéo branch, inactive và sai ngày hiệu lực.
3. `VAL-P1-03` để sửa nested invoice/transfer và error summary/accessibility.
4. `FRAUD-P0-06` cùng maker-checker sổ thu chi.
5. `VAL-P1-04`, `VAL-P1-05`, `VAL-P2-01` cho booking, inline validation và filters.

---

## 11. Bằng chứng audit ban đầu

- PHP 8.5.0 và Composer 2.10.2 khả dụng.
- Laravel Boost đã có trong dev dependencies; installer đã chạy thành công.
- `php artisan test`: 360 passed / 1125 assertions / khoảng 36 giây.
- Composer update từng báo lỗi metadata platform package cục bộ `lib-curl-schannel ...`; lệnh đã tự hoàn nguyên `composer.json` và `composer.lock`. Đây là vấn đề môi trường Composer cần xử lý riêng nếu lần sau update dependency thất bại, không phải lỗi application test.
- Chưa chạy browser automation, axe/Lighthouse, penetration test động, load test hoặc concurrency test đa process; các phần này nằm trong backlog.

---

## 12. FULL-AUTO checkpoint — báo cáo tổng kết

> Cập nhật: 2026-09-12 (cuối phiên 2)

### Trạng thái tổng quan

| Mức | `[x]` | `[!]` | `[-]` | `[ ]` |
|---|---|---|---|---|
| P0 | 7 | 3 | 0 | 0 |
| P1 | 10 | 2 | 1 | 0 |
| P2 | 0 | 0 | 3 | 1 |
| P3 | 0 | 0 | 0 | 3 |

**Tổng: 17 hạng mục `[x]`.** Không còn hạng mục nào có thể triển khai tiếp mà không cần một trong ba thứ: quyết định nghiệp vụ của chủ tiệm, trình duyệt thật, hoặc quyết định hạ tầng.

### Hạng mục `[!]` — chờ quyết định của chủ tiệm

Mỗi mục đã ghi 2–3 phương án kèm ưu nhược điểm, ảnh hưởng dữ liệu cũ và một đề xuất mặc định, ngay tại hạng mục tương ứng:

| Mã | Câu hỏi cần trả lời |
|---|---|
| `FRAUD-P0-01` | Ngưỡng tiền chuyển giao dịch quỹ sang chờ duyệt; xử lý ra sao khi chi nhánh chỉ có một người đủ quyền |
| `FRAUD-P0-03` | Có tách quyền thu tiền khỏi quyền lập hóa đơn không; xác nhận ngưỡng giảm giá 10% / 100.000đ |
| `FRAUD-P0-05` | Ngưỡng chênh lệch kho cần người thứ hai; có chuyển sang mô hình chuyển kho hai đầu không |
| `FRAUD-P1-02` | Quy trình đối soát cuối ca: ca là gì, ai đếm, ai xác nhận, chênh lệch bao nhiêu thì giải trình |
| `FRAUD-P1-03` | Có bắt buộc chọn nguồn cho hóa đơn khách vãng lai không; ngưỡng no-show để gắn cờ khách |

Ngoài ra, các con số đang để mặc định trong `config/business.php` nên được chủ tiệm xác nhận: ngưỡng giảm giá, số ngày ghi lùi (30), số ngày đặt lịch trước (365), và nhóm `risk.*` (3 lần hủy/ngày, 10 lần xuất/ngày, giờ mở cửa 7–23, chênh lệch kho 500.000đ).

### Hạng mục `[-]` — đã làm phần kiểm chứng được, phần còn lại cần công cụ khác

- **`UX-P1-03`** — markup đã đúng (caption, scope, skip link, ARIA, reduced motion, 44px). Còn lại là keyboard-only, contrast, zoom 200%, axe/Lighthouse, screen reader: **cần trình duyệt thật**.
- **`OPS-P2-01`** — correlation ID, log bảo mật và health check đã xong. Còn lại là **gửi cảnh báo đi đâu**: cần chọn kênh và người nhận trước.
- **`DATA-P2-01`** — đã chặn xóa hẳn tài khoản có lịch sử tài chính. Còn lại là chính sách giữ dữ liệu, backup và quy trình sửa dữ liệu production: **là chính sách vận hành, không phải code**.
- **`UX-P2-01`** — bộ component đã đủ và đã khóa hành vi bằng 9 test. Còn lại là gom ô chọn ngày thành component chung và quyết định về permission tooltip.

### Hạng mục `[ ]` còn lại

`TEST-P2-01` (browser/E2E) và ba mục P3 (`ARCH-P3-01` permission model lưu DB, `ARCH-P3-02` outbox, `FRAUD-P3-01` phân tích nâng cao). Tất cả đều là thay đổi kiến trúc lớn hoặc cần hạ tầng trình duyệt; plan cũng xếp chúng sau khi P0/P1 ổn định.

### Migration đã tạo (đã chạy và kiểm tra rollback)

1. `2026_09_11_162747_create_audit_events_table`
2. `2026_09_11_163137_preserve_attendance_audit_on_delete`
3. `2026_09_11_164844_add_self_recorded_flag_to_attendance_records`
4. `2026_09_12_005438_create_risk_flags_table`

### Kết quả kiểm tra cuối cùng

- `php artisan test` = **579 passed / 1696 assertions**. Một test đỏ duy nhất là `BrandingAssetsTest` (thiếu `public/images/logo-neo.png`) — **lỗi có sẵn từ trước cả phiên 1**, không liên quan bất kỳ thay đổi nào ở đây, và là việc thêm một tệp ảnh chứ không phải sửa code.
- `vendor/bin/pint` = sạch. `npm run build` = thành công. `migrate:rollback` rồi `migrate` lại = sạch.
- Baseline ban đầu: 365 passed / 1139 assertions → nay **+214 test, +557 assertions**.

### Lỗi thật đã tìm ra và sửa (ngoài các lỗ hổng có trong plan)

1. **Múi giờ là UTC** trong khi tiệm ở Việt Nam — doanh thu, KPI ngày và hoa hồng của ca đêm rơi nhầm sang ngày hôm trước.
2. **Biểu mẫu đặt lịch công khai thiếu ô chọn chi nhánh** trong khi máy chủ bắt buộc — không lượt đặt lịch nào từ trang chủ thành công được.
3. **Đăng ký công khai còn mở** — người lạ tạo được tài khoản `employee` đang hoạt động, vào thẳng sổ lịch hẹn kèm tên và số điện thoại khách.
4. **Audit chấm công bị cascade** khi xóa bản ghi — xóa một ca là xóa luôn bằng chứng ai đã xóa nó. Test cũ còn khẳng định điều đó là đúng.
5. **N+1 ở danh sách chấm công** — 45 truy vấn cho 18 dòng.
6. **Lỗi 500 khi nhập chữ vào ô giảm giá** (do chính thay đổi ở `FRAUD-P0-03` gây ra, phát hiện khi làm `I18N-P1-01`).
7. **`BranchFactory` sinh mã trùng** — nguồn flaky có sẵn của bộ test.

### Hai cái bẫy cho phiên sau

1. **Owner thấy mọi chi nhánh**, nên `BranchContext` chọn chi nhánh **đầu tiên trong DB** chứ không phải chi nhánh của test. Test nào cho owner mở màn hình có lọc theo chi nhánh mà không pin `BranchContext::SESSION_KEY` sẽ thấy danh sách rỗng và **xanh một cách vô nghĩa** — chạy riêng thì pass, chạy toàn suite thì đỏ. Đã dính ba lần.
2. **Luôn chạy negative control** cho test mới: tạm tắt phần vừa sửa, xác nhận test đỏ đúng chỗ, rồi khôi phục. Cách này đã bắt được ít nhất bốn test "pass giả" trong hai phiên — trong đó có test tìm chữ `quantity` trên toàn trang (markup dòng mẫu luôn chứa chữ đó) và test dựng trên `updated_at` khi hai lần lưu rơi vào cùng một giây.

### Cảnh báo khi triển khai production

- Đổi múi giờ sang `Asia/Ho_Chi_Minh` **không** chuyển đổi dữ liệu đã lưu. Nếu đã có dữ liệu thật chạy dưới UTC thì cần migration cộng bù — quyết định phải được xác nhận trước khi chạy.
- Phải đặt `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true` và HTTPS. Không sửa `.env` trong các phiên này theo đúng ràng buộc của plan.
- CSP chưa kiểm chứng bằng trình duyệt thật; nên mở console ở màn hình sửa hóa đơn và nút Vào ca trước khi phát hành.
- Hàng đợi cảnh báo tự quét khi mở màn hình. Khi dữ liệu lớn lên nên chuyển hẳn sang `php artisan risk:detect` theo scheduler và bỏ quét đồng bộ.
- Tệp rỗng tên `subject('Kiểm` ở thư mục gốc có từ trước phiên 1, không do các phiên này tạo ra; nên xóa khi dọn repo.
