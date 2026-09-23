<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * What the shared admin components promise.
 *
 * These are used on every screen, so a change to one of them changes the whole
 * admin area at once. That is the point of having them — and the reason the
 * behaviour worth relying on should be written down rather than rediscovered
 * by opening pages and looking.
 *
 * Rendered in isolation on purpose: a full page test would pass for the wrong
 * reason if some other part of the layout happened to emit the same markup.
 */
class ComponentContractTest extends TestCase
{
    use RefreshDatabase;

    /** A required field has to be announced as required, not just starred. */
    public function test_a_field_marks_itself_required_for_assistive_technology(): void
    {
        $html = $this->render('<x-admin.field name="amount" label="Số tiền" required><input id="amount"></x-admin.field>');

        $this->assertStringContainsString('for="amount"', $html);
        // Dấu sao chỉ để nhìn, nên phải ẩn khỏi trình đọc màn hình.
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    /** Help text always has an id, so a control can point at it. */
    public function test_a_field_always_exposes_a_help_id(): void
    {
        $withHelp = $this->render('<x-admin.field name="note" label="Ghi chú" help="Tối đa 200 ký tự"><input id="note"></x-admin.field>');
        $withoutHelp = $this->render('<x-admin.field name="note" label="Ghi chú"><input id="note"></x-admin.field>');

        $this->assertStringContainsString('id="note-help"', $withHelp);
        // Vẫn tồn tại khi rỗng, nếu không aria-describedby sẽ trỏ vào id không có thật.
        $this->assertStringContainsString('id="note-help"', $withoutHelp);
    }

    /** An error is rendered with an id the control can reference. */
    public function test_a_field_exposes_an_error_id_when_invalid(): void
    {
        $html = $this->renderWithErrors(
            '<x-admin.field name="amount" label="Số tiền"><input id="amount"></x-admin.field>',
            ['amount' => 'Hãy nhập số tiền.'],
        );

        $this->assertStringContainsString('id="amount-error"', $html);
        $this->assertStringContainsString('Hãy nhập số tiền.', $html);
    }

    /** Money always reads the same way, wherever it appears. */
    public function test_money_is_formatted_consistently(): void
    {
        $html = $this->render('<x-admin.money :value="1234567" />');

        $this->assertStringContainsString('1.234.567', $html);
    }

    /** A money input carries its own accessibility wiring. */
    public function test_a_money_input_is_wired_up_when_invalid(): void
    {
        $html = $this->renderWithErrors(
            '<x-admin.money-input name="amount" />',
            ['amount' => 'Hãy nhập số tiền.'],
        );

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertMatchesRegularExpression('/aria-describedby="[^"]*amount-error/', $html);
    }

    /** Status badges turn an enum into something a person can read. */
    public function test_a_status_badge_shows_the_human_label(): void
    {
        $html = $this->render('<x-admin.status-badge :status="$status" />', [
            'status' => InvoiceStatus::Paid,
        ]);

        $this->assertStringContainsString(InvoiceStatus::Paid->label(), $html);
    }

    /** An empty list should offer a way forward, not just say "nothing". */
    public function test_an_empty_state_can_carry_a_next_action(): void
    {
        $html = $this->render(
            '<x-admin.empty-state title="Chưa có hóa đơn" hint="Tạo hóa đơn đầu tiên."><a href="/new">Tạo mới</a></x-admin.empty-state>'
        );

        $this->assertStringContainsString('Chưa có hóa đơn', $html);
        $this->assertStringContainsString('Tạo hóa đơn đầu tiên.', $html);
        $this->assertStringContainsString('Tạo mới', $html);
    }

    /** A destructive confirm can demand a typed reason. */
    public function test_a_confirm_form_can_require_a_reason(): void
    {
        $html = $this->render(
            '<x-admin.confirm-form action="/void" method="DELETE" label="Hủy" message="Hủy khoản này?" reason-field="void_reason" />'
        );

        $this->assertStringContainsString('data-neo-confirm-reason="void_reason"', $html);
        $this->assertStringContainsString('data-neo-confirm="Hủy khoản này?"', $html);
        // Không có giá trị lý do dựng sẵn trong biểu mẫu.
        $this->assertStringNotContainsString('value="Hủy bởi người dùng"', $html);
    }

    /** The summary stays out of the way when there is nothing wrong. */
    public function test_the_error_summary_is_absent_on_a_clean_form(): void
    {
        $html = $this->render('<x-admin.error-summary />');

        $this->assertStringNotContainsString('alert-danger', $html);
    }

    /** A one-click action posts, and carries the token that makes it valid. */
    public function test_a_post_button_submits_its_own_form_with_a_token(): void
    {
        $html = $this->render('<x-admin.post-button action="/approve" label="Duyệt" :fields="[\'approved\' => 1]" variant="primary" />');

        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('action="/approve"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="approved" value="1"', $html);
        // Nút trong bảng phải là submit, nếu không hàng đó bấm vào không làm gì.
        $this->assertStringContainsString('type="submit"', $html);
    }

    /** A select wires its label, its control and its error to one id. */
    public function test_a_select_field_ties_label_and_error_to_the_control(): void
    {
        $html = $this->renderWithErrors(
            '<x-admin.select-field name="work_shift_id" label="Ca làm" placeholder="Chọn ca" required />',
            ['work_shift_id' => 'Hãy chọn ca.'],
        );

        $this->assertStringContainsString('for="work_shift_id"', $html);
        $this->assertStringContainsString('id="work_shift_id"', $html);
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('id="work_shift_id-error"', $html);
        $this->assertStringContainsString('<option value="">Chọn ca</option>', $html);
    }

    /**
     * Hai biểu mẫu trên một trang có thể gửi cùng một tên ô — ca xin nghỉ và ca
     * cần đổi đều là `shift_assignment_id`. Khi đó nhãn phải đi theo id riêng
     * của từng ô, nếu không bấm vào nhãn thứ hai lại nhảy lên biểu mẫu thứ nhất.
     */
    public function test_a_field_can_carry_its_own_id_when_a_name_repeats(): void
    {
        $html = $this->render(
            '<x-admin.select-field name="shift_assignment_id" id="swap_shift_assignment_id" label="Ca của bạn" />'
        );

        $this->assertStringContainsString('for="swap_shift_assignment_id"', $html);
        $this->assertStringContainsString('id="swap_shift_assignment_id"', $html);
        $this->assertStringContainsString('name="shift_assignment_id"', $html);
    }

    /** @param array<string, mixed> $data */
    private function render(string $template, array $data = []): string
    {
        View::share('errors', new ViewErrorBag);

        return Blade::render($template, $data);
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function renderWithErrors(string $template, array $errors): string
    {
        $bag = new ViewErrorBag;
        $bag->put('default', new MessageBag($errors));

        View::share('errors', $bag);

        return Blade::render($template);
    }
}
