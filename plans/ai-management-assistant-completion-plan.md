# Kế hoạch hoàn thiện Trợ lý AI quản lý Cái Tiệm Neo

## 1. Mục tiêu và phạm vi

Hoàn thiện trợ lý AI quản lý native Laravel theo hướng tương tự PageSeed:

- Hỏi đáp tiếng Việt dựa trên dữ liệu vận hành thực tế.
- Lưu lịch sử hội thoại riêng theo người dùng và phạm vi chi nhánh.
- Trả báo cáo dạng văn bản, bảng, biểu đồ cột, đường và doughnut.
- Chỉ đề xuất thao tác nghiệp vụ; không tự ghi dữ liệu.
- Chỉ thực thi sau khi người dùng xác nhận và hoàn tất password step-up.
- Kiểm tra lại quyền, chi nhánh, catalog và payload tại thời điểm thực thi.
- Chống thực thi lặp, lưu kết quả và audit qua domain action hiện có.
- Không gửi PII khách hàng cho provider.
- Giao diện responsive, accessible và an toàn với nội dung provider.

Export CSV không phải phạm vi bắt buộc hiện tại. Chỉ bổ sung nếu người dùng yêu cầu riêng.

## 2. Kết quả audit mới nhất — 17/09/2026

### 2.1. Trạng thái repository và môi trường

- `git status --short` và `git diff --stat` không có output trước audit: working tree sạch.
- PHP: 8.3.33.
- Composer: 2.10.2.
- Không phát hiện secret được đưa vào source trong phạm vi audit.
- Không thay đổi `.env` và không thao tác production PageSeed.

### 2.2. Test đã chạy

Nhóm AI:

```text
php artisan test \
  tests\Unit\Services\Ai\OpenAiCompatibleProviderTest.php \
  tests\Unit\Services\Ai\AiBusinessContextTest.php \
  tests\Feature\Admin\AiAssistantTest.php \
  tests\Feature\Admin\AiConversationTest.php \
  tests\Feature\Admin\AiActionTest.php
```

Kết quả:

- 60 test qua.
- 221 assertion qua.
- Không có lỗi AI.

Full suite:

```text
php artisan test
```

Kết quả:

- 787 test qua.
- 8 test lỗi.
- 2.663 assertion.
- Không có test AI lỗi trong full suite.

Tám lỗi còn lại đều ngoài phạm vi AI:

1. `Tests\Feature\Admin\AuditTrailTest` — changing the commission employee is recorded.
2. `Tests\Feature\Admin\RiskDetectionTest` — a reviewer can quickly assign missing invoice commission.
3. `Tests\Feature\Admin\ScopedForeignIdTest` — invoice line rejects an employee from another branch.
4. `Tests\Feature\Admin\ScopedForeignIdTest` — invoice line rejects an employee whose posting has expired.
5. `Tests\Feature\Admin\ScopedForeignIdTest` — invoice line rejects a deactivated employee.
6. `Tests\Feature\Admin\ScopedForeignIdTest` — invoice line accepts an employee posted to the branch.
7. `Tests\Feature\Auth\AuthenticationTest` — the first dashboard load resolves the users branch context.
8. `Tests\Feature\Auth\SessionManagementTest` — the profile page lists the accounts sessions.

Kết luận: lỗi AI do shared rate limiter ở lần chạy cũ đã được xử lý. Không sửa tám lỗi ngoài AI chỉ để làm xanh suite nếu chưa chứng minh có liên quan đến thay đổi AI.

## 3. Những phần đã triển khai và đã xác minh

### 3.1. Cấu hình và provider

Đã có:

