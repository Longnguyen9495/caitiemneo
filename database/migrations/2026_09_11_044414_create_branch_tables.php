<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branch domain: the shops themselves, and the history of who works where.
 *
 * A default branch is created so every row that already exists has somewhere to
 * belong; the seeding step is idempotent and safe to re-run.
 */
return new class extends Migration
{
    public const DEFAULT_CODE = 'CN-01';

    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::create('employee_branch_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'branch_id', 'starts_on', 'ends_on'], 'eba_user_branch_window_index');
            $table->index(['branch_id', 'starts_on'], 'eba_branch_start_index');
        });

        $this->createDefaultBranch();
        $this->assignExistingUsers();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_branch_assignments');
        Schema::dropIfExists('branches');
    }

    /** Give the data that already exists a home, without duplicating on re-run. */
    private function createDefaultBranch(): void
    {
        if (DB::table('branches')->where('code', self::DEFAULT_CODE)->exists()) {
            return;
        }

        DB::table('branches')->insert([
            'code' => self::DEFAULT_CODE,
            'name' => 'Chi nhánh chính',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Every account that predates the branch model belongs to the default branch. */
    private function assignExistingUsers(): void
    {
        $branchId = DB::table('branches')->where('code', self::DEFAULT_CODE)->value('id');

        DB::table('users')
            ->select('id', 'created_at')
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($branchId): void {
                foreach ($users as $user) {
                    $alreadyAssigned = DB::table('employee_branch_assignments')
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($alreadyAssigned) {
                        continue;
                    }

                    DB::table('employee_branch_assignments')->insert([
                        'branch_id' => $branchId,
                        'user_id' => $user->id,
                        'is_primary' => true,
                        'starts_on' => $user->created_at ? Carbon\Carbon::parse($user->created_at)->toDateString() : '2000-01-01',
                        'ends_on' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};
