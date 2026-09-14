<x-layouts.admin title="Cảnh báo bất thường" heading="Cảnh báo bất thường">
    <x-admin.page-header
        title="Việc cần xem lại"
        description="Mỗi dòng là một câu hỏi, không phải kết luận. Hãy xem rồi ghi lại nhận định của bạn."
    >
        <x-slot:actions>
            <span class="badge rounded-pill text-bg-warning">{{ $openCount }} chờ xem xét</span>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :action="route('admin.risk-flags.index')">
        <div class="col-12 col-lg-4">
            <label class="form-label" for="status">Trạng thái</label>
            <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </x-admin.filter-bar>

    <section class="card">
        @forelse ($flags as $flag)
            <article class="border-0 border-bottom px-3 py-3">
                <div class="d-flex flex-wrap align-items-baseline gap-2">
                    <span class="badge rounded-pill text-bg-{{ $flag->severity->tone() }}">{{ $flag->severity->label() }}</span>
                    <span class="badge rounded-pill text-bg-{{ $flag->review_status->tone() }}">{{ $flag->review_status->label() }}</span>
                    <strong>{{ $flag->actorLabel() }}</strong>
                    <span class="small text-body-secondary">{{ $flag->branch?->name }}</span>
                    <time class="small text-body-secondary" datetime="{{ $flag->detected_at?->toIso8601String() }}">
                        {{ $flag->detected_at?->format('d/m/Y H:i') }}
                    </time>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <p class="mb-0">{{ $flag->summary }}</p>

                    @if ($subjectLink = $subjectLinks->get($flag->getKey()))
                        <a href="{{ $subjectLink['url'] }}" class="btn btn-sm btn-outline-secondary">
                            {{ $subjectLink['label'] }}
                        </a>
                    @endif
                </div>

                @if ($flag->review_note)
                    <p class="mb-0 mt-2 small text-body-secondary">
                        {{ $flag->reviewer?->name ?? 'Người xem xét' }} đã kết luận: {{ $flag->review_note }}
                    </p>
                @endif

                @can('review', $flag)
                    @if ($flag->isOpen() && $flag->rule === 'invoice_line_without_employee')
                        @php($eligibleEmployees = $employeesByBranch->get($flag->branch_id, collect()))

                        <form method="POST" action="{{ route('admin.risk-flags.assign-employee', $flag) }}" class="row g-2 mt-2 align-items-end rounded border bg-body-tertiary p-2">
                            @csrf
                            @method('PATCH')

                            <div class="col-12 col-lg-7">
                                <label class="form-label" for="employee_id_{{ $flag->id }}">Phân công nhanh cho nhân viên</label>
                                <select class="form-select" id="employee_id_{{ $flag->id }}" name="employee_id" required>
                                    <option value="">Chọn nhân viên nhận hoa hồng</option>
                                    @foreach ($eligibleEmployees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Chỉ gán các dòng còn thiếu nhân viên; số tiền hóa đơn không thay đổi.</div>
                            </div>

                            <div class="col-12 col-lg-5">
                                <button type="submit" class="btn btn-primary w-100" @disabled($eligibleEmployees->isEmpty())>
                                    Phân công và tính hoa hồng
                                </button>
                            </div>
                        </form>

                        @if ($eligibleEmployees->isEmpty())
                            <p class="mb-0 mt-2 small text-danger">Chưa có nhân viên đang làm việc tại chi nhánh này để phân công.</p>
                        @endif
                    @endif

                    @if ($flag->isOpen())
                        <form method="POST" action="{{ route('admin.risk-flags.update', $flag) }}" class="row g-2 mt-2 align-items-end">
                            @csrf
                            @method('PATCH')

                            <div class="col-12 col-lg-4">
                                <label class="form-label" for="review_status_{{ $flag->id }}">Kết luận</label>
                                <select class="form-select" id="review_status_{{ $flag->id }}" name="review_status" required>
                                    <option value="dismissed">Đã xem, không phải vấn đề</option>
                                    <option value="accepted">Xác nhận là vấn đề</option>
                                </select>
                            </div>

                            <div class="col-12 col-lg-6">
                                <label class="form-label" for="review_note_{{ $flag->id }}">Ghi chú</label>
                                <input class="form-control" id="review_note_{{ $flag->id }}" name="review_note"
                                       maxlength="500" required placeholder="Vì sao bạn kết luận như vậy?">
                            </div>

                            <div class="col-12 col-lg-2">
                                <button type="submit" class="btn btn-outline-primary w-100">Lưu</button>
                            </div>
                        </form>
                    @endif
                @else
                    {{-- Người bị nêu tên không tự đóng được cảnh báo của chính mình. --}}
                    @if ($flag->isOpen() && (int) $flag->actor_id === (int) auth()->id())
                        <p class="mb-0 mt-2 small text-body-secondary">
                            Cảnh báo này nói về thao tác của bạn, nên cần một người khác xem xét.
                        </p>
                    @endif
                @endcan
            </article>
        @empty
            <x-admin.empty-state
                icon="inbox"
                title="Không có cảnh báo nào"
                hint="Khi hệ thống thấy một thao tác đáng hỏi lại, nó sẽ xuất hiện ở đây."
            />
        @endforelse

        @if ($flags->hasPages())
            <div class="p-3">
                <x-admin.pagination :paginator="$flags" />
            </div>
        @endif
    </section>
</x-layouts.admin>
