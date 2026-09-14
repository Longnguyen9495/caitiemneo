<x-layouts.admin title="Đơn nghỉ và đổi ca" heading="Đơn nghỉ và đổi ca">
    @include('admin.partials.staff-nav')

    <x-admin.page-header title="Đơn nghỉ và đổi ca" description="Theo dõi yêu cầu nghỉ, đổi ca và tình trạng người thay ca.">
        @if ($canManage)
            <x-slot:actions>
                <span class="badge text-bg-light border">Quản lý theo chi nhánh đang chọn</span>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @if (auth()->user()->isEmployee())
        <section class="card p-3 mb-3">
            <h2 class="fs-6 fw-semibold">Gửi đơn xin nghỉ</h2>
            <form method="POST" action="{{ route('admin.shift-requests.leave.store') }}" class="row g-2 align-items-end">
                @csrf
                <x-admin.field name="shift_assignment_id" label="Ca làm" col="col-12 col-md-5" required>
                    <select class="form-select @error('shift_assignment_id') is-invalid @enderror" name="shift_assignment_id" id="shift_assignment_id" required>
                        <option value="">Chọn ca của bạn</option>
                        @foreach ($ownAssignments as $assignment)
                            <option value="{{ $assignment->id }}">{{ $assignment->work_date->format('d/m/Y') }} · {{ $assignment->shift_name }} ({{ $assignment->timeRangeLabel() }})</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field name="reason" label="Lý do" col="col-12 col-md-5">
                    <input class="form-control @error('reason') is-invalid @enderror" name="reason" id="reason" value="{{ old('reason') }}">
                </x-admin.field>
                <div class="col-12 col-md-2"><x-admin.submit-button label="Gửi đơn" class="w-100" /></div>
            </form>
            <p class="form-text mb-0 mt-2">Ngày nghỉ hưởng lương chỉ áp dụng khi quản lý đã xếp trước đúng ngày đó.</p>
        </section>

        <section class="card p-3 mb-3">
            <h2 class="fs-6 fw-semibold">Gửi đề nghị đổi ca</h2>
            @if ($swapAssignments->isNotEmpty())
                <form method="POST" action="{{ route('admin.shift-requests.swap.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <x-admin.field name="shift_assignment_id" label="Ca của bạn" col="col-12 col-lg-3" required>
                        <select class="form-select @error('shift_assignment_id') is-invalid @enderror" name="shift_assignment_id" id="swap_shift_assignment_id" required>
                            <option value="">Chọn ca cần đổi</option>
                            @foreach ($ownAssignments as $assignment)
                                <option value="{{ $assignment->id }}" @selected(old('shift_assignment_id') == $assignment->id)>{{ $assignment->work_date->format('d/m/Y') }} · {{ $assignment->shift_name }} ({{ $assignment->timeRangeLabel() }})</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                    <x-admin.field name="recipient_id" label="Người đổi ca" col="col-12 col-lg-3" required>
                        <select class="form-select @error('recipient_id') is-invalid @enderror" name="recipient_id" id="recipient_id" required>
                            <option value="">Chọn nhân viên</option>
                            @foreach ($swapAssignments->pluck('employee')->unique('id')->sortBy('name') as $employee)
                                <option value="{{ $employee->id }}" @selected(old('recipient_id') == $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                    <x-admin.field name="counter_shift_assignment_id" label="Ca đối ứng" col="col-12 col-lg-4" required>
                        <select class="form-select @error('counter_shift_assignment_id') is-invalid @enderror" name="counter_shift_assignment_id" id="counter_shift_assignment_id" required>
                            <option value="">Chọn ca của người đổi</option>
                            @foreach ($swapAssignments as $assignment)
                                <option value="{{ $assignment->id }}" @selected(old('counter_shift_assignment_id') == $assignment->id)>{{ $assignment->employee->name }} · {{ $assignment->work_date->format('d/m/Y') }} · {{ $assignment->shift_name }} ({{ $assignment->timeRangeLabel() }})</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                    <x-admin.field name="reason" label="Lý do" col="col-12 col-lg-2">
                        <input class="form-control @error('reason') is-invalid @enderror" name="reason" id="swap_reason" value="{{ old('reason') }}">
                    </x-admin.field>
                    <div class="col-12"><x-admin.submit-button label="Gửi đề nghị đổi ca" /></div>
                </form>
                <p class="form-text mb-0 mt-2">Chỉ đổi được hai ca cùng ngày, cùng chi nhánh. Hệ thống sẽ kiểm tra lại lịch, trạng thái ca và xung đột trước khi gửi đơn.</p>
            @else
                <p class="mb-0 text-body-secondary">Chưa có ca đối ứng cùng ngày và chi nhánh để gửi đề nghị đổi ca.</p>
            @endif
        </section>
    @endif

    <section class="card overflow-hidden">
        <table class="table neo-table align-middle mb-0">
            <thead><tr><th>Loại</th><th>Nhân viên</th><th>Ngày / ca</th><th>Trạng thái</th><th>Quyền lợi</th><th>Thao tác</th></tr></thead>
            <tbody>
                @forelse ($requests as $shiftRequest)
                    <tr>
                        <td>{{ $shiftRequest->type->label() }}</td>
                        <td>{{ $shiftRequest->requester->name }}</td>
                        <td>{{ $shiftRequest->work_date->format('d/m/Y') }} · {{ $shiftRequest->shiftAssignment->shift_name }}</td>
                        <td>
                            <x-admin.status-badge :status="$shiftRequest->status" />
                            @if ($shiftRequest->replacement)
                                <span class="d-block small text-success mt-1">Người thay: {{ $shiftRequest->replacement->replacementEmployee?->name }}</span>
                            @elseif ($shiftRequest->status === \App\Enums\ShiftRequestStatus::Approved && $shiftRequest->type->value === 'leave')
                                <span class="d-block small text-warning-emphasis mt-1">Cần phân người thay</span>
                            @endif
                        </td>
                        <td>{{ $shiftRequest->leave_entitlement?->label() ?? '—' }}</td>
                        <td class="d-flex flex-wrap gap-1">
                            @can('cancel', $shiftRequest)
                                <form method="POST" action="{{ route('admin.shift-requests.cancel', $shiftRequest) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Hủy</button></form>
                            @endcan
                            @can('respond', $shiftRequest)
                                <form method="POST" action="{{ route('admin.shift-requests.respond', $shiftRequest) }}">@csrf<input type="hidden" name="accepted" value="1"><button class="btn btn-sm btn-outline-primary">Xác nhận</button></form>
                                <form method="POST" action="{{ route('admin.shift-requests.respond', $shiftRequest) }}">@csrf<input type="hidden" name="accepted" value="0"><button class="btn btn-sm btn-outline-danger">Từ chối</button></form>
                            @endcan
                            @can('decide', $shiftRequest)
                                <form method="POST" action="{{ route('admin.shift-requests.decide', $shiftRequest) }}">@csrf<input type="hidden" name="approved" value="1"><button class="btn btn-sm btn-primary">Duyệt</button></form>
                                <form method="POST" action="{{ route('admin.shift-requests.decide', $shiftRequest) }}">@csrf<input type="hidden" name="approved" value="0"><button class="btn btn-sm btn-outline-danger">Từ chối</button></form>
                            @endcan
                            @can('assignReplacement', $shiftRequest)
                                @if ($shiftRequest->status === \App\Enums\ShiftRequestStatus::Approved && $shiftRequest->type->value === 'leave' && ! $shiftRequest->replacement)
                                    @php $candidates = $replacementCandidates->get($shiftRequest->id, collect()); @endphp
                                    @if ($candidates->isNotEmpty())
                                        <form method="POST" action="{{ route('admin.shift-requests.replacement.assign', $shiftRequest) }}" class="d-flex gap-1 align-items-center">
                                            @csrf
                                            <label class="visually-hidden" for="replacement_{{ $shiftRequest->id }}">Nhân viên thay ca</label>
                                            <select class="form-select form-select-sm" id="replacement_{{ $shiftRequest->id }}" name="replacement_employee_id" required>
                                                <option value="">Chọn người thay</option>
                                                @foreach ($candidates as $candidate)
                                                    <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-success" type="submit">Phân thay</button>
                                        </form>
                                    @else
                                        <span class="small text-body-secondary">Chưa có ứng viên phù hợp.</span>
                                    @endif
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state :colspan="6" icon="calendar" title="Chưa có đơn nghỉ hoặc đổi ca" hint="Đơn mới sẽ xuất hiện tại đây." />
                @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $requests->links() }}</div>
    </section>
</x-layouts.admin>
