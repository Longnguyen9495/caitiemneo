<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The booking form is the one page an unauthenticated stranger can post to.
 *
 * Everything it accepts is attacker-controlled by definition, so branch
 * scoping must hold here too: a booking must not be able to name a service that
 * its chosen branch does not offer. Staff allocation is always performed later
 * by management, never by the public request.
 */
class PublicBookingValidationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->branch = Branch::factory()->create();
        $this->otherBranch = Branch::factory()->create();

        $this->service = Service::factory()->create();

        BranchService::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'service_id' => $this->service->getKey(),
            'is_active' => true,
        ]);
    }

    public function test_a_valid_booking_is_accepted(): void
    {
        $this->post(route('booking.store'), $this->payload())->assertSessionHasNoErrors();

        $appointment = Appointment::query()->sole();

        $this->assertNull($appointment->employee_id);
    }

    /** A stranger cannot assign a worker, even with a crafted request payload. */
    public function test_a_public_employee_id_is_ignored(): void
    {
        $outsider = User::factory()->employee()->atBranch($this->otherBranch)->create();

        $this->post(route('booking.store'), $this->payload([
            'employee_id' => $outsider->getKey(),
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Appointment::query()->sole()->employee_id);
    }

    /** Nor a service that branch does not offer. */
    public function test_a_service_outside_the_branch_menu_is_refused(): void
    {
        $foreign = Service::factory()->create();

        $this->post(route('booking.store'), $this->payload([
            'service_ids' => [$foreign->getKey()],
        ]))->assertSessionHasErrors('service_ids.0');

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_an_inactive_branch_is_refused(): void
    {
        $closed = Branch::factory()->inactive()->create();

        $this->post(route('booking.store'), $this->payload([
            'branch_id' => $closed->getKey(),
        ]))->assertRedirect(route('home').'#dat-lich')->assertSessionHasErrors('branch_id');
    }

    /** Every field the form accepts has to be able to report its own error. */
    public function test_the_form_shows_an_error_for_every_field_it_accepts(): void
    {
        $this->from(route('home'))
            ->post(route('booking.store'), [])
            ->assertRedirect(route('home').'#dat-lich')
            ->assertSessionHasErrors(['branch_id', 'customer_name', 'customer_phone', 'starts_at']);

        // Đi theo đúng chuyển hướng: lỗi nằm trong session flash nên một request
        // rời rạc sẽ không thấy gì, và test sẽ xanh một cách vô nghĩa.
        $page = $this->followingRedirects()
            ->from(route('home'))
            ->post(route('booking.store'), [])
            ->assertOk();

        // Người đặt lịch phải đọc được lỗi ngay trên biểu mẫu, bất kể ngôn ngữ
        // validation được cấu hình cho môi trường chạy test.
        $page->assertSee('<small>', false);
        $page->assertSee('chi nhánh', false);
    }

    /**
     * Biểu mẫu phải hỏi đủ những gì máy chủ bắt buộc.
     *
     * `branch_id` là trường bắt buộc nhưng form công khai không hề có ô chọn
     * chi nhánh, nên **mọi lượt đặt lịch từ trang chủ đều thất bại**. Test cũ
     * không bắt được vì chúng gửi `branch_id` thẳng trong payload, không đi
     * qua biểu mẫu thật.
     */
    public function test_the_form_asks_for_every_field_the_server_requires(): void
    {
        $page = $this->get(route('home'))->assertOk();

        $page->assertSee('name="branch_id"', false);
        $page->assertSee('name="customer_name"', false);
        $page->assertSee('name="customer_phone"', false);
        $page->assertSee('name="starts_at"', false);
        $page->assertSee('name="duration_minutes"', false);
    }

    public function test_a_booking_in_the_past_is_refused(): void
    {
        $this->post(route('booking.store'), $this->payload([
            'starts_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ]))->assertRedirect(route('home').'#dat-lich')->assertSessionHasErrors('starts_at');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $this->branch->getKey(),
            'customer_name' => 'Khach dat online',
            'customer_phone' => '0900000000',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'service_ids' => [$this->service->getKey()],
        ], $overrides);
    }
}
