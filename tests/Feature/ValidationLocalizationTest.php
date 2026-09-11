<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Error messages in the language the shop actually speaks.
 *
 * The app runs with locale `vi` and falls back to `en`, and there was no
 * `lang/vi/validation.php` — so every rule without a hand-written message came
 * back as "The customer name field is required." A `attributes()` list only
 * renames the field; it does not translate the sentence around it.
 */
class ValidationLocalizationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>}> */
    public static function commonRules(): array
    {
        return [
            'required' => [['name' => ''], ['name' => 'required']],
            'email' => [['email' => 'khong-phai-email'], ['email' => 'email']],
            'numeric' => [['amount' => 'abc'], ['amount' => 'numeric']],
            'integer' => [['count' => 'abc'], ['count' => 'integer']],
            'min on a number' => [['amount' => 1], ['amount' => 'numeric|min:10']],
            'max on a string' => [['name' => 'aaaaaa'], ['name' => 'string|max:3']],
            'date' => [['day' => 'hom qua'], ['day' => 'date']],
            'after' => [['end' => '2020-01-01'], ['end' => 'date|after:2030-01-01']],
            'boolean' => [['flag' => 'co le'], ['flag' => 'boolean']],
            'array' => [['rows' => 'mot dong'], ['rows' => 'array']],
            'in' => [['choice' => 'khac'], ['choice' => 'in:a,b']],
            'confirmed' => [['password' => 'abc'], ['password' => 'confirmed']],
            'gt' => [['amount' => 1], ['amount' => 'numeric|gt:5']],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     */
    #[DataProvider('commonRules')]
    public function test_a_common_rule_speaks_vietnamese(array $data, array $rules): void
    {
        app()->setLocale('vi');

        $message = Validator::make($data, $rules)->errors()->first();

        $this->assertNotSame('', $message);
        $this->assertStringNotContainsString('The ', $message, "Thông báo còn tiếng Anh: {$message}");
        $this->assertStringNotContainsString(' field ', $message, "Thông báo còn tiếng Anh: {$message}");
        $this->assertStringNotContainsString('must be', $message, "Thông báo còn tiếng Anh: {$message}");
    }

    /** A nested row must not surface a raw key like `items.0.quantity`. */
    public function test_a_nested_field_reads_naturally(): void
    {
        app()->setLocale('vi');

        $message = Validator::make(
            ['items' => [['quantity' => '']]],
            ['items.*.quantity' => 'required'],
            [],
            ['items.*.quantity' => 'số lượng'],
        )->errors()->first();

        $this->assertStringNotContainsString('The ', $message);
        $this->assertStringContainsString('số lượng', $message);
    }

    /** The real forms have to come back readable, not just the rules in isolation. */
    public function test_an_invalid_booking_answers_in_vietnamese(): void
    {
        $response = $this->post(route('booking.store'), []);

        $errors = $this->errorsFrom($response);

        $this->assertNotSame([], $errors, 'Đặt lịch rỗng phải sinh lỗi.');

        foreach ($errors as $message) {
            $this->assertStringNotContainsString('The ', $message, "Thông báo còn tiếng Anh: {$message}");
        }
    }

    public function test_an_invalid_invoice_edit_answers_in_vietnamese(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager()->atBranch($branch)->create();
        $invoice = Invoice::factory()->create([
            'branch_id' => $branch->getKey(),
            'created_by' => $manager->getKey(),
            'status' => InvoiceStatus::Draft,
        ]);

        $response = $this->actingAs($manager)
            ->from(route('admin.invoices.edit', $invoice))
            ->patch(route('admin.invoices.update', $invoice), [
                'discount' => 'khong phai so',
                'items_submitted' => 1,
                'items' => [['name' => '', 'quantity' => 'abc', 'unit_price' => '']],
            ]);

        $response->assertInvalid();

        $errors = $this->errorsFrom($response);

        $this->assertNotSame([], $errors);

        foreach ($errors as $message) {
            $this->assertStringNotContainsString('The ', $message, "Thông báo còn tiếng Anh: {$message}");
        }
    }

    public function test_an_invalid_cash_void_answers_in_vietnamese(): void
    {
        $branch = Branch::factory()->create();
        $author = User::factory()->manager()->atBranch($branch)->create();
        $checker = User::factory()->manager()->atBranch($branch)->create();

        $transaction = CashTransaction::factory()->create([
            'branch_id' => $branch->getKey(),
            'created_by' => $author->getKey(),
        ]);

        $response = $this->actingAs($checker)
            ->from(route('admin.cash.index'))
            ->delete(route('admin.cash.destroy', $transaction), ['void_reason' => '']);

        $errors = $this->errorsFrom($response);

        $this->assertNotSame([], $errors);

        foreach ($errors as $message) {
            $this->assertStringNotContainsString('The ', $message, "Thông báo còn tiếng Anh: {$message}");
        }
    }

    /**
     * Các câu lỗi mà người dùng thực sự nhìn thấy sau khi bị chuyển hướng lại.
     *
     * @return array<int, string>
     */
    private function errorsFrom(TestResponse $response): array
    {
        $base = $response->baseResponse;

        if (! method_exists($base, 'getSession') || $base->getSession() === null) {
            return [];
        }

        $errors = $base->getSession()->get('errors');

        if ($errors instanceof ViewErrorBag) {
            return $errors->getBag('default')->all();
        }

        if ($errors instanceof MessageBag) {
            return $errors->all();
        }

        // Trước khi redirect được gửi đi, lỗi còn nằm ở dạng mảng thô.
        return is_array($errors) ? Arr::flatten($errors) : [];
    }
}
