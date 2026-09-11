{{-- Thông báo kết quả và lỗi. Không dùng dấu chấm than: tự tin, không ồn ào. --}}
@if (session('success'))
    <div class="alert alert-success d-flex align-items-start gap-2 border-0" role="status">
        <x-admin.icon name="check" class="flex-shrink-0 mt-1" size="18" />
        <div>{{ session('success') }}</div>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger border-0" role="alert">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger border-0" role="alert">
        <p class="fw-semibold mb-1">Vui lòng kiểm tra lại thông tin:</p>
        <ul class="mb-0 ps-3 small">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
