<?php

namespace Tests\Feature\Admin;

use App\Enums\GalleryMediaType;
use App\Models\Branch;
use App\Models\GalleryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Trang đăng ảnh/video cho album ngoài trang chủ.
 *
 * Đây là đường duy nhất để nội dung lạ đi thẳng lên mặt tiền công khai của
 * tiệm, nên phân quyền và bộ lọc định dạng phải chắc.
 */
class GalleryUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Migration bàn giao đã nạp sẵn bộ ảnh cũ vào album; xoá đi để mỗi bài
        // kiểm thử chỉ nhìn thấy đúng dữ liệu mình tạo ra.
        GalleryItem::query()->delete();

        $branch = Branch::factory()->create();
        $this->manager = User::factory()->manager()->atBranch($branch)->create();
    }

    public function test_the_album_page_lists_what_is_already_up(): void
    {
        $item = GalleryItem::factory()->create(['original_name' => 'mau-cu.jpg']);

        $response = $this->actingAs($this->manager)->get(route('admin.gallery.index'));

        $response->assertOk();
        $response->assertSee('mau-cu.jpg');
        $response->assertSee($item->thumbnailUrl(), false);
    }

    public function test_a_manager_uploads_a_photo_and_it_is_compressed_into_several_widths(): void
    {
        $response = $this->actingAs($this->manager)->post(route('admin.gallery.store'), [
            'media' => [UploadedFile::fake()->image('mau-moi.jpg', 1600, 2000)],
        ]);

        $response->assertRedirect(route('admin.gallery.index'));

        $item = GalleryItem::query()->sole();

        $this->assertSame(GalleryMediaType::Photo, $item->type);
        $this->assertSame('mau-moi.jpg', $item->original_name);
        $this->assertSame($this->manager->getKey(), $item->uploaded_by);
        $this->assertSame([480, 960, 1440], array_keys($item->sources));

        foreach ($item->sources as $path) {
            Storage::disk('public')->assertExists(Str::after($path, 'storage/'));
        }
    }

    public function test_an_uploaded_video_is_kept_as_a_video(): void
    {
        $this->actingAs($this->manager)->post(route('admin.gallery.store'), [
            'media' => [UploadedFile::fake()->create('quay-tai-tiem.mp4', 2048, 'video/mp4')],
        ]);

        $item = GalleryItem::query()->sole();

        $this->assertSame(GalleryMediaType::Video, $item->type);
        $this->assertNull($item->sources);
        Storage::disk('public')->assertExists(Str::after($item->path, 'storage/'));
    }

    /** Ảnh HEIC của iPhone GD không đọc được, nhận vào chỉ ra một ảnh hỏng. */
    public function test_an_unsupported_file_type_is_rejected(): void
    {
        $response = $this->actingAs($this->manager)->post(route('admin.gallery.store'), [
            'media' => [UploadedFile::fake()->create('anh.heic', 500, 'image/heic')],
        ]);

        $response->assertSessionHasErrors('media.0');
        $this->assertSame(0, GalleryItem::query()->count());
    }

    public function test_a_file_larger_than_nine_megabytes_is_rejected(): void
    {
        $response = $this->actingAs($this->manager)->post(route('admin.gallery.store'), [
            'media' => [UploadedFile::fake()->create('video-dai.mp4', 9217, 'video/mp4')],
        ]);

        $response->assertSessionHasErrors('media.0');
        $this->assertSame(0, GalleryItem::query()->count());
    }

    public function test_an_employee_cannot_upload_to_the_album(): void
    {
        $employee = User::factory()->employee()->atBranch(Branch::factory()->create())->create();

        $this->actingAs($employee)
            ->post(route('admin.gallery.store'), ['media' => [UploadedFile::fake()->image('a.jpg', 800, 800)]])
            ->assertForbidden();

        $this->assertSame(0, GalleryItem::query()->count());
    }

    public function test_removing_an_item_deletes_the_files_it_owns(): void
    {
        $this->actingAs($this->manager)->post(route('admin.gallery.store'), [
            'media' => [UploadedFile::fake()->image('go-di.jpg', 900, 1200)],
        ]);

        $item = GalleryItem::query()->sole();
        $paths = $item->filePaths();

        $this->actingAs($this->manager)
            ->delete(route('admin.gallery.destroy', $item))
            ->assertRedirect(route('admin.gallery.index'));

        $this->assertSame(0, GalleryItem::query()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing(Str::after($path, 'storage/'));
        }
    }

    /**
     * Bộ ảnh cũ nằm trong `public/images/products/web` và được dựng lại từ ảnh
     * gốc bằng `gallery:build`, nên gỡ khỏi album không được đụng tới tệp.
     */
    public function test_removing_an_imported_photo_leaves_the_original_batch_on_disk(): void
    {
        $imported = GalleryItem::factory()->create([
            'path' => 'images/products/web/anh-cu-1440.webp',
            'sources' => [480 => 'images/products/web/anh-cu-480.webp'],
        ]);

        $this->actingAs($this->manager)->delete(route('admin.gallery.destroy', $imported));

        $this->assertSame(0, GalleryItem::query()->whereKey($imported->getKey())->count());
        $this->assertFileExists(public_path('images/products/web/manifest.json'));
    }
}
