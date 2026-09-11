<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The business day has to be the one the shop actually works.
 *
 * With the application on UTC, a bill settled at 2am in Ho Chi Minh City is
 * stored as 19:00 the previous day — so a late shift's takings, its KPI and the
 * commission on it quietly land on the wrong date. Nobody is attacking
 * anything here; the numbers are simply wrong, and wrong in a way that only
 * shows up on the days somebody works late.
 */
class BusinessTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_runs_on_the_business_timezone(): void
    {
        $this->assertSame('Asia/Ho_Chi_Minh', config('app.timezone'));
    }

    /** Takings from a late shift belong to the day the shop was open. */
    public function test_a_late_night_payment_counts_on_the_local_day(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();

        // 01:30 giờ Việt Nam ngày 12 — vẫn là ca đêm của ngày 12.
        Carbon::setTestNow(Carbon::parse('2026-09-12 01:30:00'));

        Invoice::factory()->create([
            'branch_id' => $branch->getKey(),
            'created_by' => $owner->getKey(),
            'status' => InvoiceStatus::Paid,
            'total' => '500000.00',
            'paid_at' => now(),
        ]);

        $countedToday = Invoice::query()
            ->whereDate('paid_at', now()->startOfDay())
            ->count();

        Carbon::setTestNow();

        $this->assertSame(1, $countedToday, 'Hóa đơn ca đêm bị tính sang ngày khác.');
    }

    public function test_now_matches_the_business_clock(): void
    {
        $this->assertSame('+07:00', now()->format('P'));
    }
}
