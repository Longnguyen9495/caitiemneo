<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Appointments\SaveAppointmentAction;
use App\Actions\Invoices\ConvertAppointmentToInvoiceAction;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppointmentRequest;
use App\Http\Requests\Admin\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\BranchService;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Appointment::class);

        $selectedDate = Carbon::parse($request->string('date')->toString() ?: now()->toDateString())->startOfDay();

        $appointments = Appointment::query()
            ->with(['employee', 'services.service', 'invoice', 'galleryItem'])
            ->whereIn('branch_id', $this->branchContext->scopeIds() ?: [0])
            ->whereBetween('starts_at', [$selectedDate, $selectedDate->copy()->endOfDay()])
            ->when(
                $request->user()->isEmployee() && ! $request->user()->can_manage_appointments,
                fn ($query) => $query->where('employee_id', $request->user()->getKey()),
                fn ($query) => $query->when($request->filled('employee_id'), fn ($inner) => $inner->where('employee_id', $request->integer('employee_id'))),
            )
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('starts_at')
            ->get();

        return view('admin.appointments.index', [
            'appointments' => $appointments,
            'selectedDate' => $selectedDate,
            'employees' => $this->employees(),
            'statuses' => AppointmentStatus::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Appointment::class);

        return view('admin.appointments.form', $this->formData(new Appointment([
            'starts_at' => now()->addHour()->startOfHour(),
            'duration_minutes' => 60,
        ])));
    }

    public function store(AppointmentRequest $request, SaveAppointmentAction $saveAppointment): RedirectResponse
    {
        $appointment = $saveAppointment->handle($request->validated(), actor: $request->user());

        return redirect()
            ->route('admin.appointments.index', ['date' => $appointment->starts_at->toDateString()])
            ->with('success', 'Đã tạo lịch hẹn.');
    }

    public function edit(Appointment $appointment): View
    {
        $this->authorize('update', $appointment);

        $appointment->load('services');

        return view('admin.appointments.form', $this->formData($appointment));
    }

    public function update(AppointmentRequest $request, Appointment $appointment, SaveAppointmentAction $saveAppointment): RedirectResponse
    {
        $saveAppointment->handle($request->validated(), $appointment, $request->user());

        return redirect()
            ->route('admin.appointments.index', ['date' => $appointment->fresh()->starts_at->toDateString()])
            ->with('success', 'Đã cập nhật lịch hẹn.');
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): RedirectResponse
    {
        $appointment->update($request->validated());

        return back()->with('success', 'Đã cập nhật trạng thái lịch hẹn.');
    }

    public function convertToInvoice(
        Request $request,
        Appointment $appointment,
        ConvertAppointmentToInvoiceAction $convertToInvoice,
    ): RedirectResponse {
        $this->authorize('convertToInvoice', $appointment);

        $invoice = $convertToInvoice->handle($appointment, $request->user());

        return redirect()
            ->route('admin.invoices.edit', $invoice)
            ->with('success', 'Đã tạo hóa đơn nháp từ lịch hẹn.');
    }

    /** @return array<string, mixed> */
    private function formData(Appointment $appointment): array
    {
        $branchId = $appointment->branch_id ?? $this->branchContext->currentId();
        $onDate = $appointment->starts_at ?? now();

        return [
            'appointment' => $appointment,
            // The menu offered is the branch's own, at the branch's own price.
            'services' => BranchService::query()
                ->with('service')
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->get()
                ->sortBy(fn (BranchService $row): string => (string) $row->service?->name)
                ->values(),
            'employees' => $this->employees($onDate),
            'statuses' => AppointmentStatus::options(),
            'branch' => $appointment->branch ?? $this->branchContext->current(),
        ];
    }

    /** @return Collection<int, User> */
    /**
     * Staff who may take a booking at the active branch on the given date.
     *
     * Filtering here is a convenience; {@see SaveAppointmentAction}
     * re-checks the posting server side, so a tampered form cannot book someone
     * who does not work there.
     */
    private function employees(?Carbon $onDate = null)
    {
        return User::query()
            ->active()
            ->where('role', UserRole::Employee)
            ->postedTo($this->branchContext->scopeIds(), $onDate?->toDateString())
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
