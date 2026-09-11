<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Đăng ký công khai đã được gỡ bỏ.
 *
 * Test cũ của Breeze khẳng định người lạ đăng ký được — đúng với một ứng dụng
 * mẫu, nhưng sai với hệ thống nội bộ của một tiệm. Xem SelfRegistrationTest để
 * biết lý do và các test thay thế.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_routes_no_longer_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }
}
