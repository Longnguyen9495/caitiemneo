<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_user_can_update_their_phone_bank_details_and_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $avatar = UploadedFile::fake()->image('avatar.png');

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'phone' => '0901234567',
                'bank_name' => 'Vietcombank',
                'bank_account_holder' => 'NGUYEN VAN A',
                'bank_account_number' => '0123456789',
                'avatar' => $avatar,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('0901234567', $user->phone);
        $this->assertSame('Vietcombank', $user->bank_name);
        $this->assertSame('NGUYEN VAN A', $user->bank_account_holder);
        $this->assertSame('0123456789', $user->bank_account_number);
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    /**
     * Ô ngân hàng là danh mục đóng, không phải ô gõ tự do.
     *
     * Một cái tên gõ tay sai chính tả chỉ lộ ra vào ngày trả lương, lúc người
     * chuyển khoản không tìm thấy ngân hàng nào tên như vậy.
     */
    public function test_a_bank_outside_the_directory_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'bank_name' => 'Ngân hàng Vietcom',
            ])
            ->assertSessionHasErrors('bank_name');

        $this->assertNull($user->refresh()->bank_name);
    }

    public function test_the_profile_form_offers_the_bank_directory(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('searchableSelect(', escape: false);

        // Danh mục phải tới được trình duyệt, nếu không ô chọn sẽ rỗng mà
        // không có gì báo lỗi.
        foreach (['Vietcombank', 'Techcombank', 'Agribank'] as $bank) {
            $response->assertSee($bank, escape: false);
        }
    }

    public function test_replacing_an_avatar_removes_the_previous_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => 'avatars/old-avatar.jpg']);
        Storage::disk('public')->put($user->avatar_path, 'old avatar');

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('replacement.png'),
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        Storage::disk('public')->assertMissing('avatars/old-avatar.jpg');
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
