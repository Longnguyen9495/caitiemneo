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
        if ($this->isOwner()) {
            return Branch::query()->active()->orderBy('id')->pluck('id')->all();
        }

        return $this->branchAssignments()
            ->when($onDate !== null, fn ($query) => $query->covering($onDate))
            ->orderBy('branch_id')
            ->pluck('branch_id')
            ->unique()
            ->values()
            ->all();
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