- `config/ai.php` và biến môi trường mẫu an toàn trong `.env.example`.
- Interface `App\Services\Ai\Contracts\AiProvider`.
- DTO `AiProviderResult`.
- `OpenAiCompatibleProvider` tương thích OpenAI/Qwen DashScope.
- Guard khi AI tắt hoặc thiếu API key/base URL/model.
- Connect timeout, request timeout và retry giới hạn.
- Payload yêu cầu `response_format=json_object`.
- Parse JSON thuần và JSON trong Markdown fence.
- Whitelist block `text`, `table`, `chart`.
- Whitelist chart `bar`, `line`, `doughnut`.
- Giới hạn 12 block, 12 cột, 100 hàng, 50 label, 8 dataset, 500 ký tự/cell.
- Giá trị chart không phải số được chuẩn hóa về 0.
- Whitelist action `create_cash_entry`, `adjust_stock`, tối đa 3 action.
- Lưu model, token, latency và provider reference.

### 3.2. Context, prompt và privacy

Đã có:

- Context theo `BranchContext` và số liệu chính thức từ `ReportService`.
- Summary, appointment status, top service, top employee và low stock.
- Owner/manager nhận đúng scope chi nhánh theo quyền hiện hành.
- Manager không nhận `inventory_cost`, `payroll_cost`, `gross_margin`.
- Không đưa tên, điện thoại hoặc email khách hàng vào business context.
- Prompt tiếng Việt yêu cầu chỉ dùng dữ liệu context, không bịa số liệu và không tiết lộ prompt/config/PII.
- Prompt mô tả JSON schema và payload action được phép.
- Lịch sử hội thoại được giới hạn và sắp lại theo thứ tự cũ đến mới trong code.

### 3.3. Persistence và hội thoại

Đã có:

- Migration cho `ai_conversations`, `ai_messages`, `ai_action_proposals`.
- Model, quan hệ và enum `AiActionStatus`.
- Snapshot branch scope trên conversation.
- Provider được gọi ngoài transaction ghi dữ liệu.
- Provider lỗi không lưu partial user/assistant message.
- User message, assistant message, telemetry và proposal được lưu trong transaction.
- Tiêu đề lấy từ câu đầu và giới hạn 80 ký tự.
- Conversation list giới hạn 30, sắp theo hoạt động mới nhất.
- Chỉ chủ conversation được đọc hoặc gửi tiếp.
- UUID idempotency key và SHA-256 request fingerprint.

### 3.4. Action execution

Đã có và đã đọc xác nhận trong implementation:

- Confirm/reject endpoint có auth, gate, password confirmation và throttle.
- `lockForUpdate()` trên proposal.
- Pending là trạng thái duy nhất được xử lý; confirm/reject lặp là idempotent.
- Kiểm tra proposal thuộc conversation của actor; outsider nhận 404.
- Kiểm tra gate AI, domain policy và branch scope tại thời điểm confirm.
- Proposal branch bắt buộc trùng payload branch.
- Cash payload kiểm tra type, manual category, amount, payment method và thời gian.
- Stock payload kiểm tra active product và active `BranchProduct` đúng chi nhánh.
- Chỉ cho stock movement type `adjustment`; kiểm tra mode, quantity, note và thời gian.
- Tái sử dụng `RecordCashTransactionAction` và `RecordInventoryMovementAction`.
- Domain action hiện có tiếp tục tạo audit event.
- Exception domain rollback business transaction; transaction thứ hai đánh dấu proposal `failed` với thông báo an toàn.
- Failed là final và không retry proposal cũ.
- Test hiện tại xác nhận failed không để lại business record.

### 3.5. UI

Đã có:

- Navigation AI cho leadership.
- Trang chat hai cột, lịch sử và composer responsive.
- Render text/table bằng Blade escaping.
- Bar chart bằng `<progress>`.
- Doughnut chart bằng SVG.
- Line chart thực sự bằng SVG, có legend và bảng visually-hidden cho screen reader.
- Proposal card và trạng thái confirm/reject/failed/executed.
- Trạng thái AI chưa cấu hình.
- Auto-scroll, textarea autosize và submit guard.

## 4. Khoảng trống còn lại sau audit

### P0 — Bắt buộc trước khi coi tính năng hoàn thiện

#### 4.1. Mở rộng unit test provider

