<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dashboard mixes operational counters with company money.
 *
 * Revenue and the cash position are leadership figures: an operator who only
 * rings up bills has no business reading branch takings or the fund balance
 * from the landing page, so each card is gated on its own.
 */
class DashboardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();

        // Every record is pinned to this one branch and one author, so no
        // nested factory spawns a second branch behind the test's back.
        $author = User::factory()->owner()->atBranch($this->branch)->create();

        Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $author->getKey(),
            'status' => InvoiceStatus::Paid,
            'total' => '1234567.00',
            'paid_at' => now(),
        ]);

        CashTransaction::factory()->income()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $author->getKey(),
            'amount' => '7654321.00',
        ]);
    }

    public function test_plain_employee_sees_no_money_on_the_dashboard(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();

        $response = $this->actingAs($employee)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Doanh thu hôm nay');
        $response->assertDontSee('Số dư thu chi');
        $response->assertDontSee('1.234.567');
        $response->assertDontSee('7.654.321');
    }

    public function test_invoicing_employee_sees_revenue_but_not_the_cash_fund(): void
    {
        $cashier = User::factory()->employee()->atBranch($this->branch)->create([
            'can_create_invoices' => true,
        ]);

        $response = $this->actingAs($cashier)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Doanh thu hôm nay');
        $response->assertDontSee('Số dư thu chi');
        $response->assertDontSee('7.654.321');
    }

    public function test_manager_and_owner_see_every_card(): void
    {
        foreach ([
            User::factory()->manager()->atBranch($this->branch)->create(),
            User::factory()->owner()->atBranch($this->branch)->create(),
        ] as $leader) {
            $response = $this->actingAs($leader)->get(route('admin.dashboard'));

            $response->assertOk();
            $response->assertSee('Doanh thu hôm nay');
            $response->assertSee('Số dư thu chi');
        }
    }

    /** A card the viewer may not see must not cost a query either. */
    public function test_forbidden_cards_are_not_queried_for_a_plain_employee(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($employee)->get(route('admin.dashboard'))->assertOk();

        $moneyQueries = array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, 'income_total') || str_contains($sql, 'sum("total")') || str_contains($sql, 'sum(`total`)'),
        );

        $this->assertSame([], array_values($moneyQueries), 'Dashboard ran a money query for an employee who may not see money.');
    }
}
