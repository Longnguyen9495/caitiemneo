<?php

namespace Tests\Feature;

use App\Actions\Appointments\SaveAppointmentAction;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\GalleryItem;
use Database\Factories\BranchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trang album là chỗ khách chọn mẫu rồi đặt lịch ngay tại tấm ảnh đó.
 *
 * Giá trị của trang nằm ở sợi dây nối giữa tấm ảnh và lịch hẹn: nếu mẫu khách
 * bấm vào không đi theo yêu cầu đặt lịch thì cái nút ấy chỉ là một biểu mẫu
 * bình thường, và thợ vẫn phải hỏi lại khách muốn làm mẫu nào.
 */
class LookbookPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'branch_id' => BranchFactory::resolveId(),
            'customer_name' => 'Khách xem album',
            'customer_phone' => '0911222333',
            'starts_at' => now()->addDays(2)->startOfHour()->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'source' => 'lookbook',
        ], $extra);
    }

    public function test_the_album_shows_photos_newest_first(): void
    {
        GalleryItem::query()->delete();

        $older = GalleryItem::factory()->create(['created_at' => now()->subDays(3)]);
        $newest = GalleryItem::factory()->create(['created_at' => now()]);

        $response = $this->get(route('lookbook'));

        $response->assertOk();
        $response->assertSeeInOrder([$newest->url(), $older->url()], false);
        $response->assertSee('data-photo-id="'.$newest->id.'"', false);
    }

    /** Video nằm ở khu riêng của trang giới thiệu, không phải mẫu để làm theo. */
    public function test_the_album_leaves_videos_out(): void
    {
        GalleryItem::query()->delete();

        $video = GalleryItem::factory()->video()->create();

        $this->get(route('lookbook'))->assertDontSee($video->url(), false);
    }

    public function test_an_empty_album_still_offers_a_way_to_reach_the_shop(): void
    {
        GalleryItem::query()->delete();

        $response = $this->get(route('lookbook'));

        $response->assertOk();
        $response->assertSee('0826 881 094');
    }

    public function test_the_home_page_points_at_the_album(): void
    {
        GalleryItem::factory()->create();

        $this->get('/')->assertSee(route('lookbook'), false);
    }

    public function test_a_booking_made_from_a_photo_keeps_that_photo(): void
    {
        $photo = GalleryItem::factory()->create();

        $this->post(route('booking.store'), $this->payload(['gallery_item_id' => $photo->id]))
            ->assertRedirect(route('lookbook'))
            ->assertSessionHas('booking_success');

        $this->assertSame($photo->id, Appointment::query()->firstOrFail()->gallery_item_id);
    }

    /** Khách đặt lịch từ trang giới thiệu thì vẫn về đúng khu đặt lịch ở đó. */
    public function test_a_booking_made_from_the_home_page_returns_there(): void
    {
        $this->post(route('booking.store'), $this->payload(['source' => 'home']))
            ->assertRedirect(route('home').'#dat-lich');
    }

    /**
     * Ô ẩn mang mã mẫu đến từ trình duyệt, nên nó là dữ liệu của người lạ.
     *
     * Một mã trỏ vào video hoặc vào thứ không tồn tại phải bị chặn ngay, thay
     * vì để lịch hẹn mang một tham chiếu gãy mà không ai biết.
     */
    public function test_a_reference_to_something_that_is_not_a_photo_is_refused(): void
    {
        $video = GalleryItem::factory()->video()->create();

        $this->from(route('lookbook'))
            ->post(route('booking.store'), $this->payload(['gallery_item_id' => $video->id]))
            ->assertRedirect(route('lookbook'))
            ->assertSessionHasErrors('gallery_item_id');

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_a_reference_to_a_missing_photo_is_refused(): void
    {
        $this->post(route('booking.store'), $this->payload(['gallery_item_id' => 9999]))
            ->assertSessionHasErrors('gallery_item_id');

        $this->assertSame(0, Appointment::query()->count());
    }

    /**
     * Biểu mẫu bị trả về thì hộp đặt lịch phải mở lại với đúng mẫu khách chọn.
     *
     * Nếu không, khách nhìn thấy một trang đầy ảnh và phải tự đoán vì sao yêu
     * cầu của mình biến mất.
     */
    public function test_the_album_reopens_the_form_with_the_photo_the_visitor_picked(): void
    {
        $photo = GalleryItem::factory()->create();

        $this->post(route('booking.store'), $this->payload([
            'gallery_item_id' => $photo->id,
            'customer_phone' => '',
        ]))->assertRedirect(route('lookbook'));

        $response = $this->get(route('lookbook'));

        $response->assertSee('data-booking-reopen', false);
        $response->assertSee('value="'.$photo->id.'" data-booking-photo-input', false);
    }

    /**
     * Sửa lịch hẹn trong khu quản trị không được làm mất mẫu khách đã chọn.
     *
     * Biểu mẫu ở đó không có ô chọn mẫu, nên "không gửi lên" phải có nghĩa là
     * giữ nguyên — nếu hiểu thành xóa thì chỉ cần đổi giờ hẹn một lần là thợ
     * mất luôn tấm ảnh khách trỏ vào.
     */
    public function test_editing_an_appointment_later_keeps_the_photo(): void
    {
        $photo = GalleryItem::factory()->create();

        $this->post(route('booking.store'), $this->payload(['gallery_item_id' => $photo->id]));

        $appointment = Appointment::query()->firstOrFail();

        app(SaveAppointmentAction::class)->handle([
            'branch_id' => $appointment->branch_id,
            'customer_name' => $appointment->customer_name,
            'customer_phone' => $appointment->customer_phone,
            'starts_at' => $appointment->starts_at->copy()->addHour(),
            'duration_minutes' => 90,
            'status' => AppointmentStatus::Confirmed->value,
        ], $appointment);

        $this->assertSame($photo->id, $appointment->fresh()->gallery_item_id);
    }
}
