<?php

namespace App\Http\Requests\Admin;

use App\Enums\AttendanceStatus;
use App\Http\Requests\Concerns\ResolvesBranch;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceRecordRequest extends FormRequest
{
    use ResolvesBranch;

    public function authorize(): bool
    {
        $record = $this->route('attendance');

        return $record instanceof AttendanceRecord
            ? $this->user()->can('update', $record)
            : $this->user()->can('create', AttendanceRecord::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $record = $this->route('attendance');

        return [
            'branch_id' => $this->branchRules(),
            'employee_id' => ['required', Rule::exists(User::class, 'id')],
            'work_date' => ['required', 'date'],
            'shift_name' => [
                'required', 'string', 'max:60',
                Rule::unique(AttendanceRecord::class)
                    ->where(fn ($query) => $query
                        ->where('employee_id', $this->input('employee_id'))
                        ->whereDate('work_date', $this->date('work_date')?->toDateString()))
                    ->ignore($record instanceof AttendanceRecord ? $record->getKey() : null),
            ],
            'shift_value' => ['required', 'numeric', 'min:0', 'max:10'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'checked_in_at' => ['nullable', 'date'],
            'checked_out_at' => ['nullable', 'date', 'after:checked_in_at'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $record = $this->route('attendance');

        $this->merge([
            'branch_id' => $record instanceof AttendanceRecord
                ? $record->branch_id
                : $this->contextBranchId(),
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'employee_id' => 'nhân viên',
            'work_date' => 'ngày làm',
            'shift_name' => 'tên ca',
            'shift_value' => 'hệ số ca',
            'status' => 'trạng thái',
            'checked_in_at' => 'giờ vào',
            'checked_out_at' => 'giờ ra',
            'note' => 'ghi chú',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->branchMessages() + [
            'shift_name.unique' => 'Nhân viên này đã có ca cùng tên trong ngày.',
            'checked_out_at.after' => 'Giờ ra phải sau giờ vào.',
        ];
    }
}
