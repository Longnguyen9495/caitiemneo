# Kế hoạch hoàn thiện Trợ lý AI quản lý Cái Tiệm Neo

## 1. Mục tiêu

Hoàn thiện trợ lý AI quản lý native Laravel theo hướng tương tự PageSeed, gồm:

- Hỏi đáp bằng tiếng Việt dựa trên dữ liệu vận hành thực tế.
- Lịch sử hội thoại được lưu theo từng người dùng.
- Báo cáo có cấu trúc dưới dạng văn bản, bảng và biểu đồ.
- Đề xuất thao tác nghiệp vụ nhưng tuyệt đối không tự ghi dữ liệu.
- Chỉ thực hiện thao tác sau khi người dùng xác nhận và xác nhận lại mật khẩu.
- Kiểm tra lại quyền, chi nhánh và payload tại thời điểm thực thi.
- Chống thực thi lặp.
- Tái sử dụng domain action hiện có để giữ nguyên audit trail.
- Giao diện responsive, an toàn trước nội dung do provider trả về.

## 2. Trạng thái đã triển khai

### 2.1. Cấu hình và provider

Đã có:

- `config/ai.php`.
- Biến môi trường mẫu trong `.env.example`.
- Interface `App\Services\Ai\Contracts\AiProvider`.
- Provider OpenAI-compatible `OpenAiCompatibleProvider`.
- DTO `AiProviderResult`.
- Kiểm tra cấu hình trước khi gọi provider.
- Timeout, connect timeout và retry có giới hạn.
- Yêu cầu provider trả JSON.
- Chuẩn hóa và whitelist block `text`, `table`, `chart`.
- Whitelist action `create_cash_entry`, `adjust_stock`.
- Giới hạn số block, dòng bảng, nhãn và dataset.
- Lưu telemetry model, token, latency và provider reference.

### 2.2. Context và prompt

Đã có:

- `AiBusinessContext` tạo context theo `BranchContext`.
- Sử dụng số liệu chính thức từ `ReportService`.
- Context gồm tổng quan kỳ hiện tại, lịch hẹn, dịch vụ, nhân viên và tồn kho thấp.
- Không gửi tên, email, số điện thoại khách hàng.
- Manager không nhận các trường lợi nhuận/giá vốn/lương nhạy cảm.
- Prompt tiếng Việt, yêu cầu chỉ dùng dữ liệu context và không bịa số liệu.
- Prompt mô tả JSON schema và payload hợp lệ cho từng action.
- Lịch sử hội thoại có giới hạn.

### 2.3. Cơ sở dữ liệu và model

Đã có migration tạo:

- `ai_conversations`.
- `ai_messages`.
- `ai_action_proposals`.

Đã có model và quan hệ:

- `AiConversation`.
- `AiMessage`.
- `AiActionProposal`.
- Enum `AiActionStatus`.

Proposal có:

- UUID idempotency key duy nhất.
- Request fingerprint SHA-256.
- Trạng thái pending/executing/executed/rejected/failed.
- Người quyết định và thời gian quyết định.
- Polymorphic result.
- Failure message.

### 2.4. Hội thoại

Đã có:

- Tạo hội thoại theo user và snapshot phạm vi chi nhánh.
- Gọi provider ngoài transaction.
- Chỉ lưu user message và assistant message sau khi provider trả thành công.
- Lưu block, telemetry và action proposal trong transaction.
- Tự đặt tiêu đề từ câu hỏi đầu tiên.
- Chỉ cho phép người dùng đọc hội thoại của chính họ.

### 2.5. Xác nhận thao tác

Đã có:

- Endpoint confirm/reject.
- Middleware `password.confirm`.
- Rate limit.
- `lockForUpdate()` khi xử lý proposal.
- Chỉ proposal pending mới được thực thi.
- Kiểm tra trực tiếp proposal thuộc hội thoại của actor.
- Kiểm tra gate AI, policy domain và phạm vi chi nhánh tại thời điểm thực thi.
- Validate lại payload.
- Dùng `RecordCashTransactionAction` và `RecordInventoryMovementAction`.
- Domain action hiện có tiếp tục ghi audit event.
- Gửi confirm lặp không tạo thêm bản ghi.
- Proposal bị reject không thể chạy lại.

