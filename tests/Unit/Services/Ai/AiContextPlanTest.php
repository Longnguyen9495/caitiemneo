<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\AiContextPlan;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiContextPlanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Neo thời gian để các mốc "tuần trước", "quý này" luôn tính ra cùng một
        // kết quả, thay vì đổi theo ngày chạy kiểm thử.
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 9, 17, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_a_question_without_a_period_is_flagged_as_inferred(): void
    {
        // Không đánh dấu thì model trả lời chắc nịch trên một khoảng do hệ thống
        // tự chọn, và không ai biết câu trả lời đang nói về tháng nào.
        $plan = AiContextPlan::fromQuestion('doanh thu thế nào');

        $this->assertTrue($plan->periodInferred);
        $this->assertSame('2026-09-01', $plan->from->toDateString());
        $this->assertSame('2026-09-30', $plan->to->toDateString());
    }

    public function test_an_explicit_period_is_not_flagged_as_inferred(): void
    {
        $plan = AiContextPlan::fromQuestion('doanh thu hôm nay');

        $this->assertFalse($plan->periodInferred);
        $this->assertSame('2026-09-17', $plan->from->toDateString());
        $this->assertSame('2026-09-17', $plan->to->toDateString());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function periodPhrases(): array
    {
        return [
            'n ngày qua' => ['doanh thu 3 ngày qua', '2026-09-15', '2026-09-17'],
            'tuần trước' => ['doanh thu tuần trước', '2026-09-07', '2026-09-13'],
            'tháng trước' => ['doanh thu tháng trước', '2026-08-01', '2026-08-31'],
            'tháng có số' => ['doanh thu tháng 7', '2026-07-01', '2026-07-31'],
            'quý này' => ['doanh thu quý này', '2026-07-01', '2026-09-30'],
            'quý trước' => ['doanh thu quý trước', '2026-04-01', '2026-06-30'],
            'năm ngoái' => ['doanh thu năm ngoái', '2025-01-01', '2025-12-31'],
            'hôm qua' => ['doanh thu hôm qua', '2026-09-16', '2026-09-16'],
        ];
    }

    /**
     * Các cách nói này trước đây đều rơi về mặc định tháng này, nên câu trả lời
     * sai khoảng thời gian mà vẫn trông như đúng.
     */
    #[DataProvider('periodPhrases')]
    public function test_common_vietnamese_periods_are_understood(string $question, string $from, string $to): void
    {
        $plan = AiContextPlan::fromQuestion($question);

        $this->assertFalse($plan->periodInferred, "Câu hỏi [{$question}] phải được coi là đã nêu khoảng thời gian.");
        $this->assertSame($from, $plan->from->toDateString());
        $this->assertSame($to, $plan->to->toDateString());
    }

    public function test_a_revenue_question_without_the_word_invoice_still_selects_the_invoice_domain(): void
    {
        // "bán được bao nhiêu" không chứa từ khóa "hóa đơn" nào, nhưng vẫn phải
        // dẫn tới dữ liệu doanh thu thay vì rơi về tổng quan.
        $plan = AiContextPlan::fromQuestion('tháng này bán được bao nhiêu');

        $this->assertContains('invoices', $plan->domains);
    }

    public function test_a_customer_question_selects_the_appointment_domain(): void
    {
        $plan = AiContextPlan::fromQuestion('chị Lan có hẹn không');

        $this->assertContains('appointments', $plan->domains);
    }
}
