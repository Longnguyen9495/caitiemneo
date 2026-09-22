<?php

namespace Tests\Feature\Admin;

use App\Enums\GalleryAlbum;
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
            'album' => 'showcase',
            'media' => [UploadedFile::fake()->image('mau-moi.jpg', 1600, 2000)],
        ]);

        $response->assertRedirect(route('admin.gallery.index', ['album' => 'showcase']));

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
            'album' => 'showcase',
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
            'album' => 'showcase',
            'media' => [UploadedFile::fake()->create('anh.heic', 500, 'image/heic')],
        ]);

        $response->assertSessionHasErrors('media.0');
        $this->assertSame(0, GalleryItem::query()->count());
    }

    public function test_a_file_larger_than_nine_megabytes_is_rejected(): void
    {
        $response = $this->actingAs($this->manager)->post(route('admin.gallery.store'), [
            'album' => 'showcase',
            'media' => [UploadedFile::fake()->create('video-dai.mp4', 9217, 'video/mp4')],
        ]);

        $response->assertSessionHasErrors('media.0');
        $this->assertSame(0, GalleryItem::query()->count());
    }

    public function test_an_employee_cannot_upload_to_the_album(): void
    {
        $employee = User::factory()->employee()->atBranch(Branch::factory()->create())->create();

        $this->actingAs($employee)
            ->post(route('admin.gallery.store'), ['album' => 'showcase', 'media' => [UploadedFile::fake()->image('a.jpg', 800, 800)]])
            ->assertForbidden();

        $this->assertSame(0, GalleryItem::query()->count());
    }

    public function test_removing_an_item_deletes_the_files_it_owns(): void
    {
        $this->actingAs($this->manager)->post(route('admin.gallery.store'), [
            'album' => 'showcase',
            'media' => [UploadedFile::fake()->image('go-di.jpg', 900, 1200)],
        ]);

        $item = GalleryItem::query()->sole();
        $paths = $item->filePaths();

        $this->actingAs($this->manager)
            ->delete(route('admin.gallery.destroy', $item))
            ->assertRedirect(route('admin.gallery.index', ['album' => 'showcase']));

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

    /**
     * Ảnh feedback đi vào khu feedback, không lẫn vào lưới mẫu móng.
     *
     * Đây là điểm dễ vỡ nhất của hai khu dùng chung một bảng: một ảnh chụp tin
     * nhắn nằm giữa lưới mẫu là mời khách đặt lịch làm "mẫu" đó.
     */
    public function test_a_photo_uploaded_to_the_feedback_album_stays_there(): void
    {
        $this->actingAs($this->manager)->post(route('admin.gallery.store'), [
            'album' => 'feedback',
            'media' => [UploadedFile::fake()->image('tin-nhan-khach.jpg', 1080, 1600)],
        ])->assertRedirect(route('admin.gallery.index', ['album' => 'feedback']));

        $item = GalleryItem::query()->sole();

        $this->assertSame(GalleryAlbum::Feedback, $item->album);
        $this->assertSame(GalleryMediaType::Photo, $item->type);

        // Trang album mẫu móng không được trưng nó ra.
        $this->get(route('lookbook'))->assertDontSee($item->url(), false);
    }

    /** Mỗi thẻ trong khu quản trị chỉ liệt kê tệp của chính khu đó. */
    public function test_each_album_tab_lists_only_its_own_files(): void
    {
        $showcase = GalleryItem::factory()->create(['original_name' => 'mau-mong.jpg']);
        $feedback = GalleryItem::factory()->feedback()->create(['original_name' => 'tin-nhan.jpg']);

        $this->actingAs($this->manager)
            ->get(route('admin.gallery.index', ['album' => 'showcase']))
            ->assertSee('mau-mong.jpg')
            ->assertDontSee('tin-nhan.jpg');

        $this->actingAs($this->manager)
            ->get(route('admin.gallery.index', ['album' => 'feedback']))
            ->assertSee('tin-nhan.jpg')
            ->assertDontSee('mau-mong.jpg');

        $this->assertSame(GalleryAlbum::Showcase, $showcase->album);
        $this->assertSame(GalleryAlbum::Feedback, $feedback->album);
    }

    /**
     * Thiếu khu thì từ chối, không đoán.
     *
     * Mặc định ngầm là "mẫu móng" nghĩa là một hôm nào đó biểu mẫu gửi thiếu
     * khóa này, ảnh tin nhắn khách sẽ lặng lẽ hiện ra giữa lưới mẫu.
     */
    public function test_an_upload_without_an_album_is_refused(): void
    {
        $this->actingAs($this->manager)->post(route('admin.gallery.store'), [
            'media' => [UploadedFile::fake()->image('khong-ro-khu.jpg', 900, 1200)],
        ])->assertSessionHasErrors('album');

        $this->assertSame(0, GalleryItem::query()->count());
    }
}
