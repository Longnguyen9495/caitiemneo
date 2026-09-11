<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <h2>Chi tiết các khoản</h2>
            <p>Khoản hệ thống tự tính sẽ được dựng lại mỗi lần tính lại; khoản nhập tay luôn được giữ nguyên.</p>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>Hạng mục</th><th>Diễn giải</th><th>Chi nhánh</th><th>Nguồn</th><th class="admin-numeric">Số tiền</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($payroll->adjustments->sortByDesc('is_automatic') as $adjustment)
                    <tr>
                        <td><strong>{{ $adjustment->category->label() }}</strong></td>
                        <td>{{ $adjustment->description }}</td>
                        <td>@if ($adjustment->branch)<span class="admin-branch-chip">{{ $adjustment->branch->code }}</span>@else <span class="admin-muted-text">Toàn kỳ</span> @endif</td>
                        <td>
                            <x-admin.status-badge
                                :tone="$adjustment->is_automatic ? 'is-active' : 'is-muted'"
                                :label="$adjustment->is_automatic ? 'Hệ thống tính' : 'Nhập tay'"
                            />
                        </td>
                        <td class="admin-numeric">
                            <span class="admin-money {{ $adjustment->direction->sign() > 0 ? 'is-positive' : 'is-negative' }}">
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
    </div>
</section>