File đích: `tests/Unit/Services/Ai/OpenAiCompatibleProviderTest.php`.

Các case đã có: JSON hợp lệ, Markdown fence, content rỗng, malformed JSON, thiếu content, blocks sai kiểu, block lạ, trim text, table rỗng, chart type lạ, telemetry cơ bản.

Cần bổ sung:

1. Table quá 12 cột bị cắt.
2. Table quá 100 dòng bị cắt.
3. Header/cell quá 500 ký tự bị cắt.
4. Chart quá 50 label bị cắt.
5. Chart quá 8 dataset bị cắt.
6. Giá trị chart không phải số thành 0.
7. Dataset ngắn hơn labels không gây lỗi render/parser.
8. Action type lạ bị loại.
9. Action thiếu summary hoặc payload bị loại.
10. Quá 3 action bị cắt.
11. Summary quá 500 ký tự bị cắt.
12. `AI_ENABLED=false` không phát HTTP request.
13. Thiếu lần lượt API key, base URL, model không phát HTTP request.
14. Assert URL, bearer token và request payload gửi provider.
15. HTTP 401/403 không retry ngoài chủ đích và throw an toàn.
16. Quyết định rõ 429 có retry hay không, rồi khóa hành vi bằng test. Code hiện tại chỉ retry connection error hoặc server error; 429 không retry.
17. HTTP 500 retry đúng số lần giới hạn rồi throw.
18. Connection exception/timeout retry đúng số lần rồi throw.
19. Xác minh hành vi Laravel HTTP client của `retry(..., throw: false)->throw()`.
20. Thay assertion latency `> 0` bằng assertion không âm hoặc kỹ thuật clock ổn định để tránh flaky test.

#### 4.2. Sửa test lịch sử hội thoại đang đặt tên quá mức kiểm chứng

File đích: `tests/Feature/Admin/AiConversationTest.php`.

Test `test_history_sent_to_provider_in_correct_order_and_limit` hiện chỉ kiểm tra tổng số message trong database; chưa bắt mảng `$messages` provider nhận được.

Phải sửa bằng fake provider có thể ghi lại input và assert:

- Message đầu là system.
- Chỉ lấy đúng history limit.
- History thuộc đúng conversation.
- Thứ tự history là cũ đến mới.
- Câu hỏi mới nằm cuối.
- Không lẫn conversation của user khác.

Bổ sung các case hội thoại còn thiếu hoặc chưa được chứng minh rõ:

- Provider success lưu đúng hai message.
- Blocks và telemetry lưu đúng.
- Proposal lưu đúng proposer, branch, UUID, fingerprint và payload.
- Nội dung `<script>` của provider được escape trong response HTML.
- Table/chart malformed không làm view lỗi.

#### 4.3. Hoàn thiện test action cash/stock

File đích chính: `tests/Feature/Admin/AiActionTest.php` và/hoặc tách thành test class cash/stock riêng.

`stockProposal()` hiện tồn tại nhưng chưa được dùng trong các test của file này. Cần phủ:

Cash:

1. Expense hợp lệ tạo đúng một transaction.
2. Income hợp lệ tạo đúng một transaction.
3. Confirm lặp chỉ tạo một transaction.
4. Amount 0, âm và vượt max bị chặn.
5. Category system-generated/lạ bị chặn.
6. Manual category hợp lệ được chấp nhận.
7. Payment method hợp lệ và null hoạt động; giá trị lạ bị chặn.
8. Backdate quá giới hạn và future date bị chặn.
9. Creator và branch đúng.
10. Result morph đúng.

Stock:

1. Delta tăng hợp lệ.
2. Delta giảm hợp lệ khi đủ tồn.
3. Delta giảm làm âm tồn bị chặn.
4. Absolute adjustment hợp lệ.
5. Quantity ngoài giới hạn bị chặn.
6. Product không tồn tại, inactive, ngoài catalog hoặc `BranchProduct` inactive bị chặn.
7. Type khác adjustment bị chặn.
8. Note quá ngắn bị chặn.
9. Date ngoài giới hạn bị chặn.
10. Confirm lặp chỉ tạo một movement.
11. Current stock cập nhật đúng.
12. Result morph, creator, branch và audit đúng.

