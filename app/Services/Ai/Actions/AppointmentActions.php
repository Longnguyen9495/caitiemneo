<?php

namespace App\Services\Ai\Actions;

use App\Actions\Appointments\CancelAppointmentAction;
use App\Actions\Appointments\SaveAppointmentAction;
use App\Actions\Invoices\ConvertAppointmentToInvoiceAction;
use App\Enums\AppointmentStatus;
use App\Http\Requests\Admin\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\BranchService;
use App\Models\User;
use App\Support\BranchContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Thao tác trên lịch hẹn.
 *
 * Luật của ba thao tác đầu viết tay chứ không mượn AppointmentRequest: phiếu
 * của trợ lý còn phải chặn thêm chuyện chi nhánh nằm ngoài phạm vi người duyệt,
 * điều mà form quản trị không cần vì đã bị thanh chọn chi nhánh khóa sẵn.
 */
final class AppointmentActions
{
    /** @var array<string, string> Nhãn tiếng Việt cho câu báo lỗi. */
    private const ATTRIBUTES = [
        'branch_id' => 'chi nhánh',
        'customer_name' => 'tên khách hàng',
        'customer_phone' => 'số điện thoại',
        'customer_email' => 'email khách',
        'employee_id' => 'kỹ thuật viên',
        'starts_at' => 'thời gian bắt đầu',
        'duration_minutes' => 'thời lượng',
        'status' => 'trạng thái',
        'service_ids' => 'dịch vụ',
        'appointment_id' => 'lịch hẹn',
        'note' => 'ghi chú',
    ];

    /** @return array<int, ActionDefinition> */
    public static function all(): array
    {
        return [
            self::create(),
            self::update(),
            self::cancel(),
            self::setStatus(),
            self::convertToInvoice(),
        ];
    }

    private static function create(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'create_appointment',
            label: 'Tạo lịch hẹn',
            operation: 'create',
            resource: 'appointment',
            destructive: false,
            fields: fn (?int $branchId): array => self::fields(),
            validator: Validation::inline(fn (?int $branchId): array => self::rules($branchId), self::ATTRIBUTES),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('create', Appointment::class);

                return app(SaveAppointmentAction::class)->handle($payload, actor: $actor);
            },
        );
    }

    private static function update(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'update_appointment',
            label: 'Sửa lịch hẹn',
            operation: 'update',
            resource: 'appointment',
            destructive: false,
            fields: fn (?int $branchId): array => array_merge([self::picker('Lịch hẹn cần sửa')], self::fields()),
            validator: Validation::inline(
                fn (?int $branchId): array => ['appointment_id' => self::idRules($branchId)] + self::rules($branchId),
                self::ATTRIBUTES,
            ),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('update', $subject);
                unset($payload['appointment_id']);

                return app(SaveAppointmentAction::class)->handle($payload, $subject, $actor);
            },
            subject: self::subject(),
            hint: 'Giữ nguyên trường cũ nếu người dùng không yêu cầu đổi.',
        );
    }

    private static function cancel(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'cancel_appointment',
            label: 'Hủy lịch hẹn',
            operation: 'delete',
            resource: 'appointment',
            destructive: true,
            fields: fn (?int $branchId): array => [
                self::picker('Lịch hẹn cần hủy'),
                Field::textarea('reason', 'Lý do hủy')->required()->help('Khách báo bận, trùng giờ, tiệm hết vật tư…'),
            ],
            validator: Validation::inline(fn (?int $branchId): array => [
                'branch_id' => ['required', 'integer', Rule::in(app(BranchContext::class)->scopeIds())],
                'appointment_id' => self::idRules($branchId),
                'reason' => ['required', 'string', 'min:3', 'max:500'],
            ], self::ATTRIBUTES + ['reason' => 'lý do hủy']),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('update', $subject);

                return app(CancelAppointmentAction::class)->handle($subject, $actor, $payload['reason']);
            },
            subject: self::subject(),
            hint: 'Dùng thay cho xóa cứng để giữ lịch sử.',
        );
    }

    private static function setStatus(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'set_appointment_status',
            label: 'Đổi trạng thái lịch hẹn',
            operation: 'update',
            resource: 'appointment',
            destructive: false,
            fields: fn (?int $branchId): array => [
                self::picker('Lịch hẹn'),
                Field::select('status', 'Trạng thái mới', AppointmentStatus::options())->required(),
            ],
            validator: Validation::formRequest(UpdateAppointmentStatusRequest::class, 'appointment'),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                $subject->update(['status' => $payload['status']]);

                return $subject;
            },
            subject: self::subject(),
            branchScoped: false,
            hint: 'Dùng khi chỉ cần đánh dấu khách đã đến, đã xong hay không đến.',
        );
    }

    private static function convertToInvoice(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'convert_appointment_to_invoice',
            label: 'Tạo hóa đơn từ lịch hẹn',
            operation: 'create',
            resource: 'invoice',
            destructive: false,
            fields: fn (?int $branchId): array => [self::picker('Lịch hẹn đã xong')],
            validator: Validation::inline(
                ['appointment_id' => ['required', 'integer']],
                self::ATTRIBUTES,
            ),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('convertToInvoice', $subject);

                return app(ConvertAppointmentToInvoiceAction::class)->handle($subject, $actor);
            },
            subject: self::subject(),
            branchScoped: false,
            hint: 'Tạo hóa đơn nháp từ dịch vụ của lịch hẹn; chưa thu tiền.',
        );
    }

    private static function picker(string $label): Field
    {
        return Field::select('appointment_id', $label, fn (?int $id): array => Catalogue::appointments($id))->required();
    }

    private static function subject(): Closure
    {
        return Subject::inBranch(Appointment::class, 'appointment_id', 'lịch hẹn');
    }

    /** @return array<int, mixed> */
    private static function idRules(?int $branchId): array
    {
        return [
            'required',
            'integer',
            Rule::exists(Appointment::class, 'id')->where('branch_id', $branchId),
        ];
    }

    /** @return array<int, Field> */
    private static function fields(): array
    {
        return [
            Field::text('customer_name', 'Tên khách')->required(),
            Field::phone('customer_phone', 'Số điện thoại')->required(),
            Field::email('customer_email', 'Email khách')->help('Không bắt buộc.'),
            Field::datetime('starts_at', 'Giờ hẹn')->required(),
            Field::number('duration_minutes', 'Thời lượng (phút)')
                ->default(60)->required()->attributes(['min' => 15, 'max' => 480, 'step' => 15]),
            Field::select('status', 'Trạng thái', AppointmentStatus::options())
                ->default(AppointmentStatus::Pending->value)->required(),
            Field::select('employee_id', 'Kỹ thuật viên', fn (?int $id): array => Catalogue::employees($id))
                ->help('Để trống thì tiệm sắp xếp sau.'),
            Field::checkboxes('service_ids', 'Dịch vụ dự kiến', fn (?int $id): array => Catalogue::services($id)),
            Field::textarea('note', 'Ghi chú'),
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private static function rules(?int $branchId): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::in(app(BranchContext::class)->scopeIds())],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'employee_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('is_active', true)],
            'starts_at' => [
                'required', 'date', 'after:now',
                'before:'.now()->addDays((int) config('business.max_booking_days_ahead'))->toDateTimeString(),
            ],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'service_ids' => ['nullable', 'array', 'max:20'],
            'service_ids.*' => [
                'integer',
                Rule::exists(BranchService::class, 'service_id')
                    ->where('branch_id', $branchId)
                    ->where('is_active', true),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
