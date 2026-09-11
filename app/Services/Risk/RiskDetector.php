<?php

namespace App\Services\Risk;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Enums\InventoryMovementType;
use App\Enums\InvoiceStatus;
use App\Enums\RiskSeverity;
use App\Models\Appointment;
use App\Models\AttendanceRecord;
use App\Models\AuditEvent;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\RiskFlag;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Plain rules over the audit trail, looking for shapes that fraud tends to take.
 *
 * No cleverness on purpose. Every flag here can be explained to the person it
 * names in one sentence, which matters because the output accuses somebody by
 * implication: a rule nobody can explain is a rule that gets ignored or, worse,
 * believed without question.
 *
 * Each flag is a question, never a verdict. The queue exists so a human answers
 * it — see {@see RiskFlag}.
 */
class RiskDetector
{
    /**
     * Re-derive flags for a window of time.
     *
     * Deliberately idempotent: flags are keyed on rule + subject, so running
     * this twice produces one flag, and a review already recorded is never
     * overwritten by a later sweep.
     *
     * @return int number of new flags raised
     */
    public function sweep(?Carbon $since = null): int
    {
        $since ??= now()->subDays(7);

        return collect([
            $this->repeatedInvoiceCancellations($since),
            $this->repeatedCashVoids($since),
            $this->outOfHoursMoneyMovements($since),
            $this->selfRecordedAttendance($since),
            $this->largeStockAdjustments($since),
            $this->bulkExports($since),
            $this->completedAppointmentsWithoutInvoice($since),
            $this->paidInvoicesWithNothingOnThem($since),
            $this->commissionLinesWithNobodyToPay($since),
        ])->sum();
    }

    /**
     * Cancelling several invoices in one day.
     *
     * One cancellation is a customer changing their mind; several by the same
     * person in a day is the shape of ringing up sales, pocketing the cash and
     * voiding the paperwork afterwards.
     */
    private function repeatedInvoiceCancellations(Carbon $since): int
    {
        return $this->flagDailyRepeats(
            $since,
            AuditAction::Cancelled,
            'invoice_cancellations',
            (int) config('business.risk.daily_cancellations'),
            RiskSeverity::High,
            fn (int $count, string $day): string => "Hủy {$count} hóa đơn trong ngày {$day}.",
        );
    }

    /** The same pattern on the cash book. */
    private function repeatedCashVoids(Carbon $since): int
    {
        return $this->flagDailyRepeats(
            $since,
            AuditAction::Voided,
            'cash_voids',
            (int) config('business.risk.daily_voids'),
            RiskSeverity::High,
            fn (int $count, string $day): string => "Hủy {$count} giao dịch quỹ trong ngày {$day}.",
        );
    }

    /** Repeated exports look like somebody taking a copy of the database. */
    private function bulkExports(Carbon $since): int
    {
        return $this->flagDailyRepeats(
            $since,
            AuditAction::Exported,
            'bulk_exports',
            (int) config('business.risk.daily_exports'),
            RiskSeverity::Medium,
            fn (int $count, string $day): string => "Xuất dữ liệu {$count} lần trong ngày {$day}.",
        );
    }

    /**
     * Money moved outside the hours the shop is open.
     *
     * The shop is shut, so there is no customer to have paid — which is when a
     * figure gets adjusted with nobody around to notice.
     */
    private function outOfHoursMoneyMovements(Carbon $since): int
    {
        $start = (int) config('business.risk.business_hours_start');
        $end = (int) config('business.risk.business_hours_end');

        $events = AuditEvent::query()
            ->whereIn('action', [AuditAction::Paid->value, AuditAction::Voided->value, AuditAction::Cancelled->value])
            ->where('created_at', '>=', $since)
            ->get()
            ->filter(function (AuditEvent $event) use ($start, $end): bool {
                $hour = (int) $event->created_at?->format('G');

                return $hour < $start || $hour >= $end;
            });

        $raised = 0;

        foreach ($events as $event) {
            $raised += $this->raise(
                rule: 'out_of_hours_money',
                severity: RiskSeverity::Medium,
                subjectType: 'audit_event',
                subjectId: $event->getKey(),
                branchId: $event->branch_id,
                actorId: $event->actor_id,
                actorName: $event->actor_name,
                summary: sprintf(
                    'Thao tác tiền lúc %s, ngoài giờ mở cửa của tiệm.',
                    $event->created_at?->format('H:i d/m/Y'),
                ),
                context: ['action' => $event->action?->value, 'subject' => $event->auditable_label],
                detectedAt: $event->created_at,
            ) ? 1 : 0;
        }

        return $raised;
    }

