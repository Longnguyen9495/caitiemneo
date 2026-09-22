<?php

namespace App\Services\Ai\Actions;

use App\Actions\Invoices\CancelInvoiceAction;
use App\Actions\Risk\AssignMissingInvoiceEmployeeAction;
use App\Enums\RiskReviewStatus;
use App\Http\Requests\Admin\AssignRiskInvoiceEmployeeRequest;
use App\Http\Requests\Admin\CancelInvoiceRequest;
use App\Http\Requests\Admin\ReviewRiskFlagRequest;
use App\Models\Invoice;
use App\Models\RiskFlag;
use App\Models\User;
use App\Models\User as Employee;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Hóa đơn và cờ rủi ro.
 *
 * Sửa hóa đơn và thu tiền không có ở đây: sửa hóa đơn là sửa từng dòng dịch vụ,
 * còn thu tiền bắt buộc đính ảnh chứng từ — cả hai đều cần màn hình thật chứ
 * không gói vừa một phiếu trong khung chat. Hủy hóa đơn thì gói vừa, và đó
 * cũng là việc hay phải làm gấp nhất.
 */
final class InvoiceActions
{
    /** @return array<int, ActionDefinition> */
    public static function all(): array
    {
        return [
            self::cancelInvoice(),
            self::reviewRiskFlag(),
            self::assignRiskEmployee(),
        ];
    }

    private static function cancelInvoice(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'cancel_invoice',
            label: 'Hủy hóa đơn',
            operation: 'delete',
            resource: 'invoice',
            destructive: true,
            fields: fn (?int $branchId): array => [
                Field::select('invoice_id', 'Hóa đơn cần hủy', fn (?int $id): array => Catalogue::invoices($id))->required(),
                Field::textarea('cancel_reason', 'Lý do hủy')->required(),
            ],
            validator: Validation::formRequest(CancelInvoiceRequest::class, 'invoice'),
            handler: fn (array $payload, User $actor, ?Model $subject): Model => app(CancelInvoiceAction::class)
                ->handle($subject, $actor, $payload['cancel_reason']),
            subject: Subject::inBranch(Invoice::class, 'invoice_id', 'hóa đơn'),
            branchScoped: false,
            hint: 'Hóa đơn gốc được giữ lại và ghi bút toán đảo.',
        );
    }

    private static function reviewRiskFlag(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'review_risk_flag',
            label: 'Kết luận cảnh báo rủi ro',
            operation: 'update',
            resource: 'risk_flag',
            destructive: false,
            fields: fn (?int $branchId): array => [
                self::riskPicker(),
                Field::select('review_status', 'Kết luận', [
                    RiskReviewStatus::Accepted->value => RiskReviewStatus::Accepted->label(),
                    RiskReviewStatus::Dismissed->value => RiskReviewStatus::Dismissed->label(),
                ])->required(),
                Field::textarea('review_note', 'Ghi chú kết luận')->required()->help('Ít nhất 10 ký tự.'),
            ],
            validator: Validation::formRequest(ReviewRiskFlagRequest::class, 'risk_flag'),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                $subject->forceFill([
                    'review_status' => $payload['review_status'],
                    'review_note' => $payload['review_note'],
                    'reviewed_by' => $actor->getKey(),
                    'reviewed_at' => now(),
                ])->save();

                return $subject;
            },
            subject: self::riskSubject(),
            branchScoped: false,
        );
    }

    private static function assignRiskEmployee(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'assign_risk_invoice_employee',
            label: 'Phân công nhân viên cho hóa đơn thiếu',
            operation: 'update',
            resource: 'risk_flag',
            destructive: false,
            fields: fn (?int $branchId): array => [
                self::riskPicker(),
                Field::select('employee_id', 'Nhân viên thực hiện', fn (?int $id): array => Catalogue::employees($id))->required(),
            ],
            validator: Validation::formRequest(AssignRiskInvoiceEmployeeRequest::class, 'risk_flag'),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                $employee = Employee::query()->findOrFail($payload['employee_id']);
                app(AssignMissingInvoiceEmployeeAction::class)->handle($subject, $employee, $actor);

                return $subject->refresh();
            },
            subject: self::riskSubject(),
            branchScoped: false,
            hint: 'Tính lại hoa hồng cho những dòng còn thiếu người thực hiện.',
        );
    }

    private static function riskPicker(): Field
    {
        return Field::select('risk_flag_id', 'Cảnh báo', fn (?int $id): array => Catalogue::riskFlags($id))->required();
    }

    private static function riskSubject(): Closure
    {
        return Subject::inBranch(RiskFlag::class, 'risk_flag_id', 'cảnh báo rủi ro');
    }
}
