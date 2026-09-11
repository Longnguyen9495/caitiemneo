<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\EmployeeBranchAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The branch list is memoised per instance, so it must not outlive a change.
 *
 * Caching anything that decides access is worth being nervous about: if the
 * remembered answer survived a revoked posting, somebody removed from a branch
 * would keep reaching it. The cache lives on one model instance for one
 * request, and these tests hold that line.
 */
class BranchScopeCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_instance_sees_a_revoked_posting(): void
    {
        $branch = Branch::factory()->create();
        $employee = User::factory()->employee()->atBranch($branch)->create();

        $this->assertTrue($employee->canAccessBranch($branch->getKey()));

        EmployeeBranchAssignment::query()
            ->where('user_id', $employee->getKey())
            ->delete();

        // Request kế tiếp dựng lại model, nên phải thấy ngay thay đổi.
        $this->assertFalse($employee->fresh()->canAccessBranch($branch->getKey()));
    }

    public function test_a_fresh_instance_sees_a_new_posting(): void
    {
        $first = Branch::factory()->create();
        $second = Branch::factory()->create();
        $employee = User::factory()->employee()->atBranch($first)->create();

        $this->assertFalse($employee->canAccessBranch($second->getKey()));

        EmployeeBranchAssignment::query()->create([
            'branch_id' => $second->getKey(),
            'user_id' => $employee->getKey(),
            'is_primary' => false,
            'starts_on' => '2000-01-01',
        ]);

        $this->assertTrue($employee->fresh()->canAccessBranch($second->getKey()));
    }

    /** The dated question and the undated one must not share an answer. */
    public function test_a_dated_lookup_is_cached_separately(): void
    {
        $branch = Branch::factory()->create();
        $employee = User::factory()->employee()
            ->atBranch($branch, true, '2000-01-01', '2000-12-31')
            ->create();

        // Không kèm ngày: phân công vẫn tồn tại nên vẫn tính là có.
        $this->assertTrue($employee->canAccessBranch($branch->getKey()));

        // Kèm ngày hôm nay: phân công đã hết hạn nên phải là không.
        $this->assertFalse($employee->canAccessBranch($branch->getKey(), now()->toDateString()));
    }
}