    /**
     * Somebody writing their own hours by hand.
     *
     * Managers are blocked outright (see the attendance policy); what reaches
     * here is the owner, who cannot be blocked without locking a one-owner shop
     * out of its own records. So it is flagged instead of prevented.
     */
    private function selfRecordedAttendance(Carbon $since): int
    {
        $records = AttendanceRecord::query()
            ->with('employee:id,name')
            ->where('is_self_recorded', true)
            ->where('created_at', '>=', $since)
            ->get();

        $raised = 0;

        foreach ($records as $record) {
            $raised += $this->raise(
                rule: 'self_recorded_attendance',
                severity: RiskSeverity::Medium,
                subjectType: 'attendance_record',
                subjectId: $record->getKey(),
                branchId: $record->branch_id,
                actorId: $record->employee_id,
                actorName: $record->employee?->name,
                summary: sprintf(
                    'Tự chấm công ngày %s, ca "%s".',
                    $record->work_date?->format('d/m/Y'),
                    $record->shift_name,
                ),
                context: ['work_date' => $record->work_date?->toDateString()],
                detectedAt: $record->created_at,
            ) ? 1 : 0;
        }

        return $raised;
    }

    /**
     * A stock adjustment worth more than the configured threshold.
     *
     * Adjustments are the one movement that can reduce stock on a typed reason
     * alone, so a large one is exactly where goods leave without a transfer.
     */
    private function largeStockAdjustments(Carbon $since): int
    {
        $threshold = (int) config('business.risk.stock_adjustment_value');

        $events = AuditEvent::query()
            ->where('auditable_type', InventoryMovement::class)
            ->where('created_at', '>=', $since)
            ->get()
            ->filter(function (AuditEvent $event) use ($threshold): bool {
                $after = $event->after ?? [];

                if (($after['type'] ?? null) !== InventoryMovementType::Adjustment->value) {
                    return false;
                }

                $value = abs((float) ($after['quantity'] ?? 0)) * (float) ($after['unit_cost'] ?? 0);

                return $value >= $threshold;
            });

        $raised = 0;

        foreach ($events as $event) {
            $after = $event->after ?? [];

            $raised += $this->raise(
                rule: 'large_stock_adjustment',
                severity: RiskSeverity::High,
                subjectType: 'audit_event',
                subjectId: $event->getKey(),
                branchId: $event->branch_id,
                actorId: $event->actor_id,
                actorName: $event->actor_name,
                summary: sprintf(
                    'Điều chỉnh kho "%s": tồn %s → %s.',
                    $after['product_name'] ?? 'vật tư',
                    $after['stock_before'] ?? '?',
                    $after['stock_after'] ?? '?',
                ),
                context: ['reason' => $event->reason],
                detectedAt: $event->created_at,
            ) ? 1 : 0;
        }

        return $raised;
    }

    /**
     * Work was done but no bill was ever raised.
     *
     * This is the plainest form of revenue leakage there is: the customer was
     * served, the appointment was marked finished, and nothing was charged. It
     * is usually an oversight, which is exactly why it needs surfacing — an
     * oversight nobody notices is indistinguishable from one nobody wanted
     * noticed.
     */
    private function completedAppointmentsWithoutInvoice(Carbon $since): int
    {
        $appointments = Appointment::query()
            ->where('status', AppointmentStatus::Completed->value)
            ->where('updated_at', '>=', $since)
            ->whereDoesntHave('invoice')
            ->with('employee:id,name')
            ->get();

        $raised = 0;

        foreach ($appointments as $appointment) {
            $raised += $this->raise(
                rule: 'completed_appointment_without_invoice',
                severity: RiskSeverity::High,
                subjectType: 'appointment',
                subjectId: $appointment->getKey(),
                branchId: $appointment->branch_id,
                actorId: $appointment->employee_id,
                actorName: $appointment->employee?->name,
                summary: sprintf(
                    'Lịch hẹn ngày %s của khách "%s" đã hoàn tất nhưng chưa có hóa đơn.',
                    $appointment->starts_at?->format('d/m/Y H:i'),
                    $appointment->customer_name,
                ),
                context: ['appointment_id' => $appointment->getKey()],
                detectedAt: $appointment->updated_at,
            ) ? 1 : 0;
        }

        return $raised;
    }

    /**
     * A bill settled for nothing, or for no services.
     *
     * A paid invoice with no lines or a zero total means money was recorded as
     * collected against nothing at all — either a mistake in the takings or a
     * receipt written to make a till balance.
     */
    private function paidInvoicesWithNothingOnThem(Carbon $since): int
    {
        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->where('paid_at', '>=', $since)
            ->where(fn ($query) => $query
                ->whereDoesntHave('items')
                ->orWhere('total', '<=', 0))
            ->with('creator:id,name')
            ->get();

        $raised = 0;

        foreach ($invoices as $invoice) {
            $raised += $this->raise(
                rule: 'paid_invoice_without_value',
                severity: RiskSeverity::High,
                subjectType: 'invoice',
                subjectId: $invoice->getKey(),
                branchId: $invoice->branch_id,
                actorId: $invoice->created_by,
                actorName: $invoice->creator?->name,
                summary: sprintf(
                    'Hóa đơn %s đã thanh toán nhưng không có dòng dịch vụ hoặc tổng tiền bằng 0.',
                    $invoice->number,
                ),
                context: ['total' => (string) $invoice->total],
                detectedAt: $invoice->paid_at,
            ) ? 1 : 0;
        }

        return $raised;
    }

