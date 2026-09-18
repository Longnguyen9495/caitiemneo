<?php

namespace Tests\Unit;

use App\Enums\AiActionStatus;
use App\Enums\AppointmentStatus;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use App\Services\Ai\AiPayloadPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiPayloadPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_describes_an_appointment_payload_without_field_names_or_ids(): void
    {
        $branch = Branch::factory()->create(['name' => 'Cái Tiệm Neo Thái Hà']);
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $technician = User::factory()->atBranch($branch)->create(['name' => 'Kỹ thuật viên Mai']);
        $service = Service::factory()->create(['name' => 'Đắp gel']);

        $rows = app(AiPayloadPresenter::class)->rows($this->proposal($owner, $branch, 'create_appointment', [
            'branch_id' => $branch->id,
            'customer_name' => 'Chị Lan',
            'customer_phone' => '0901234567',
            'customer_email' => null,
            'employee_id' => $technician->id,
            'starts_at' => '2026-09-20T14:00:00+07:00',
            'duration_minutes' => 60,
            'status' => AppointmentStatus::Pending->value,
            'service_ids' => [$service->id],
            'note' => 'Khách quen',
        ]));

        $labelled = $this->labelled($rows);

        $this->assertSame('Cái Tiệm Neo Thái Hà', $labelled['Chi nhánh']);
        $this->assertSame('Chị Lan', $labelled['Khách hàng']);
        $this->assertSame('Kỹ thuật viên Mai', $labelled['Kỹ thuật viên']);
        $this->assertSame('Đắp gel', $labelled['Dịch vụ']);
        $this->assertSame('14:00 ngày 20/09/2026', $labelled['Bắt đầu']);
        $this->assertSame('60 phút', $labelled['Thời lượng']);
        $this->assertSame('Chờ xác nhận', $labelled['Trạng thái']);
        $this->assertSame('—', $labelled['Email']);

        // Điều người dùng phàn nàn: không được để lộ tên trường hay số ID.
        $rendered = implode(' ', array_merge(array_keys($labelled), array_values($labelled)));
        foreach (['branch_id', 'customer_name', 'starts_at', 'duration_minutes', 'employee_id', 'service_ids', 'pending'] as $technicalToken) {
            $this->assertStringNotContainsString($technicalToken, $rendered);
        }
    }

    public function test_it_formats_money_and_enum_labels_for_a_cash_entry(): void
    {
        $branch = Branch::factory()->create(['name' => 'Cái Tiệm Neo Thái Hà']);
        $owner = User::factory()->owner()->atBranch($branch)->create();

        $rows = app(AiPayloadPresenter::class)->rows($this->proposal($owner, $branch, 'create_cash_entry', [
            'branch_id' => $branch->id,
            'type' => CashTransactionType::Expense->value,
            'category' => array_key_first(CashTransactionCategory::manualOptions()),
            'amount' => 500000,
            'payment_method' => PaymentMethod::Cash->value,
            'reference' => null,
            'note' => 'Chi marketing',
            'occurred_at' => '2026-09-18T09:30:00+07:00',
        ]));

        $labelled = $this->labelled($rows);

        $this->assertSame('500.000đ', $labelled['Số tiền']);
        $this->assertSame('Khoản chi', $labelled['Loại']);
        $this->assertSame(CashTransactionCategory::from(array_key_first(CashTransactionCategory::manualOptions()))->label(), $labelled['Hạng mục']);
        $this->assertSame(PaymentMethod::Cash->label(), $labelled['Hình thức thanh toán']);
        $this->assertSame('09:30 ngày 18/09/2026', $labelled['Thời điểm']);
        $this->assertSame('—', $labelled['Chứng từ']);
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $rows
     * @return array<string, string>
     */
    private function labelled(array $rows): array
    {
        return array_reduce(
            $rows,
            fn (array $carry, array $row): array => $carry + [$row['label'] => $row['value']],
            [],
        );
    }

    /** @param  array<string, mixed>  $payload */
    private function proposal(User $owner, Branch $branch, string $type, array $payload): AiActionProposal
    {
        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'branch_id' => $branch->id,
            'scope_branch_ids' => [$branch->id],
            'last_message_at' => now(),
        ]);
        $message = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Đề xuất thao tác.',
        ]);

        return AiActionProposal::query()->create([
            'message_id' => $message->id,
            'proposed_by' => $owner->id,
            'branch_id' => $branch->id,
            'type' => $type,
            'summary' => 'Đề xuất thao tác',
            'payload' => $payload,
            'status' => AiActionStatus::Pending,
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', Str::uuid()->toString()),
        ]);
    }
}
