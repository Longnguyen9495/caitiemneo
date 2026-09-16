<?php

namespace Tests\Feature;

use App\Enums\ServiceCategory;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use App\Support\ProductGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trang công khai là mặt tiền quảng cáo của tiệm.
 *
 * Nó chỉ được trưng ra thứ tiệm bán thật, và album ảnh phải là bản đã nén —
 * ảnh gốc trong `public/images/products` nặng vài MB một tấm.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_gallery_has_been_built_for_the_web(): void
    {
        $this->assertFileExists(
            ProductGallery::manifestPath(),
            'Chưa có bản web của album: hãy chạy `php artisan gallery:build`.'
        );

        $photos = ProductGallery::all();

        $this->assertNotEmpty($photos);
        $this->assertStringContainsString('480w', $photos[0]['srcset']);
        $this->assertStringContainsString(ProductGallery::PUBLIC_PATH, $photos[0]['src']);
    }

    public function test_the_home_page_shows_the_gallery(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-gallery-rail', false);
        $response->assertSee(ProductGallery::all()[0]['src'], false);
    }

    public function test_the_price_list_shows_a_service_a_branch_sells(): void
    {
        $branch = Branch::factory()->create();
        $service = Service::factory()->create([
            'name' => 'Vẽ móng hoạ tiết',
            'category' => ServiceCategory::Decoration,
            'is_active' => true,
        ]);

        BranchService::factory()->create([
            'branch_id' => $branch->getKey(),
            'service_id' => $service->getKey(),
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertSee('Vẽ móng hoạ tiết');
        $response->assertSee(ServiceCategory::Decoration->label());
    }

    /** Không quảng cáo dịch vụ mà không cơ sở nào đang nhận làm. */
    public function test_the_price_list_hides_a_service_no_branch_sells(): void
    {
        Service::factory()->create(['name' => 'Đắp bột kim tuyến', 'is_active' => true]);

        $this->get('/')->assertDontSee('Đắp bột kim tuyến');
    }

    public function test_the_price_list_hides_a_service_every_branch_stopped_selling(): void
    {
        $branch = Branch::factory()->create();
        $service = Service::factory()->create(['name' => 'Nối móng bột', 'is_active' => true]);

        BranchService::factory()->create([
            'branch_id' => $branch->getKey(),
            'service_id' => $service->getKey(),
            'is_active' => false,
        ]);

        $this->get('/')->assertDontSee('Nối móng bột');
    }
}
