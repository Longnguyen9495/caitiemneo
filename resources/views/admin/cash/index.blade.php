<x-layouts.admin title="Sổ thu chi" heading="Hóa đơn & thu chi">
    @include('admin.partials.finance-nav')

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Sổ thu chi</h2>
                <p>Dòng tiền được tính theo thời điểm phát sinh của giao dịch, độc lập với ngày lập hóa đơn.</p>
            </div>
            <div class="admin-page-actions">
                <a href="{{ route('admin.reports.export.cash', request()->query()) }}" class="admin-button is-ghost">Xuất CSV</a>
                <a href="{{ route('admin.cash.create') }}" class="admin-button">+ Ghi khoản thu/chi</a>
            </div>
        </header>

        <x-admin.filter-bar :action="route('admin.cash.index')">
            <label>Tìm kiếm<input type="search" name="search" value="{{ request('search') }}" placeholder="Tham chiếu hoặc ghi chú"></label>
            <label>
                Loại
                <select name="type">
                    <option value="">Tất cả</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Hạng mục
                <select name="category">
                    <option value="">Tất cả</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Phương thức
                <select name="payment_method">
                    <option value="">Tất cả</option>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Từ ngày<input type="date" name="from" value="{{ request('from') }}"></label>
            <label>Đến ngày<input type="date" name="to" value="{{ request('to') }}"></label>
            <label class="admin-checkbox"><input type="checkbox" name="include_voided" value="1" @checked(request()->boolean('include_voided'))><span>Hiện cả giao dịch đã hủy</span></label>
        </x-admin.filter-bar>

        <div class="admin-summary-grid">
            <div><p>Tổng thu theo bộ lọc</p><strong><x-admin.money :value="$totals['income']" /></strong></div>
            <div><p>Tổng chi theo bộ lọc</p><strong><x-admin.money :value="$totals['expense']" /></strong></div>
            <div><p>Số dư</p><strong><x-admin.money :value="$totals['balance']" signed /></strong></div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Chi nhánh</th>
                        <th>Thời điểm</th>
                        <th>Loại</th>
                        <th>Hạng mục</th>
                        <th class="admin-numeric">Số tiền</th>
                        <th>Phương thức</th>
                        <th>Nguồn</th>
                        <th>Người tạo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td><span class="admin-branch-chip">{{ $transaction->branch?->code }}</span></td>
                            <td>
                                <strong>{{ $transaction->occurred_at?->format('d/m/Y') }}</strong>
                                <p>{{ $transaction->occurred_at?->format('H:i') }}</p>
                            </td>
                            <td><x-admin.status-badge :status="$transaction->type" /></td>
                            <td>
                                {{ $transaction->category->label() }}
                                @if ($transaction->note)<p>{{ $transaction->note }}</p>@endif
                            </td>
                            <td class="admin-numeric">
                                <x-admin.money :value="$transaction->type->sign() * (float) $transaction->amount" signed />
                            </td>
                            <td>{{ $transaction->payment_method?->label() ?? '—' }}</td>
                            <td>
                                @if ($transaction->invoice)
                                    <a href="{{ route('admin.invoices.edit', $transaction->invoice) }}">{{ $transaction->invoice->number }}</a>
                                @elseif ($transaction->payroll)
                                    <a href="{{ route('admin.payrolls.show', $transaction->payroll) }}">Lương {{ $transaction->payroll->employee?->name }}</a>
                                @else
                                    {{ $transaction->reference ?: 'Nhập tay' }}
                                @endif
                            </td>
                            <td>{{ $transaction->creator?->name ?? '—' }}</td>
                            <td>
                                <div class="admin-row-actions">
                                    @if ($transaction->isVoided())
                                        <x-admin.status-badge tone="is-danger" label="Đã hủy" />
                                    @else
                                        @can('update', $transaction)
                                            <a href="{{ route('admin.cash.edit', $transaction) }}">Sửa</a>
                                        @endcan
                                        @can('void', $transaction)
                                            <x-admin.confirm-form
                                                :action="route('admin.cash.destroy', $transaction)"
                                                method="DELETE"
                                                label="Hủy"
                                                message="Hủy giao dịch này? Dữ liệu vẫn được giữ lại để đối soát."
                                            >
                                                <input type="hidden" name="void_reason" value="Hủy bởi người dùng">
                                            </x-admin.confirm-form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="9" title="Chưa có giao dịch nào" hint="Thanh toán hóa đơn hoặc ghi khoản thu/chi thủ công để bắt đầu theo dõi dòng tiền." />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$transactions" />
    </section>
</x-layouts.admin>
