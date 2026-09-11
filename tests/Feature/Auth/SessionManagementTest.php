<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Người dùng tự nhìn thấy và tự kết thúc được các phiên của mình.
 *
 * Một thiết bị lạ đang đăng nhập là thứ nên phát hiện được ngay, chứ không
 * phải chỉ biết sau khi đã mất tiền.
 */
class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Suite chạy với session driver `array` cho nhanh, còn production dùng
     * `database`. Việc liệt kê và thu hồi phiên chỉ có nghĩa với driver
     * database, nên test chuyển sang đúng driver đó — kiểm tra nhánh thật sự
     * được dùng, không phải một nhánh chết.
     */
    protected function setUp(): void
    {
        putenv('SESSION_DRIVER=database');
        $_ENV['SESSION_DRIVER'] = 'database';

        parent::setUp();

        config(['session.driver' => 'database']);
    }

    protected function tearDown(): void
    {
        putenv('SESSION_DRIVER=array');
        $_ENV['SESSION_DRIVER'] = 'array';

        parent::tearDown();
    }

    public function test_the_profile_page_lists_the_accounts_sessions(): void
    {
        $user = User::factory()->owner()->create();
        $this->seedSessionFor($user, '203.0.113.10');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Phiên đăng nhập')
            ->assertSee('203.0.113.10');
    }

    public function test_a_user_can_end_their_other_sessions(): void
    {
        $user = User::factory()->owner()->create();
        $this->seedSessionFor($user, '203.0.113.10');

        $this->actingAs($user)
            ->delete(route('profile.sessions.revoke'))
            ->assertRedirect(route('profile.edit'));

        // Phiên đang thao tác được giữ lại — người dùng không tự đá mình ra.
        $this->assertDatabaseMissing('sessions', ['ip_address' => '203.0.113.10']);
    }

    /** Kết thúc phiên của mình không được đụng tới tài khoản khác. */
    public function test_ending_sessions_only_touches_the_actors_own_account(): void
    {
        $user = User::factory()->owner()->create();
        $somebodyElse = User::factory()->manager()->create();

        $this->seedSessionFor($user, '203.0.113.10');
        $this->seedSessionFor($somebodyElse, '203.0.113.20');

        $this->actingAs($user)->delete(route('profile.sessions.revoke'));

        $this->assertSame(1, DB::table('sessions')->where('user_id', $somebodyElse->getKey())->count());
    }

    public function test_a_guest_cannot_reach_the_session_controls(): void
    {
        $this->delete(route('profile.sessions.revoke'))->assertRedirect(route('login'));
    }

    private function seedSessionFor(User $user, string $ip): void
    {
        DB::table('sessions')->insert([
            'id' => 'phien-'.$user->getKey().'-'.str_replace('.', '', $ip),
            'user_id' => $user->getKey(),
            'ip_address' => $ip,
            'user_agent' => 'Trinh duyet thu nghiem',
            'payload' => '',
            'last_activity' => time(),
        ]);
    }
}
