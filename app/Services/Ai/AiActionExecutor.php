<?php

namespace App\Services\Ai;

use App\Actions\Appointments\CancelAppointmentAction;
use App\Actions\Appointments\SaveAppointmentAction;
use App\Actions\Cash\RecordCashTransactionAction;
use App\Actions\Inventory\RecordInventoryMovementAction;
use App\Enums\AiActionStatus;
use App\Enums\AppointmentStatus;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\InventoryMovementType;
use App\Enums\PaymentMethod;
use App\Models\AiActionProposal;
use App\Models\Appointment;
use App\Models\BranchProduct;
use App\Models\BranchService;
use App\Models\CashTransaction;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AiActionExecutor
{
    public function __construct(
        private BranchContext $branches,
        private AiActionCatalog $catalog,
        private RecordCashTransactionAction $cash,
        private RecordInventoryMovementAction $inventory,
        private SaveAppointmentAction $appointments,
        private CancelAppointmentAction $cancelAppointment,
    ) {}

    public function execute(AiActionProposal $proposal, User $actor): AiActionProposal
    {
        try {
            return DB::transaction(function () use ($proposal, $actor): AiActionProposal {
                $locked = AiActionProposal::query()->lockForUpdate()->findOrFail($proposal->id);

                if ($locked->status !== AiActionStatus::Pending) {
                    return $locked->load('result');
                }

                $this->guardOwnership($locked, $actor);
                $this->guardBranch($locked);
                $payload = $this->validatePayload($locked);

                $locked->forceFill(['status' => AiActionStatus::Executing])->save();
                $result = $this->dispatch($locked->type, $payload, $actor);

                $locked->forceFill([
                    'status' => AiActionStatus::Executed,
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'result_type' => $result->getMorphClass(),
                    'result_id' => $result->getKey(),
                    'failure_message' => null,
                ])->save();

                return $locked->load('result');
            });
        } catch (ValidationException|HttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            DB::transaction(function () use ($proposal, $exception): void {
                $locked = AiActionProposal::query()->lockForUpdate()->findOrFail($proposal->id);

                if ($locked->status === AiActionStatus::Pending || $locked->status === AiActionStatus::Executing) {
                    $locked->forceFill([
                        'status' => AiActionStatus::Failed,
                        'failure_message' => $this->safeFailureMessage($exception),
                    ])->save();
                }
            });

            throw $exception;
        }
    }

    public function reject(AiActionProposal $proposal, User $actor): AiActionProposal
    {
        return DB::transaction(function () use ($proposal, $actor): AiActionProposal {
            $locked = AiActionProposal::query()->lockForUpdate()->findOrFail($proposal->id);

            if ($locked->status !== AiActionStatus::Pending) {
                return $locked;
            }

            $this->guardOwnership($locked, $actor);
            $locked->forceFill([
                'status' => AiActionStatus::Rejected,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();

            return $locked;
        });
    }

    private function guardOwnership(AiActionProposal $proposal, User $actor): void
    {
        $belongsToActor = AiActionProposal::query()
            ->whereKey($proposal->getKey())
            ->whereHas('message.conversation', fn ($query) => $query->where('user_id', $actor->id))
            ->exists();

        if (! $belongsToActor) {
            abort(404);
        }

        Gate::forUser($actor)->authorize('use-ai-assistant');
    }

    private function guardBranch(AiActionProposal $proposal): void
    {
        $branchId = $proposal->branch_id;

        if ($branchId === null || ! in_array((int) $branchId, $this->branches->scopeIds(), true)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Chi nhánh của đề xuất không còn nằm trong phạm vi bạn được phép thao tác.',
            ]);
        }

        $payloadBranchId = $proposal->payload['branch_id'] ?? null;

        if ($payloadBranchId === null || (int) $payloadBranchId !== (int) $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Chi nhánh trong đề xuất không khớp với chi nhánh được chỉ định.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function validatePayload(AiActionProposal $proposal): array
    {
        $payload = $proposal->payload;
        $backdate = now()->subDays((int) config('business.backdate_days'))->startOfDay()->toDateTimeString();
        $todayEnd = now()->endOfDay()->toDateTimeString();

        if (! $this->catalog->supports($proposal->type)) {
            throw ValidationException::withMessages([
                'action' => 'Loại thao tác AI này không được hệ thống hỗ trợ.',
            ]);
        }

        $appointmentIdRules = [
            'required',
            'integer',
            Rule::exists(Appointment::class, 'id')->where('branch_id', $proposal->branch_id),
        ];
        $serviceRules = [
            'integer',
            Rule::exists(BranchService::class, 'service_id')
                ->where('branch_id', $proposal->branch_id)
                ->where('is_active', true),
        ];
        $appointmentRules = [
            'branch_id' => ['required', 'integer', Rule::in($this->branches->scopeIds())],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'employee_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('is_active', true)],
            'starts_at' => ['required', 'date', 'after:now', 'before:'.now()->addDays((int) config('business.max_booking_days_ahead'))->toDateTimeString()],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'service_ids' => ['nullable', 'array', 'max:20'],
            'service_ids.*' => $serviceRules,
            'note' => ['nullable', 'string', 'max:2000'],
        ];

        $rules = match ($proposal->type) {
            'create_cash_entry' => [
                'branch_id' => ['required', 'integer', Rule::in($this->branches->scopeIds())],
                'type' => ['required', Rule::enum(CashTransactionType::class)],
                'category' => ['required', Rule::in(array_keys(CashTransactionCategory::manualOptions()))],
                'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
                'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
                'occurred_at' => ['required', 'date', "before_or_equal:{$todayEnd}", "after_or_equal:{$backdate}"],
                'reference' => ['nullable', 'string', 'max:255'],
                'note' => ['nullable', 'string', 'max:2000'],
            ],
            'adjust_stock' => [
                'branch_id' => ['required', 'integer', Rule::in($this->branches->scopeIds())],
                'product_id' => [
                    'required',
                    'integer',
                    Rule::exists(Product::class, 'id')->where('is_active', true),
                    function (string $attribute, mixed $value, \Closure $fail) use ($proposal): void {
                        $branchId = (int) ($proposal->payload['branch_id'] ?? $proposal->branch_id);
                        $productId = (int) $value;
                        $belongs = BranchProduct::query()
                            ->where('branch_id', $branchId)
                            ->where('product_id', $productId)
                            ->where('is_active', true)
                            ->exists();

                        if (! $belongs) {
                            $fail('Vật tư này không thuộc danh mục hoặc không còn hoạt động tại chi nhánh được chọn.');
                        }
                    },
                ],
                'type' => ['required', Rule::in([InventoryMovementType::Adjustment->value])],
                'adjustment_mode' => ['required', Rule::in(['absolute', 'delta'])],
                'quantity' => ['required', 'numeric', 'min:-9999999', 'max:9999999'],
                'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
                'reference' => ['nullable', 'string', 'max:255'],
                'note' => ['required', 'string', 'min:6', 'max:2000'],
                'occurred_at' => ['required', 'date', "before_or_equal:{$todayEnd}", "after_or_equal:{$backdate}"],
            ],
            'create_appointment' => $appointmentRules,
            'update_appointment' => ['appointment_id' => $appointmentIdRules] + $appointmentRules,
            'cancel_appointment' => [
                'branch_id' => ['required', 'integer', Rule::in($this->branches->scopeIds())],
                'appointment_id' => $appointmentIdRules,
                'reason' => ['required', 'string', 'min:3', 'max:500'],
            ],
            default => throw ValidationException::withMessages(['action' => 'Thao tác không được hỗ trợ.']),
        };

        $validated = Validator::make($payload, $rules, [], [
            'branch_id' => 'chi nhánh',
            'amount' => 'số tiền',
            'product_id' => 'vật tư',
            'quantity' => 'số lượng',
            'note' => 'lý do',
            'occurred_at' => 'thời điểm',
            'appointment_id' => 'lịch hẹn',
            'customer_name' => 'tên khách hàng',
            'customer_phone' => 'số điện thoại',
            'starts_at' => 'thời gian bắt đầu',
            'duration_minutes' => 'thời lượng',
            'service_ids' => 'dịch vụ',
            'reason' => 'lý do hủy',
        ])->validate();

        return $validated;
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(string $type, array $payload, User $actor): Model
    {
        return match ($type) {
            'create_cash_entry' => $this->createCashEntry($payload, $actor),
            'adjust_stock' => $this->adjustStock($payload, $actor),
            'create_appointment' => $this->createAppointment($payload, $actor),
            'update_appointment' => $this->updateAppointment($payload, $actor),
            'cancel_appointment' => $this->cancelAppointment($payload, $actor),
            default => throw ValidationException::withMessages(['action' => 'Thao tác không được hỗ trợ.']),
        };
    }

    /** @param array<string, mixed> $payload */
    private function createCashEntry(array $payload, User $actor): CashTransaction
    {
        Gate::forUser($actor)->authorize('create', CashTransaction::class);

        return $this->cash->handle($payload, $actor);
    }

    /** @param array<string, mixed> $payload */
    private function adjustStock(array $payload, User $actor): InventoryMovement
    {
        Gate::forUser($actor)->authorize('create', InventoryMovement::class);

        return $this->inventory->handle($payload, $actor);
    }

    /** @param array<string, mixed> $payload */
    private function createAppointment(array $payload, User $actor): Appointment
    {
        Gate::forUser($actor)->authorize('create', Appointment::class);

        return $this->appointments->handle($payload, actor: $actor);
    }

    /** @param array<string, mixed> $payload */
    private function updateAppointment(array $payload, User $actor): Appointment
    {
        $appointment = Appointment::query()->findOrFail($payload['appointment_id']);
        Gate::forUser($actor)->authorize('update', $appointment);
        unset($payload['appointment_id']);

        return $this->appointments->handle($payload, $appointment, $actor);
    }

    /** @param array<string, mixed> $payload */
    private function cancelAppointment(array $payload, User $actor): Appointment
    {
        $appointment = Appointment::query()->findOrFail($payload['appointment_id']);
        Gate::forUser($actor)->authorize('update', $appointment);

        return $this->cancelAppointment->handle($appointment, $actor, $payload['reason']);
    }

    private function safeFailureMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return implode(' ', array_map(
                fn (array $messages): string => implode(' ', $messages),
                $exception->errors()
            ));
        }

        return 'Thao tác thất bại. Vui lòng thử lại hoặc liên hệ hỗ trợ.';
    }
}
