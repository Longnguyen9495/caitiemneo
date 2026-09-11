<?php

namespace Tests\Feature\Branch;

use App\Actions\Inventory\CompleteStockTransferAction;
use App\Actions\Inventory\RecordInventoryMovementAction;
use App\Enums\InventoryMovementType;
use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::factory()->create(['code' => 'CN-A']);
        $this->branchB = Branch::factory()->create(['code' => 'CN-B']);
        $this->product = Product::factory()->create(['name' => 'Sơn gel đỏ']);
    }

    private function stock(Branch $branch, int $quantity): void
    {
        InventoryMovement::factory()->create([
            'branch_id' => $branch->id,
            'product_id' => $this->product->id,
            'type' => InventoryMovementType::In,
            'quantity' => $quantity,
        ]);
    }

    private function balance(Branch $branch): string
    {
        return Product::query()->withCurrentStock($branch->id)->find($this->product->id)->current_stock;
    }

    private function transfer(int $quantity = 4): StockTransfer
    {
        $transfer = StockTransfer::factory()->create([
            'source_branch_id' => $this->branchA->id,
            'destination_branch_id' => $this->branchB->id,
        ]);

        $transfer->items()->create([
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_cost' => 50000,
        ]);

        return $transfer;
    }

    public function test_stock_is_counted_separately_per_branch(): void
    {
        $this->stock($this->branchA, 10);
        $this->stock($this->branchB, 3);

        $this->assertSame('10.00', $this->balance($this->branchA));
        $this->assertSame('3.00', $this->balance($this->branchB));
    }

    public function test_an_issue_at_one_branch_cannot_draw_on_another_branch_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $this->stock($this->branchB, 50);

        $this->expectException(ValidationException::class);

        app(RecordInventoryMovementAction::class)->handle([
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'type' => InventoryMovementType::Out->value,
            'quantity' => 1,
            'occurred_at' => now(),
        ], $owner);
    }

    public function test_completing_a_transfer_writes_one_movement_on_each_side(): void
    {
        $owner = User::factory()->owner()->create();
        $this->stock($this->branchA, 10);

        app(CompleteStockTransferAction::class)->handle($this->transfer(4), $owner);

        $this->assertSame('6.00', $this->balance($this->branchA));
        $this->assertSame('4.00', $this->balance($this->branchB));

        $this->assertSame(1, InventoryMovement::query()->where('branch_id', $this->branchA->id)->where('type', InventoryMovementType::Out)->count());
        $this->assertSame(1, InventoryMovement::query()->where('branch_id', $this->branchB->id)->where('type', InventoryMovementType::In)->where('quantity', 4)->count());
    }

    public function test_completing_twice_does_not_duplicate_the_movements(): void
    {
        $owner = User::factory()->owner()->create();
        $this->stock($this->branchA, 10);
        $transfer = $this->transfer(4);

        $action = app(CompleteStockTransferAction::class);
        $action->handle($transfer, $owner);
        $action->handle($transfer->fresh(), $owner);

        $this->assertSame(3, InventoryMovement::query()->count());
        $this->assertSame('6.00', $this->balance($this->branchA));
        $this->assertSame('4.00', $this->balance($this->branchB));
        $this->assertSame(StockTransferStatus::Completed, $transfer->fresh()->status);
    }

    public function test_a_transfer_larger_than_the_source_stock_is_refused(): void
    {
        $owner = User::factory()->owner()->create();
        $this->stock($this->branchA, 2);

        $this->expectException(ValidationException::class);

        app(CompleteStockTransferAction::class)->handle($this->transfer(9), $owner);
    }

    public function test_a_cancelled_transfer_moves_no_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $this->stock($this->branchA, 10);
        $transfer = $this->transfer(4);

        $this->actingAs($owner)->delete(route('admin.stock-transfers.cancel', $transfer))->assertRedirect();

        $this->assertSame(StockTransferStatus::Cancelled, $transfer->fresh()->status);
        $this->assertSame('10.00', $this->balance($this->branchA));
        $this->assertSame('0.00', $this->balance($this->branchB));
    }

    public function test_a_manager_cannot_send_stock_out_of_a_branch_they_do_not_run(): void
    {
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branchB)->create();
        $this->stock($this->branchA, 10);
        $transfer = $this->transfer(4);

        $this->actingAs($manager)->post(route('admin.stock-transfers.complete', $transfer))->assertForbidden();
        $this->assertSame('10.00', $this->balance($this->branchA));
    }

    public function test_the_transfer_form_refuses_a_source_branch_outside_the_users_reach(): void
    {
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branchB)->create();

        $this->actingAs($manager)
            ->from(route('admin.stock-transfers.create'))
            ->post(route('admin.stock-transfers.store'), [
                'source_branch_id' => $this->branchA->id,
                'destination_branch_id' => $this->branchB->id,
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('source_branch_id');
    }

    public function test_source_and_destination_must_differ(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->from(route('admin.stock-transfers.create'))
            ->post(route('admin.stock-transfers.store'), [
                'source_branch_id' => $this->branchA->id,
                'destination_branch_id' => $this->branchA->id,
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('destination_branch_id');
    }
}
