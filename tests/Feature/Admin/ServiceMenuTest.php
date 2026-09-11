<?php

namespace Tests\Feature\Admin;

use App\Enums\ServiceCategory;
use App\Enums\ServiceUnit;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\ServiceMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
    }

    public function test_a_service_can_declare_a_price_range(): void
    {
        $this->actingAs($this->owner)
            ->post(route('admin.services.store'), [
                'name' => 'Vẽ móng',
                'category' => ServiceCategory::Decoration->value,
                'unit' => ServiceUnit::Finger->value,
                'price' => 5000,
                'price_min' => 5000,
                'price_max' => 30000,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $service = Service::query()->where('name', 'Vẽ móng')->firstOrFail();

        $this->assertTrue($service->hasPriceRange());
        $this->assertSame(ServiceUnit::Finger, $service->unit);
        $this->assertSame(ServiceCategory::Decoration, $service->category);
        $this->assertSame('5.000 đ – 30.000 đ', $service->formattedPriceRange());
    }

    public function test_a_service_without_a_range_is_treated_as_a_fixed_price(): void
    {
        $this->actingAs($this->owner)
            ->post(route('admin.services.store'), [
                'name' => 'Sơn gel',
                'category' => ServiceCategory::BasicNail->value,
                'unit' => ServiceUnit::Set->value,
                'price' => 130000,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $service = Service::query()->where('name', 'Sơn gel')->firstOrFail();

        $this->assertFalse($service->hasPriceRange());
        $this->assertNull($service->price_min);
        // Không có khoảng thì mọi mức giá đều hợp lệ.
        $this->assertTrue($service->priceIsWithinRange(999_000));
    }

    public function test_declaring_only_one_end_of_the_range_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from(route('admin.services.create'))
            ->post(route('admin.services.store'), [
                'name' => 'Thiếu trần',
                'category' => ServiceCategory::Decoration->value,
                'unit' => ServiceUnit::Finger->value,
                'price' => 10000,
                'price_min' => 10000,
            ])
            ->assertSessionHasErrors('price_max');

        $this->assertSame(0, Service::query()->count());
    }

    public function test_a_ceiling_below_the_floor_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from(route('admin.services.create'))
            ->post(route('admin.services.store'), [
                'name' => 'Khoảng ngược',
                'category' => ServiceCategory::Decoration->value,
                'unit' => ServiceUnit::Finger->value,
                'price' => 10000,
                'price_min' => 50000,
                'price_max' => 10000,
            ])
            ->assertSessionHasErrors('price_max');
    }

    /** Giá điền sẵn mà nằm ngoài khoảng thì cảnh báo sẽ bật vĩnh viễn. */
    public function test_a_default_price_outside_its_own_range_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from(route('admin.services.create'))
            ->post(route('admin.services.store'), [
                'name' => 'Giá lệch khoảng',
                'category' => ServiceCategory::Decoration->value,
                'unit' => ServiceUnit::Finger->value,
                'price' => 90000,
                'price_min' => 5000,
                'price_max' => 30000,
            ])
            ->assertSessionHasErrors('price');
    }

    public function test_price_range_checking_uses_integer_money_not_floats(): void
    {
        $service = Service::factory()->create([
            'price' => 10000,
            'price_min' => 10000,
            'price_max' => 50000,
        ]);

        $this->assertTrue($service->priceIsWithinRange(10000));
        $this->assertTrue($service->priceIsWithinRange(50000));
        $this->assertTrue($service->priceIsWithinRange('25000.00'));
        $this->assertFalse($service->priceIsWithinRange(9999));
        $this->assertFalse($service->priceIsWithinRange(50001));
        // Bỏ trống không phải là sai: dòng chưa nhập giá thì chưa cảnh báo.
        $this->assertTrue($service->priceIsWithinRange(null));
    }

    /** Sắp theo chuỗi sẽ cho Trang trí trước Nối móng, không khớp menu giấy. */
    public function test_services_are_listed_in_the_order_of_the_paper_menu(): void
    {
        Service::factory()->create(['name' => 'Charm đá', 'category' => ServiceCategory::Decoration, 'display_order' => 0]);
        Service::factory()->create(['name' => 'Nối móng đắp gel', 'category' => ServiceCategory::Extension, 'display_order' => 0]);
        Service::factory()->create(['name' => 'Sơn gel', 'category' => ServiceCategory::BasicNail, 'display_order' => 0]);

        $this->assertSame(
            ['Sơn gel', 'Nối móng đắp gel', 'Charm đá'],
            Service::query()->inMenuOrder()->pluck('name')->all(),
        );
    }

    public function test_the_menu_seeder_imports_the_whole_price_list(): void
    {
        Branch::factory()->create(['code' => 'CN-MENU']);

        $this->seed(ServiceMenuSeeder::class);

        $this->assertSame(26, Service::query()->count());
        $this->assertSame(14, Service::query()->whereNull('price_min')->count());
        $this->assertSame(12, Service::query()->whereNotNull('price_min')->count());

        $this->assertSame(10, Service::query()->inCategory(ServiceCategory::BasicNail)->count());
        $this->assertSame(4, Service::query()->inCategory(ServiceCategory::Extension)->count());
        $this->assertSame(12, Service::query()->inCategory(ServiceCategory::Decoration)->count());

        // Trang trí tính theo ngón, hai nhóm còn lại tính theo bộ.
        $this->assertSame(12, Service::query()->where('unit', ServiceUnit::Finger->value)->count());

        // Mọi chi nhánh đều được gán giá.
        $this->assertSame(26 * Branch::query()->count(), BranchService::query()->count());
    }

    /** "Tráng gương" có ở cả nhóm Nail cơ bản (180k/bộ) và Trang trí (15k/ngón). */
    public function test_the_same_name_may_exist_in_two_categories_with_different_prices(): void
    {
        Branch::factory()->create(['code' => 'CN-DUP2']);

        $this->seed(ServiceMenuSeeder::class);

        $byCategory = Service::query()
            ->where('name', 'like', 'Tráng gương%')
            ->get()
            ->groupBy(fn (Service $service): string => $service->category->value);

        $this->assertCount(1, $byCategory[ServiceCategory::Decoration->value]);
        $this->assertSame('15000.00', $byCategory[ServiceCategory::Decoration->value]->first()->price);
        $this->assertSame(ServiceUnit::Finger, $byCategory[ServiceCategory::Decoration->value]->first()->unit);

        // Hai dòng của nhóm Nail cơ bản là "có nền" và "không nền".
        $this->assertCount(2, $byCategory[ServiceCategory::BasicNail->value]);
    }

    public function test_the_seeder_does_not_overwrite_a_price_changed_by_hand(): void
    {
        Branch::factory()->create(['code' => 'CN-KEEP']);
        $this->seed(ServiceMenuSeeder::class);

        $service = Service::query()->where('name', 'Sơn gel (free sửa và cứng móng)')->firstOrFail();
        $service->update(['price' => 145000]);

        $this->seed(ServiceMenuSeeder::class);

        $this->assertSame('145000.00', $service->fresh()->price);
        $this->assertSame(26, Service::query()->count());
    }

    public function test_the_service_list_page_shows_groups_and_ranges(): void
    {
        Branch::factory()->create(['code' => 'CN-PAGE']);
        $this->seed(ServiceMenuSeeder::class);

        $this->actingAs($this->owner)
            ->get(route('admin.services.index', ['category' => ServiceCategory::Decoration->value]))
            ->assertOk()
            ->assertSee('Trang trí')
            ->assertSee('Giá theo ngón')
            ->assertSee('5.000 đ – 30.000 đ')
            ->assertDontSee('Sơn gel');
    }

    public function test_the_invoice_editor_offers_services_grouped_with_their_ranges(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-INV']);
        $this->seed(ServiceMenuSeeder::class);

        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();
        $invoice = Invoice::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($manager)
            ->get(route('admin.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('<optgroup label="Nail cơ bản">', false)
            ->assertSee('<optgroup label="Trang trí">', false)
            ->assertSee('data-range-label="5.000 đ – 30.000 đ"', false)
            ->assertSee('data-unit-label="Số ngón"', false);
    }
}
