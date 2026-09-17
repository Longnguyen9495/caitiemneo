<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\InventoryMovementType;
use App\Enums\InvoiceStatus;
use App\Enums\OvertimeStatus;
use App\Enums\PaymentMethod;
use App\Enums\ShiftRequestStatus;
use App\Enums\StockTransferStatus;
use App\Models\AttendanceRecord;
use App\Models\CashTransaction;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\ShiftRequest;
use App\Models\StockTransfer;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Dịch một dòng audit sang tiếng Việt cho timeline thay đổi.
 *
 * Snapshot cố tình lưu giá trị thô — tên cột và giá trị backed enum — để lịch
 * sử vẫn đọc được dù nhãn hiển thị có đổi câu chữ về sau. Sự thô đó đúng cho
 * lưu trữ nhưng sai cho màn hình, nên việc dịch nằm ở lúc render chứ không
 * nằm ở lúc ghi.
 */
class AuditDictionary
{
    /**
     * Tên cột -> nhãn tiếng Việt.
     *
     * Nhiều cột dùng chung giữa các đối tượng (`status`, `note`, `type`) nên một
     * bảng phẳng phục vụ được mọi timeline; cột nào thiếu sẽ rơi về dạng đọc
     * được của chính khóa đó thay vì biến mất.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        // Hóa đơn
        'number' => 'Số hóa đơn',
        'status' => 'Trạng thái',
        'customer_name' => 'Tên khách',
        'customer_phone' => 'Số điện thoại khách',
        'subtotal' => 'Tạm tính',
        'discount' => 'Giảm giá',
        'total' => 'Tổng tiền',
        'employee_id' => 'Nhân viên',
        'commission_rate' => 'Tỷ lệ hoa hồng',
        'commission_amount' => 'Tiền hoa hồng',
        'commission_rate_source' => 'Nguồn tỷ lệ hoa hồng',
        'commission_rate_reason' => 'Lý do đổi tỷ lệ hoa hồng',
        'payment_method' => 'Hình thức thanh toán',
        'paid_at' => 'Thời điểm thanh toán',
        'cancelled_at' => 'Thời điểm hủy',
        'qualified_for_bill_kpi' => 'Tính vào KPI bill',
        'items' => 'Dòng dịch vụ',
        'service_id' => 'Dịch vụ',
        'quantity' => 'Số lượng',
        'unit_price' => 'Đơn giá',
        'line_total' => 'Thành tiền',
        'work_context' => 'Bối cảnh làm việc',

        // Chấm công
        'work_date' => 'Ngày làm',
        'shift_name' => 'Tên ca',
        'shift_value' => 'Công',
        'checked_in_at' => 'Giờ vào',
        'checked_out_at' => 'Giờ ra',
        'late_minutes' => 'Số phút đi muộn',
        'overtime_minutes' => 'Số phút tăng ca',
        'approved_overtime_minutes' => 'Số phút tăng ca được duyệt',
        'overtime_status' => 'Trạng thái tăng ca',
        'leave_date' => 'Ngày nghỉ',

        // Sổ thu chi
        'type' => 'Loại',
        'category' => 'Hạng mục',
        'amount' => 'Số tiền',
        'reference' => 'Chứng từ',
        'occurred_at' => 'Thời điểm phát sinh',
        'voided_at' => 'Thời điểm hủy bút toán',
        'void_reason' => 'Lý do hủy bút toán',

        // Kho và chuyển kho
        'source_branch_id' => 'Chi nhánh gửi',
        'destination_branch_id' => 'Chi nhánh nhận',
        'transferred_at' => 'Thời điểm chuyển',
        'product_id' => 'Sản phẩm',
        'product_name' => 'Tên sản phẩm',
        'unit_cost' => 'Giá vốn',
        'stock_before' => 'Tồn trước',
        'stock_after' => 'Tồn sau',
        'supplier_id' => 'Nhà cung cấp',

        // Cảnh báo rủi ro
        'review_status' => 'Trạng thái xử lý',
        'review_note' => 'Ghi chú xử lý',
        'reviewed_by' => 'Người xử lý',
        'assigned_at' => 'Thời điểm phân công',
        'assigned_by' => 'Người phân công',

        // Ca làm và đổi ca
        'from_status' => 'Trạng thái trước',
        'to_status' => 'Trạng thái sau',
        'request' => 'Yêu cầu',
        'shift_request_id' => 'Mã yêu cầu ca',
        'shift_assignment_id' => 'Mã phân ca',
        'original_shift_assignment_id' => 'Phân ca gốc',
        'replacement_shift_assignment_id' => 'Phân ca thay thế',
        'counter_shift_assignment_id' => 'Phân ca đối ứng',
        'replacement_employee_id' => 'Nhân viên thay ca',
        'requester_id' => 'Người yêu cầu',
        'recipient_id' => 'Người nhận',
        'scheduled_by' => 'Người xếp lịch',
        'work_shift_id' => 'Ca làm việc',
        'effective_from' => 'Áp dụng từ',
        'effective_to' => 'Áp dụng đến',

        // Dùng chung
        'id' => 'Mã',
        'name' => 'Tên',
        'note' => 'Ghi chú',
        'reason' => 'Lý do',
        'branch_id' => 'Chi nhánh',
        'actor_id' => 'Người thực hiện',
        'actor_name' => 'Tên người thực hiện',
        'created_by' => 'Người tạo',
        'completed_by' => 'Người hoàn tất',
    ];

    /**
     * Lớp đối tượng -> cột -> backed enum sở hữu giá trị đã lưu.
     *
     * Khóa theo đối tượng vì `status` của hóa đơn là một tập giá trị khác với
     * `status` của bản ghi chấm công, trong khi snapshot chỉ giữ chuỗi trần.
     *
     * @var array<class-string, array<string, class-string<BackedEnum>>>
     */
    private const ENUMS = [
        Invoice::class => [
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
        ],
        CashTransaction::class => [
            'type' => CashTransactionType::class,
            'category' => CashTransactionCategory::class,
            'payment_method' => PaymentMethod::class,
        ],
        AttendanceRecord::class => [
            'status' => AttendanceStatus::class,
            'overtime_status' => OvertimeStatus::class,
        ],
        StockTransfer::class => [
            'status' => StockTransferStatus::class,
        ],
        InventoryMovement::class => [
            'type' => InventoryMovementType::class,
        ],
        ShiftRequest::class => [
            'status' => ShiftRequestStatus::class,
            'from_status' => ShiftRequestStatus::class,
            'to_status' => ShiftRequestStatus::class,
        ],
    ];

