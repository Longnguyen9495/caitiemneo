<?php

namespace App\Models;

use App\Support\Coordinates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'address',
    'phone',
    'latitude',
    'longitude',
    'attendance_radius_meters',
    'attendance_accuracy_limit_meters',
    'gps_attendance_enabled',
    'is_active',
])]
class Branch extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'attendance_radius_meters' => 'integer',
            'attendance_accuracy_limit_meters' => 'integer',
            'gps_attendance_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function workShifts(): HasMany
    {
        return $this->hasMany(WorkShift::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeBranchAssignment::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function branchServices(): HasMany
    {
        return $this->hasMany(BranchService::class);
    }

    public function branchProducts(): HasMany
    {
        return $this->hasMany(BranchProduct::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'branch_services')
            ->withPivot(['price', 'duration_minutes', 'commission_rate', 'overtime_commission_rate', 'is_active'])
            ->withTimestamps();
    }

    public function payrollPolicies(): HasMany
    {
        return $this->hasMany(PayrollPolicy::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return $this->code.' · '.$this->name;
    }

    /**
     * Whether this shop can accept a GPS clock event right now.
     *
     * The flag alone is not enough: without coordinates there is nothing to
     * measure a distance against, so both must be present.
     */
    public function acceptsGpsAttendance(): bool
    {
        return $this->gps_attendance_enabled && $this->coordinates() !== null;
    }

    public function coordinates(): ?Coordinates
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return Coordinates::tryFrom($this->latitude, $this->longitude);
    }
}
