<?php

namespace Tests\Feature;

use App\Enums\FeedbackStatus;
use App\Models\Branch;
use App\Models\Feedback;
use App\Models\GalleryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feedback khách gửi từ trang chủ.
 *
 * Đây là ô nhập tự do duy nhất mở cho người lạ mà nội dung của nó được in ra
 * mặt tiền của tiệm. Hai điều phải luôn đúng: không có đường nào để người gửi
 * tự đưa chữ của mình lên trang, và người duyệt phải là chủ hoặc quản lý.
 */
class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'author_name' => 'Chị Linh',
            'rating' => 5,
            'content' => 'Thợ làm kỹ, mẫu giống ảnh, tiệm sạch và thơm.',
        ], $extra);
    }

    public function test_a_visitor_can_send_feedback_and_it_waits_for_review(): void
    {
        $this->post(route('feedback.store'), $this->payload())
            ->assertRedirect(route('home').'#feedback')
            ->assertSessionHas('feedback_success');

        $feedback = Feedback::query()->firstOrFail();

        $this->assertSame(FeedbackStatus::Pending, $feedback->status);
        $this->assertNull($feedback->published_at);
        $this->assertNull($feedback->reviewed_by);
        $this->assertSame(5, $feedback->rating);
    }

    /**
     * Trạng thái không đọc từ biểu mẫu.
     *
     * Nếu đọc, bất kỳ ai cũng chỉ cần thêm một ô ẩn là đăng thẳng lên trang chủ
     * của tiệm — đúng cái mà hàng đợi duyệt sinh ra để ngăn.
     */
    public function test_a_sender_cannot_publish_their_own_feedback(): void
    {
        $this->post(route('feedback.store'), $this->payload([
            'status' => FeedbackStatus::Published->value,
            'published_at' => now()->toDateTimeString(),
        ]));

        $feedback = Feedback::query()->firstOrFail();

        $this->assertSame(FeedbackStatus::Pending, $feedback->status);
        $this->assertNull($feedback->published_at);
    }

    public function test_feedback_without_a_rating_is_refused(): void
    {
        $this->post(route('feedback.store'), $this->payload(['rating' => null]))
            ->assertRedirect(route('home').'#feedback')
            ->assertSessionHasErrors(['rating'], null, 'feedback');

        $this->assertSame(0, Feedback::query()->count());
    }

    public function test_a_rating_outside_the_five_stars_is_refused(): void
    {
        $this->post(route('feedback.store'), $this->payload(['rating' => 9]))
            ->assertSessionHasErrors(['rating'], null, 'feedback');

        $this->assertSame(0, Feedback::query()->count());
    }

    public function test_a_one_word_comment_is_refused(): void
    {
        $this->post(route('feedback.store'), $this->payload(['content' => 'ok']))
            ->assertSessionHasErrors(['content'], null, 'feedback');

        $this->assertSame(0, Feedback::query()->count());
    }

    public function test_the_home_page_shows_published_feedback_only(): void
    {
        $published = Feedback::factory()->published()->create([
            'author_name' => 'Chị Hà',
            'content' => 'Mẫu tay nào cũng vừa ý, tiệm tư vấn thật lòng.',
        ]);
        $pending = Feedback::factory()->create(['content' => 'Feedback này còn chờ duyệt.']);
        $rejected = Feedback::factory()->rejected()->create(['content' => 'Feedback này đã bị ẩn.']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee($published->author_name);
        $response->assertSee($published->content);
        $response->assertDontSee($pending->content);
        $response->assertDontSee($rejected->content);
    }

    public function test_the_home_page_shows_the_average_of_published_feedback(): void
    {
        Feedback::factory()->published()->create(['rating' => 5]);
        Feedback::factory()->published()->create(['rating' => 4]);
        // Chờ duyệt thì không được kéo điểm trung bình của trang.
        Feedback::factory()->create(['rating' => 1]);

        $response = $this->get('/');

        $response->assertSee('4.5');
        $response->assertSee('2 lượt đánh giá');
    }

    /**
     * Hai biểu mẫu trên cùng một trang phải có hai túi lỗi riêng.
     *
     * Gõ thiếu ở ô feedback mà làm biểu mẫu đặt lịch đỏ lên là cách nhanh nhất
     * để khách bỏ dở việc đặt lịch — thứ mang lại tiền cho tiệm.
     */
    public function test_a_failed_feedback_does_not_flag_the_booking_form(): void
    {
        $this->post(route('feedback.store'), $this->payload(['content' => '']));

        $response = $this->get('/');

        $response->assertSee('Feedback chưa gửi được');
        $response->assertDontSee('Vui lòng kiểm tra lại các thông tin sau');
    }

    public function test_a_manager_can_publish_feedback(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager()->atBranch($branch)->create();
        $feedback = Feedback::factory()->create();

        $filtered = route('admin.feedback.index', ['status' => 'pending']);

        $this->actingAs($manager)
            ->from($filtered)
            ->patch(route('admin.feedback.update', $feedback), ['status' => 'published'])
            ->assertRedirect($filtered);

        $feedback->refresh();

        $this->assertSame(FeedbackStatus::Published, $feedback->status);
        $this->assertNotNull($feedback->published_at);
        $this->assertSame($manager->getKey(), $feedback->reviewed_by);
        $this->get('/')->assertSee($feedback->content);
    }

    public function test_a_manager_can_take_feedback_back_off_the_page(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager()->atBranch($branch)->create();
        $feedback = Feedback::factory()->published()->create();

        $this->actingAs($manager)
            ->patch(route('admin.feedback.update', $feedback), ['status' => 'rejected']);

        $feedback->refresh();

        $this->assertSame(FeedbackStatus::Rejected, $feedback->status);
        $this->assertNull($feedback->published_at);
        $this->get('/')->assertDontSee($feedback->content);
    }

    /** Ẩn hay đăng là quyết định về mặt tiền của tiệm, không phải việc của thợ. */
    public function test_an_employee_cannot_moderate_feedback(): void
    {
        $branch = Branch::factory()->create();
        $employee = User::factory()->employee()->atBranch($branch)->create();
        $feedback = Feedback::factory()->create();

        $this->actingAs($employee)
            ->patch(route('admin.feedback.update', $feedback), ['status' => 'published'])
            ->assertForbidden();

        $this->actingAs($employee)
            ->delete(route('admin.feedback.destroy', $feedback))
            ->assertForbidden();

        $this->assertSame(FeedbackStatus::Pending, $feedback->refresh()->status);
    }

    public function test_an_owner_can_delete_spam(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $feedback = Feedback::factory()->create();

        $this->actingAs($owner)
            ->delete(route('admin.feedback.destroy', $feedback))
            ->assertRedirect(route('admin.feedback.index'));

        $this->assertSame(0, Feedback::query()->count());
    }

    /**
     * Feedback thật của khách là ảnh và video tiệm tải lên.
     *
     * Khu này lấy tệp từ album "Feedback khách" trong khu quản trị, nên nó hiện
     * ngay khi tiệm đăng, không phải chờ ai gõ gì.
     */
    public function test_the_home_page_shows_the_feedback_photos_and_videos_the_shop_uploaded(): void
    {
        GalleryItem::query()->delete();

        $shot = GalleryItem::factory()->feedback()->create();
        $clip = GalleryItem::factory()->feedback()->video()->create();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('feedback-wall', false);
        $response->assertSee($shot->url(), false);
        $response->assertSee($clip->url(), false);
    }

    /** Mẫu móng không phải lời khen: nó thuộc khu album, không vào khu feedback. */
    public function test_a_nail_design_does_not_appear_as_feedback(): void
    {
        GalleryItem::query()->delete();

        GalleryItem::factory()->create();

        $this->get('/')->assertDontSee('feedback-wall', false);
    }

    /** Màn hình mặc định là hàng đợi: cái đang chờ mới là việc phải làm. */
    public function test_the_review_screen_defaults_to_what_is_waiting(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager()->atBranch($branch)->create();
        $pending = Feedback::factory()->create(['content' => 'Feedback dang cho duyet.']);
        $published = Feedback::factory()->published()->create(['content' => 'Feedback da len trang.']);

        $response = $this->actingAs($manager)->get(route('admin.feedback.index'));

        $response->assertOk();
        $response->assertSee('Feedback khách');
        $response->assertSee($pending->content);
        $response->assertDontSee($published->content);
    }
}
