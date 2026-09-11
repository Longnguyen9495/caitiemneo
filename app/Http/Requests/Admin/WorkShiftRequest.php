<?php

namespace App\Http\Requests\Admin;

use App\Models\WorkShift;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('work_shift');

        return $shift instanceof WorkShift
            ? $this->user()->can('update', $shift)
            : $this->user()->can('create', WorkShift::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $shift = $this->route('work_shift');

        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => [
                'required', 'string', 'max:60',
                // A shared template has a null branch, and MySQL treats NULLs
                // as distinct in a unique index, so the shared case is checked
                // here rather than left to the database.
                Rule::unique(WorkShift::class, 'name')
                    ->where(fn ($query) => $this->input('branch_id') === null
                        ? $query->whereNull('branch_id')
                        : $query->where('branch_id', $this->integer('branch_id')))
                    ->ignore($shift instanceof WorkShift ? $shift->getKey() : null),
            ],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
            'shift_value' => ['required', 'numeric', 'min:0', 'max:10'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'early_check_in_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $branchId = $this->input('branch_id');

        // "Dùng chung" comes through as an empty option value. Only the owner
        // may declare one, and a manager's choice is pinned to a branch they
        // actually run rather than whatever the form posted.
        $resolved = $branchId === null || $branchId === '' || $branchId === 'shared'
            ? null
            : (int) $branchId;

        if ($resolved !== null && ! $this->user()->canAccessBranch($resolved)) {
            $resolved = app(BranchContext::class)->currentId();
        }

        if ($resolved === null && ! $this->user()->isOwner()) {
            $resolved = app(BranchContext::class)->currentId();
        }

        $this->merge([
            'branch_id' => $resolved,
            'is_active' => $this->boolean('is_active'),
            'grace_minutes' => $this->input('grace_minutes', 0),
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'name' => 'tên ca',
            'starts_at' => 'giờ bắt đầu',
            'ends_at' => 'giờ kết thúc',
            'shift_value' => 'hệ số ca',
            'grace_minutes' => 'số phút ân hạn',
            'early_check_in_minutes' => 'số phút được vào ca sớm',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Đã có ca cùng tên trong phạm vi này.',
            'ends_at.different' => 'Giờ kết thúc phải khác giờ bắt đầu.',
        ];
    }
}
