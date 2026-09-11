@props(['action', 'label', 'variant' => 'primary'])

{{--
    Nút chấm công.

    Vị trí chỉ được lấy đúng một lần, ngay khi người dùng bấm: không
    watchPosition, không chạy nền. Toạ độ được đưa vào ba ô ẩn rồi form tự gửi,
    nên nếu trình duyệt chặn JavaScript thì nút đơn giản là không gửi được chứ
    không gửi thiếu dữ liệu.
--}}
<form method="POST" action="{{ $action }}" x-data="clockButton" x-on:submit="onSubmit($event)" novalidate>
    @csrf
    <input type="hidden" name="latitude" x-ref="latitude">
    <input type="hidden" name="longitude" x-ref="longitude">
    <input type="hidden" name="accuracy" x-ref="accuracy">

    <button type="submit"
            class="btn btn-{{ $variant }} btn-lg w-100 d-inline-flex align-items-center justify-content-center gap-2"
            x-bind:disabled="busy">
        <x-admin.icon name="pin" size="20" />
        <span x-show="!busy">{{ $label }}</span>
        <span x-show="busy" x-cloak x-text="statusText"></span>
    </button>

    <p class="form-text mb-0 mt-2" x-show="!error">
        Trình duyệt sẽ hỏi quyền truy cập vị trí. Hệ thống chỉ đọc vị trí đúng lúc bạn bấm nút.
    </p>

    <div class="alert alert-danger border-0 mt-2 mb-0" role="alert" x-show="error" x-cloak x-text="error"></div>
</form>
