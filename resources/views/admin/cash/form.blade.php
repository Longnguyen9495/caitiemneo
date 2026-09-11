<x-layouts.admin
    :title="$transaction->exists ? 'Sửa giao dịch' : 'Ghi khoản thu chi'"
    heading="Hóa đơn & thu chi"
>
    @include('admin.partials.finance-nav')

    <x-admin.page-header
        :title="$transaction->exists ? 'Chỉnh sửa giao dịch' : 'Ghi khoản thu / chi'"
        description="Số tiền luôn nhập dương. Chiều tiền do loại giao dịch quyết định."
        :breadcrumbs="['Sổ thu chi' => route('admin.cash.index'), ($transaction->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]"
    />

    {{-- data-neo-dirty-guard: cảnh báo nếu rời trang khi đã gõ mà chưa lưu. --}}
    <form method="POST" class="card p-3 p-lg-4" data-neo-dirty-guard
          action="{{ $transaction->exists ? route('admin.cash.update', $transaction) : route('admin.cash.store') }}">
        @csrf
        @if ($transaction->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <x-admin.field name="type" label="Loại giao dịch" required>
                <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $transaction->type?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="category" label="Hạng mục" required>
                <select class="form-select @error('category') is-invalid @enderror" id="category" name="category" required>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(old('category', $transaction->category?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="amount" label="Số tiền" required>
                <x-admin.money-input name="amount" :value="$transaction->amount" min="1" required />
            </x-admin.field>

            <x-admin.field name="payment_method" label="Phương thức">
                <select class="form-select @error('payment_method') is-invalid @enderror" id="payment_method" name="payment_method">
                    <option value="">Không xác định</option>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method', $transaction->payment_method?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="occurred_at" label="Thời điểm phát sinh" required>
                <input class="form-control @error('occurred_at') is-invalid @enderror" id="occurred_at"
                       type="datetime-local" name="occurred_at" required
                       value="{{ old('occurred_at', ($transaction->occurred_at ?? now())->format('Y-m-d\TH:i')) }}">
            </x-admin.field>

            <x-admin.field name="reference" label="Tham chiếu">
                <input class="form-control @error('reference') is-invalid @enderror" id="reference" name="reference"
                       value="{{ old('reference', $transaction->reference) }}" placeholder="Số phiếu, hợp đồng…">
            </x-admin.field>

            <x-admin.field name="note" label="Ghi chú" col="col-12">
                <textarea class="form-control @error('note') is-invalid @enderror" id="note" name="note" rows="3">{{ old('note', $transaction->note) }}</textarea>
            </x-admin.field>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.cash.index') }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button :label="$transaction->exists ? 'Lưu thay đổi' : 'Ghi nhận giao dịch'" />
        </div>
    </form>
</x-layouts.admin>
