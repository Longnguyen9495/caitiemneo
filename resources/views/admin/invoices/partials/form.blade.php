@php
    $editable = $invoice->isEditable() && auth()->user()->can('update', $invoice);
    $canApproveOvertime = auth()->user()->can('approveOvertime', $invoice);
    // Giá ngoài khoảng bảng giá là quyết định của quản lý, và phải kèm lý do.
    $canOverridePrice = auth()->user()->can('overridePrice', $invoice);
    // Chỉ chủ tiệm được tự đặt tỷ lệ hoa hồng, và bắt buộc kèm lý do.
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
        // Lý do chỉ tồn tại trong một lần gửi, không lưu trên dòng hóa đơn:
        // nó đã nằm trong audit event của lần sửa tương ứng.
        'price_override_reason' => '',
    ])->values()->all());
@endphp

<section class="card mb-3" x-data="invoiceEditor(@js($rows), @js($errors->getMessages()))">
    <div class="card-header">
        <h2 class="neo-display fs-5 mb-0">Nội dung hóa đơn</h2>
        <p class="mb-0 small text-body-secondary">Thành tiền, hoa hồng và tổng cộng luôn do máy chủ tính lại khi lưu.</p>
    </div>

    @unless ($editable)
        <div class="card-body pb-0">
            <p class="alert alert-secondary mb-0 py-2 small">
                Hóa đơn đang ở trạng thái <strong>{{ $invoice->status->label() }}</strong> nên các trường tài chính đã bị khóa.
            </p>
        </div>
    @endunless

    <form method="POST" data-neo-dirty-guard action="{{ route('admin.invoices.update', $invoice) }}">
        @csrf
        @method('PATCH')
        {{-- Cho phép xóa hết dòng dịch vụ: trình duyệt không gửi mảng rỗng. --}}
        <input type="hidden" name="items_submitted" value="1">

        {{-- Phiên bản của hóa đơn lúc mở biểu mẫu. Nếu người khác đã lưu trong
             lúc này, máy chủ từ chối thay vì lặng lẽ ghi đè công của họ. --}}
        <input type="hidden" name="expected_version" value="{{ $invoice->updated_at?->timestamp }}">

        <div class="card-body pt-3">
            {{-- Mỗi dòng là một thẻ riêng: dễ đọc và đủ chỗ chạm trên điện thoại. --}}
            <template x-for="(row, index) in rows" :key="row.key">
                <fieldset class="border rounded-3 p-3 mb-2">
                    <input type="hidden" :name="`items[${index}][id]`" :value="row.id ?? ''">

                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-lg-4">
                            <label class="form-label" :for="`svc-${index}`">Dịch vụ</label>
                            <select class="form-select" :id="`svc-${index}`" :name="`items[${index}][service_id]`"
                                    x-model="row.service_id" x-on:change="applyService(row)" @disabled(! $editable)>
                                <option value="">Dòng tự nhập</option>
                                @foreach ($services->groupBy(fn ($item) => $item->category?->label() ?? 'Khác') as $groupLabel => $grouped)
                                    <optgroup label="{{ $groupLabel }}">
                                        @foreach ($grouped as $service)
                                            <option value="{{ $service->id }}" data-price="{{ $service->price }}" data-label="{{ $service->name }}"
                                                    data-unit="{{ $service->unit?->value }}" data-unit-label="{{ $service->quantityLabel() }}"
                                                    @if ($service->hasPriceRange()) data-min="{{ $service->price_min }}" data-max="{{ $service->price_max }}" data-range-label="{{ $service->formattedPriceRange() }}" @endif>{{ $service->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label" :for="`emp-${index}`">Nhân viên</label>
                            <select class="form-select" :id="`emp-${index}`" :name="`items[${index}][employee_id]`" x-model="row.employee_id" @disabled(! $editable)>
                                <option value="">Chưa phân công</option>
                                @foreach ($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" :for="`qty-${index}`" x-text="row.unit_label || 'Số lượng'">Số lượng</label>
                            <input class="form-control text-end neo-num" :id="`qty-${index}`" type="number" step="0.01" min="0.01" :name="`items[${index}][quantity]`" x-model="row.quantity" required @disabled(! $editable)>
                        </div>
                        <div class="col-6 col-lg-3">
                            <label class="form-label" :for="`price-${index}`">Đơn giá</label>
                            <input class="form-control text-end neo-num" :id="`price-${index}`" type="number" step="1000" min="0" :class="priceOutOfRange(row) ? 'border-warning' : ''" :name="`items[${index}][unit_price]`" x-model="row.unit_price" required @disabled(! $editable)>
                            <div class="form-text" x-show="row.range_label" x-cloak>Bảng giá: <span class="neo-num" x-text="row.range_label"></span></div>
                            <div class="invalid-feedback d-block" role="alert" x-show="rowError(index, 'unit_price')" x-cloak x-text="rowError(index, 'unit_price')"></div>
                        </div>
                        <div class="col-12" x-show="!row.service_id" x-cloak>
                            <label class="form-label" :for="`name-${index}`">Tên dòng tự nhập</label>
                            <input class="form-control" :id="`name-${index}`" type="text" :name="`items[${index}][name]`" x-model="row.name" :required="!row.service_id" placeholder="Ví dụ: Phụ phí móng dài" @disabled(! $editable)>
                        </div>
                    </div>

                    <details class="mt-3">
                        <summary class="small text-primary fw-semibold">Tuỳ chọn nâng cao (bối cảnh, hoa hồng và giá ngoại lệ)</summary>
                        <div class="row g-2 mt-1">
                            <div class="col-6 col-lg-4">
                                <label class="form-label" :for="`ctx-${index}`">Bối cảnh</label>
                                <select class="form-select" :id="`ctx-${index}`" :name="`items[${index}][work_context]`" x-model="row.work_context" @disabled(! $editable || ! $canApproveOvertime)>
                                    @foreach (App\Enums\WorkContext::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label" :for="`rate-${index}`">Hoa hồng %</label>
                                <input class="form-control text-end neo-num" :id="`rate-${index}`" type="number" step="0.5" min="0" max="100" :name="`items[${index}][commission_rate]`" x-model="row.commission_rate" @disabled(! $editable || ! $canOverrideRate)>
                            </div>
                            @if ($canOverrideRate)
                                <div class="col-12 col-lg-5">
                                    <label class="form-label" :for="`reason-${index}`">Lý do đổi tỷ lệ</label>
                                    <input class="form-control" :id="`reason-${index}`" type="text" :name="`items[${index}][commission_rate_reason]`" x-model="row.commission_rate_reason" placeholder="Bắt buộc khi tự đặt tỷ lệ" @disabled(! $editable)>
                                </div>
                            @endif
                            @if ($canOverridePrice)
                                <div class="col-12" x-show="priceOutOfRange(row)" x-cloak>
                                    <label class="form-label" :for="`price-reason-${index}`">Lý do giá ngoài khoảng</label>
                                    <input class="form-control" :class="rowError(index, 'price_override_reason') ? 'is-invalid' : ''" :id="`price-reason-${index}`" type="text" maxlength="255" :name="`items[${index}][price_override_reason]`" x-model="row.price_override_reason" placeholder="Ví dụ: ca khó, làm lại phần bị lỗi" @disabled(! $editable)>
                                    <div class="invalid-feedback d-block" role="alert" x-show="rowError(index, 'price_override_reason')" x-cloak x-text="rowError(index, 'price_override_reason')"></div>
                                </div>
                            @endif
                        </div>
                    </details>

                    <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
                        <span class="small text-body-secondary">Thành tiền</span>
                        <span class="d-flex align-items-center gap-3">
                            <strong class="neo-num" x-text="formatMoney(lineTotal(row))"></strong>
                            @if ($editable)
                                <button type="button" class="btn btn-sm btn-outline-danger" x-on:click="rows.splice(index, 1)">Xóa</button>
                            @endif
                        </span>
                    </div>
                </fieldset>
            </template>

            <p class="text-body-secondary small" x-show="rows.length === 0">Hóa đơn chưa có dòng dịch vụ nào.</p>

            @if ($editable)
                <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" x-on:click="addRow()">
                    <x-admin.icon name="plus" size="16" /> Thêm dòng dịch vụ
                </button>
            @endif

            <p class="d-flex justify-content-between align-items-baseline mt-3 mb-0 pt-3 border-top">
                <span class="text-body-secondary small">Tạm tính (máy chủ tính lại khi lưu)</span>
                <strong class="fs-6 neo-num" x-text="formatMoney(subtotal())"></strong>
            </p>
        </div>

        <div class="card-body border-top">
            <details>
                <summary class="fw-semibold text-primary">Thông tin thêm</summary>
                <p class="small text-body-secondary mb-3">Khách hàng, giảm giá, phương thức dự kiến và ghi chú.</p>
                <div class="row g-3">
                    <x-admin.field name="customer_name" label="Tên khách hàng">
                        <input class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', $invoice->customer_name) }}" @disabled(! $editable)>
                    </x-admin.field>
                    <x-admin.field name="customer_phone" label="Số điện thoại">
                        <input class="form-control neo-num" id="customer_phone" name="customer_phone" type="tel" value="{{ old('customer_phone', $invoice->customer_phone) }}" @disabled(! $editable)>
                    </x-admin.field>
                    <x-admin.field name="discount" label="Giảm giá">
                        <x-admin.money-input name="discount" :value="$invoice->discount" :disabled="! $editable" required />
                    </x-admin.field>
                    <x-admin.field name="payment_method" label="Phương thức dự kiến">
                        <select class="form-select" id="payment_method_expected" name="payment_method" @disabled(! $editable)>
                            <option value="">Chưa chọn</option>
                            @foreach ($paymentMethods as $value => $label)<option value="{{ $value }}" @selected(old('payment_method', $invoice->payment_method?->value) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </x-admin.field>
                    <x-admin.field name="note" label="Ghi chú" col="col-12">
                        <textarea class="form-control" id="note" name="note" rows="2" @disabled(! $editable)>{{ old('note', $invoice->note) }}</textarea>
                    </x-admin.field>
                </div>
            </details>

            @if ($editable)
                <div class="neo-formbar">
                    <a href="{{ route('admin.invoices.index') }}" class="btn btn-light">Quay lại</a>
                    <x-admin.submit-button label="Lưu hóa đơn" />
                </div>
            @endif
        </div>
    </form>
</section>
