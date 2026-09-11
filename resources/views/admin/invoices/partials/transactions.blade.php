@if ($invoice->cashTransactions->isNotEmpty())
    <section class="card">
        <div class="card-header">
            <h2 class="neo-display fs-5 mb-0">Giao dịch liên quan</h2>
            <p class="mb-0 small text-body-secondary">Bút toán sinh tự động từ hóa đơn này, không sửa được trong sổ thu chi.</p>
        </div>

        <table class="table neo-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Thời điểm</th>
                    <th scope="col">Loại</th>
                    <th scope="col">Hạng mục</th>
                    <th scope="col" class="text-end">Số tiền</th>
                    <th scope="col">Người thực hiện</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->cashTransactions as $transaction)
                    <tr>
                        <td class="neo-num">{{ $transaction->occurred_at?->format('d/m/Y H:i') }}</td>
                        <td data-label="Loại"><x-admin.status-badge :status="$transaction->type" /></td>
                        <td data-label="Hạng mục">{{ $transaction->category->label() }}</td>
                        <td data-label="Số tiền" class="text-end fw-semibold"><x-admin.money :value="$transaction->amount" /></td>
                        <td data-label="Người thực hiện">{{ $transaction->creator?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif
