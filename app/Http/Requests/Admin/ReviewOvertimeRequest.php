<?php

namespace App\Http\Requests\Admin;

use App\Enums\OvertimeStatus;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('attendance');

        return $record instanceof AttendanceRecord
            && $this->user()->can('reviewOvertime', $record);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'overtime_status' => ['required', Rule::in(array_keys(OvertimeStatus::decisionOptions()))],
            // The upper bound is checked again in ReviewOvertimeAction against
            // the row itself, because that is the only place that knows how
            // many minutes were actually detected.
            'approved_overtime_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'overtime_approval_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function decision(): OvertimeStatus
    {
        return OvertimeStatus::from($this->string('overtime_status')->toString());
    }

    public function approvedMinutes(): ?int
    {
        return $this->filled('approved_overtime_minutes')
            ? $this->integer('approved_overtime_minutes')
            : null;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'overtime_status' => 'quyết định',
            'approved_overtime_minutes' => 'số phút duyệt',
            'overtime_approval_note' => 'ghi chú',
        ];
    }
}