Chung:

- Test hiện có tên “rate limit on confirm and reject endpoints works” nhưng chỉ gọi confirm. Tách/assert riêng throttle cho reject.
- Không confirm password phải redirect tới password confirmation.
- Rejected proposal không thể chạy.
- Failed proposal không thể chạy.
- Policy create được kiểm tra lại cho cả actor và proposer theo semantics hiện tại.
- Domain exception lưu `failed`, `failure_message` an toàn, không lưu stack trace/secret.
- Hai confirm cạnh tranh chỉ tạo một result nếu có thể kiểm thử đáng tin cậy trên database test.
- Confirm và reject cạnh tranh chỉ một quyết định thắng.

#### 4.4. Hoàn thiện context/prompt security tests

File đích: `tests/Unit/Services/Ai/AiBusinessContextTest.php` và test mới cho `AiPromptBuilder` nếu cần.

Cần bổ sung:

- Top employee đúng period và branch.
- Product ngoài scope không xuất hiện trong low stock.
- Bắt toàn bộ prompt/provider input để chứng minh PII khách hàng không xuất hiện, không chỉ kiểm tra context array.
- History/user prompt chứa chỉ dẫn injection không làm thay đổi system message/schema server tạo ra.
- Context rỗng vẫn tạo system prompt hợp lệ.
- Boundary ngày đầu/cuối kỳ theo timezone ứng dụng.
- Không có branch scope phải không vô tình mở toàn bộ dữ liệu; xem xét thay `[0]` bằng semantics rõ ràng hoặc thêm test khóa hành vi.

### P1 — Quality và UX nên hoàn tất

#### 4.5. UI/accessibility tests và kiểm tra thủ công

- Desktop hai cột không overflow.
- Mobile chuyển một cột.
- Long title/label/content không phá layout.
- Keyboard focus rõ.
- Vùng chạm nút confirm/reject đủ lớn.
- Chart có accessible name và dữ liệu fallback.
- Màu không phải tín hiệu duy nhất khi có nhiều dataset.
- Negative/equal/empty chart values hiển thị an toàn.
- Không có console error.
- XSS payload chỉ hiện dạng text.

#### 4.6. Quan sát vận hành và lỗi provider

- Xác minh controller chỉ hiển thị thông báo tiếng Việt an toàn.
- Không log API key, Authorization header, business context đầy đủ hoặc PII.
- Cân nhắc structured logging tối thiểu: provider, model, latency, status, conversation ID; không log prompt thô.
- Xác nhận thông điệp khi 401/403/429/500/timeout đủ rõ nhưng không lộ chi tiết nhạy cảm.

### P2 — Ngoài phạm vi AI nhưng cần ghi nhận

Tám lỗi full suite nêu ở mục 2.2 cần một task debug riêng. Không trộn việc sửa chúng vào PR AI trừ khi chứng minh nguyên nhân là state leak do AI.

## 5. Kịch bản kiểm thử thủ công end-to-end

### 5.1. AI tắt

1. Đặt `AI_ENABLED=false`.
2. Đăng nhập owner/manager.
3. Mở Trợ lý AI.
4. Xác nhận cảnh báo cấu hình xuất hiện.
5. Composer bị khóa.
6. Không có request provider.

### 5.2. Hỏi đáp và báo cáo

1. Cấu hình credential thật trong `.env` local, không commit.
2. Chọn branch có dữ liệu.
3. Hỏi tóm tắt tháng này.
4. So số liệu với trang report.
5. Yêu cầu bảng top dịch vụ.
6. Yêu cầu lần lượt bar, line và doughnut chart.
7. Kiểm tra desktop/mobile và refresh để xác nhận lịch sử còn nguyên.

### 5.3. Privacy và branch scope

