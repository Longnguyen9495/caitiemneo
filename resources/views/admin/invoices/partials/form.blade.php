@php
    $editable = $invoice->isEditable() && auth()->user()->can('update', $invoice);
    $canApproveOvertime = auth()->user()->can('approveOvertime', $invoice);
    // Only the owner may hand-set a commission rate, and only with a reason.
    $canOverrideRate = auth()->user()->isOwner();
    $rows = old('items', $invoice->items->map(fn ($item) => [
        'id' => $item->id,
        'service_id' => $item->service_id,
        'employee_id' => $item->employee_id,
        'name' => $item->name,
        'quantity' => \App\Support\Money::toDecimal(\App\Support\Money::toMinor($item->quantity)),
        'unit_price' => \App\Support\Money::toDecimal(\App\Support\Money::toMinor($item->unit_price)),
        'commission_rate' => \App\Support\Money::toDecimal(\App\Support\Money::toMinor($item->commission_rate)),
        'work_context' => $item->work_context->value,
        'commission_rate_reason' => $item->commission_rate_reason,
    ])->values()->all());
@endphp

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <h2>Nội dung hóa đơn</h2>
            <p>Thành tiền, hoa hồng và tổng cộng luôn do máy chủ tính lại từ đơn giá và số lượng.</p>
        </div>
    </header>

    @unless ($editable)
        <div class="admin-panel-body">
            <p class="admin-muted-text">
                Hóa đơn đang ở trạng thái <strong>{{ $invoice->status->label() }}</strong> nên các trường tài chính đã bị khóa.
            </p>
        </div>
    @endunless

    <form method="POST" action="{{ route('admin.invoices.update', $invoice) }}" x-data="invoiceEditor(@js($rows))">
        @csrf
        @method('PATCH')

        {{-- Cho phép xóa hết dòng dịch vụ: trình duyệt không gửi mảng rỗng. --}}
        <input type="hidden" name="items_submitted" value="1">

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="min-width: 13rem;">Dịch vụ</th>
                        <th>Nhân viên</th>
                        <th class="admin-numeric">Số lượng</th>
                        <th class="admin-numeric">Đơn giá</th>
                        <th>Bối cảnh</th>
                        <th class="admin-numeric">Hoa hồng %</th>
                        <th class="admin-numeric">Thành tiền</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in rows" :key="row.key">
                        <tr>
                            <td>
                                <input type="hidden" :name="`items[${index}][id]`" :value="row.id ?? ''">
                                <select :name="`items[${index}][service_id]`" x-model="row.service_id" x-on:change="applyService(row)" @disabled(! $editable)>
                                    <option value="">Dòng tự nhập</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}" data-price="{{ $service->price }}" data-label="{{ $service->name }}">{{ $service->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" :name="`items[${index}][name]`" x-model="row.name" placeholder="Tên hiển thị trên hóa đơn" required @disabled(! $editable)>
                            </td>
                            <td>
                                <select :name="`items[${index}][employee_id]`" x-model="row.employee_id" @disabled(! $editable)>
                                    <option value="">Chưa phân công</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="admin-numeric"><input class="admin-money-input" type="number" step="0.01" min="0.01" :name="`items[${index}][quantity]`" x-model="row.quantity" required @disabled(! $editable)></td>
                            <td class="admin-numeric"><input class="admin-money-input" type="number" step="1000" min="0" :name="`items[${index}][unit_price]`" x-model="row.unit_price" required @disabled(! $editable)></td>
                            <td>
                                <select :name="`items[${index}][work_context]`" x-model="row.work_context" @disabled(! $editable || ! $canApproveOvertime)>
                                    @foreach (App\Enums\WorkContext::options() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="admin-numeric">
                                <input class="admin-money-input" type="number" step="0.5" min="0" max="100" :name="`items[${index}][commission_rate]`" x-model="row.commission_rate" @disabled(! $editable || ! $canOverrideRate)>
                                @if ($canOverrideRate)
                                    <input type="text" :name="`items[${index}][commission_rate_reason]`" x-model="row.commission_rate_reason" placeholder="Lý do đổi tỷ lệ" @disabled(! $editable)>
                                @endif
                            </td>
                            <td class="admin-numeric"><span class="admin-money" x-text="formatMoney(lineTotal(row))"></span></td>
                            <td>
                                @if ($editable)
                                    <button type="button" class="admin-button is-ghost" x-on:click="rows.splice(index, 1)">Xóa</button>
                                @endif
                            </td>
                        </tr>
                    </template>
                    <tr x-show="rows.length === 0">
                        <td colspan="8" class="admin-empty"><strong>Hóa đơn chưa có dòng dịch vụ nào</strong></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="6">Tạm tính (máy chủ tính lại khi lưu)</th>
                        <th class="admin-numeric" colspan="2"><span x-text="formatMoney(subtotal())"></span></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="admin-panel-body">
            @if ($editable)
                <p><button type="button" class="admin-button is-ghost" x-on:click="addRow()">+ Thêm dòng dịch vụ</button></p>
            @endif

            <div class="admin-form">
                <label>
                    Tên khách hàng
                    <input name="customer_name" value="{{ old('customer_name', $invoice->customer_name) }}" @disabled(! $editable)>
                    @error('customer_name')<small>{{ $message }}</small>@enderror
                </label>
                <label>
                    Số điện thoại
                    <input name="customer_phone" value="{{ old('customer_phone', $invoice->customer_phone) }}" @disabled(! $editable)>
                    @error('customer_phone')<small>{{ $message }}</small>@enderror
                </label>
                <label>
                    Giảm giá (VNĐ)
                    <x-admin.money-input name="discount" :value="$invoice->discount" :disabled="! $editable" required />
                    @error('discount')<small>{{ $message }}</small>@enderror
                </label>
                <label>
                    Phương thức dự kiến
                    <select name="payment_method" @disabled(! $editable)>
                        <option value="">Chưa chọn</option>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method', $invoice->payment_method?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<small>{{ $message }}</small>@enderror
                </label>
                <label class="admin-form-wide">
                    Ghi chú
                    <textarea name="note" rows="3" @disabled(! $editable)>{{ old('note', $invoice->note) }}</textarea>
                    @error('note')<small>{{ $message }}</small>@enderror
                </label>
            </div>

            @if ($editable)
                <div class="admin-form-actions">
                    <a href="{{ route('admin.invoices.index') }}">Quay lại danh sách</a>
                    <x-admin.submit-button label="Lưu hóa đơn" />
                </div>
            @endif
        </div>
    </form>
</section>
