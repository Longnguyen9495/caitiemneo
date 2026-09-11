@props(['title' => 'Hãy kiểm tra lại các thông tin bên dưới'])

{{--
    Tóm tắt lỗi ở đầu biểu mẫu.

    Một form dài bị trả về mà không có mục này sẽ thả người dùng ở đầu trang và
    để họ tự đi tìm ô nào sai trong vài chục ô. Mỗi dòng là một liên kết tới
    đúng control gây lỗi, nên bấm vào là nhảy thẳng tới đó — cũng là cách duy
    nhất dùng được bằng bàn phím mà không cần JavaScript.

    `role="alert"` để trình đọc màn hình đọc ngay khi trang tải lại; `tabindex`
    để JavaScript đưa được tiêu điểm vào đây.
--}}
@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'alert alert-danger']) }}
         role="alert"
         tabindex="-1"
         data-neo-error-summary>
        <p class="fw-semibold mb-2">{{ $title }}</p>

        <ul class="mb-0 ps-3">
            @foreach ($errors->getMessages() as $field => $messages)
                <li>
                    {{-- Khóa lồng nhau như items.2.quantity không có control nào
                         mang đúng id đó, nên chỉ hiện chữ, không tạo liên kết hỏng. --}}
                    @if (str_contains($field, '.'))
                        {{ $messages[0] }}
                    @else
                        <a href="#{{ $field }}" class="alert-link">{{ $messages[0] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
