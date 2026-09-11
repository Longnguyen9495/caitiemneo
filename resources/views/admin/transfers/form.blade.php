@php
    $productOptions = $products->map(fn ($product) => [
        'id' => $product->id,
        'name' => $product->name.' ('.$product->unit.')',
        'cost' => (string) $product->cost_price,
    ])->values();
@endphp

<x-layouts.admin title="Tạo phiếu chuyển kho" heading="Chuyển kho">
    @include('admin.partials.inventory-nav')

    <x-admin.page-header
        title="Tạo phiếu chuyển kho"
        description="Phiếu lưu ở trạng thái nháp; tồn kho chỉ thay đổi khi bạn hoàn tất phiếu."
        :breadcrumbs="['Chuyển kho' => route('admin.stock-transfers.index'), 'Tạo phiếu' => null]"
    />

    <form method="POST" data-neo-dirty-guard action="{{ route('admin.stock-transfers.store') }}" class="card p-3 p-lg-4"
          x-data="transferEditor(@js($productOptions), @js(old('items', [])), @js($errors->getMessages()))">
        @csrf

        <div class="row g-3">
            <x-admin.field name="source_branch_id" label="Chi nhánh gửi" required>
                <select class="form-select @error('source_branch_id') is-invalid @enderror" id="source_branch_id" name="source_branch_id" required>
                    @foreach ($sourceBranches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('source_branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="destination_branch_id" label="Chi nhánh nhận" required>
                <select class="form-select @error('destination_branch_id') is-invalid @enderror" id="destination_branch_id" name="destination_branch_id" required>
                    <option value="">Chọn chi nhánh nhận</option>
                    @foreach ($destinationBranches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('destination_branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="note" label="Ghi chú" col="col-12">
                <input class="form-control @error('note') is-invalid @enderror" id="note" name="note"
                       value="{{ old('note') }}" placeholder="Lý do điều chuyển…">
            </x-admin.field>
        </div>

        <h3 class="fs-6 fw-semibold mt-4 mb-2">Vật tư cần chuyển</h3>

        <template x-for="(row, index) in rows" :key="row.key">
            <fieldset class="border rounded-3 p-3 mb-2">
                <div class="row g-2">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" :for="`prod-${index}`">Vật tư</label>
                        <select class="form-select" :class="rowError(index, 'product_id') ? 'is-invalid' : ''"
                                :id="`prod-${index}`" :name="`items[${index}][product_id]`"
                                :aria-invalid="rowError(index, 'product_id') ? 'true' : 'false'"
                                x-model="row.product_id" x-on:change="applyProduct(row)" required>
                            <option value="">Chọn vật tư</option>
                            <template x-for="product in products" :key="product.id">
                                <option :value="product.id" x-text="product.name"></option>
                            </template>
                        </select>
                        <div class="invalid-feedback d-block" role="alert" x-show="rowError(index, 'product_id')" x-cloak
                             x-text="rowError(index, 'product_id')"></div>
                    </div>

                    <div class="col-6 col-lg-3">
                        <label class="form-label" :for="`tqty-${index}`">Số lượng</label>
                        <input class="form-control text-end neo-num" :class="rowError(index, 'quantity') ? 'is-invalid' : ''"
                               :id="`tqty-${index}`" type="number" step="0.01" min="0.01"
                               :aria-invalid="rowError(index, 'quantity') ? 'true' : 'false'"
                               :name="`items[${index}][quantity]`" x-model="row.quantity" required>
                        <div class="invalid-feedback d-block" role="alert" x-show="rowError(index, 'quantity')" x-cloak
                             x-text="rowError(index, 'quantity')"></div>
                    </div>

                    <div class="col-6 col-lg-3">
                        <label class="form-label" :for="`tcost-${index}`">Đơn giá</label>
                        <input class="form-control text-end neo-num" :id="`tcost-${index}`" type="number" step="1000" min="0"
                               :name="`items[${index}][unit_cost]`" x-model="row.unit_cost">
                    </div>
                </div>

                <div class="text-end mt-2" x-show="rows.length > 1">
                    <button type="button" class="btn btn-sm btn-outline-danger" x-on:click="rows.splice(index, 1)">Xóa dòng</button>
                </div>
            </fieldset>
        </template>

        @error('items')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

        <button type="button" class="btn btn-outline-primary btn-sm align-self-start d-inline-flex align-items-center gap-1" x-on:click="addRow()">
            <x-admin.icon name="plus" size="16" /> Thêm dòng
        </button>

        <div class="neo-formbar">
            <a href="{{ route('admin.stock-transfers.index') }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button label="Tạo phiếu nháp" />
        </div>
    </form>
</x-layouts.admin>
