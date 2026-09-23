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
                <x-admin.select-field name="shift_assignment_id" label="Ca làm" col="col-12 col-md-5" placeholder="Chọn ca của bạn" required>
                    @foreach ($ownAssignments as $assignment)
                        <option value="{{ $assignment->id }}">{{ $assignment->scheduleLabel() }}</option>
                    @endforeach
                </x-admin.select-field>
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
                    <x-admin.select-field name="shift_assignment_id" id="swap_shift_assignment_id" label="Ca của bạn" col="col-12 col-lg-3" placeholder="Chọn ca cần đổi" required>
                        @foreach ($ownAssignments as $assignment)
                            <option value="{{ $assignment->id }}" @selected(old('shift_assignment_id') == $assignment->id)>{{ $assignment->scheduleLabel() }}</option>
                        @endforeach
                    </x-admin.select-field>
                    <x-admin.select-field name="recipient_id" label="Người đổi ca" col="col-12 col-lg-3" placeholder="Chọn nhân viên" required>
                        @foreach ($swapAssignments->pluck('employee')->unique('id')->sortBy('name') as $employee)
                            <option value="{{ $employee->id }}" @selected(old('recipient_id') == $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </x-admin.select-field>
                    <x-admin.select-field name="counter_shift_assignment_id" label="Ca đối ứng" col="col-12 col-lg-4" placeholder="Chọn ca của người đổi" required>
                        @foreach ($swapAssignments as $assignment)
                            <option value="{{ $assignment->id }}" @selected(old('counter_shift_assignment_id') == $assignment->id)>{{ $assignment->employee->name }} · {{ $assignment->scheduleLabel() }}</option>
                        @endforeach
                    </x-admin.select-field>
                    <x-admin.field name="reason" label="Lý do" col="col-12 col-lg-2" id="swap_reason">
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
        {{-- Huy hiệu trên menu dẫn thẳng vào "Cần xử lý", nên bộ lọc phải có
             đúng lựa chọn đó chứ không chỉ là danh sách theo ngày gửi. --}}
        <x-admin.filter-bar :action="route('admin.shift-requests.index')">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="loc">Trạng thái</label>
                <select class="form-select" id="loc" name="loc">
                    <option value="">Tất cả</option>
                    @foreach ($filters as $value => $label)
                        <option value="{{ $value }}" @selected($activeFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-admin.filter-bar>

        <table class="table neo-table align-middle mb-0">
            <caption class="visually-hidden">Đơn nghỉ và đổi ca</caption>
            <thead>
                <tr>
                    <th scope="col">Loại</th>
                    <th scope="col">Nhân viên</th>
                    <th scope="col">Ngày / ca</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Quyền lợi</th>
                    <th scope="col">Người thay</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $shiftRequest)
                    <tr>
                        <td>{{ $shiftRequest->type->label() }}</td>
                        <td data-label="Nhân viên">{{ $shiftRequest->requester->name }}</td>
                        <td data-label="Ngày / ca">{{ $shiftRequest->work_date->format('d/m/Y') }} · {{ $shiftRequest->shiftAssignment->shift_name }}</td>
                        <td data-label="Trạng thái" class="neo-table__status">
                            <x-admin.status-badge :status="$shiftRequest->status" />
                        </td>
                        <td data-label="Quyền lợi">{{ $shiftRequest->leave_entitlement?->label() ?? '—' }}</td>
                        <td data-label="Người thay">
                            @if ($shiftRequest->replacement)
                                {{ $shiftRequest->replacement->replacementEmployee?->name }}
                            @elseif ($shiftRequest->awaitingReplacement())
                                <span class="text-warning-emphasis">Chưa phân</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="neo-actions">
                            @can('cancel', $shiftRequest)
                                <x-admin.post-button :action="route('admin.shift-requests.cancel', $shiftRequest)" label="Hủy" />
                            @endcan
                            @can('respond', $shiftRequest)
                                <x-admin.post-button :action="route('admin.shift-requests.respond', $shiftRequest)" :fields="['accepted' => 1]" label="Xác nhận" variant="outline-primary" />
                                <x-admin.post-button :action="route('admin.shift-requests.respond', $shiftRequest)" :fields="['accepted' => 0]" label="Từ chối" variant="outline-danger" />
                            @endcan
                            @can('decide', $shiftRequest)
                                {{-- Duyệt đơn nghỉ ghi một ngày hưởng lương và không có đường
                                     hoàn tác; từ chối cũng là trạng thái cuối. Hai nút này
                                     hỏi lại, còn những nút nhẹ hơn thì không — hỏi tất cả
                                     thì chẳng ai đọc nữa. --}}
                                <x-admin.confirm-form
                                    :action="route('admin.shift-requests.decide', $shiftRequest)"
                                    label="Duyệt"
                                    variant="primary"
                                    :message="'Duyệt đơn '.mb_strtolower($shiftRequest->type->label()).' của '.$shiftRequest->requester->name.' ngày '.$shiftRequest->work_date->format('d/m/Y').'? Thao tác này không hoàn tác được.'"
                                >
                                    <input type="hidden" name="approved" value="1">
                                </x-admin.confirm-form>
                                <x-admin.confirm-form
                                    :action="route('admin.shift-requests.decide', $shiftRequest)"
                                    label="Từ chối"
                                    :message="'Từ chối đơn của '.$shiftRequest->requester->name.' ngày '.$shiftRequest->work_date->format('d/m/Y').'? Nhân viên sẽ phải gửi lại đơn mới.'"
                                >
                                    <input type="hidden" name="approved" value="0">
                                </x-admin.confirm-form>
                            @endcan
                            @can('assignReplacement', $shiftRequest)
                                @if ($shiftRequest->awaitingReplacement())
                                    @include('admin.shift-requests.partials.replacement-picker', [
                                        'shiftRequest' => $shiftRequest,
                                        'candidates' => $replacementCandidates->get($shiftRequest->id, collect()),
                                    ])
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state :colspan="7" icon="calendar" title="Chưa có đơn nghỉ hoặc đổi ca" hint="Đơn mới sẽ xuất hiện tại đây." />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$requests" />
    </section>
</x-layouts.admin>
