<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The parts of accessibility a test can actually hold.
 *
 * Contrast, zoom and screen-reader behaviour need a browser and a person; those
 * stay on the checklist. What is checkable here is the markup that assistive
 * technology reads: a table that announces what it contains, headers tied to
 * their columns, and a skip link so a keyboard user is not made to tab through
 * the whole menu on every page.
 */
class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->owner = User::factory()->owner()->atBranch($this->branch)->create();

        CashTransaction::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
        ]);
    }

    /**
     * A data table has to say what it is.
     *
     * Without a caption a screen reader announces "table, eight columns" and
     * nothing else, so somebody arriving by keyboard has no idea which of the
     * page's tables they have landed in.
     */
    public function test_a_data_table_describes_itself(): void
    {
        $this->visit(route('admin.cash.index'))
            ->assertSee('<caption', false);
    }

    /** Column headers must be tied to their column, not just bold text. */
    public function test_column_headers_are_marked_up_as_headers(): void
    {
        $this->visit(route('admin.cash.index'))
            ->assertSee('scope="col"', false);
    }

    /** A keyboard user must be able to jump past the navigation. */
    public function test_every_page_offers_a_skip_link(): void
    {
        $this->visit(route('admin.cash.index'))
            ->assertSee('neo-skip', false)
            ->assertSee('#neo-main', false);
    }

    /** The main landmark the skip link points at has to exist. */
    public function test_the_main_landmark_exists(): void
    {
        $this->visit(route('admin.cash.index'))
            ->assertSee('id="neo-main"', false);
    }

    /** Page language matters for how a screen reader pronounces the text. */
    public function test_the_page_declares_vietnamese(): void
    {
        $this->visit(route('admin.cash.index'))
            ->assertSee('lang="vi"', false);
    }

    private function visit(string $url): TestResponse
    {
        return $this->actingAs($this->owner)
            ->withSession([BranchContext::SESSION_KEY => $this->branch->getKey()])
            ->get($url)
            ->assertOk();
    }
}
