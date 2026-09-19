<?php

namespace App\Services\Ai;

/**
 * Khai báo các công cụ truy vấn mà model được phép tự gọi.
 *
 * Trước đây toàn bộ dữ liệu bị nhồi sẵn vào prompt dựa trên đoán từ khóa: đoán
 * trượt thì model không có dữ liệu để trả lời, đoán trúng thì prompt phình lên
 * hàng chục nghìn token. Cho model tự gọi công cụ với tham số nó chọn giải quyết
 * cả hai: nó lấy đúng thứ cần và chỉ lấy đúng lượng cần.
 *
 * Quan trọng hơn: có `find_appointment` và `find_customer` thì model không còn
 * phải đoán ID cho các action sửa/hủy — nó tra ra ID thật trước khi đề xuất.
 */
final class AiToolRegistry
{
    /**
     * Số bản ghi tối đa một công cụ được trả về trong một lần gọi.
     */
    public const MAX_LIMIT = 50;

    public const DEFAULT_LIMIT = 20;

    /** @return array<int, string> */
    public function names(): array
    {
        return array_keys($this->definitions());
    }

    public function supports(string $name): bool
    {
        return array_key_exists($name, $this->definitions());
    }

    /**
     * Schema theo chuẩn OpenAI function-calling để gửi kèm request.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toolSchemas(): array
    {
        return array_map(
            fn (array $definition): array => [
                'type' => 'function',
                'function' => [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'parameters' => $definition['parameters'],
                ],
            ],
            array_values($this->definitions()),
        );
    }

    /** @return array<string, array<string, mixed>>|null */
    public function definition(string $name): ?array
    {
        return $this->definitions()[$name] ?? null;
    }

