<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a form sends somebody back to.
 *
 * A manager who has filtered the cash book to one week, found the wrong entry
 * and voided it should land back on that week — not on an unfiltered list they
 * have to narrow down again. Losing the filter on every action is the kind of
 * small friction that ends with people batching up corrections "for later",
 * which is exactly when they stop happening.
 */
class FilterPreservationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $author;

    private User $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->author = User::factory()->manager()->atBranch($this->branch)->create();
        $this->checker = User::factory()->manager()->atBranch($this->branch)->create();
    }

    public function test_voiding_returns_to_the_filtered_list(): void
    {
        $transaction = CashTransaction::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->author->getKey(),
        ]);

        $filtered = route('admin.cash.index', ['from' => '2026-09-01', 'to' => '2026-09-30']);

        $this->actingAs($this->checker)
            ->from($filtered)
            ->delete(route('admin.cash.destroy', $transaction), [
                'void_reason' => 'Doi soat cuoi ca phat hien ghi trung',
            ])
            ->assertRedirect($filtered);
    }

    public function test_creating_returns_to_the_filtered_list(): void
    {
        $filtered = route('admin.cash.index', ['type' => 'expense']);

        $this->actingAs($this->author)
            ->from($filtered)
            ->post(route('admin.cash.store'), [
                'type' => 'expense',
                'category' => 'rent',
                'amount' => 500000,
                'occurred_at' => now()->toDateTimeString(),
                'note' => 'Tien thue thang nay',
            ])
            ->assertRedirect($filtered);
    }

    /** Arriving from somewhere unrelated still lands on the plain list. */
    public function test_without_a_prior_filter_the_plain_list_is_used(): void
    {
        $this->actingAs($this->author)
            ->post(route('admin.cash.store'), [
                'type' => 'expense',
                'category' => 'rent',
                'amount' => 500000,
                'occurred_at' => now()->toDateTimeString(),
                'note' => 'Tien thue thang nay',
            ])
            ->assertRedirect(route('admin.cash.index'));
    }

    /**
     * The referer must not become a way to bounce somebody elsewhere.
     *
     * Only a URL pointing at the same list is honoured; anything else falls
     * back to the plain list, so a crafted link cannot turn a successful save
     * into a redirect off-site.
     */
    public function test_a_foreign_referer_is_ignored(): void
    {
        $this->actingAs($this->author)
            ->from('https://evil.test/admin/cash')
            ->post(route('admin.cash.store'), [
                'type' => 'expense',
                'category' => 'rent',
                'amount' => 500000,
                'occurred_at' => now()->toDateTimeString(),
                'note' => 'Tien thue thang nay',
            ])
            ->assertRedirect(route('admin.cash.index'));
    }

    /** Nor a way to reach a different admin screen than the one acted on. */
    public function test_a_referer_for_another_screen_is_ignored(): void
    {
        $this->actingAs($this->author)
            ->from(route('admin.invoices.index'))
            ->post(route('admin.cash.store'), [
                'type' => 'expense',
                'category' => 'rent',
                'amount' => 500000,
                'occurred_at' => now()->toDateTimeString(),
                'note' => 'Tien thue thang nay',
            ])
            ->assertRedirect(route('admin.cash.index'));
    }

    /** A long form must warn before the browser throws away typed work. */
    public function test_a_long_form_guards_against_losing_typed_work(): void
    {
        $this->actingAs($this->author)
            ->get(route('admin.cash.create'))
            ->assertOk()
            ->assertSee('data-neo-dirty-guard', false);
    }
}
