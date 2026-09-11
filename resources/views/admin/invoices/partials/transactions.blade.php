@if ($invoice->cashTransactions->isNotEmpty())
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Giao dịch liên quan</h2>
                <p>Các bút toán sinh tự động từ hóa đơn này. Chúng không thể sửa trực tiếp trong sổ thu chi.</p>
            </div>
        </header>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Thời điểm</th><th>Loại</th><th>Hạng mục</th><th class="admin-numeric">Số tiền</th><th>Người thực hiện</th></tr>
                </thead>
                <tbody>
                    @foreach ($invoice->cashTransactions as $transaction)
                        <tr>
                            <td>{{ $transaction->occurred_at?->format('d/m/Y H:i') }}</td>
                            <td><x-admin.status-badge :status="$transaction->type" /></td>
                            <td>{{ $transaction->category->label() }}</td>
                            <td class="admin-numeric"><x-admin.money :value="$transaction->amount" /></td>
                            <td>{{ $transaction->creator?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
