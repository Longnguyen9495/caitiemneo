<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Separation of duties on the cash book.
 *
 * The cheapest way to hide a shortfall is to book a transaction and then quietly
 * amend or void it yourself. One person may write an entry, but a second has to
 * be the one who unwinds it — and the reason must be typed by a human, not
 * supplied by a hidden field.
 */
class CashMakerCheckerTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $maker;

    private User $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->maker = User::factory()->manager()->atBranch($this->branch)->create();
        $this->checker = User::factory()->manager()->atBranch($this->branch)->create();
    }

    public function test_the_author_may_not_void_their_own_transaction(): void
    {
        $transaction = $this->transactionBy($this->maker);

        $this->actingAs($this->maker)
            ->delete(route('admin.cash.destroy', $transaction), [
                'void_reason' => 'Ghi nham so tien',
            ])
            ->assertForbidden();

        $this->assertNull($transaction->fresh()->voided_at);
    }

    public function test_the_author_may_not_edit_their_own_transaction(): void
    {
        $transaction = $this->transactionBy($this->maker);

        $this->actingAs($this->maker)
            ->put(route('admin.cash.update', $transaction), [
                'type' => 'expense',
                'category' => 'rent',
                'amount' => 99000,
                'occurred_at' => now()->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->assertSame('1500000.00', $transaction->fresh()->amount);
    }

    public function test_a_second_person_in_the_branch_may_void_it(): void
    {
        $transaction = $this->transactionBy($this->maker);

        $this->actingAs($this->checker)
            ->delete(route('admin.cash.destroy', $transaction), [
                'void_reason' => 'Doi soat cuoi ca phat hien ghi trung',
            ])
            ->assertRedirect();

        $voided = $transaction->fresh();

        $this->assertNotNull($voided->voided_at);
        $this->assertSame($this->checker->getKey(), $voided->voided_by);
        $this->assertSame('Doi soat cuoi ca phat hien ghi trung', $voided->void_reason);
    }

    /** The void must carry a reason a person actually typed. */
    public function test_voiding_without_a_reason_is_rejected(): void
    {
        $transaction = $this->transactionBy($this->maker);

        $this->actingAs($this->checker)
            ->from(route('admin.cash.index'))
            ->delete(route('admin.cash.destroy', $transaction), ['void_reason' => ''])
            ->assertSessionHasErrors('void_reason');

        $this->assertNull($transaction->fresh()->voided_at);
    }

    /** A one-word reason is not a reason; it defeats the point of asking. */
    public function test_a_token_reason_is_rejected(): void
    {
        $transaction = $this->transactionBy($this->maker);

        $this->actingAs($this->checker)
            ->from(route('admin.cash.index'))
            ->delete(route('admin.cash.destroy', $transaction), ['void_reason' => 'Huy'])
            ->assertSessionHasErrors('void_reason');
    }

    /** Voiding twice must not produce a second void stamp or a new actor. */
    public function test_voiding_twice_is_idempotent(): void
    {
        $transaction = $this->transactionBy($this->maker);

        $this->actingAs($this->checker)->delete(route('admin.cash.destroy', $transaction), [
            'void_reason' => 'Doi soat cuoi ca phat hien ghi trung',
        ]);

        $firstStamp = $transaction->fresh()->voided_at;

        $this->actingAs($this->checker)->delete(route('admin.cash.destroy', $transaction), [
            'void_reason' => 'Bam lai lan hai',
        ]);

        $after = $transaction->fresh();

        $this->assertEquals($firstStamp, $after->voided_at);
        $this->assertSame('Doi soat cuoi ca phat hien ghi trung', $after->void_reason);
    }

    public function test_the_void_is_recorded_in_the_audit_trail_with_both_actors(): void
    {
        $transaction = $this->transactionBy($this->maker);

        $this->actingAs($this->checker)->delete(route('admin.cash.destroy', $transaction), [
            'void_reason' => 'Doi soat cuoi ca phat hien ghi trung',
        ]);

        $event = AuditEvent::query()
            ->forSubject($transaction)
            ->where('action', AuditAction::Voided)
            ->firstOrFail();

        $this->assertSame($this->checker->getKey(), $event->actor_id);
        $this->assertSame('Doi soat cuoi ca phat hien ghi trung', $event->reason);
        $this->assertSame($this->maker->getKey(), $transaction->created_by);
    }

    /** A manager from another branch is not a valid second pair of eyes. */
    public function test_a_manager_of_another_branch_may_not_void_it(): void
    {
        $transaction = $this->transactionBy($this->maker);
        $outsider = User::factory()->manager()->atBranch(Branch::factory()->create())->create();

        $this->actingAs($outsider)
            ->delete(route('admin.cash.destroy', $transaction), [
                'void_reason' => 'Khong phai chi nhanh cua toi',
            ])
            ->assertForbidden();

        $this->assertNull($transaction->fresh()->voided_at);
    }

    /** The list view must not ship a ready-made reason in a hidden field. */
    public function test_the_list_view_does_not_supply_a_default_void_reason(): void
    {
        $this->transactionBy($this->maker);

        $this->actingAs($this->checker)
            ->get(route('admin.cash.index'))
            ->assertOk()
            ->assertDontSee('name="void_reason" value="Hủy bởi người dùng"', false);
    }

    private function transactionBy(User $author): CashTransaction
    {
        return CashTransaction::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $author->getKey(),
            'amount' => '1500000.00',
        ]);
    }
}
