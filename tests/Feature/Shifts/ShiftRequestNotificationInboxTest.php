<?php

namespace Tests\Feature\Shifts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShiftRequestNotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_sees_only_their_own_shift_request_notifications_and_opening_the_inbox_marks_them_read(): void
    {
        $user = User::factory()->employee()->create();
        $otherUser = User::factory()->employee()->create();

        $notification = DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ShiftRequestNotification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => [
                'message' => 'Đơn nghỉ của bạn đã được duyệt.',
                'work_date' => '2026-10-15',
                'url' => route('admin.shift-requests.index'),
            ],
        ]);
        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ShiftRequestNotification',
            'notifiable_type' => $otherUser->getMorphClass(),
            'notifiable_id' => $otherUser->getKey(),
            'data' => ['message' => 'Thông báo của tài khoản khác.'],
        ]);

        $this->actingAs($user)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Đơn nghỉ của bạn đã được duyệt.')
            ->assertDontSee('Thông báo của tài khoản khác.')
            ->assertSee('Đã xem');

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