### 2.6. Giao diện

Đã có:

- Navigation “Trợ lý AI” dành cho owner/manager.
- Trang hội thoại hai cột.
- Lịch sử hội thoại.
- Message bubble user/assistant.
- Render text, table và chart an toàn bằng Blade escaping.
- Proposal card và nút xác nhận/từ chối.
- Trạng thái AI chưa cấu hình.
- SCSS responsive riêng.
- JavaScript scroll tới tin mới nhất, autosize textarea và khóa submit lặp.
- Biểu đồ doughnut bằng SVG và bar bằng progress, không phụ thuộc thư viện ngoài.

### 2.7. Kiểm tra đã chạy

Đã thành công:

- PHP lint cho toàn bộ PHP thuộc tính năng AI.
- `php artisan route:list --name=admin.ai`: có đủ 4 route.
- `php artisan view:cache`.
- `npm run build`.
- `php artisan migrate --pretend` cho migration AI.
- Test AI chạy riêng: 8 test, 32 assertion đều qua trước khi chạy full suite.

Full suite gần nhất:

- 734 test qua.
- 9 test lỗi.
- Một lỗi AI là nhiễu rate limiter dùng chung IP khi chạy cả suite; test AI chạy riêng đã qua.
- Tám lỗi còn lại nằm ở các test cũ về audit/invoice assignment, scoped foreign ID, branch context, session profile; cần xác minh baseline, không được tự quy lỗi cho tính năng AI.

## 3. Cảnh báo trạng thái workspace

Workspace đang có nhiều thay đổi từ các tác vụ trước như booking email, password reset, audit dictionary và các thay đổi chưa commit khác.

`vendor/bin/pint` gần nhất đã tự sửa line ending/style ở một số file ngoài phạm vi AI. AI tiếp quản phải:

1. Chạy `git diff --name-only` và `git diff` trước khi sửa tiếp.
2. Không dùng `git reset --hard`, `git checkout .`, `git clean -fd` hoặc thao tác phá hủy tương tự.
3. Không hoàn nguyên file chỉ vì không thuộc AI nếu chưa xác định thay đổi đó do Pint vừa tạo hay là công việc người dùng đang giữ.
4. Nếu cần hoàn nguyên format ngoài phạm vi, chỉ hoàn nguyên từng file/hunk đã xác minh chắc chắn.
5. Không thay đổi `.env` hoặc đưa API key vào Git.
6. Không thao tác production PageSeed.

## 4. Phần việc bắt buộc còn lại

### Giai đoạn A — Chụp và bảo vệ trạng thái hiện tại

1. Chạy:
   - `git status --short`
   - `git diff --stat`
   - `git diff -- app/Services/Ai app/Http/Controllers/Admin/AiActionController.php app/Http/Controllers/Admin/AiAssistantController.php config/ai.php routes/web.php resources/views/admin/ai resources/scss/_ai.scss resources/js/admin.js tests/Feature/Admin/AiAssistantTest.php`
2. Phân loại thay đổi:
   - Thuộc AI.
   - Thuộc booking/email đã hoàn tất.
   - Thuộc audit dictionary hoặc tác vụ khác.
   - Chỉ là line ending/style do Pint.
3. Không sửa hay hoàn nguyên ngoài phạm vi nếu chưa chắc chắn.

### Giai đoạn B — Ổn định test AI trong full suite

1. Trong `AiAssistantTest`, vô hiệu hóa riêng middleware throttle hoặc reset rate limiter trong `setUp()`.
2. Không vô hiệu hóa `password.confirm`, auth, verified, role hoặc branch middleware trong các test bảo mật.
3. Chạy test AI riêng ít nhất hai lần liên tiếp.
4. Chạy test AI sau một nhóm test admin để xác nhận không còn phụ thuộc thứ tự.
5. Nếu test vẫn nhiễu, dùng IP riêng cho từng request hoặc `RateLimiter::clear()` đúng key.