    /**
     * Những cột mang chuỗi thường chứ không phải backed enum.
     *
     * @var array<string, array<string, string>>
     */
    private const VALUES = [
        'commission_rate_source' => [
            'branch_service' => 'Giá dịch vụ chi nhánh',
            'compensation_profile' => 'Hồ sơ hoa hồng',
            'none' => 'Không áp dụng',
        ],
    ];

    /** Những cột hiển thị theo ngày, không kèm giờ. */
    private const DATE_ONLY_FIELDS = [
        'work_date',
        'leave_date',
        'effective_from',
        'effective_to',
    ];

    public static function field(string $field): string
    {
        return self::FIELDS[$field] ?? Str::ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Một vế của thay đổi, bằng đúng cách gọi mà phần còn lại của app đang dùng.
     *
     * @param  class-string|null  $subjectType  giá trị `auditable_type` của audit event
     */
    public static function value(mixed $value, string $field, ?string $subjectType = null): string
    {
        if (is_string($value)) {
            if ($label = self::enumLabel($value, $field, $subjectType)) {
                return $label;
            }

            if ($label = self::VALUES[$field][$value] ?? null) {
                return $label;
            }

            if ($moment = self::dateLabel($value, $field)) {
                return $moment;
            }
        }

        return AuditValue::display($value);
    }

    /** @param  class-string|null  $subjectType */
    private static function enumLabel(string $value, string $field, ?string $subjectType): ?string
    {
        $enum = $subjectType === null ? null : (self::ENUMS[$subjectType][$field] ?? null);

        if ($enum === null) {
            return null;
        }

        $case = $enum::tryFrom($value);

        return $case !== null && method_exists($case, 'label') ? $case->label() : null;
    }

    /** Timestamp lưu dạng ISO; timeline đọc theo thứ tự ngày trước. */
    private static function dateLabel(string $value, string $field): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $value)) {
            return null;
        }

        try {
            $moment = CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }

        return in_array($field, self::DATE_ONLY_FIELDS, true)
            ? $moment->format('d/m/Y')
            : $moment->format('d/m/Y H:i');
    }
}
