<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * What a person sees when a long form comes back rejected.
 *
 * Without a summary, a rejected payroll or invoice form drops the operator at
 * the top of the page with no idea which of thirty fields is wrong. Without the
 * ARIA wiring, a screen reader announces the input and then stops — the error
 * text underneath is just unrelated prose to it. Both push somebody towards
 * resubmitting blind or giving up, and a cash entry that never gets recorded is
 * a hole in the books as surely as a stolen one.
 */
class FormErrorUxTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->manager()->atBranch($this->branch)->create();
    }

    public function test_a_rejected_form_shows_a_summary_of_what_went_wrong(): void
    {
        $page = $this->submitInvalidCashEntry();

        $page->assertSee('Hãy kiểm tra lại', false);
        $page->assertSee('role="alert"', false);
    }

    /** The summary must name the fields, not just say "something is wrong". */
    public function test_the_summary_lists_the_actual_messages(): void
    {
        $page = $this->submitInvalidCashEntry();

        $page->assertSee('số tiền', false);
    }

    /** Each message must link to the control it belongs to. */
    public function test_the_summary_links_to_the_offending_field(): void
    {
        $page = $this->submitInvalidCashEntry();

        $page->assertSee('href="#amount"', false);
    }

    /** A clean form must not display an empty alert box. */
    public function test_a_clean_form_shows_no_summary(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.cash.create'))
            ->assertOk()
            ->assertDontSee('Hãy kiểm tra lại', false);
    }

    /**
     * A screen reader has to be told the input is invalid and which text
     * explains why; visually pairing them is not enough.
     */
    public function test_an_invalid_input_is_wired_up_for_assistive_technology(): void
    {
        $page = $this->submitInvalidCashEntry();

        $page->assertSee('aria-invalid="true"', false);
        $page->assertSee('id="amount-error"', false);

        // Control trỏ tới câu lỗi qua aria-describedby; thuộc tính này có thể
        // chứa nhiều id (lỗi và trợ giúp) nên kiểm tra theo id chứ không so cả chuỗi.
        $this->assertMatchesRegularExpression(
            '/aria-describedby="[^"]*amount-error[^"]*"/',
            $page->getContent(),
            'Ô nhập không trỏ tới câu lỗi của chính nó.',
        );
    }

    /** Help text must stay announced when the field is fine. */
    public function test_help_text_is_announced_on_a_valid_field(): void
    {
        $page = $this->actingAs($this->manager)
            ->get(route('admin.cash.create'))
            ->assertOk();

        $page->assertSee('-help"', false);
        $page->assertSee('aria-describedby=', false);
    }

    private function submitInvalidCashEntry(): TestResponse
    {
        return $this->actingAs($this->manager)
            ->followingRedirects()
            ->from(route('admin.cash.create'))
            ->post(route('admin.cash.store'), [
                'type' => 'expense',
                'category' => 'rent',
                'amount' => '',
                'occurred_at' => now()->toDateTimeString(),
            ])
            ->assertOk();
    }
}
