@php
    $productOptions = $products->map(fn ($product) => [
        'id' => $product->id,
        'name' => $product->name.' ('.$product->unit.')',
        'cost' => (string) $product->cost_price,
    ])->values();
@endphp

<x-layouts.admin title="Tạo phiếu chuyển kho" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <section class="admin-form-panel">
        <x-admin.page-header
            title="Tạo phiếu chuyển kho"
            description="Phiếu được lưu ở trạng thái nháp; tồn kho chỉ thay đổi khi bạn hoàn tất phiếu."
            :breadcrumbs="['Chuyển kho' => route('admin.stock-transfers.index'), 'Tạo phiếu' => null]"
        />

        <form method="POST" action="{{ route('admin.stock-transfers.store') }}" x-data="transferEditor(@js($productOptions))">
            @csrf

            <div class="admin-form">
                <label>
                    Chi nhánh gửi
                    <select name="source_branch_id" required>
                        @foreach ($sourceBranches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('source_branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('source_branch_id')<small>{{ $message }}</small>@enderror
                </label>

                <label>
                    Chi nhánh nhận
                    <select name="destination_branch_id" required>
                        <option value="">Chọn chi nhánh nhận</option>
                        @foreach ($destinationBranches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('destination_branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('destination_branch_id')<small>{{ $message }}</small>@enderror
                </label>

                <label class="admin-form-wide">
                    Ghi chú
                    <input name="note" value="{{ old('note') }}" placeholder="Lý do điều chuyển…">
                    @error('note')<small>{{ $message }}</small>@enderror
                </label>
            </div>

            <div class="admin-table-wrap" style="margin-top: 1.2rem;">
                <table class="admin-table">
                    <thead><tr><th style="min-width: 14rem;">Vật tư</th><th class="admin-numeric">Số lượng</th><th class="admin-numeric">Đơn giá</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(row, index) in rows" :key="row.key">
                            <tr>
                                <td>
                                    <select :name="`items[${index}][product_id]`" x-model="row.product_id" x-on:change="applyProduct(row)" required>
                                        <option value="">Chọn vật tư</option>
                                        <template x-for="product in products" :key="product.id">
                                            <option :value="product.id" x-text="product.name"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="admin-numeric"><input class="admin-money-input" type="number" step="0.01" min="0.01" :name="`items[${index}][quantity]`" x-model="row.quantity" required></td>
                                <td class="admin-numeric"><input class="admin-money-input" type="number" step="1000" min="0" :name="`items[${index}][unit_cost]`" x-model="row.unit_cost"></td>
                                <td><button type="button" class="admin-button is-ghost" x-on:click="rows.splice(index, 1)" x-show="rows.length > 1">Xóa</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            @error('items')<small style="color:#b83c68; font-size:.7rem;">{{ $message }}</small>@enderror

            <div class="admin-form-actions" style="margin-top: 1rem;">
                <button type="button" class="admin-button is-ghost" x-on:click="addRow()">+ Thêm dòng</button>
                <a href="{{ route('admin.stock-transfers.index') }}">Hủy</a>
                <x-admin.submit-button label="Tạo phiếu nháp" />
            </div>
        </form>
    </section>
</x-layouts.admin>
