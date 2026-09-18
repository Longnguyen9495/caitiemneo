<?php

namespace Tests\Unit;

use App\Models\AttendanceRecord;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Support\AuditDictionary;
use PHPUnit\Framework\TestCase;

/**
 * The audit timeline is read by salon staff, not by developers.
 *
 * Snapshots store column names and raw enum values, so without this dictionary
 * the history panel reads `payment_method: — → transfer`. These tests pin the
 * translation, including the part that depends on which subject was audited.
 */
class AuditDictionaryTest extends TestCase
{
    public function test_it_names_columns_in_vietnamese(): void
    {
        $this->assertSame('Hình thức thanh toán', AuditDictionary::field('payment_method'));
        $this->assertSame('Tính vào KPI bill', AuditDictionary::field('qualified_for_bill_kpi'));
        $this->assertSame('Trạng thái', AuditDictionary::field('status'));
    }

    /** An unmapped column must still read as words, never vanish. */
    public function test_an_unmapped_column_falls_back_to_a_readable_name(): void
    {
        $this->assertSame('Some new column', AuditDictionary::field('some_new_column'));
    }

    public function test_it_resolves_enum_values_through_the_subject_that_owns_them(): void
    {
        $this->assertSame(
            'Đã thanh toán',
            AuditDictionary::value('paid', 'status', Invoice::class),
        );

        $this->assertSame(
            'Chuyển khoản',
            AuditDictionary::value('transfer', 'payment_method', Invoice::class),
        );

        $this->assertSame(
            'Doanh thu dịch vụ',
            AuditDictionary::value('service_revenue', 'category', CashTransaction::class),
        );
    }

    /**
     * `status` is a different set of values on every subject, and the snapshot
     * only keeps the bare string — so the subject must decide the reading.
     */
    public function test_the_same_stored_value_reads_differently_per_subject(): void
    {
        $this->assertSame('Nháp', AuditDictionary::value('draft', 'status', Invoice::class));
        $this->assertSame('Đi trễ', AuditDictionary::value('late', 'status', AttendanceRecord::class));
    }

    /** Without a subject there is nothing to resolve against; show the raw value. */
    public function test_an_unknown_subject_leaves_the_value_untouched(): void
    {
        $this->assertSame('paid', AuditDictionary::value('paid', 'status', null));
    }

    public function test_it_reads_timestamps_day_first(): void
    {
        $this->assertSame('21/08/2026 10:40', AuditDictionary::value('2026-08-21 10:40:00', 'paid_at', Invoice::class));
        $this->assertSame('21/08/2026', AuditDictionary::value('2026-08-21', 'work_date', AttendanceRecord::class));
    }

    /** Money and free text must survive the date sniffing unchanged. */
    public function test_it_leaves_plain_values_alone(): void
    {
        $this->assertSame('200000.00', AuditDictionary::value('200000.00', 'total', Invoice::class));
        $this->assertSame('Khach doi lich', AuditDictionary::value('Khach doi lich', 'note', Invoice::class));
    }

    public function test_it_keeps_the_existing_readings_for_empty_booleans_and_line_arrays(): void
    {
        $this->assertSame('—', AuditDictionary::value(null, 'paid_at', Invoice::class));
        $this->assertSame('Có', AuditDictionary::value(true, 'qualified_for_bill_kpi', Invoice::class));
        $this->assertSame('Không', AuditDictionary::value(false, 'qualified_for_bill_kpi', Invoice::class));
        $this->assertSame('2 dòng', AuditDictionary::value([[], []], 'items', Invoice::class));
    }
}