Tiêu chí hoàn tất:

- Test AI chạy riêng và chạy trong full suite không phụ thuộc thứ tự.
- Test “execute exactly once” luôn tạo đúng một cash transaction.

### Giai đoạn C — Hoàn thiện semantics lỗi action

Hiện trạng: nếu domain action throw exception thì transaction rollback, vì vậy trạng thái `failed` và `failure_message` không được lưu.

Phương án ưu tiên:

1. Giữ việc thực thi domain action trong transaction nguyên tử.
2. Catch exception bên ngoài transaction thực thi.
3. Mở transaction thứ hai để lock proposal.
4. Chỉ đánh dấu `failed` nếu proposal vẫn ở trạng thái pending/executing phù hợp và chưa có result.
5. Lưu thông báo an toàn, không lưu stack trace hay secret.
6. Re-throw exception để controller trả thông báo lỗi chuẩn.
7. Cho phép retry failed hay không phải được quyết định rõ:
   - Khuyến nghị: failed là final để tránh thao tác mơ hồ; người dùng yêu cầu AI tạo proposal mới.
8. Điều chỉnh enum/UI/test theo quyết định.

Cần xử lý đặc biệt tình huống không chắc chắn:

- Nếu domain write đã commit nhưng cập nhật proposal thất bại, idempotency ở proposal không đủ để đảm bảo exactly-once.
- Vì domain action hiện chạy cùng outer transaction nên ưu tiên xác minh nested transaction của Laravel vẫn rollback/commit cùng connection.
- Viết test ép lỗi sau domain action nếu có thể; bảo đảm không tồn tại business record khi proposal không được đánh dấu executed.

### Giai đoạn D — Siết action validation và idempotency

1. Xác minh `request_fingerprint` có mục đích rõ ràng:
   - Nếu chỉ phục vụ audit thì giữ index thường.
   - Nếu muốn chặn proposal trùng trong cùng hội thoại/user, thêm unique constraint phù hợp hoặc logic dedupe có chủ đích.
2. Không dùng fingerprint toàn cục để vô tình chặn hai khoản chi hợp lệ giống nhau ở hai thời điểm.
3. Xác minh branch ID trong cột proposal trùng branch ID trong payload.
4. Xác minh product thuộc catalog/stock của đúng branch trước stock adjustment.
5. Kiểm tra product active tại thời điểm confirm.
6. Kiểm tra amount, quantity, occurred_at, category và payment method bằng cùng quy tắc form nghiệp vụ hiện tại.
7. Xác minh manager chỉ được thực hiện action mà policy hiện tại cho phép.
8. Bảo đảm route model binding không làm lộ proposal: outsider phải nhận 404.

### Giai đoạn E — Hoàn thiện biểu đồ và báo cáo

1. Quyết định mức parity với PageSeed:
   - Bắt buộc hiện tại: text/table/bar/doughnut.
   - Nên hoàn thiện: line chart thực sự, không render giống bar.
   - Tùy chọn: export block CSV.
2. Nếu thêm line chart:
   - Dùng SVG nội bộ, không thêm dependency nặng nếu không cần.
   - Escape title/label.
   - Xử lý dataset rỗng, âm, bằng nhau và quá lớn.
   - Có fallback accessible dạng bảng/danh sách hoặc aria-label đầy đủ.
3. Nếu thêm export:
   - Provider chỉ đề xuất dữ liệu có cấu trúc, server tự tạo file.
   - Chống CSV injection cho ô bắt đầu bằng `=`, `+`, `-`, `@`.
   - Không cho provider tự chỉ định filesystem path hoặc URL tùy ý.
   - Áp quyền/branch scope lại khi export.
4. Hiển thị đơn vị tiền/số lượng thân thiện thay vì số thô nếu metadata cho phép.

### Giai đoạn F — Kiểm thử provider parser

Viết unit test cho `OpenAiCompatibleProvider` bằng `Http::fake()`:

