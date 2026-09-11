<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * An account that has touched money cannot simply be erased.
 *
 * Deleting it would leave every invoice they raised and every entry they made
 * pointing at nobody — the books would still balance, but the question "who
 * did this" would have no answer for the whole of that person's history. That
 * is indistinguishable from tidying up after yourself, which is precisely why
 * it has to be refused rather than merely discouraged.
 *
 * Deactivating remains available and is the right tool: the person loses
 * access immediately and the trail stays readable.
 */
class ActorRetentionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
    }

    public function test_an_account_with_invoices_cannot_delete_itself(): void
    {
        $owner = $this->ownerWithPassword();

        Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $owner->getKey(),
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->actingAs($owner)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'mat-khau-rat-dai-123'])
            ->assertSessionHasErrors(['password'], null, 'userDeletion');

        $this->assertDatabaseHas('users', ['id' => $owner->getKey()]);
        $this->assertAuthenticated();
    }

    public function test_an_account_with_cash_entries_cannot_delete_itself(): void
    {
        $owner = $this->ownerWithPassword();

        CashTransaction::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $owner->getKey(),
        ]);

        $this->actingAs($owner)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'mat-khau-rat-dai-123'])
            ->assertSessionHasErrors(['password'], null, 'userDeletion');

        $this->assertDatabaseHas('users', ['id' => $owner->getKey()]);
    }

    /** An account that never touched money is free to go. */
    public function test_an_account_with_no_financial_history_can_be_deleted(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create([
            'password' => Hash::make('mat-khau-rat-dai-123'),
        ]);

        $this->actingAs($employee)
            ->delete(route('profile.destroy'), ['password' => 'mat-khau-rat-dai-123'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $employee->getKey()]);
    }

    /** Deactivating is the supported way to remove somebody's access. */
    public function test_deactivating_keeps_the_account_and_its_history(): void
    {
        $owner = $this->ownerWithPassword();
        $leaver = User::factory()->employee()->atBranch($this->branch)->create();

        Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $leaver->getKey(),
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withConfirmedPassword()
            ->put(route('admin.employees.update', $leaver), [
                'name' => $leaver->name,
                'email' => $leaver->email,
                'role' => $leaver->role->value,
                'is_active' => 0,
                'base_salary' => 0,
                'shift_rate' => 0,
                'commission_rate' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($leaver->fresh()->is_active);
        $this->assertDatabaseHas('invoices', ['created_by' => $leaver->getKey()]);
    }

    private function ownerWithPassword(): User
    {
        return User::factory()->owner()->atBranch($this->branch)->create([
            'password' => Hash::make('mat-khau-rat-dai-123'),
        ]);
    }
}
