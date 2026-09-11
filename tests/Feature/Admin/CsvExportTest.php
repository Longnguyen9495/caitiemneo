<?php

namespace Tests\Feature\Admin;

use App\Enums\InventoryMovementType;
use App\Models\CashTransaction;
use App\Models\InventoryMovement;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cash_export_follows_the_current_filter(): void
    {
        $owner = User::factory()->owner()->create();

        CashTransaction::factory()->create(['note' => 'Trong kỳ', 'occurred_at' => '2026-08-10 09:00:00']);
        CashTransaction::factory()->create(['note' => 'Ngoài kỳ', 'occurred_at' => '2026-09-10 09:00:00']);

        $content = $this->actingAs($owner)->withConfirmedPassword()
            ->get(route('admin.reports.export.cash', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Trong kỳ', $content);
        $this->assertStringNotContainsString('Ngoài kỳ', $content);
    }

    public function test_the_inventory_export_lists_movements(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['name' => 'Sơn móng đỏ']);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovementType::In,
            'quantity' => 6,
            'reference' => 'PN-2026-01',
        ]);

        $content = $this->actingAs($owner)->withConfirmedPassword()
            ->get(route('admin.reports.export.inventory'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Sơn móng đỏ', $content);
        $this->assertStringContainsString('PN-2026-01', $content);
        $this->assertStringContainsString('Nhập kho', $content);
    }

    public function test_the_payroll_export_is_limited_to_payroll_managers(): void
    {
        $employee = User::factory()->employee()->create(['name' => 'Chị Thu']);
        Payroll::factory()->create(['employee_id' => $employee->id, 'total' => 5000000]);

        $content = $this->actingAs(User::factory()->owner()->create())
            ->withConfirmedPassword()
            ->get(route('admin.reports.export.payrolls'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Chị Thu', $content);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('admin.reports.export.payrolls'))
            ->assertForbidden();
    }

    public function test_an_employee_cannot_export_the_cash_book(): void
    {
        $this->actingAs(User::factory()->employee()->create())
            ->get(route('admin.reports.export.cash'))
            ->assertForbidden();
    }
}
