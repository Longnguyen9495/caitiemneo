<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Customer;
use App\Models\EmployeeBranchAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAppointmentAction
{
    /**
     * Create or update an appointment together with its customer and service lines.
     *
     * @param  array{branch_id: int|string, customer_name: string, customer_phone: string, employee_id?: int|string|null, starts_at: string, duration_minutes: int|string, status: string, note?: string|null, service_ids?: array<int, int|string>}  $data
     *
     * @throws ValidationException when the branch, the posting or the slot does not hold up
     */
    public function handle(array $data, ?Appointment $appointment = null): Appointment
    {
        $branchId = (int) $data['branch_id'];
        $startsAt = $data['starts_at'] instanceof Carbon
            ? $data['starts_at']->copy()
            : Carbon::parse($data['starts_at']);
        $durationMinutes = (int) $data['duration_minutes'];
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);
        $employeeId = isset($data['employee_id']) ? ((int) $data['employee_id'] ?: null) : null;

        $this->guardBranchIsActive($branchId);
        $this->guardEmployeeIsPosted($employeeId, $branchId, $startsAt);
        $this->guardAgainstOverlap($employeeId, $startsAt, $endsAt, $appointment?->getKey());

        return DB::transaction(function () use ($data, $appointment, $branchId, $employeeId, $startsAt, $endsAt, $durationMinutes): Appointment {
            $customer = $this->saveCustomer($data['customer_name'], $data['customer_phone']);

            $attributes = [
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'employee_id' => $employeeId,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $durationMinutes,
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
            ];

            if ($appointment === null) {
                $appointment = Appointment::query()->create($attributes);
            } else {
                $this->guardBranchChange($appointment, $branchId);
                $appointment->update($attributes);
                $appointment->services()->delete();
            }

            $this->syncServices($appointment, $data['service_ids'] ?? [], $branchId);

            return $appointment;
        });
    }

    /**
     * An employee may only be booked at a branch they are actually posted to on
     * the day of the appointment, not merely today.
     *
     * @throws ValidationException
     */
    public function guardEmployeeIsPosted(?int $employeeId, int $branchId, Carbon $startsAt): void
    {
        if (! $employeeId) {
            return;
        }

        $isPosted = EmployeeBranchAssignment::query()
            ->where('user_id', $employeeId)
            ->where('branch_id', $branchId)
            ->covering($startsAt->toDateString())
            ->exists();

        if (! $isPosted) {
            throw ValidationException::withMessages([
                'employee_id' => 'Nhân viên không được phân công tại chi nhánh này vào ngày đã chọn.',
            ]);
        }
    }

    /**
     * A person cannot be in two places at once, so the slot check deliberately
     * ignores the branch and looks at the employee across the whole company.
     *
     * @throws ValidationException
     */
    public function guardAgainstOverlap(?int $employeeId, Carbon $startsAt, Carbon $endsAt, int|string|null $ignoreAppointmentId = null): void
    {
        if (! $employeeId) {
            return;
        }

        $query = Appointment::query()
            ->active()
            ->where('employee_id', $employeeId)
            ->overlapping($startsAt, $endsAt);

        if ($ignoreAppointmentId) {
            $query->whereKeyNot($ignoreAppointmentId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => 'Nhân viên đã có lịch hẹn trong khung giờ này.',
            ]);
        }
    }

    /** @throws ValidationException */
    private function guardBranchIsActive(int $branchId): void
    {
        $isActive = Branch::query()->whereKey($branchId)->where('is_active', true)->exists();

        if (! $isActive) {
            throw ValidationException::withMessages([
                'branch_id' => 'Chi nhánh không hợp lệ hoặc đã ngừng hoạt động.',
            ]);
        }
    }

    /**
     * Revenue has to stay with the shop that earned it, so once an invoice
     * exists the appointment can no longer move branch.
     *
     * @throws ValidationException
     */
    private function guardBranchChange(Appointment $appointment, int $branchId): void
    {
        if ($appointment->branch_id === $branchId) {
            return;
        }

        if ($appointment->invoice()->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => 'Lịch hẹn đã phát sinh hóa đơn nên không thể đổi chi nhánh.',
            ]);
        }
    }

    private function saveCustomer(string $name, string $phone): Customer
    {
        $customer = Customer::query()->firstOrCreate(['phone' => $phone], ['name' => $name]);
        $customer->update(['name' => $name]);

        return $customer;
    }

    /**
     * Prices are snapshotted from the branch catalogue at booking time.
     *
     * @param  array<int, int|string>  $serviceIds
     */
    private function syncServices(Appointment $appointment, array $serviceIds, int $branchId): void
    {
        BranchService::query()
            ->with('service')
            ->where('branch_id', $branchId)
            ->whereIn('service_id', $serviceIds)
            ->get()
            ->each(fn (BranchService $branchService) => AppointmentService::query()->create([
                'appointment_id' => $appointment->id,
                'service_id' => $branchService->service_id,
                'price' => $branchService->price,
            ]));
    }
}
