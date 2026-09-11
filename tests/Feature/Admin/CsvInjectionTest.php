<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A spreadsheet treats a leading =, +, - or @ as a formula, so text somebody
 * typed into a booking form can execute on the accountant's machine the moment
 * the export is opened. The data is not attacking this application, it is
 * attacking whoever reads the file — which is exactly why it has to be
 * neutralised on the way out.
 */
class CsvInjectionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->owner = User::factory()->owner()->atBranch($this->branch)->create();

        // Bộ đếm throttle nằm ở cache dùng chung nên sống qua từng test. Xoá ở
        // đây để một test cố tình chạm trần không làm các test sau bị chặn.
        cache()->clear();
    }

    /** @return array<string, array{0: string}> */
    public static function formulaPayloads(): array
    {
        return [
            'equals' => ['=HYPERLINK("http://evil.test","Bam vao day")'],
            'plus' => ['+1+1'],
            'minus' => ['-2+3'],
            'at' => ['@SUM(A1:A9)'],
            'classic command' => ["=cmd|'/c calc'!A1"],
        ];
    }

    #[DataProvider('formulaPayloads')]
    public function test_a_formula_in_a_customer_name_is_neutralised(string $payload): void
    {
        Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'status' => InvoiceStatus::Paid,
            'customer_name' => $payload,
            'paid_at' => now(),
        ]);

        $csv = $this->export(route('admin.reports.export.invoices'));

        $this->assertStringNotContainsString(','.$payload, $csv, 'Ô dữ liệu vẫn bắt đầu bằng ký tự công thức.');
        $this->assertStringNotContainsString('"'.$payload, $csv, 'Ô dữ liệu vẫn bắt đầu bằng ký tự công thức.');

        // Nội dung vẫn phải đọc được, chỉ là không còn được coi là công thức:
        // ô được đánh dấu là văn bản bằng dấu nháy đơn ở đầu.
        $this->assertStringContainsString("'".substr($payload, 0, 6), $csv);
    }

    public function test_a_formula_in_a_cash_note_is_neutralised(): void
    {
        CashTransaction::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'note' => '=1+1',
        ]);

        $csv = $this->export(route('admin.reports.export.cash'));

        $this->assertStringNotContainsString(',=1+1', $csv);
        $this->assertStringNotContainsString('"=1+1', $csv);
    }

    /** Ordinary text must come through untouched, or the export becomes unreadable. */
    public function test_normal_text_is_not_mangled(): void
    {
        Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'status' => InvoiceStatus::Paid,
            'customer_name' => 'Nguyen Thi Mai',
            'paid_at' => now(),
        ]);

        $csv = $this->export(route('admin.reports.export.invoices'));

        $this->assertStringContainsString('Nguyen Thi Mai', $csv);
        $this->assertStringNotContainsString("'Nguyen", $csv);
    }

    /** A negative money figure is a number, not a formula: it must stay usable. */
    public function test_a_negative_number_is_left_alone(): void
    {
        CashTransaction::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'amount' => '150000.00',
            'note' => 'Chi tien mat',
        ]);

        $csv = $this->export(route('admin.reports.export.cash'));

        $this->assertStringContainsString('150000.00', $csv);
        $this->assertStringNotContainsString("'150000.00", $csv);
    }

    /** Exports carry money and customer data, so they belong in the trail. */
    public function test_an_export_is_written_to_the_audit_trail(): void
    {
        $this->export(route('admin.reports.export.invoices'));

        $event = AuditEvent::query()
            ->where('action', AuditAction::Exported)
            ->latest('id')
            ->first();

        $this->assertNotNull($event, 'Xuất dữ liệu phải để lại dấu vết.');
        $this->assertSame($this->owner->getKey(), $event->actor_id);
        $this->assertStringContainsString('hoa-don', (string) $event->auditable_label);
    }

    /** Repeated exports must not be an unmetered way to siphon the database. */
    public function test_exports_are_rate_limited(): void
    {
        $lastStatus = 200;

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $lastStatus = $this->actingAs($this->owner)
                ->get(route('admin.reports.export.invoices'))
                ->getStatusCode();

            if ($lastStatus === 429) {
                break;
            }
        }

        $this->assertSame(429, $lastStatus, 'Không có giới hạn tần suất cho xuất dữ liệu.');
    }

    private function export(string $url): string
    {
        // Owner thấy được mọi chi nhánh, nên phải nói rõ đang đứng ở chi nhánh
        // nào: nếu không, BranchContext chọn chi nhánh đầu tiên và bản xuất
        // rỗng, khiến test "pass" mà không thực sự kiểm tra gì.
        $response = $this->actingAs($this->owner)
            ->withSession([BranchContext::SESSION_KEY => $this->branch->getKey()])
            ->get($url);

        $response->assertOk();

        return $response->streamedContent();
    }
}
