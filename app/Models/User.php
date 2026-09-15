<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'username',
    'email',
    'password',
    'role',
    'phone',
    'avatar_path',
    'bank_name',
    'bank_account_holder',
    'bank_account_number',
    'base_salary',
    'shift_rate',
    'commission_rate',
    'can_manage_appointments',
    'can_create_invoices',
    'can_manage_payroll',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Kết quả {@see accessibleBranchIds()} đã tính trong request này.
     *
     * Chỉ sống trong một instance nên một lần ghi phân công mới vẫn thấy ngay
     * ở request kế tiếp; không phải cache bền.
     *
     * @var array<string, array<int, int>>
     */
    private array $accessibleBranchIdCache = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'base_salary' => 'decimal:2',
            'shift_rate' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'can_manage_appointments' => 'boolean',
            'can_create_invoices' => 'boolean',
            'can_manage_payroll' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::Employee;
    }

    /** Leadership: the two roles that run a branch or the company. */
    public function isLeadership(): bool
    {
        return $this->isOwner() || $this->isManager();
    }

    /**
     * May read takings — the money the shop has billed.
     *
     * Whoever rings up bills already sees every line they write, so hiding the
     * day's total from them buys nothing; a plain operator gets no figure.
     */
    public function canViewRevenueFigures(): bool
    {
        return $this->isLeadership() || $this->can_create_invoices;
    }

    /**
     * May read the cash fund position.
     *
     * Deliberately narrower than revenue: the fund balance is what a skim is
     * measured against, so it stays with the people accountable for the float.
     */
    public function canViewCashPosition(): bool
    {
        return $this->isLeadership();
    }

    /** @param array<int, UserRole|string> $roles */
    public function hasAnyRole(array $roles): bool
    {
        $values = array_map(
            fn (UserRole|string $role): string => $role instanceof UserRole ? $role->value : $role,
            $roles,
        );

        return in_array($this->role->value, $values, true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role', [UserRole::Owner->value, UserRole::Manager->value, UserRole::Employee->value]);
    }

    /**
     * Staff posted to any of the given branches over a period.
     *
     * A single date asks "who works here that day"; adding an end date asks
     * "who works here at any point in these days". An empty branch list means
     * the caller has no branch restriction, so everybody stays in view.
     *
     * @param  array<int, int>  $branchIds
     */
    public function scopePostedTo(Builder $query, array $branchIds, mixed $onDate = null, mixed $until = null): Builder
    {
        if ($branchIds === []) {
            return $query;
        }

        return $query->whereHas('branchAssignments', fn (Builder $assignment) => $assignment
            ->whereIn('branch_id', $branchIds)
            ->when(
                $onDate !== null && $until === null,
                fn (Builder $inner) => $inner->covering($onDate),
            )
            ->when(
                $until !== null,
                fn (Builder $inner) => $inner->overlappingWindow($onDate, $until),
            ));
    }

    public function branchAssignments(): HasMany
    {
        return $this->hasMany(EmployeeBranchAssignment::class, 'user_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'employee_branch_assignments')
            ->withPivot(['is_primary', 'starts_on', 'ends_on'])
            ->withTimestamps();
    }

    public function compensationProfiles(): HasMany
    {
        return $this->hasMany(EmployeeCompensationProfile::class, 'user_id');
    }

    public function policyAssignments(): HasMany
    {
        return $this->hasMany(EmployeePolicyAssignment::class, 'user_id');
    }

    public function fixedShifts(): HasMany
    {
        return $this->hasMany(EmployeeFixedShift::class, 'employee_id');
    }

    public function monthlyPaidLeaveDays(): HasMany
    {
        return $this->hasMany(MonthlyPaidLeaveDay::class, 'employee_id');
    }

    public function submittedShiftRequests(): HasMany
    {
        return $this->hasMany(ShiftRequest::class, 'requester_id');
    }

    public function receivedShiftRequests(): HasMany
    {
        return $this->hasMany(ShiftRequest::class, 'recipient_id');
    }

    /**
     * Ids of the branches this account may act in on a given business date.
     *
     * The owner is deliberately not tied to assignments: they run the whole
     * company and always see every active branch.
     *
     * @return array<int, int>
     */
    public function accessibleBranchIds(mixed $onDate = null): array
    {
        // Nhớ trong phạm vi một request. Mỗi dòng của danh sách đều gọi
        // @can(...) và mỗi lần như vậy lại hỏi lại cơ sở dữ liệu — với owner
        // thì đó là một truy vấn "mọi chi nhánh" cho từng dòng. Danh sách 18
        // dòng từng chạy 37 truy vấn chỉ vì việc này.
        $key = $onDate === null ? '*' : (string) ($onDate instanceof \DateTimeInterface ? $onDate->format('Y-m-d') : $onDate);

        if (array_key_exists($key, $this->accessibleBranchIdCache)) {
            return $this->accessibleBranchIdCache[$key];
        }

        $ids = $this->isOwner()
            ? Branch::query()->active()->orderBy('id')->pluck('id')->all()
            : $this->branchAssignments()
                ->when($onDate !== null, fn ($query) => $query->covering($onDate))
                ->orderBy('branch_id')
                ->pluck('branch_id')
                ->unique()
                ->values()
                ->all();

        return $this->accessibleBranchIdCache[$key] = $ids;
    }

    /**
     * Whether this account has touched money the books still refer to.
     *
     * Deleting such an account would leave every invoice they raised and every
     * entry they made pointing at nobody: the totals would still add up, but
     * "who did this" would have no answer for the whole of their history.
     */
    public function hasFinancialHistory(): bool
    {
        return $this->createdInvoices()->exists()
            || $this->cashTransactions()->exists()
            || $this->invoiceItems()->exists()
            || $this->inventoryMovements()->exists()
            || $this->payrolls()->exists();
    }

    /** Whether this account may act in one branch on a given business date. */
    public function canAccessBranch(int|Branch|null $branch, mixed $onDate = null): bool
    {
        $branchId = $branch instanceof Branch ? $branch->getKey() : $branch;

        if ($branchId === null) {
            return false;
        }

        return in_array((int) $branchId, $this->accessibleBranchIds($onDate), true);
    }

    /** The branch this employee is primarily posted to on a given date. */
    public function primaryBranchId(mixed $onDate = null): ?int
    {
        $assignment = $this->branchAssignments()
            ->when($onDate !== null, fn ($query) => $query->covering($onDate))
            ->orderByDesc('is_primary')
            ->orderByDesc('starts_on')
            ->first();

        return $assignment?->branch_id;
    }

    public function assignedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'employee_id');
    }

    public function createdInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'employee_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'created_by');
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'created_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'employee_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'employee_id');
    }
}
