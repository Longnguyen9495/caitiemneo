<?php

namespace Tests\Feature;

use App\Enums\ServiceCategory;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\GalleryItem;
use App\Models\Service;
use App\Support\ProductGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trang công khai là mặt tiền quảng cáo của tiệm.
 *
 * Nó chỉ được trưng ra thứ tiệm bán thật, và album phải xếp theo đúng thứ tự
 * tiệm mong đợi: đăng sau thì đứng trước.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_original_photo_batch_has_been_compressed_for_the_web(): void
    {
        $this->assertFileExists(
            ProductGallery::manifestPath(),
            'Chưa có bản web của bộ ảnh ban đầu: hãy chạy `php artisan gallery:build`.'
        );

        $this->assertNotEmpty(ProductGallery::manifestEntries());
    }

    public function test_the_home_page_shows_the_newest_photo_first(): void
    {
        GalleryItem::query()->delete();

        $older = GalleryItem::factory()->create(['created_at' => now()->subDays(3)]);
        $newest = GalleryItem::factory()->create(['created_at' => now()]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-gallery-rail', false);
        $response->assertSeeInOrder([$newest->url(), $older->url()], false);
    }

    public function test_the_home_page_shows_videos_in_their_own_section(): void
    {
        GalleryItem::query()->delete();

        $video = GalleryItem::factory()->video()->create();

        $response = $this->get('/');

        $response->assertSee('reel-rail', false);
        $response->assertSee($video->url(), false);
    }

    /** Album trống thì ẩn hẳn hai khu đó thay vì để lại tiêu đề chơ vơ. */
    public function test_an_empty_album_hides_the_gallery_and_the_videos(): void
    {
        GalleryItem::query()->delete();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('data-gallery-rail', false);
        $response->assertDontSee('reel-rail', false);
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
