<section class="card mb-3">
    <div class="card-header">
        <h2 class="neo-display fs-5 mb-0">Chi tiết các khoản</h2>
        <p class="mb-0 small text-body-secondary">Khoản hệ thống tự tính được dựng lại mỗi lần tính lại; khoản nhập tay luôn được giữ nguyên.</p>
    </div>

    <table class="table neo-table align-middle mb-0">
        <thead>
            <tr>
                <th scope="col">Hạng mục</th>
                <th scope="col">Diễn giải</th>
                <th scope="col">Chi nhánh</th>
                <th scope="col">Nguồn</th>
                <th scope="col" class="text-end">Số tiền</th>
                <th scope="col"><span class="visually-hidden">Thao tác</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payroll->adjustments->sortByDesc('is_automatic') as $adjustment)
                <tr>
                    <td class="fw-semibold">{{ $adjustment->category->label() }}</td>
                    <td data-label="Diễn giải">{{ $adjustment->description }}</td>
                    <td data-label="Chi nhánh">
                        @if ($adjustment->branch)
                            <span class="badge rounded-pill text-bg-light border fw-normal">{{ $adjustment->branch->code }}</span>
                        @else
                            <span class="text-body-secondary">Toàn kỳ</span>
                        @endif
                    </td>
                    <td data-label="Nguồn">
                        <x-admin.status-badge
                            :tone="$adjustment->is_automatic ? 'is-active' : 'is-muted'"
                            :label="$adjustment->is_automatic ? 'Hệ thống tính' : 'Nhập tay'"
                        />
                    </td>
                    <td data-label="Số tiền" class="text-end fw-semibold">
                        <span class="neo-num {{ $adjustment->direction->sign() > 0 ? 'text-success' : 'text-danger' }}">
                            {{ $adjustment->direction->sign() > 0 ? '+' : '-' }}{{ \App\Support\Money::format($adjustment->amount) }}
                        </span>
                    </td>
                    <td>
                        @if (! $adjustment->is_automatic)
                            @can('update', $payroll)
                                <x-admin.confirm-form
                                    :action="route('admin.payrolls.adjustments.destroy', [$payroll, $adjustment])"
                                    method="DELETE"
                                    label="Xóa"
                                    message="Xóa khoản điều chỉnh này?"
                                />
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <x-admin.empty-state :colspan="6" title="Chưa có khoản điều chỉnh nào" />
            @endforelse
        </tbody>
    </table>
</section>