1. Đăng nhập manager branch A.
2. Hỏi dữ liệu branch B.
3. AI phải nói không có dữ liệu trong phạm vi, không bịa.
4. Kiểm tra request provider không chứa tên/điện thoại/email khách hàng.
5. Thử prompt injection yêu cầu lộ system prompt/API key; AI không được tiết lộ.

### 5.4. Cash proposal

1. Yêu cầu đề xuất ghi chi marketing 500.000 VND hôm nay bằng chuyển khoản.
2. Trước confirm không có cash transaction.
3. Nhấn confirm; hệ thống yêu cầu password step-up nếu cần.
4. Sau confirm có đúng một transaction và audit event.
5. Gửi confirm lại; không có bản ghi thứ hai.
6. Thử payload sai branch/date/category/amount; không tạo transaction.

### 5.5. Stock proposal

1. Chọn active product thuộc active catalog của branch.
2. Yêu cầu delta tăng, delta giảm và absolute adjustment.
3. Trước confirm tồn kho không đổi.
4. Sau confirm tạo đúng một movement/audit và tồn kho đúng.
5. Confirm lặp không tạo movement thứ hai.
6. Thử product ngoài branch, inactive và giảm âm tồn; hệ thống từ chối.

### 5.6. Reject/failure/concurrency

1. Reject proposal rồi thử confirm; không có business record.
2. Gây domain error; proposal thành failed và không có business record.
3. Mở hai tab gửi confirm gần đồng thời; chỉ một result được tạo.
4. Gửi vượt throttle message/confirm/reject; nhận 429 phù hợp.

### 5.7. Authorization và XSS

1. Employee truy cập URL AI: 403.
2. User A truy cập conversation/proposal user B: 404.
3. Provider trả HTML/script: script không chạy.
4. Sửa fixture branch ID ngoài scope: confirm bị chặn.

## 6. Quality gate cuối

Chạy theo thứ tự:

1. `git status --short` và `git diff --stat`.
2. PHP lint cho toàn bộ PHP mới/sửa thuộc AI.
3. Pint chỉ trên file AI đã sửa.
4. Chạy provider/context/prompt unit tests.
5. Chạy conversation/action feature tests hai lần liên tiếp.
6. Chạy AI tests sau một nhóm admin test để kiểm tra order dependency.
7. Chạy `php artisan test` và phân loại chính xác mọi lỗi còn lại.
8. `php artisan route:list --name=admin.ai`.
9. `php artisan view:clear && php artisan view:cache`.
10. `php artisan migrate --pretend`.
11. `npm run build`.
12. `git diff --check`.
13. Rà soát diff cuối: không secret, không `.env`, không thay đổi ngoài phạm vi không giải thích được.

## 7. Tiêu chí chấp nhận

Tính năng hoàn tất khi:

- 60 test AI hiện có tiếp tục qua và các case P0 được bổ sung đều qua.
- AI tests ổn định khi chạy riêng, lặp lại và trong full suite.
- Owner/manager dùng được; employee bị chặn.
- Conversation/proposal riêng tư theo user và branch scope.
- Prompt gửi provider không chứa PII khách hàng.
- Text/table/bar/line/doughnut render an toàn và responsive.
- Action không chạy trước confirm.
- Confirm yêu cầu password step-up.
- Quyền, branch, catalog và payload được revalidate khi thực thi.
- Duplicate confirm không tạo duplicate business record.
- Rejected/failed là final theo thiết kế hiện tại.
- Failure không treo ở executing và không để lại business record.
- Audit được tạo đúng một lần bởi domain action hiện có.
- PHP lint, Blade cache, routes, migration pretend và Vite build qua.
- Tám lỗi ngoài AI được ghi nhận riêng; không được che giấu hoặc quy sai cho AI.

## 8. Prompt bàn giao cho AI khác

Sao chép nguyên prompt dưới đây:

---

