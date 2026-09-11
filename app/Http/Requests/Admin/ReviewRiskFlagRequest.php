<?php

namespace App\Http\Requests\Admin;

use App\Enums\RiskReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewRiskFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('risk_flag'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'review_status' => ['required', Rule::in([
                RiskReviewStatus::Accepted->value,
                RiskReviewStatus::Dismissed->value,
            ])],
            // Kết luận không kèm lý do thì lần sau không ai hiểu vì sao đóng.
            'review_note' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'review_status' => 'kết luận',
            'review_note' => 'ghi chú xem xét',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'review_note.min' => 'Hãy ghi rõ kết luận để người sau hiểu được, ít nhất 10 ký tự.',
        ];
    }
}
