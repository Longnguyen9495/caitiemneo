<x-layouts.admin title="Sổ thu chi" heading="Hóa đơn & thu chi">
    @include('admin.partials.finance-nav')

    <x-admin.page-header title="Sổ thu chi" description="Dòng tiền tính theo thời điểm phát sinh, độc lập với ngày lập hóa đơn.">
        <x-slot:actions>
            <a href="{{ route('admin.reports.export.cash', request()->query()) }}" class="btn btn-sm btn-outline-secondary">Xuất CSV</a>
            <a href="{{ route('admin.cash.create') }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                <x-admin.icon name="plus" size="18" /> Ghi khoản
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-2 mb-3">
        <div class="col-4">
            <dl class="neo-stat mb-0"><dt>Tổng thu</dt><dd class="fs-6"><x-admin.money :value="$totals['income']" /></dd></dl>
        </div>
        <div class="col-4">
            <dl class="neo-stat mb-0"><dt>Tổng chi</dt><dd class="fs-6"><x-admin.money :value="$totals['expense']" /></dd></dl>
        </div>
        <div class="col-4">
            <dl class="neo-stat mb-0"><dt>Số dư</dt><dd class="fs-6"><x-admin.money :value="$totals['balance']" signed /></dd></dl>
        </div>
    </div>

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.cash.index')">
            <div class="col-12 col-lg-3">
                <label class="form-label" for="search">Tìm kiếm</label>
                <input class="form-control" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Tham chiếu hoặc ghi chú">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="type">Loại</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tất cả</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="category">Hạng mục</label>
                <select class="form-select" id="category" name="category">
                    <option value="">Tất cả</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="from">Từ ngày</label>
                <input class="form-control" id="from" type="date" name="from" value="{{ request('from') }}">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="to">Đến ngày</label>
                <input class="form-control" id="to" type="date" name="to" value="{{ request('to') }}">
            </div>
            <div class="col-12">
                <label class="form-check d-inline-flex align-items-center gap-2 mb-0">
                    <input class="form-check-input m-0" type="checkbox" name="include_voided" value="1" @checked(request()->boolean('include_voided'))>
                    <span class="small">Hiện cả giao dịch đã hủy</span>
                </label>
            </div>
        </x-admin.filter-bar>

        <table class="table neo-table align-middle mb-0">
            <caption class="visually-hidden">Danh sách giao dịch thu chi</caption>
            <thead>
                <tr>
                    <th scope="col">Thời điểm</th>
                    <th scope="col">Chi nhánh</th>
                    <th scope="col">Hạng mục</th>
                    <th scope="col" class="text-end">Số tiền</th>
                    <th scope="col">Phương thức</th>
                    <th scope="col">Nguồn</th>
                    <th scope="col">Người tạo</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td>
                            <span class="fw-semibold neo-num">{{ $transaction->occurred_at?->format('d/m/Y') }}</span>
                            <small class="text-body-secondary neo-num">{{ $transaction->occurred_at?->format('H:i') }}</small>
                        </td>
                        <td data-label="Chi nhánh"><span class="badge rounded-pill text-bg-light border fw-normal">{{ $transaction->branch?->code }}</span></td>
                        <td data-label="Hạng mục">
                            <x-admin.status-badge :status="$transaction->type" class="me-1" />
                            {{ $transaction->category->label() }}
                            @if ($transaction->note)<small class="d-block text-body-secondary">{{ $transaction->note }}</small>@endif
                        </td>
                        <td data-label="Số tiền" class="text-end fw-semibold">
                            <x-admin.money :value="$transaction->type->sign() * (float) $transaction->amount" signed />
                        </td>
                        <td data-label="Phương thức">{{ $transaction->payment_method?->label() ?? '—' }}</td>
                        <td data-label="Nguồn">
                            @if ($transaction->invoice)
                                <a href="{{ route('admin.invoices.edit', $transaction->invoice) }}">{{ $transaction->invoice->number }}</a>
                            @elseif ($transaction->payroll)
                                <a href="{{ route('admin.payrolls.show', $transaction->payroll) }}">Lương {{ $transaction->payroll->employee?->name }}</a>
                            @else
                                {{ $transaction->reference ?: 'Nhập tay' }}
                            @endif
                        </td>
                        <td data-label="Người tạo">{{ $transaction->creator?->name ?? '—' }}</td>
                        <td>
                            @if ($transaction->isVoided())
                                <x-admin.status-badge tone="is-danger" label="Đã hủy" />
                            @else
                                <span class="d-inline-flex flex-wrap gap-2">
                                    @can('update', $transaction)
                                        <a href="{{ route('admin.cash.edit', $transaction) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                    @endcan
                                    @can('void', $transaction)
                                        {{-- Lý do do người dùng gõ trong hộp xác nhận, không có giá trị mặc định ẩn. --}}
                                        <x-admin.confirm-form
                                            :action="route('admin.cash.destroy', $transaction)"
                                            method="DELETE"
                                            label="Hủy"
                                            :message="'Hủy giao dịch '.\App\Support\Money::format($transaction->amount).' ('.$transaction->category->label().') tại '.($transaction->branch?->name ?? 'chi nhánh này').'? Giao dịch vẫn được giữ lại để đối soát.'"
                                            reason-field="void_reason"
                                            reason-label="Lý do hủy"
                                        />
                                    @endcan
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="8"
                        icon="wallet"
                        title="Chưa có giao dịch nào"
                        hint="Thanh toán hóa đơn hoặc ghi khoản thu chi thủ công để bắt đầu theo dõi dòng tiền."
                    />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$transactions" />
    </section>
</x-layouts.admin>