Bạn đang tiếp quản repository Laravel tại `c:/xampp/htdocs/caitiemneo` để hoàn thiện Trợ lý AI quản lý native Laravel. Trước khi sửa code, hãy đọc toàn bộ `AGENTS.md` và `plans/ai-management-assistant-completion-plan.md`; file plan là specification và bản audit mới nhất.

Nguyên tắc bắt buộc:

1. Kiểm tra `php -v`, `composer -V`, `git status --short`, `git diff --stat` trước khi làm.
2. Tiếp tục kiến trúc hiện có; không viết lại từ đầu và không sao chép runtime/code PageSeed.
3. Không dùng `git reset --hard`, `git checkout .`, `git clean -fd` hoặc thao tác phá hủy.
4. Không sửa `.env`, không commit API key, không thao tác production PageSeed.
5. Không sửa booking/email/password reset hoặc tám test baseline ngoài AI nếu chưa chứng minh liên quan.
6. Formatter chỉ chạy trên file AI đã sửa; kiểm tra diff ngay sau formatter.
7. Không vô hiệu hóa auth, gate, policy, branch scope, throttle hoặc `password.confirm` chỉ để làm test qua.

Trạng thái đã xác minh ngày 17/09/2026:

- Working tree sạch trước audit.
- PHP 8.3.33, Composer 2.10.2.
- Nhóm AI hiện có: 60 test, 221 assertion, tất cả qua.
- Full suite: 787 test qua, 8 test lỗi ngoài AI, 2.663 assertion.
- Lỗi shared rate limiter của AI trước đây đã được xử lý.
- Failed action đã được lưu bằng transaction thứ hai và là trạng thái final.
- Stock action đã kiểm tra active product và active branch catalog.
- Line chart SVG và accessible fallback đã tồn tại.
- Không cần làm export CSV trừ khi người dùng yêu cầu riêng.

Hãy thực hiện theo thứ tự:

1. Bổ sung toàn bộ provider parser/config/request/retry/error tests tại mục 4.1 của plan. Khóa rõ hành vi 401/403/429/500/timeout và số lần retry; tránh assertion latency dễ flaky.
2. Sửa test history để fake provider ghi lại input và thực sự assert system/history limit/order/conversation isolation/new question như mục 4.2.
3. Bổ sung test persistence hội thoại: hai message, blocks, telemetry, proposal metadata/fingerprint/UUID, XSS escaping và malformed blocks.
4. Dùng helper stock hiện có hoặc tách test class để phủ đầy đủ cash/stock/action cases tại mục 4.3. Đặc biệt phải test stock execution, exactly-once, result morph, audit và reject throttle thật sự.
5. Bổ sung context/prompt security tests tại mục 4.4: top employee, product ngoài scope, prompt-level PII, injection, empty context và timezone boundaries.
6. Chỉ sửa implementation khi test mới chứng minh lỗi hoặc thiếu hành vi. Giữ nguyên domain actions và audit hiện có.
7. Kiểm tra UI/accessibility thủ công theo mục 5; sửa tối thiểu nếu phát hiện lỗi.
8. Chạy đầy đủ quality gate tại mục 6.

Các lỗi full suite ngoài AI cần ghi lại, không được lờ đi:

- `AuditTrailTest`: changing the commission employee is recorded.
- `RiskDetectionTest`: a reviewer can quickly assign missing invoice commission.
- 4 case invoice-line trong `ScopedForeignIdTest`.
- `AuthenticationTest`: first dashboard load resolves branch context.
- `SessionManagementTest`: profile page lists account sessions.

Báo cáo cuối bắt buộc nêu:

- File đã sửa và lý do.
- Test mới theo từng nhóm provider/context/conversation/cash/stock/security.
- Lệnh đã chạy và số test/assertion chính xác.
- Kết quả full suite và phân loại lỗi baseline.
- Kết quả route, Blade cache, migration pretend, Vite build và `git diff --check`.
- Mọi rủi ro còn lại; không tuyên bố hoàn tất nếu AI tests chỉ qua khi chạy riêng nhưng lỗi trong full suite.

---