1. JSON hợp lệ có text/table/chart/action.
2. JSON nằm trong markdown fence.
3. Content rỗng.
4. JSON malformed.
5. Thiếu `content`.
6. `blocks` không phải array.
7. Block type lạ bị loại.
8. Text block bị giới hạn/chuẩn hóa.
9. Table quá 12 cột bị cắt.
10. Table quá 100 dòng bị cắt.
11. Cell quá dài bị cắt.
12. Chart type lạ bị loại.
13. Chart quá 50 label bị cắt.
14. Chart quá 8 dataset bị cắt.
15. Giá trị chart không phải số thành 0.
16. Dataset ngắn hơn label.
17. Action type lạ bị loại.
18. Quá 3 action bị cắt.
19. Action thiếu summary/payload bị loại.
20. API 401/403 không retry vô hạn.
21. API 429 có hành vi retry đúng chủ đích.
22. API 500 được retry giới hạn.
23. Timeout/connection exception.
24. `AI_ENABLED=false` không gửi HTTP request.
25. Thiếu API key/base URL/model không gửi HTTP request.
26. Token/latency/reference được map đúng.
27. Xác minh Laravel 13 HTTP client với `retry(..., throw: false)->throw()` hoạt động như kỳ vọng.

### Giai đoạn G — Kiểm thử context và privacy

Viết unit/feature test bắt prompt/provider input:

1. Owner ở all-branch nhận đúng danh sách branch được phép.
2. Manager chỉ nhận branch đang được phân công.
3. Manager không nhận `inventory_cost`, `payroll_cost`, `gross_margin`.
4. Owner có quyền nhận các trường tài chính nhạy cảm đã thiết kế.
5. Employee không gọi được AI.
6. Customer name không xuất hiện.
7. Customer phone không xuất hiện.
8. Customer email không xuất hiện.
9. Số liệu appointment status đúng kỳ.
10. Top service đúng kỳ và branch.
11. Top employee đúng kỳ và branch.
12. Low-stock chỉ thuộc branch scope.
13. Product ngoài scope không xuất hiện.
14. Context không bị mở rộng bởi query/session branch giả mạo.
15. Kỳ mặc định đúng timezone ứng dụng.
16. Không có dữ liệu thì prompt vẫn hợp lệ và AI phải nói không đủ dữ liệu.
17. Lịch sử chỉ lấy đúng conversation và đúng limit.
18. Prompt injection từ user không làm system prompt lộ secret hay đổi schema.

### Giai đoạn H — Kiểm thử hội thoại

1. Guest bị chuyển login.
2. User chưa verify bị chặn theo middleware hiện hành.
3. Employee nhận 403.
4. Owner truy cập được.
5. Manager truy cập được.
6. AI disabled vẫn mở trang nhưng composer bị khóa và có cảnh báo.
7. Hội thoại mới lưu user ID, branch ID và scope snapshot.
8. Câu đầu tạo title tối đa 80 ký tự.
9. Câu sau không ghi đè title.
10. User không đọc được hội thoại người khác.
11. User không post message vào hội thoại người khác.
12. ID hội thoại không tồn tại trả 404.
13. Message rỗng bị validate.
14. Message quá giới hạn bị validate.
15. Provider success lưu đúng 2 message.
16. Provider failure không lưu partial message.
17. Provider malformed JSON không lưu partial message.
18. Blocks và telemetry lưu đúng.
19. Proposal lưu đúng actor/branch/fingerprint/UUID.
20. Danh sách hội thoại giới hạn 30 và sắp xếp mới nhất.
21. History gửi provider đúng thứ tự và đúng giới hạn.
22. Nội dung HTML/script của provider được escape trong view.
23. Table/chart malformed không làm vỡ view.
24. Rate limit message endpoint hoạt động.

### Giai đoạn I — Kiểm thử confirm/reject action

#### Chung

