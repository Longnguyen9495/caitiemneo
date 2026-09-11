<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Headers that limit the damage of a mistake elsewhere.
 *
 * None of these stop an attack on their own; they narrow what a successful one
 * can do. A content policy turns an injected script into a blocked request, and
 * a frame policy stops the admin area being loaded invisibly inside somebody
 * else's page to harvest clicks.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_page_carries_the_baseline_headers(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_the_admin_area_carries_them_too(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    /** Geolocation is used for clocking in, and must not be lent to anybody else. */
    public function test_geolocation_is_limited_to_this_site(): void
    {
        $response = $this->get(route('home'));

        $policy = $response->headers->get('Permissions-Policy');

        $this->assertNotNull($policy, 'Thiếu Permissions-Policy.');
        $this->assertStringContainsString('geolocation=(self)', $policy);
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
    }

    public function test_a_content_security_policy_is_sent(): void
    {
        $response = $this->get(route('home'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, 'Thiếu Content-Security-Policy.');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    /**
     * The policy has to permit what the pages genuinely load, or it will be
     * switched off the first time a font fails to appear.
     */
    public function test_the_policy_allows_the_fonts_the_site_actually_uses(): void
    {
        $csp = $this->get(route('home'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
    }

    /**
     * Cookie hardening that only bites in production.
     *
     * The local setup runs over plain HTTP, so a secure-only cookie would lock
     * developers out. The check is therefore about the production posture: the
     * setting must be driven by config rather than left to chance.
     */
    public function test_the_session_cookie_is_hardened(): void
    {
        $this->assertTrue(config('session.http_only'), 'Cookie phiên phải chặn JavaScript đọc.');
        $this->assertContains(config('session.same_site'), ['lax', 'strict'], 'SameSite phải là lax hoặc strict.');
    }

    /**
     * Debug phải đọc từ môi trường, không được ghi cứng thành bật.
     *
     * Không thể kiểm tra giá trị thật của production từ trong test — ở đây
     * `APP_DEBUG` luôn bật. Thứ kiểm tra được, và cũng là thứ từng gây sự cố
     * thật, là `config/app.php` có ghi cứng `true` hay không: nếu có thì dù
     * `.env` của production đặt đúng cũng vô nghĩa.
     */
    public function test_debug_is_driven_by_the_environment(): void
    {
        $config = file_get_contents(config_path('app.php'));

        $this->assertStringContainsString(
            "'debug' => (bool) env('APP_DEBUG'",
            $config,
            'APP_DEBUG phải đọc từ môi trường, không được ghi cứng trong config.',
        );
    }

    /** A download must not be sniffed into something executable. */
    public function test_headers_are_present_on_a_non_html_response(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();

        $response = $this->actingAs($owner)->get(route('admin.reports.export.invoices'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
