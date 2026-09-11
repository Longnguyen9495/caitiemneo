<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who is allowed to create an account at all.
 *
 * This is an internal system for one salon: accounts belong to people the owner
 * has hired. Public self-registration handed anybody who found the URL an
 * active `employee` account — which reaches the appointment book, the customer
 * names and phone numbers on it, and the stock list. Nobody has to break
 * anything; they just fill in a form.
 *
 * Accounts are created by the owner on the staff screen instead.
 */
class SelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_page_is_not_reachable(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_a_stranger_cannot_create_an_account(): void
    {
        $this->post('/register', [
            'name' => 'Nguoi la',
            'email' => 'nguoila@example.test',
            'password' => 'mat-khau-rat-dai-123',
            'password_confirmation' => 'mat-khau-rat-dai-123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'nguoila@example.test']);
        $this->assertGuest();
    }

    /** The owner still creates staff accounts from inside the admin area. */
    public function test_the_owner_still_creates_accounts_on_the_staff_screen(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->withConfirmedPassword()
            ->post(route('admin.employees.store'), [
                'name' => 'Nhan vien moi',
                'email' => 'nhanvien@example.test',
                'password' => 'mat-khau-rat-dai-123',
                'password_confirmation' => 'mat-khau-rat-dai-123',
                'role' => 'employee',
                'is_active' => 1,
                'base_salary' => 0,
                'shift_rate' => 0,
                'commission_rate' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'nhanvien@example.test']);
    }

    /** Signing in must keep working; only account creation is closed. */
    public function test_login_is_unaffected(): void
    {
        $this->get(route('login'))->assertOk();
    }
}