    /**
     * A service line with nobody credited for doing the work.
     *
     * Commission is attributed per line, so a line with no employee pays
     * nobody. Left alone it quietly shrinks somebody's wages, and it also hides
     * who actually served the customer.
     */
    private function commissionLinesWithNobodyToPay(Carbon $since): int
    {
        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->where('paid_at', '>=', $since)
            ->whereHas('items', fn ($query) => $query->whereNull('employee_id'))
            ->with('creator:id,name')
            ->get();

        $raised = 0;

        foreach ($invoices as $invoice) {
            $raised += $this->raise(
                rule: 'invoice_line_without_employee',
                severity: RiskSeverity::Medium,
                subjectType: 'invoice',
                subjectId: $invoice->getKey(),
                branchId: $invoice->branch_id,
                actorId: $invoice->created_by,
                actorName: $invoice->creator?->name,
                summary: sprintf(
                    'Hóa đơn %s có dòng dịch vụ chưa gán nhân viên, nên không ai được tính hoa hồng.',
                    $invoice->number,
                ),
                context: ['invoice_id' => $invoice->getKey()],
                detectedAt: $invoice->paid_at,
            ) ? 1 : 0;
        }

        return $raised;
    }

    /**
     * Shared shape: one actor doing the same thing too many times in a day.
     *
     * @param  callable(int, string): string  $describe
     */
    private function flagDailyRepeats(
        Carbon $since,
        AuditAction $action,
        string $rule,
        int $threshold,
        RiskSeverity $severity,
        callable $describe,
    ): int {
        $groups = AuditEvent::query()
            ->where('action', $action->value)
            ->where('created_at', '>=', $since)
            ->whereNotNull('actor_id')
            ->get()
            ->groupBy(fn (AuditEvent $event): string => $event->actor_id.'|'.$event->created_at?->toDateString());

        $raised = 0;

        foreach ($groups as $key => $events) {
            if ($events->count() < $threshold) {
                continue;
            }

            [$actorId, $day] = explode('|', (string) $key);
            $first = $events->first();

            $raised += $this->raise(
                rule: $rule,
                severity: $severity,
                // Chủ thể là "người này, ngày này" nên chạy lại không nhân bản cờ.
                subjectType: 'actor_day',
                subjectId: null,
                branchId: $first->branch_id,
                actorId: (int) $actorId,
                actorName: $first->actor_name,
                summary: $describe($events->count(), Carbon::parse($day)->format('d/m/Y')),
                context: ['count' => $events->count(), 'day' => $day],
                detectedAt: $events->last()->created_at,
                subjectKey: $rule.':'.$key,
            ) ? 1 : 0;
        }

        return $raised;
    }

    /**
     * Record a flag unless one already exists for this rule and subject.
     *
     * @param  array<string, mixed>  $context
     * @return bool whether a new flag was created
     */
    private function raise(
        string $rule,
        RiskSeverity $severity,
        ?string $subjectType,
        ?int $subjectId,
        ?int $branchId,
        ?int $actorId,
        ?string $actorName,
        string $summary,
        array $context,
        ?Carbon $detectedAt,
        ?string $subjectKey = null,
    ): bool {
        // Nhóm theo actor+ngày không có id bản ghi, nên dùng một khóa dẫn xuất
        // ổn định để lần quét sau nhận ra đúng cờ cũ.
        $identity = [
            'rule' => $rule,
            'subject_type' => $subjectKey !== null ? 'actor_day' : $subjectType,
            'subject_id' => $subjectKey !== null ? crc32($subjectKey) : $subjectId,
        ];

        if (RiskFlag::query()->where($identity)->exists()) {
            return false;
        }

        RiskFlag::query()->create($identity + [
            'severity' => $severity,
            'branch_id' => $branchId,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'summary' => $summary,
            'context' => $context,
            'detected_at' => $detectedAt ?? now(),
        ]);

        return true;
    }

    /** @return Collection<int, RiskFlag> */
    public function openFlagsFor(array $branchIds): Collection
    {
        return RiskFlag::query()
            ->open()
            ->forBranches($branchIds)
            ->with(['actor:id,name', 'branch:id,name'])
            ->orderByDesc('detected_at')
            ->get();
    }
}
