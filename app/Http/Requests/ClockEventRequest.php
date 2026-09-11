<?php

namespace App\Http\Requests;

use App\Support\Coordinates;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The only three values a clocking employee is allowed to send.
 *
 * Everything else — who they are, which shift, which branch, what time it is —
 * is derived on the server. There is deliberately no employee_id, branch_id,
 * shift_assignment_id or timestamp in these rules: accepting them at all would
 * create an attack surface that validation then has to defend.
 */
class ClockEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->is_active;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            // The browser reports accuracy as a radius in metres. A huge value
            // means the fix came from the network rather than the satellites;
            // it is accepted here and refused later against the branch's own
            // threshold, so the employee gets a message that names the limit.
            'accuracy' => ['required', 'numeric', 'min:0', 'max:'.config('attendance.max_accuracy_meters', 100000)],
        ];
    }

    public function coordinates(): Coordinates
    {
        return Coordinates::make($this->input('latitude'), $this->input('longitude'));
    }

    public function accuracyMeters(): int
    {
        return (int) ceil((float) $this->input('accuracy'));
    }

    /**
     * The non-location evidence kept alongside the fix.
     *
     * @return array{ip: string|null, user_agent: string|null}
     */
    public function clientFingerprint(): array
    {
        return [
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'latitude' => 'vĩ độ',
            'longitude' => 'kinh độ',
            'accuracy' => 'độ chính xác GPS',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Không nhận được vị trí. Hãy bật định vị cho trình duyệt rồi thử lại.',
            'longitude.required' => 'Không nhận được vị trí. Hãy bật định vị cho trình duyệt rồi thử lại.',
            'accuracy.required' => 'Không đọc được độ chính xác của GPS. Hãy thử lại.',
        ];
    }
}
