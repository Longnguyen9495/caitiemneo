<?php

namespace App\Http\Requests\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Requests\Concerns\ResolvesBranch;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends FormRequest
{
    use ResolvesBranch;

    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment
            ? $this->user()->can('update', $appointment)
            : $this->user()->can('create', Appointment::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => $this->branchRules(),
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'employee_id' => ['nullable', Rule::exists(User::class, 'id')->where('is_active', true)],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => [Rule::exists(Service::class, 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $appointment = $this->route('appointment');

        // An existing appointment keeps the branch it was booked at; moving one
        // between shops is a deliberate action, not a side effect of an edit.
        $this->merge([
            'branch_id' => $appointment instanceof Appointment
                ? $appointment->branch_id
                : $this->contextBranchId(),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->branchMessages();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'customer_name' => 'tên khách hàng',
            'customer_phone' => 'số điện thoại',
            'employee_id' => 'nhân viên',
            'starts_at' => 'thời gian bắt đầu',
            'duration_minutes' => 'thời lượng',
            'status' => 'trạng thái',
            'note' => 'ghi chú',
        ];
    }
}