1. Không confirm password thì redirect tới trang xác nhận mật khẩu.
2. Employee nhận 403.
3. Proposal của user khác trả 404.
4. Proposal branch ngoài scope bị từ chối.
5. Proposal pending được xử lý.
6. Proposal executed gửi lại không chạy lần hai.
7. Proposal rejected không chạy được.
8. Proposal failed xử lý đúng policy retry đã chọn.
9. Hai request confirm cạnh tranh chỉ tạo một result.
10. Reject gửi lặp không thay đổi quyết định đầu tiên.
11. Confirm và reject cạnh tranh chỉ một quyết định thắng.
12. `decided_by`, `decided_at`, result morph lưu đúng.
13. Audit event có actor, branch, before/after, correlation ID.
14. Validation lỗi không tạo business record.
15. Exception domain không để proposal ở trạng thái `executing` vĩnh viễn.
16. Rate limit endpoint confirm/reject hoạt động.

#### Cash action

1. Expense hợp lệ tạo đúng một transaction.
2. Income hợp lệ tạo đúng một transaction.
3. Amount âm/0 bị chặn.
4. Amount vượt max bị chặn.
5. Category system-generated bị chặn.
6. Category manual hợp lệ được chấp nhận.
7. Payment method hợp lệ/null hoạt động.
8. Payment method lạ bị chặn.
9. Backdate quá giới hạn bị chặn.
10. Future date bị chặn.
11. Branch payload khác proposal branch bị chặn.
12. Policy create cash được kiểm tra lại.
13. Transaction được gắn đúng creator/branch.
14. Audit cash được tạo đúng một lần.

#### Stock action

1. Delta tăng hợp lệ.
2. Delta giảm hợp lệ khi đủ tồn.
3. Delta giảm làm âm tồn bị chặn.
4. Absolute adjustment hợp lệ.
5. Quantity ngoài giới hạn bị chặn.
6. Product không tồn tại bị chặn.
7. Product inactive bị chặn.
8. Product không thuộc branch/catalog bị chặn.
9. Type khác adjustment bị chặn.
10. Note quá ngắn bị chặn.
11. Date ngoài giới hạn bị chặn.
12. Movement tạo đúng một lần.
13. Current stock cập nhật đúng.
14. Audit before/after stock chính xác.

### Giai đoạn J — UI, accessibility và frontend

1. Trang desktop hai cột không overflow.
2. Trang mobile chuyển một cột.
3. History có scroll hợp lý.
4. Message list tự scroll cuối.
5. Textarea autosize nhưng không vượt max-height.
6. Submit khóa ngay sau lần nhấn đầu.
7. Nút giữ disabled khi AI tắt.
8. Keyboard focus rõ ràng.
9. Bảng có header scope và responsive wrapper.
10. Chart có accessible name.
11. Màu chart có độ tương phản đủ và không chỉ dựa vào màu để hiểu dữ liệu.
12. Proposal status hiển thị rõ.
13. Nút confirm/reject đủ vùng chạm mobile.
14. Long text/label không phá layout.
15. XSS payload hiển thị dạng text.
16. Blade compile thành công.
17. Vite build thành công.
18. Không có console error trên trang AI.

### Giai đoạn K — Xử lý full suite hiện có

Chạy riêng từng test đang lỗi để xác định baseline:

