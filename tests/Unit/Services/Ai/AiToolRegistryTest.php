<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\AiToolRegistry;
use Tests\TestCase;

class AiToolRegistryTest extends TestCase
{
    private function names(?array $domains): array
    {
        return array_map(
            fn (array $tool): string => $tool['function']['name'],
            (new AiToolRegistry)->toolSchemas($domains),
        );
    }

    public function test_filtering_by_domain_is_smaller_than_the_full_set(): void
    {
        $all = $this->names(null);
        $filtered = $this->names(['inventory']);

        $this->assertLessThan(count($all), count($filtered));
    }

    public function test_lookup_tools_are_always_offered(): void
    {
        // Người dùng hay nhắc tên khách giữa một câu hỏi về doanh thu. Thiếu hai
        // công cụ tra cứu này thì model quay lại đoán ID.
        foreach (['invoices', 'payroll', 'attendance', 'inventory'] as $domain) {
            $names = $this->names([$domain]);

            $this->assertContains('find_appointment', $names, "Miền {$domain} thiếu find_appointment.");
            $this->assertContains('find_customer', $names, "Miền {$domain} thiếu find_customer.");
            $this->assertContains('get_overview', $names, "Miền {$domain} thiếu get_overview.");
        }
    }

    public function test_each_domain_offers_its_own_tool(): void
    {
        $expected = [
            'invoices' => 'get_invoices',
            'appointments' => 'get_appointments',
            'cash' => 'get_cash_flow',
            'inventory' => 'get_inventory',
            'attendance' => 'get_attendance',
            'payroll' => 'get_payroll',
            'services' => 'get_services',
        ];

        foreach ($expected as $domain => $tool) {
            $this->assertContains($tool, $this->names([$domain]), "Miền {$domain} phải có {$tool}.");
        }
    }

    public function test_an_unknown_domain_still_returns_the_always_offered_tools(): void
    {
        // Bộ đoán miền có thể trả về thứ không nằm trong bảng; khi đó vẫn phải
        // còn đường tra cứu chứ không được trả về danh sách rỗng.
        $names = $this->names(['khong_ton_tai']);

        $this->assertNotEmpty($names);
        $this->assertContains('get_overview', $names);
    }

    public function test_every_offered_tool_is_executable(): void
    {
        $registry = new AiToolRegistry;

        // Gửi cho model một công cụ mà executor không nhận ra là hứa suông: nó
        // gọi rồi nhận về lỗi "công cụ không tồn tại".
        foreach ($registry->toolSchemas(null) as $tool) {
            $this->assertTrue(
                $registry->supports($tool['function']['name']),
                "Công cụ {$tool['function']['name']} được gửi đi nhưng không chạy được.",
            );
        }
    }
}