    /**
     * Tham số ngày tháng dùng chung. Model luôn phải nêu khoảng rõ ràng thay vì
     * dựa vào một mặc định ẩn, vì mặc định ẩn chính là nguồn gốc trả lời sai
     * khoảng thời gian mà không ai phát hiện.
     *
     * @return array<string, mixed>
     */
    private function periodProperties(): array
    {
        return [
            'from' => [
                'type' => 'string',
                'description' => 'Ngày bắt đầu, định dạng YYYY-MM-DD. Bắt buộc.',
            ],
            'to' => [
                'type' => 'string',
                'description' => 'Ngày kết thúc, định dạng YYYY-MM-DD. Bắt buộc.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function limitProperty(): array
    {
        return [
            'type' => 'integer',
            'description' => 'Số bản ghi tối đa muốn lấy, 1-'.self::MAX_LIMIT.'. Mặc định '.self::DEFAULT_LIMIT.'. Chỉ lấy nhiều khi thật sự cần liệt kê.',
            'minimum' => 1,
            'maximum' => self::MAX_LIMIT,
        ];
    }

    /** @return array<string, mixed> */
    private function branchProperty(): array
    {
        return [
            'type' => 'integer',
            'description' => 'Chỉ lấy dữ liệu của một chi nhánh cụ thể. Bỏ trống để lấy toàn bộ chi nhánh người dùng được xem.',
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        return [
            'get_overview' => [
                'name' => 'get_overview',
                'description' => 'Bức tranh tổng quan một khoảng thời gian: doanh thu, số hóa đơn, lịch hẹn theo trạng thái, dịch vụ bán chạy, nhân viên doanh thu cao, hàng sắp hết. Dùng khi người dùng hỏi chung chung như "tình hình thế nào", "tóm tắt tháng này".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->periodProperties() + ['branch_id' => $this->branchProperty()],
                    'required' => ['from', 'to'],
                ],
            ],
            'get_invoices' => [
                'name' => 'get_invoices',
                'description' => 'Doanh thu và danh sách hóa đơn trong khoảng thời gian. Trả về tổng đã thanh toán, tổng đã tạo, thống kê theo trạng thái và chi tiết từng hóa đơn.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->periodProperties() + [
                        'branch_id' => $this->branchProperty(),
                        'limit' => $this->limitProperty(),
                        'status' => [
                            'type' => 'string',
                            'description' => 'Lọc theo trạng thái hóa đơn, ví dụ paid hoặc draft. Bỏ trống để lấy tất cả.',
                        ],
                        'group_by_day' => [
                            'type' => 'boolean',
                            'description' => 'Đặt true để nhận thêm doanh thu tách theo từng ngày — dùng khi cần so sánh ngày hoặc vẽ biểu đồ theo thời gian.',
                        ],
                    ],
                    'required' => ['from', 'to'],
                ],
            ],
            'get_appointments' => [
                'name' => 'get_appointments',
                'description' => 'Lịch hẹn trong khoảng thời gian, kèm mã lịch hẹn thật để dùng cho thao tác sửa hoặc hủy.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->periodProperties() + [
                        'branch_id' => $this->branchProperty(),
                        'limit' => $this->limitProperty(),
                        'status' => [
                            'type' => 'string',
                            'description' => 'Lọc theo trạng thái lịch hẹn. Bỏ trống để lấy tất cả.',
                        ],
                    ],
                    'required' => ['from', 'to'],
                ],
            ],
            'find_appointment' => [
                'name' => 'find_appointment',
                'description' => 'Tìm lịch hẹn theo tên hoặc số điện thoại khách. BẮT BUỘC gọi công cụ này để lấy mã lịch hẹn trước khi đề xuất sửa hoặc hủy lịch — tuyệt đối không tự đoán mã.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Tên khách hoặc số điện thoại, có thể gõ một phần.',
                        ],
                        'from' => [
                            'type' => 'string',
                            'description' => 'Giới hạn từ ngày (YYYY-MM-DD). Bỏ trống sẽ tìm từ 30 ngày trước tới 90 ngày sau.',
                        ],
                        'to' => [
                            'type' => 'string',
                            'description' => 'Giới hạn đến ngày (YYYY-MM-DD).',
                        ],
                        'branch_id' => $this->branchProperty(),
                        'limit' => $this->limitProperty(),
                    ],
                    'required' => ['keyword'],
                ],
            ],
            'find_customer' => [
                'name' => 'find_customer',
                'description' => 'Tìm khách hàng theo tên hoặc số điện thoại, kèm số lần đã hẹn và lần gần nhất. Dùng khi người dùng nhắc tới một khách cụ thể.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Tên khách hoặc số điện thoại, có thể gõ một phần.',
                        ],
                        'limit' => $this->limitProperty(),
                    ],
                    'required' => ['keyword'],
                ],
            ],
            'get_services' => [
                'name' => 'get_services',
                'description' => 'Dịch vụ bán chạy theo doanh thu và số lượt trong khoảng thời gian. Trả về kèm mã dịch vụ để dùng khi tạo lịch hẹn.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->periodProperties() + [
                        'branch_id' => $this->branchProperty(),
                        'limit' => $this->limitProperty(),
                    ],
                    'required' => ['from', 'to'],
                ],
            ],
            'get_cash_flow' => [
                'name' => 'get_cash_flow',
                'description' => 'Thu chi tiền mặt trong khoảng thời gian: tổng thu, tổng chi, chênh lệch và các khoản chi tiết. Chỉ dùng khi người dùng có quyền xem sổ quỹ.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->periodProperties() + [
                        'branch_id' => $this->branchProperty(),
                        'limit' => $this->limitProperty(),
                    ],
                    'required' => ['from', 'to'],
                ],
            ],
            'get_inventory' => [
                'name' => 'get_inventory',
                'description' => 'Tồn kho hiện tại: hàng sắp hết so với định mức, kèm mã sản phẩm để dùng khi điều chỉnh kho.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'branch_id' => $this->branchProperty(),
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Lọc theo tên sản phẩm. Bỏ trống để chỉ lấy danh sách hàng sắp hết.',
                        ],
                        'limit' => $this->limitProperty(),
                    ],
                    'required' => [],
                ],
            ],
            'get_attendance' => [
                'name' => 'get_attendance',
                'description' => 'Chấm công trong khoảng thời gian: số buổi, số lượt đi muộn, thiếu chấm ra, và chi tiết từng bản ghi.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->periodProperties() + [
                        'branch_id' => $this->branchProperty(),
                        'limit' => $this->limitProperty(),
                        'employee_keyword' => [
                            'type' => 'string',
                            'description' => 'Lọc theo tên nhân viên. Bỏ trống để lấy tất cả.',
                        ],
                    ],
                    'required' => ['from', 'to'],
                ],
            ],
            'get_payroll' => [
                'name' => 'get_payroll',
                'description' => 'Bảng lương chồng lấn khoảng thời gian. Chỉ dùng khi người dùng có quyền xem báo cáo lợi nhuận.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->periodProperties() + [
                        'branch_id' => $this->branchProperty(),
                        'limit' => $this->limitProperty(),
                    ],
                    'required' => ['from', 'to'],
                ],
            ],
            'get_employees' => [
                'name' => 'get_employees',
                'description' => 'Danh sách nhân viên đang làm việc kèm mã nhân viên. Dùng khi cần gán nhân viên cho lịch hẹn.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'branch_id' => $this->branchProperty(),
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Lọc theo tên nhân viên.',
                        ],
                        'limit' => $this->limitProperty(),
                    ],
                    'required' => [],
                ],
            ],
        ];
    }
}
