<x-layouts.admin
    :title="$transaction->exists ? 'Sửa giao dịch' : 'Ghi khoản thu chi'"
    heading="Hóa đơn & thu chi"
>
    @include('admin.partials.finance-nav')

    <section class="admin-form-panel">
        <x-admin.page-header
            :title="$transaction->exists ? 'Chỉnh sửa giao dịch' : 'Ghi khoản thu / chi thủ công'"
            description="Số tiền luôn nhập dương. Chiều tiền do loại giao dịch quyết định."
            :breadcrumbs="['Sổ thu chi' => route('admin.cash.index'), ($transaction->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
        />

        <form
            method="POST"
            action="{{ $transaction->exists ? route('admin.cash.update', $transaction) : route('admin.cash.store') }}"
            class="admin-form"
        >
            @csrf
            @if ($transaction->exists)
                @method('PUT')
            @endif

            <label>
                Loại giao dịch
                <select name="type" required>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $transaction->type?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Hạng mục
                <select name="category" required>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(old('category', $transaction->category?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Số tiền (VNĐ)
                <x-admin.money-input name="amount" :value="$transaction->amount" min="1" required />
                @error('amount')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Phương thức
                <select name="payment_method">
                    <option value="">Không xác định</option>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method', $transaction->payment_method?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('payment_method')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Thời điểm phát sinh
                <input type="datetime-local" name="occurred_at" required
                    value="{{ old('occurred_at', ($transaction->occurred_at ?? now())->format('Y-m-d\TH:i')) }}">
                @error('occurred_at')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Tham chiếu
                <input name="reference" value="{{ old('reference', $transaction->reference) }}" placeholder="Số phiếu, hợp đồng…">
                @error('reference')<small>{{ $message }}</small>@enderror
            </label>

            <label class="admin-form-wide">
                Ghi chú
                <textarea name="note" rows="3">{{ old('note', $transaction->note) }}</textarea>
                @error('note')<small>{{ $message }}</small>@enderror
            </label>

            <div class="admin-form-actions admin-form-wide">
                <a href="{{ route('admin.cash.index') }}">Hủy</a>
                <x-admin.submit-button :label="$transaction->exists ? 'Lưu thay đổi' : 'Ghi nhận giao dịch'" />
            </div>
        </form>
    </section>
</x-layouts.admin>
