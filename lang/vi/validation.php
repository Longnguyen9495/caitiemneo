<?php

/*
|--------------------------------------------------------------------------
| Thông báo lỗi kiểm tra dữ liệu
|--------------------------------------------------------------------------
|
| Đây là bộ thông báo mặc định cho toàn hệ thống. Các Form Request chỉ nên
| khai báo `messages()` cho những câu mang tính nghiệp vụ riêng, còn lại để
| file này lo, tránh việc cùng một câu bị chép lại ở hàng chục nơi.
|
| Tiếng Việt không có mạo từ và không chia động từ theo số nhiều, nên phần
| lớn câu được viết theo lối "Hãy ..." hoặc ":attribute ..." cho tự nhiên,
| thay vì dịch sát từng chữ kiểu "The ... field must be ...".
|
*/

return [

    'accepted' => 'Hãy đồng ý với :attribute.',
    'accepted_if' => 'Hãy đồng ý với :attribute khi :other là :value.',
    'active_url' => ':attribute phải là một địa chỉ web hợp lệ.',
    'after' => ':attribute phải là ngày sau :date.',
    'after_or_equal' => ':attribute phải là ngày bằng hoặc sau :date.',
    'alpha' => ':attribute chỉ được chứa chữ cái.',
    'alpha_dash' => ':attribute chỉ được chứa chữ cái, số, dấu gạch ngang và gạch dưới.',
    'alpha_num' => ':attribute chỉ được chứa chữ cái và số.',
    'any_of' => ':attribute không hợp lệ.',
    'array' => ':attribute phải là một danh sách.',
    'array_keys' => ':attribute chỉ được chứa các khóa: :values.',
    'ascii' => ':attribute chỉ được chứa ký tự và ký hiệu một byte.',
    'base64' => ':attribute phải là chuỗi Base64 hợp lệ.',
    'before' => ':attribute phải là ngày trước :date.',
    'before_or_equal' => ':attribute phải là ngày bằng hoặc trước :date.',
    'between' => [
        'array' => ':attribute phải có từ :min đến :max mục.',
        'file' => ':attribute phải nặng từ :min đến :max ki-lô-byte.',
        'numeric' => ':attribute phải nằm trong khoảng :min đến :max.',
        'string' => ':attribute phải dài từ :min đến :max ký tự.',
    ],
    'boolean' => ':attribute chỉ nhận giá trị có hoặc không.',
    'can' => ':attribute chứa giá trị bạn không có quyền chọn.',
    'confirmed' => ':attribute nhập lại không khớp.',
    'contains' => ':attribute còn thiếu một giá trị bắt buộc.',
    'current_password' => 'Mật khẩu không đúng.',
    'date' => ':attribute phải là ngày hợp lệ.',
    'date_equals' => ':attribute phải đúng bằng ngày :date.',
    'date_format' => ':attribute phải theo định dạng :format.',
    'decimal' => ':attribute phải có :decimal chữ số thập phân.',
    'declined' => 'Hãy từ chối :attribute.',
    'declined_if' => 'Hãy từ chối :attribute khi :other là :value.',
    'different' => ':attribute và :other phải khác nhau.',
    'digits' => ':attribute phải gồm đúng :digits chữ số.',
    'digits_between' => ':attribute phải gồm từ :min đến :max chữ số.',
    'dimensions' => ':attribute có kích thước ảnh không hợp lệ.',
    'distinct' => ':attribute bị trùng giá trị.',
    'doesnt_contain' => ':attribute không được chứa: :values.',
    'doesnt_end_with' => ':attribute không được kết thúc bằng: :values.',
    'doesnt_start_with' => ':attribute không được bắt đầu bằng: :values.',
    'email' => ':attribute phải là địa chỉ email hợp lệ.',
    'encoding' => ':attribute phải được mã hóa theo :encoding.',
    'ends_with' => ':attribute phải kết thúc bằng một trong: :values.',
    'enum' => ':attribute đã chọn không hợp lệ.',
    'exists' => ':attribute đã chọn không hợp lệ.',
    'extensions' => ':attribute phải có phần mở rộng: :values.',
    'file' => ':attribute phải là một tệp.',
    'filled' => 'Hãy nhập :attribute.',
    'gt' => [
        'array' => ':attribute phải có nhiều hơn :value mục.',
        'file' => ':attribute phải nặng hơn :value ki-lô-byte.',
        'numeric' => ':attribute phải lớn hơn :value.',
        'string' => ':attribute phải dài hơn :value ký tự.',
    ],
    'gte' => [
        'array' => ':attribute phải có ít nhất :value mục.',
        'file' => ':attribute phải nặng từ :value ki-lô-byte trở lên.',
        'numeric' => ':attribute phải lớn hơn hoặc bằng :value.',
        'string' => ':attribute phải dài từ :value ký tự trở lên.',
    ],
    'hex_color' => ':attribute phải là mã màu hex hợp lệ.',
    'image' => ':attribute phải là một hình ảnh.',
    'in' => ':attribute đã chọn không hợp lệ.',
    'in_array' => ':attribute phải có trong :other.',
    'in_array_keys' => ':attribute phải chứa ít nhất một trong các khóa: :values.',
    'integer' => ':attribute phải là số nguyên.',
    'ip' => ':attribute phải là địa chỉ IP hợp lệ.',
    'ipv4' => ':attribute phải là địa chỉ IPv4 hợp lệ.',
    'ipv6' => ':attribute phải là địa chỉ IPv6 hợp lệ.',
    'json' => ':attribute phải là chuỗi JSON hợp lệ.',
    'list' => ':attribute phải là một danh sách.',
    'lowercase' => ':attribute phải viết thường.',
    'lt' => [
        'array' => ':attribute phải có ít hơn :value mục.',
        'file' => ':attribute phải nhẹ hơn :value ki-lô-byte.',
        'numeric' => ':attribute phải nhỏ hơn :value.',
        'string' => ':attribute phải ngắn hơn :value ký tự.',
    ],
    'lte' => [
        'array' => ':attribute không được có quá :value mục.',
        'file' => ':attribute phải nhẹ hơn hoặc bằng :value ki-lô-byte.',
        'numeric' => ':attribute phải nhỏ hơn hoặc bằng :value.',
        'string' => ':attribute phải dài tối đa :value ký tự.',
    ],
    'mac_address' => ':attribute phải là địa chỉ MAC hợp lệ.',
    'max' => [
        'array' => ':attribute không được có quá :max mục.',
        'file' => ':attribute không được nặng quá :max ki-lô-byte.',
        'numeric' => ':attribute không được lớn hơn :max.',
        'string' => ':attribute không được dài quá :max ký tự.',
    ],
    'max_digits' => ':attribute không được có quá :max chữ số.',
    'mimes' => ':attribute phải là tệp thuộc loại: :values.',
    'mimetypes' => ':attribute phải là tệp thuộc loại: :values.',
    'min' => [
        'array' => ':attribute phải có ít nhất :min mục.',
        'file' => ':attribute phải nặng ít nhất :min ki-lô-byte.',
        'numeric' => ':attribute phải từ :min trở lên.',
        'string' => ':attribute phải dài ít nhất :min ký tự.',
    ],
    'min_digits' => ':attribute phải có ít nhất :min chữ số.',
    'missing' => ':attribute không được xuất hiện.',
    'missing_if' => ':attribute không được xuất hiện khi :other là :value.',
    'missing_unless' => ':attribute không được xuất hiện trừ khi :other là :value.',
    'missing_with' => ':attribute không được xuất hiện khi đã có :values.',
    'missing_with_all' => ':attribute không được xuất hiện khi đã có :values.',
    'multiple_of' => ':attribute phải là bội số của :value.',
    'not_in' => ':attribute đã chọn không hợp lệ.',
    'not_regex' => ':attribute có định dạng không hợp lệ.',
    'numeric' => ':attribute phải là một số.',
    'password' => [
        'letters' => ':attribute phải chứa ít nhất một chữ cái.',
        'mixed' => ':attribute phải chứa cả chữ hoa và chữ thường.',
        'numbers' => ':attribute phải chứa ít nhất một chữ số.',
        'symbols' => ':attribute phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => ':attribute này đã từng bị lộ trong một vụ rò rỉ dữ liệu. Hãy chọn mật khẩu khác.',
    ],
    'present' => ':attribute phải được gửi lên.',
    'present_if' => ':attribute phải được gửi lên khi :other là :value.',
    'present_unless' => ':attribute phải được gửi lên trừ khi :other là :value.',
    'present_with' => ':attribute phải được gửi lên khi có :values.',
    'present_with_all' => ':attribute phải được gửi lên khi có :values.',
    'prohibited' => ':attribute không được phép nhập.',
    'prohibited_if' => ':attribute không được phép nhập khi :other là :value.',
    'prohibited_if_accepted' => ':attribute không được phép nhập khi đã đồng ý :other.',
    'prohibited_if_declined' => ':attribute không được phép nhập khi đã từ chối :other.',
    'prohibited_unless' => ':attribute không được phép nhập trừ khi :other thuộc :values.',
    'prohibits' => ':attribute khiến :other không được phép nhập.',
    'regex' => ':attribute có định dạng không hợp lệ.',
    'required' => 'Hãy nhập :attribute.',
    'required_array_keys' => ':attribute phải chứa các khóa: :values.',
    'required_if' => 'Hãy nhập :attribute khi :other là :value.',
    'required_if_accepted' => 'Hãy nhập :attribute khi đã đồng ý :other.',
    'required_if_declined' => 'Hãy nhập :attribute khi đã từ chối :other.',
    'required_unless' => 'Hãy nhập :attribute trừ khi :other thuộc :values.',
    'required_with' => 'Hãy nhập :attribute khi đã có :values.',
    'required_with_all' => 'Hãy nhập :attribute khi đã có :values.',
    'required_without' => 'Hãy nhập :attribute khi chưa có :values.',
    'required_without_all' => 'Hãy nhập :attribute khi chưa có :values.',
    'same' => ':attribute và :other phải giống nhau.',
    'size' => [
        'array' => ':attribute phải có đúng :size mục.',
        'file' => ':attribute phải nặng đúng :size ki-lô-byte.',
        'numeric' => ':attribute phải bằng :size.',
        'string' => ':attribute phải dài đúng :size ký tự.',
    ],
    'starts_with' => ':attribute phải bắt đầu bằng một trong: :values.',
    'string' => ':attribute phải là một chuỗi ký tự.',
    'timezone' => ':attribute phải là múi giờ hợp lệ.',
    'unique' => ':attribute này đã tồn tại.',
    'uploaded' => 'Tải :attribute lên không thành công.',
    'uppercase' => ':attribute phải viết hoa.',
    'url' => ':attribute phải là một địa chỉ web hợp lệ.',
    'ulid' => ':attribute phải là ULID hợp lệ.',
    'uuid' => ':attribute phải là UUID hợp lệ.',

    /*
    |--------------------------------------------------------------------------
    | Thông báo riêng theo từng trường
    |--------------------------------------------------------------------------
    |
    | Chỉ dùng cho những câu mà thông báo chung nghe không tự nhiên hoặc không
    | nói đủ ý nghiệp vụ.
    |
    */

    'custom' => [
        'password' => [
            'confirmed' => 'Mật khẩu nhập lại không khớp.',
        ],
        'email' => [
            'unique' => 'Email này đã được dùng cho một tài khoản khác.',
        ],
        'username' => [
            'unique' => 'Tên đăng nhập này đã có người dùng.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tên trường hiển thị trong thông báo
    |--------------------------------------------------------------------------
    |
    | Gồm cả các trường lồng nhau dạng `items.*.field`, để dòng thứ ba của một
    | hóa đơn không báo lỗi bằng khóa thô `items.2.quantity`.
    |
    */

    'attributes' => [
        'name' => 'tên',
        'email' => 'email',
        'username' => 'tên đăng nhập',
        'password' => 'mật khẩu',
        'password_confirmation' => 'mật khẩu nhập lại',
        'current_password' => 'mật khẩu hiện tại',
        'phone' => 'số điện thoại',
        'note' => 'ghi chú',
        'reason' => 'lý do',
        'amount' => 'số tiền',
        'quantity' => 'số lượng',
        'unit_price' => 'đơn giá',
        'discount' => 'giảm giá',
        'branch_id' => 'chi nhánh',
        'employee_id' => 'nhân viên',
        'service_id' => 'dịch vụ',
        'product_id' => 'vật tư',
        'supplier_id' => 'nhà cung cấp',
        'customer_name' => 'tên khách hàng',
        'customer_phone' => 'số điện thoại khách hàng',
        'work_date' => 'ngày làm',
        'occurred_at' => 'thời điểm',
        'starts_at' => 'giờ bắt đầu',
        'ends_at' => 'giờ kết thúc',
        'period_start' => 'từ ngày',
        'period_end' => 'đến ngày',
        'payment_method' => 'phương thức thanh toán',
        'status' => 'trạng thái',
        'role' => 'vai trò',
        'is_active' => 'trạng thái hoạt động',
        'duration_minutes' => 'thời lượng',
        'void_reason' => 'lý do hủy',
        'cancel_reason' => 'lý do hủy',

        // Các dòng động của hóa đơn và phiếu chuyển kho.
        'items' => 'danh sách dòng',
        'items.*.name' => 'tên dòng dịch vụ',
        'items.*.quantity' => 'số lượng',
        'items.*.unit_price' => 'đơn giá',
        'items.*.unit_cost' => 'giá vốn',
        'items.*.employee_id' => 'nhân viên',
        'items.*.service_id' => 'dịch vụ',
        'items.*.product_id' => 'vật tư',
        'items.*.commission_rate' => 'tỷ lệ hoa hồng',
        'items.*.price_override_reason' => 'lý do giá ngoài khoảng',
        'service_ids' => 'dịch vụ',
        'service_ids.*' => 'dịch vụ',
    ],

];
