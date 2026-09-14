<?php

namespace Tests\Feature;

use App\Enums\RiskSeverity;
use App\Enums\ShiftRequestStatus;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StatusBadgeTest extends TestCase
{
    public function test_every_shift_request_status_gets_its_own_colour(): void
    {
        $variants = [];

        foreach (ShiftRequestStatus::cases() as $status) {
            $variants[$status->value] = $this->variantOf($status);
        }

        $this->assertNotContains(
            'secondary',
            array_diff_key($variants, [ShiftRequestStatus::Cancelled->value => true]),
            'Một trạng thái đang rơi về màu xám mặc định, nghĩa là nó thiếu tông riêng.',
        );

        $this->assertSame(
            count($variants),
            count(array_unique($variants)),
            'Hai trạng thái đang dùng chung một màu nên không phân biệt được: '.json_encode($variants, JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Nhóm enum rủi ro trả về tên màu trần ("warning") thay vì "is-warning".
     * Chip phải hiểu cả hai, nếu không chúng lặng lẽ hiện thành xám.
     */
    public function test_a_tone_written_without_the_is_prefix_still_gets_its_colour(): void
    {
        $this->assertSame('danger', $this->variantOf(RiskSeverity::High));
        $this->assertSame('warning', $this->variantOf(RiskSeverity::Medium));
    }

    private function variantOf(object $status): string
    {
        $html = Blade::render('<x-admin.status-badge :status="$status" />', ['status' => $status]);

        preg_match('/text-bg-(\w+)/', $html, $matches);

        return $matches[1] ?? '';
    }
}
