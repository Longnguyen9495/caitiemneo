{{--
    Thông báo kết quả của thao tác vừa rồi. Không dùng dấu chấm than: tự tin,
    không ồn ào.

    Nội dung được render thẳng vào trang chứ không nằm sẵn trong modal, để
    trang vẫn báo được khi JavaScript hỏng hoặc chưa tải xong. Khi có
    JavaScript, `admin.js` nhấc khối này vào hộp thoại dùng chung rồi mở lên —
    nhờ vậy mọi màn hình trong khu quản trị báo theo đúng một cách, không màn
    nào phải tự dựng gì.

    Lỗi kiểm tra dữ liệu cố ý KHÔNG nằm ở đây. `x-admin.error-summary` hiển thị
    chúng kèm liên kết nhảy thẳng tới ô sai; bọc chúng vào hộp thoại sẽ che mất
    chính những ô người dùng cần sửa.
--}}
@php
    $noticeIsError = filled(session('error'));
    $noticeMessage = session('error') ?? session('success');
@endphp

@if (filled($noticeMessage))
    <div class="alert d-flex align-items-start gap-2 border-0 {{ $noticeIsError ? 'alert-danger' : 'alert-success' }}"
         role="{{ $noticeIsError ? 'alert' : 'status' }}"
         data-neo-notice
         data-neo-notice-tone="{{ $noticeIsError ? 'danger' : 'success' }}">
        <x-admin.icon :name="$noticeIsError ? 'alert' : 'check'" class="flex-shrink-0 mt-1" size="18" />
        <div data-neo-notice-text>{{ $noticeMessage }}</div>
    </div>
@endif
