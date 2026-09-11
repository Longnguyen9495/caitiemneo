<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_logo_file_is_present_in_the_public_directory(): void
    {
        $this->assertFileExists(
            public_path('images/logo-neo.png'),
            'Thiếu logo: hãy lưu ảnh thương hiệu vào public/images/logo-neo.png'
        );
    }

    public function test_home_page_exposes_the_logo_as_favicon_and_og_image(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('images/logo-neo.png', false);
        $response->assertSee('property="og:image"', false);
    }

    public function test_login_page_exposes_the_logo_as_favicon(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('images/logo-neo.png', false);
    }

    public function test_authenticated_user_can_open_the_profile_settings_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('Hồ sơ của bạn');
        $response->assertSee('Thông tin hồ sơ');
        $response->assertSee('Đổi mật khẩu');
    }
}
