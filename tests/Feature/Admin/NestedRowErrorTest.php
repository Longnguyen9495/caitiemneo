<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Errors on a dynamic row have to land on that row.
 *
 * A transfer with ten lines that answers "danh sách vật tư không hợp lệ" tells
 * the operator nothing about which line to fix, and losing the typed rows on
 * the way back makes them start again. Both together are how a form gets
 * abandoned and the stock ends up moved without paperwork.
 */
class NestedRowErrorTest extends TestCase
{
    use RefreshDatabase;

    private Branch $source;

    private Branch $destination;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = Branch::factory()->create();
        $this->destination = Branch::factory()->create();
        $this->manager = User::factory()->manager()->atBranch($this->source)->create();
    }

    /** The error must be keyed to the offending row, not to the group. */
    public function test_a_bad_transfer_row_reports_against_its_own_index(): void
    {
        $good = Product::factory()->create();

        $this->actingAs($this->manager)
            ->from(route('admin.stock-transfers.create'))
            ->post(route('admin.stock-transfers.store'), $this->payload([
                ['product_id' => $good->getKey(), 'quantity' => 1],
                ['product_id' => $good->getKey(), 'quantity' => -5],
            ]))
            ->assertSessionHasErrors('items.1.quantity');
    }

    /** What the operator typed must survive the round trip. */
    public function test_the_typed_rows_come_back_after_a_failed_submit(): void
    {
        $product = Product::factory()->create();

        $rows = [
            ['product_id' => $product->getKey(), 'quantity' => 7],
            ['product_id' => $product->getKey(), 'quantity' => -1],
        ];

        $page = $this->actingAs($this->manager)
            ->followingRedirects()
            ->from(route('admin.stock-transfers.create'))
            ->post(route('admin.stock-transfers.store'), $this->payload($rows))
            ->assertOk();

        $page->assertSee('Chuyen hang cho chi nhanh moi', false);

        $submitted = $this->rowsHandedBackToTheForm($page->getContent());

        $this->assertSame(7, $submitted[0]['quantity'] ?? null, 'Dòng hợp lệ không được truyền trở lại form.');
        $this->assertSame(-1, $submitted[1]['quantity'] ?? null, 'Dòng gây lỗi cũng phải được giữ lại để sửa.');
    }

    /** A duplicate product should name the row, not just the list. */
    public function test_a_duplicate_product_is_reported_on_the_second_row(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->manager)
            ->from(route('admin.stock-transfers.create'))
            ->post(route('admin.stock-transfers.store'), $this->payload([
                ['product_id' => $product->getKey(), 'quantity' => 1],
                ['product_id' => $product->getKey(), 'quantity' => 2],
            ]))
            ->assertSessionHasErrors('items.1.product_id');
    }

    /**
     * The rows the server handed back to the editor.
     *
     * They arrive as the second argument of the Alpine component, encoded by
     * `@js()` — so the quotes are escaped. Reading that argument specifically
     * matters: searching the whole page for "quantity" also matches the blank
     * row template, which is always present and would make this pass whether
     * the old input survived or not.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowsHandedBackToTheForm(string $html): array
    {
        preg_match("/transferEditor\(.*?,\s*JSON\.parse\('(.*?)'\)/s", $html, $matches);

        // @js() mã hóa dấu nháy kép thành chuỗi sáu ký tự \u0022.
        $json = str_replace('\u0022', '"', $matches[1] ?? '[]');

        return json_decode($json, true) ?? [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function payload(array $items): array
    {
        return [
            'source_branch_id' => $this->source->getKey(),
            'destination_branch_id' => $this->destination->getKey(),
            'note' => 'Chuyen hang cho chi nhanh moi',
            'items' => $items,
        ];
    }
}
