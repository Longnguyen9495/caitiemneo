<?php

namespace Tests\Feature\Admin;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(Product $product, string $type, mixed $quantity, array $extra = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => 50000,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
        ], $extra);
    }

    public function test_an_incoming_movement_raises_the_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create();

        $this->actingAs($owner)
            ->post(route('admin.inventory.store'), $this->payload($product, InventoryMovementType::In->value, 12))
            ->assertRedirect(route('admin.inventory.index'));

        $this->assertSame('12.00', Product::query()->withCurrentStock()->find($product->id)->current_stock);
    }

    public function test_an_outgoing_movement_is_stored_as_a_negative_delta(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create();
        InventoryMovement::factory()->create(['product_id' => $product->id, 'quantity' => 20]);

        $this->actingAs($owner)->post(route('admin.inventory.store'), $this->payload($product, InventoryMovementType::Out->value, 5));

        $movement = InventoryMovement::query()->where('type', InventoryMovementType::Out)->firstOrFail();

        $this->assertSame('-5.00', $movement->quantity);
        $this->assertSame('15.00', Product::query()->withCurrentStock()->find($product->id)->current_stock);
    }

    public function test_stock_can_never_go_negative(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create();
        InventoryMovement::factory()->create(['product_id' => $product->id, 'quantity' => 3]);

        $this->actingAs($owner)
            ->from(route('admin.inventory.create'))
            ->post(route('admin.inventory.store'), $this->payload($product, InventoryMovementType::Out->value, 10))
            ->assertRedirect(route('admin.inventory.create'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame('3.00', Product::query()->withCurrentStock()->find($product->id)->current_stock);
    }

    public function test_an_absolute_adjustment_reconciles_the_counted_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create();
        InventoryMovement::factory()->create(['product_id' => $product->id, 'quantity' => 10]);

        $this->actingAs($owner)->post(route('admin.inventory.store'), $this->payload(
            $product,
            InventoryMovementType::Adjustment->value,
            7,
            ['adjustment_mode' => 'absolute', 'note' => 'Kiểm kê cuối tháng'],
        ));

        $adjustment = InventoryMovement::query()->where('type', InventoryMovementType::Adjustment)->firstOrFail();

        $this->assertSame('-3.00', $adjustment->quantity);
        $this->assertSame('7.00', Product::query()->withCurrentStock()->find($product->id)->current_stock);
    }

    public function test_a_delta_adjustment_applies_the_signed_correction(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create();
        InventoryMovement::factory()->create(['product_id' => $product->id, 'quantity' => 10]);

        $this->actingAs($owner)->post(route('admin.inventory.store'), $this->payload(
            $product,
            InventoryMovementType::Adjustment->value,
            -4,
            ['adjustment_mode' => 'delta', 'note' => 'Hỏng hàng'],
        ));

        $this->assertSame('6.00', Product::query()->withCurrentStock()->find($product->id)->current_stock);
    }

    public function test_an_adjustment_without_a_reason_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create();

        $this->actingAs($owner)
            ->from(route('admin.inventory.create'))
            ->post(route('admin.inventory.store'), $this->payload(
                $product,
                InventoryMovementType::Adjustment->value,
                5,
                ['adjustment_mode' => 'absolute'],
            ))
            ->assertSessionHasErrors('note');
    }

    public function test_listing_products_does_not_fire_one_query_per_row(): void
    {
        $owner = User::factory()->owner()->create();

        Product::factory()->count(8)->create()->each(
            fn (Product $product) => InventoryMovement::factory()->count(2)->create(['product_id' => $product->id])
        );

        DB::enableQueryLog();
        $this->actingAs($owner)->get(route('admin.products.index'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(10, $queries, "Trang danh sách vật tư chạy {$queries} truy vấn, nghi ngờ lỗi N+1.");
    }

    public function test_the_dashboard_counts_low_stock_with_a_single_aggregate(): void
    {
        $owner = User::factory()->owner()->create();

        Product::factory()->count(5)->create(['minimum_stock' => 5]);
        $stocked = Product::factory()->create(['minimum_stock' => 1]);
        InventoryMovement::factory()->create(['product_id' => $stocked->id, 'quantity' => 50]);

        DB::enableQueryLog();
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(12, $queries, "Dashboard chạy {$queries} truy vấn, nghi ngờ lỗi N+1.");
    }

    public function test_an_employee_cannot_record_a_movement(): void
    {
        $employee = User::factory()->employee()->create();
        $product = Product::factory()->create();

        $this->actingAs($employee)
            ->post(route('admin.inventory.store'), $this->payload($product, InventoryMovementType::In->value, 5))
            ->assertForbidden();

        $this->assertSame(0, InventoryMovement::query()->count());
    }
}
