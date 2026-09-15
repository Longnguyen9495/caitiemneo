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

    public function test_a_user_sees_only_their_own_notifications_and_opening_an_item_marks_only_it_read(): void
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
            ->assertSee('Đơn ca làm')
            ->assertSee('Đơn nghỉ của bạn đã được duyệt.')
            ->assertDontSee('Thông báo của tài khoản khác.')
            ->assertSee('Mới');

        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs($user)
            ->get(route('admin.notifications.show', $notification))
            ->assertRedirect(route('admin.shift-requests.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_open_another_users_notification(): void
    {
        $user = User::factory()->employee()->create();
        $otherUser = User::factory()->employee()->create();
        $notification = DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ShiftRequestNotification',
            'notifiable_type' => $otherUser->getMorphClass(),
            'notifiable_id' => $otherUser->getKey(),
            'data' => ['message' => 'Riêng tư.', 'url' => route('admin.shift-requests.index')],
        ]);

        $this->actingAs($user)
            ->get(route('admin.notifications.show', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}
