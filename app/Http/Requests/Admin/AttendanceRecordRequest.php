<?php

namespace App\Http\Requests\Admin;

use App\Enums\AttendanceStatus;
use App\Http\Requests\Concerns\ResolvesBranch;
use App\Models\AttendanceRecord;
use App\Rules\AssignedToBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceRecordRequest extends FormRequest
{
    use ResolvesBranch;

    public function authorize(): bool
    {
        $record = $this->route('attendance');

        if ($record instanceof AttendanceRecord) {
            return $this->user()->can('update', $record);
        }

        // The employee is part of the authorization question here, not just of
        // the validation: writing a shift for yourself is the thing being
        // refused, so it must be answered before the form is even validated.
        return $this->user()->can('createFor', [AttendanceRecord::class, (int) $this->input('employee_id')]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $record = $this->route('attendance');

        return [
            'branch_id' => $this->branchRules(),
            // Hours may only be written for staff actually posted to this shop
            // on the day being recorded.
            'employee_id' => [
                'required', 'integer',
                new AssignedToBranch(
                    $record instanceof AttendanceRecord ? $record->branch_id : $this->contextBranchId(),
                    $this->date('work_date')?->toDateString(),
                ),
            ],
            // Công của ngày chưa tới thì chưa có thật.
            'work_date' => [
                'required', 'date',
                'before_or_equal:'.now()->toDateString(),
                'after_or_equal:'.now()->subDays((int) config('business.backdate_days'))->toDateString(),
            ],
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
            // Every hand-written shift carries a reason, because that is what
            // turns the audit entry from "somebody changed this" into
            // something a person can actually review later.
            'reason' => ['required', 'string', 'max:255'],
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
            'reason' => 'lý do',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->branchMessages() + [
            'shift_name.unique' => 'Nhân viên này đã có ca cùng tên trong ngày.',
            'reason.required' => 'Hãy ghi lý do nhập tay để lưu vào nhật ký chỉnh sửa.',
            'checked_out_at.after' => 'Giờ ra phải sau giờ vào.',
            'work_date.before_or_equal' => 'Không nhập được công cho ngày chưa tới.',
            'work_date.after_or_equal' => 'Công ghi lùi quá '.config('business.backdate_days').' ngày cần xử lý qua điều chỉnh bảng lương.',
        ];
    }
}
