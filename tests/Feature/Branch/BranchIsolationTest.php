<?php

namespace Tests\Feature\Branch;

use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::factory()->create(['code' => 'CN-A', 'name' => 'Chi nhánh A']);
        $this->branchB = Branch::factory()->create(['code' => 'CN-B', 'name' => 'Chi nhánh B']);
    }

    private function managerOf(Branch $branch): User
    {
        return User::factory()->manager()->withoutBranch()->atBranch($branch)->create();
    }

    public function test_an_owner_reaches_every_branch(): void
    {
        $owner = User::factory()->owner()->create();

        // The upgrade migration seeds CN-01, so the owner sees that one too.
        $this->assertEqualsCanonicalizing(
            Branch::query()->active()->pluck('id')->all(),
            $owner->accessibleBranchIds(),
        );
        $this->assertContains($this->branchA->id, $owner->accessibleBranchIds());
        $this->assertContains($this->branchB->id, $owner->accessibleBranchIds());
    }

    public function test_a_manager_only_reaches_their_own_branch(): void
    {
        $manager = $this->managerOf($this->branchA);

        $this->assertSame([$this->branchA->id], $manager->accessibleBranchIds());
        $this->assertTrue($manager->canAccessBranch($this->branchA));
        $this->assertFalse($manager->canAccessBranch($this->branchB));
    }

    public function test_the_invoice_list_hides_other_branches(): void
    {
        $manager = $this->managerOf($this->branchA);

        Invoice::factory()->create(['branch_id' => $this->branchA->id, 'number' => 'NEO-A-1']);
        Invoice::factory()->create(['branch_id' => $this->branchB->id, 'number' => 'NEO-B-1']);

        $this->actingAs($manager)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee('NEO-A-1')
            ->assertDontSee('NEO-B-1');
    }

    public function test_opening_another_branch_invoice_by_id_is_forbidden(): void
    {
        $manager = $this->managerOf($this->branchA);
        $foreign = Invoice::factory()->create(['branch_id' => $this->branchB->id]);

        $this->actingAs($manager)->get(route('admin.invoices.edit', $foreign))->assertForbidden();
        $this->actingAs($manager)->patch(route('admin.invoices.update', $foreign), ['discount' => 0])->assertForbidden();
        $this->actingAs($manager)->post(route('admin.invoices.pay', $foreign), ['payment_method' => 'cash'])->assertForbidden();
        $this->actingAs($manager)->delete(route('admin.invoices.cancel', $foreign), ['cancel_reason' => 'x'])->assertForbidden();
    }

    public function test_editing_another_branch_cash_entry_by_id_is_forbidden(): void
    {
        $manager = $this->managerOf($this->branchA);
        $foreign = CashTransaction::factory()->create(['branch_id' => $this->branchB->id]);

        $this->actingAs($manager)->get(route('admin.cash.edit', $foreign))->assertForbidden();
        $this->actingAs($manager)->delete(route('admin.cash.destroy', $foreign), ['void_reason' => 'x'])->assertForbidden();
    }

    public function test_the_branch_switcher_rejects_a_branch_outside_the_users_reach(): void
    {
        $manager = $this->managerOf($this->branchA);

        $this->actingAs($manager)
            ->from(route('admin.dashboard'))
            ->post(route('admin.branch.switch'), ['branch' => $this->branchB->id])
            ->assertSessionHasErrors('branch');

        $this->assertNotSame($this->branchB->id, session(BranchContext::SESSION_KEY));
    }

    public function test_a_manager_cannot_ask_for_the_company_wide_view(): void
    {
        $manager = $this->managerOf($this->branchA);

        $this->actingAs($manager)
            ->from(route('admin.dashboard'))
            ->post(route('admin.branch.switch'), ['branch' => 'all'])
            ->assertSessionHasErrors('branch');
    }

    public function test_a_query_string_branch_outside_reach_falls_back_instead_of_widening_access(): void
    {
        $manager = $this->managerOf($this->branchA);

        Invoice::factory()->create(['branch_id' => $this->branchB->id, 'number' => 'NEO-B-9']);

        $this->actingAs($manager)
            ->get(route('admin.invoices.index', ['branch' => $this->branchB->id]))
            ->assertOk()
            ->assertDontSee('NEO-B-9');
    }

    public function test_an_owner_may_view_every_branch_at_once(): void
    {
        $owner = User::factory()->owner()->create();

        Invoice::factory()->create(['branch_id' => $this->branchA->id, 'number' => 'NEO-A-2']);
        Invoice::factory()->create(['branch_id' => $this->branchB->id, 'number' => 'NEO-B-2']);

        $this->actingAs($owner)
            ->get(route('admin.invoices.index', ['branch' => 'all']))
            ->assertOk()
            ->assertSee('NEO-A-2')
            ->assertSee('NEO-B-2');
    }

    public function test_an_account_with_no_posting_cannot_enter_the_admin_area(): void
    {
        $orphan = User::factory()->employee()->withoutBranch()->create(['can_manage_appointments' => true]);

        $this->actingAs($orphan)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_the_upgrade_backfilled_existing_rows_onto_the_default_branch(): void
    {
        // The migration seeds CN-01 and attaches every pre-existing row to it.
        $this->assertDatabaseHas('branches', ['code' => 'CN-01']);
        $this->assertSame(0, Invoice::query()->whereNull('branch_id')->count());
    }
}
