<?php

namespace Tests\Feature\Branch;

use App\Enums\AuditAction;
use App\Enums\InventoryMovementType;
use App\Enums\StockTransferStatus;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two pairs of eyes on stock leaving a shop.
 *
 * A transfer that one person both raises and completes is just a note saying
 * goods left; nobody at either end confirmed it. The same goes for a stock
 * adjustment, which is the one movement type that can reduce inventory on
 * nothing but a typed reason.
 */
class StockMakerCheckerTest extends TestCase
{
    use RefreshDatabase;

    private Branch $source;

    private Branch $destination;

    private User $maker;

    private User $checker;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = Branch::factory()->create();
        $this->destination = Branch::factory()->create();

        $this->maker = User::factory()->manager()->atBranch($this->source)->create();
        $this->checker = User::factory()->manager()->atBranch($this->source)->create();

        $this->product = Product::factory()->create();

        // Stock the source so the transfer is not refused for lack of goods.
        InventoryMovement::factory()->create([
            'branch_id' => $this->source->getKey(),
            'product_id' => $this->product->getKey(),
            'created_by' => $this->checker->getKey(),
            'type' => InventoryMovementType::In,
            'quantity' => '100.00',
        ]);
    }

    public function test_the_author_of_a_transfer_may_not_complete_it(): void
    {
        $transfer = $this->draftTransferBy($this->maker);

        $this->actingAs($this->maker)
            ->post(route('admin.stock-transfers.complete', $transfer))
            ->assertForbidden();

        $this->assertSame(StockTransferStatus::Draft, $transfer->fresh()->status);
        $this->assertSame(0, InventoryMovement::query()->where('reference', $transfer->number)->count());
    }

    public function test_a_second_person_may_complete_the_transfer(): void
    {
        $transfer = $this->draftTransferBy($this->maker);

        $this->actingAs($this->checker)
            ->post(route('admin.stock-transfers.complete', $transfer))
            ->assertRedirect();

        $completed = $transfer->fresh();

        $this->assertSame(StockTransferStatus::Completed, $completed->status);
        $this->assertSame($this->checker->getKey(), $completed->completed_by);
        $this->assertNotSame($completed->created_by, $completed->completed_by);
    }

    /** Both actors have to be reconstructible afterwards. */
    public function test_completion_records_both_actors_in_the_audit_trail(): void
    {
        $transfer = $this->draftTransferBy($this->maker);

        $this->actingAs($this->checker)->post(route('admin.stock-transfers.complete', $transfer));

        $event = AuditEvent::query()
            ->forSubject($transfer)
            ->where('action', AuditAction::Approved)
            ->firstOrFail();

        $this->assertSame($this->checker->getKey(), $event->actor_id);
        $this->assertSame($this->maker->getKey(), (int) $transfer->fresh()->created_by);
    }

    /** Completing twice must not move the stock twice. */
    public function test_completing_twice_does_not_duplicate_the_movements(): void
    {
        $transfer = $this->draftTransferBy($this->maker);

        $this->actingAs($this->checker)->post(route('admin.stock-transfers.complete', $transfer));
        $this->actingAs($this->checker)->post(route('admin.stock-transfers.complete', $transfer));

        $this->assertSame(2, InventoryMovement::query()->where('reference', $transfer->number)->count());
    }

    /** A stock adjustment is the one movement that can reduce stock on a reason alone. */
    public function test_an_adjustment_records_the_system_quantity_and_the_delta(): void
    {
        $this->actingAs($this->maker)
            ->post(route('admin.inventory.store'), [
                'product_id' => $this->product->getKey(),
                'type' => InventoryMovementType::Adjustment->value,
                'adjustment_mode' => 'absolute',
                'quantity' => 80,
                'note' => 'Kiem ke cuoi thang, thieu 20',
                'occurred_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasNoErrors();

        $movement = InventoryMovement::query()
            ->where('type', InventoryMovementType::Adjustment)
            ->latest('id')
            ->firstOrFail();

        $event = AuditEvent::query()->forSubject($movement)->firstOrFail();

        $this->assertSame('100.00', $event->after['stock_before']);
        $this->assertSame('80.00', $event->after['stock_after']);
        $this->assertSame($this->maker->getKey(), $event->actor_id);
    }

    /** The reason has to say something a reviewer can act on. */
    public function test_an_adjustment_needs_a_substantive_reason(): void
    {
        $this->actingAs($this->maker)
            ->from(route('admin.inventory.create'))
            ->post(route('admin.inventory.store'), [
                'product_id' => $this->product->getKey(),
                'type' => InventoryMovementType::Adjustment->value,
                'adjustment_mode' => 'absolute',
                'quantity' => 80,
                'note' => 'Sai',
                // 'Sai' quá ngắn để người soát sau hiểu được chuyện gì.
                'occurred_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('note');
    }

    /** Ordinary in/out movements are unaffected by the adjustment rules. */
    public function test_a_normal_stock_in_still_works_without_a_long_note(): void
    {
        $this->actingAs($this->maker)
            ->post(route('admin.inventory.store'), [
                'product_id' => $this->product->getKey(),
                'type' => InventoryMovementType::In->value,
                'quantity' => 10,
                'occurred_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasNoErrors();
    }

    private function draftTransferBy(User $author): StockTransfer
    {
        $transfer = StockTransfer::factory()->create([
            'source_branch_id' => $this->source->getKey(),
            'destination_branch_id' => $this->destination->getKey(),
            'created_by' => $author->getKey(),
            'status' => StockTransferStatus::Draft,
        ]);

        StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->getKey(),
            'product_id' => $this->product->getKey(),
            'quantity' => '5.00',
        ]);

        return $transfer;
    }
}
