<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Factories\BranchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_invoice_list_paginates_and_carries_the_filter_into_the_page_links(): void
    {
        $owner = User::factory()->owner()->create();
        Invoice::factory()->count(25)->create(['customer_name' => 'Khách quen', 'status' => InvoiceStatus::Draft]);

        $response = $this->actingAs($owner)
            ->get(route('admin.invoices.index', ['status' => InvoiceStatus::Draft->value, 'search' => 'Khách quen']))
            ->assertOk()
            ->assertSee('Trang 1/2');

        $response->assertSee('status=draft', false);
        $response->assertSee('page=2', false);
    }

    public function test_the_invoice_filters_narrow_the_result_set(): void
    {
        $owner = User::factory()->owner()->create();

        Invoice::factory()->create([
            'number' => 'NEO-PAID-1',
            'status' => InvoiceStatus::Paid,
            'payment_method' => PaymentMethod::Transfer,
            'paid_at' => now(),
        ]);
        Invoice::factory()->create(['number' => 'NEO-DRAFT-1', 'status' => InvoiceStatus::Draft]);

        $this->actingAs($owner)
            ->get(route('admin.invoices.index', ['status' => InvoiceStatus::Paid->value]))
            ->assertSee('NEO-PAID-1')
            ->assertDontSee('NEO-DRAFT-1');

        $this->actingAs($owner)
            ->get(route('admin.invoices.index', ['payment_method' => PaymentMethod::Cash->value]))
            ->assertDontSee('NEO-PAID-1')
            ->assertSee('Chưa có hóa đơn nào');
    }

    public function test_the_low_stock_filter_only_lists_products_below_their_minimum(): void
    {
        $owner = User::factory()->owner()->create();

        Product::factory()->create(['name' => 'Nước rửa sắp hết', 'minimum_stock' => 10]);
        $stocked = Product::factory()->create(['name' => 'Sơn còn nhiều', 'minimum_stock' => 1]);
        $stocked->movements()->create([
            'branch_id' => BranchFactory::resolveId(),
            'type' => 'in',
            'quantity' => 40,
            'unit_cost' => 1000,
            'occurred_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('admin.products.index', ['low_stock' => 1]))
            ->assertOk()
            ->assertSee('Nước rửa sắp hết')
            ->assertDontSee('Sơn còn nhiều');
    }

    public function test_the_service_form_keeps_old_input_and_shows_errors(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->from(route('admin.services.create'))
            ->post(route('admin.services.store'), ['name' => '', 'price' => -1])
            ->assertRedirect(route('admin.services.create'))
            ->assertSessionHasErrors(['name', 'price'])
            ->assertSessionHasInput('price', -1);

        $this->assertSame(0, Service::query()->count());
    }

    public function test_filters_survive_a_redirect_back_to_the_list(): void
    {
        $owner = User::factory()->owner()->create();
        Service::factory()->count(3)->create();

        $this->actingAs($owner)
            ->get(route('admin.services.index', ['search' => 'khong-ton-tai']))
            ->assertOk()
            ->assertSee('Xóa lọc')
            ->assertSee('Chưa có dịch vụ');
    }
}