- `tests/Feature/Admin/AuditTrailTest.php`
- `tests/Feature/Admin/RiskDetectionTest.php`
- `tests/Feature/Admin/ScopedForeignIdTest.php`
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/SessionManagementTest.php`

Quy tắc:

1. Chạy từng file riêng.
2. Nếu chạy riêng qua nhưng full suite lỗi, điều tra state leak/order dependency/rate limiter/static factory state.
3. Nếu chạy riêng vẫn lỗi, kiểm tra `git diff` để xác định lỗi có trước AI hay do thay đổi đang tồn tại.
4. Không sửa ngoài phạm vi chỉ để làm xanh suite nếu nguyên nhân không liên quan AI.
5. Ghi rõ lỗi baseline trong báo cáo cuối nếu không được phép sửa.

### Giai đoạn L — Quality gate cuối

Chạy theo thứ tự:

1. `php -l` toàn bộ file PHP mới/sửa thuộc AI.
2. Pint chỉ trên file thuộc tính năng, không chạy toàn repository nếu chưa kiểm soát diff.
3. Unit tests provider/context.
4. Feature tests AI.
5. Các test booking/email liên quan đã có.
6. Các test admin authorization/navigation.
7. Full suite.
8. `php artisan route:list --name=admin.ai`.
9. `php artisan view:clear && php artisan view:cache`.
10. `php artisan migrate --pretend`.
11. Nếu được người dùng cho phép thay đổi DB local: `php artisan migrate`.
12. `npm run build`.
13. `git diff --check`.
14. Rà soát `git diff` cuối cùng để loại secret và thay đổi ngoài ý muốn.

## 5. Kịch bản kiểm thử thủ công end-to-end

### Kịch bản 1 — AI chưa cấu hình

1. Đặt `AI_ENABLED=false`.
2. Đăng nhập owner.
3. Mở Trợ lý AI.
4. Xác nhận có cảnh báo cấu hình.
5. Composer và nút gửi bị khóa.
6. Không có request provider.

### Kịch bản 2 — Hỏi đáp và báo cáo

1. Bật provider bằng credential thật trong `.env` local, không commit.
2. Chọn một branch có dữ liệu.
3. Hỏi “Tóm tắt tình hình tháng này”.
4. Xác nhận câu trả lời tiếng Việt và số liệu khớp trang report.
5. Hỏi “Lập bảng doanh thu top dịch vụ”.
6. Xác nhận bảng render đúng trên desktop/mobile.
7. Hỏi “Vẽ biểu đồ doanh thu top dịch vụ”.
8. Xác nhận chart render, label và số liệu đúng.
9. Refresh trang, lịch sử vẫn còn.

### Kịch bản 3 — Privacy và branch scope

1. Đăng nhập manager branch A.
2. Hỏi số liệu branch B.
3. AI phải từ chối hoặc nói không có dữ liệu trong phạm vi.
4. Kiểm tra request provider không chứa PII khách hàng.
5. Chuyển branch hợp lệ và hỏi lại.
6. Xác nhận context đổi đúng branch.

### Kịch bản 4 — Đề xuất khoản chi

1. Hỏi “Đề xuất ghi chi marketing 500.000đ hôm nay bằng chuyển khoản”.
2. AI chỉ tạo proposal, chưa có cash transaction.
3. Nhấn xác nhận.
4. Hệ thống yêu cầu xác nhận lại mật khẩu nếu phiên chưa step-up.
5. Nhập mật khẩu đúng.
6. Confirm proposal.
7. Có đúng một cash transaction và một audit event.
8. Nhấn confirm lại hoặc refresh-submit.
9. Không tạo bản ghi thứ hai.

### Kịch bản 5 — Từ chối proposal

1. Tạo proposal mới.
2. Nhấn từ chối.
3. Trạng thái chuyển rejected.
4. Confirm sau đó không tạo business record.

### Kịch bản 6 — Điều chỉnh tồn kho

1. Chọn product active thuộc branch.
2. Yêu cầu AI đề xuất điều chỉnh với lý do rõ ràng.
3. Chưa confirm thì stock không đổi.
4. Confirm thì tạo đúng một movement và audit event.
5. Thử giảm quá tồn, hệ thống từ chối và stock giữ nguyên.

### Kịch bản 7 — Quyền và tấn công

1. Employee mở URL AI trực tiếp: 403.
2. User A sửa conversation ID của user B: 404.
3. User A sửa proposal ID của user B: 404.
4. Thay branch ID trong payload database fixture sang branch ngoài scope: confirm bị chặn.
5. Provider trả `<script>alert(1)</script>`: trang chỉ hiện text, script không chạy.
6. Gửi liên tục vượt rate limit: nhận 429 phù hợp.

### Kịch bản 8 — Provider lỗi

1. Giả lập timeout/500/malformed JSON.
2. UI nhận thông báo tiếng Việt an toàn.
3. Không có partial message.
4. Không có proposal dở dang.
5. Không lộ stack trace/API key ở production.

## 6. Tiêu chí chấp nhận cuối cùng

Tính năng chỉ được coi là hoàn tất khi:

- Owner và manager dùng được; employee bị chặn.
- Hội thoại riêng tư theo user và branch scope.
- Provider không nhận PII khách hàng.
- Text/table/chart render an toàn và responsive.
- Action không bao giờ tự chạy trước confirm.
- Confirm yêu cầu password step-up.
- Quyền, branch và payload được kiểm tra lại tại execution time.
- Duplicate confirm không tạo duplicate business record.
- Reject là final.
- Failure state không bị treo ở executing và có semantics rõ ràng.
- Audit event được tạo bởi domain action hiện có.
- Test AI ổn định khi chạy riêng và trong full suite.
- PHP lint, Blade cache, routes, migration pretend và frontend build đều qua.
- Không commit secret.
- Không có thay đổi ngoài phạm vi không được giải thích.

## 7. Prompt bàn giao cho AI khác

Sao chép nguyên prompt dưới đây:

---

Bạn đang tiếp quản repository Laravel tại `c:/xampp/htdocs/caitiemneo` để hoàn thiện tính năng Trợ lý AI quản lý native Laravel, tương tự PageSeed. Hãy đọc toàn bộ file `plans/ai-management-assistant-completion-plan.md` trước khi làm bất cứ thay đổi nào và coi đó là specification bắt buộc.

Yêu cầu làm việc:

1. Trước tiên chạy `php -v`, `composer -V`, đọc `AGENTS.md`, `git status --short`, `git diff --stat` và các diff liên quan AI.
2. Không dùng `git reset --hard`, `git checkout .`, `git clean -fd`; workspace có nhiều thay đổi chưa commit từ các tác vụ khác.
3. Không sửa hoặc hoàn nguyên thay đổi booking/email/password reset/audit hay file ngoài AI nếu chưa chứng minh đó là thay đổi thừa do formatter.
4. Không sửa `.env`, không ghi API key vào source, không thao tác production PageSeed.
5. Tiếp tục từ code hiện có, không viết lại kiến trúc từ đầu.
6. Ưu tiên xử lý theo các giai đoạn A–L trong plan.
7. Sửa test AI bị nhiễu rate limiter khi chạy full suite nhưng không được vô hiệu hóa auth, authorization, branch scope hoặc `password.confirm` đang được kiểm thử.
8. Hoàn thiện semantics proposal failed để exception không làm proposal treo ở executing; bảo toàn tính nguyên tử và exactly-once.
9. Siết branch/product validation cho stock action và sự nhất quán giữa proposal branch với payload branch.
10. Viết đầy đủ unit test provider parser, context/privacy, hội thoại, confirm/reject, cash và stock theo ma trận trong plan.
11. Nếu làm line chart hoặc export block, phải an toàn, accessible, branch-scoped và không thêm dependency nặng không cần thiết.
12. Chạy formatter chỉ trên file thuộc tính năng hoặc kiểm tra diff ngay sau formatter.
13. Chạy quality gate đúng thứ tự trong plan; phân loại rõ lỗi mới và lỗi baseline.
14. Không tuyên bố hoàn tất nếu test AI chỉ chạy riêng mà thất bại trong full suite.
15. Báo cáo cuối phải liệt kê file đã sửa, migration, biến môi trường cần cấu hình, test/build đã chạy, kết quả chính xác và bất kỳ lỗi baseline nào còn lại.

Trạng thái quan trọng hiện tại:

- Route, Blade cache, migration pretend và Vite build đã qua.
- Test AI chạy riêng từng qua 8 test/32 assertion.
- Full suite gần nhất có 734 test qua, 9 lỗi. Một lỗi AI nhiều khả năng do shared rate limiter; tám lỗi khác thuộc test cũ và phải điều tra baseline trước khi sửa.
- `vendor/bin/pint` gần nhất đã chạm một số file ngoài AI do line ending; phải kiểm soát diff cẩn thận.

Hãy bắt đầu bằng việc đọc plan và kiểm tra trạng thái Git, sau đó triển khai tuần tự, mỗi thay đổi phải có test chứng minh.

---
