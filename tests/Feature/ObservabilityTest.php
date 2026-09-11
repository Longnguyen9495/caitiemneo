<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Being able to answer "what happened" afterwards.
 *
 * Two different needs share this file. A request id ties a log line to the
 * audit rows from the same request, which is the difference between reading a
 * story and guessing at one from timestamps. And the doorway — who tried to
 * sign in and failed — leaves no trace anywhere else at all, so a burst of
 * guesses against one account is invisible unless it is written down.
 */
class ObservabilityTest extends TestCase
{
    use RefreshDatabase;

    /** Support can ask "what is the code on your screen" and find the request. */
    public function test_every_response_carries_a_request_id(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    /** Two requests must not share an id, or the id explains nothing. */
    public function test_each_request_gets_its_own_id(): void
    {
        $first = $this->get(route('home'))->headers->get('X-Request-Id');
        $second = $this->get(route('home'))->headers->get('X-Request-Id');

        $this->assertNotSame($first, $second);
    }

    public function test_a_failed_sign_in_is_written_to_the_security_log(): void
    {
        User::factory()->create(['email' => 'chu.tiem@example.test']);

        Log::shouldReceive('shareContext')->andReturnNull();
        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                // Đủ để nhận ra mẫu, không đủ để thu hoạch địa chỉ email.
                return $message === 'auth.failed'
                    && $context['identifier'] !== 'chu.tiem@example.test'
                    && str_contains((string) $context['identifier'], '*');
            });
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->post(route('login'), [
            'login' => 'chu.tiem@example.test',
            'password' => 'sai-mat-khau',
        ]);
    }

    /** The attempted password must never reach the log. */
    public function test_the_attempted_password_is_never_logged(): void
    {
        User::factory()->create(['email' => 'chu.tiem@example.test']);

        $logged = [];

        Log::shouldReceive('shareContext')->andReturnNull();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->andReturnUsing(function ($message, $context) use (&$logged): void {
            $logged[] = json_encode($context);
        });
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->post(route('login'), [
            'login' => 'chu.tiem@example.test',
            'password' => 'mat-khau-bi-mat',
        ]);

        foreach ($logged as $entry) {
            $this->assertStringNotContainsString('mat-khau-bi-mat', $entry);
        }
    }

    public function test_a_successful_sign_in_is_recorded(): void
    {
        $user = User::factory()->create([
            'email' => 'chu.tiem@example.test',
            'password' => Hash::make('mat-khau-rat-dai-123'),
        ]);

        Log::shouldReceive('shareContext')->andReturnNull();
        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'auth.login'
                && $context['user_id'] === $user->getKey());

        $this->post(route('login'), [
            'login' => 'chu.tiem@example.test',
            'password' => 'mat-khau-rat-dai-123',
        ]);
    }

    /**
     * The listener has to reach a real log file, not only a mock.
     *
     * Every other test here asserts against a mocked facade, which proves the
     * listener was called but not that the channel exists or is writable. This
     * one writes for real and reads the file back.
     */
    public function test_the_security_channel_writes_to_its_own_file(): void
    {
        $path = storage_path('logs/security-'.now()->format('Y-m-d').'.log');

        if (file_exists($path)) {
            unlink($path);
        }

        User::factory()->create(['email' => 'chu.tiem@example.test']);

        $this->post(route('login'), [
            'login' => 'chu.tiem@example.test',
            'password' => 'sai-mat-khau',
        ]);

        $this->assertFileExists($path, 'Kênh log bảo mật không ghi ra tệp riêng.');

        $contents = file_get_contents($path);

        $this->assertStringContainsString('auth.failed', $contents);
        // Mật khẩu đã thử và địa chỉ email đầy đủ đều không được có mặt.
        $this->assertStringNotContainsString('sai-mat-khau', $contents);
        $this->assertStringNotContainsString('chu.tiem@example.test', $contents);

        unlink($path);
    }

    /** A monitor has to be able to tell "running" from "working". */
    public function test_the_health_check_reports_each_dependency(): void
    {
        $this->get(route('health'))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok')
            ->assertJsonPath('checks.storage', 'ok');
    }

    /** The endpoint is public, so it must not describe the internals. */
    public function test_the_health_check_leaks_no_configuration(): void
    {
        $body = $this->get(route('health'))->getContent();

        foreach (['password', 'DB_', 'secret', 'APP_KEY', 'mysql', 'sqlite'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $body);
        }
    }
}
