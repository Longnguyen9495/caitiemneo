<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cửa sổ vào ca sớm
    |--------------------------------------------------------------------------
    |
    | Số phút trước giờ bắt đầu mà nhân viên đã được phép bấm Vào ca. Mỗi ca
    | trong danh mục có thể ghi đè giá trị này bằng cột `early_check_in_minutes`.
    |
    */

    'early_check_in_minutes' => (int) env('ATTENDANCE_EARLY_CHECK_IN_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Cửa sổ vào ca muộn
    |--------------------------------------------------------------------------
    |
    | Sau mốc này kể từ giờ kết thúc dự kiến thì ca coi như đã trôi qua và phải
    | do quản lý bổ sung kèm lý do, thay vì nhân viên tự bấm.
    |
    */

    'late_check_in_grace_minutes' => (int) env('ATTENDANCE_LATE_CHECK_IN_GRACE_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Số ngày nghỉ hưởng lương mỗi tháng
    |--------------------------------------------------------------------------
    |
    | Đơn nghỉ được duyệt sẽ tự tính là có lương cho tới khi nhân viên dùng hết
    | số ngày này trong tháng của ca nghỉ; từ ngày thứ kế tiếp trở đi là nghỉ
    | không lương. Hai ca trong cùng một ngày chỉ tiêu tốn một ngày.
    |
    */

    'paid_leave_days_per_month' => (int) env('ATTENDANCE_PAID_LEAVE_DAYS_PER_MONTH', 2),

    /*
    |--------------------------------------------------------------------------
    | Giá trị GPS mặc định cho chi nhánh mới
    |--------------------------------------------------------------------------
    */

    'default_radius_meters' => 100,

    'default_accuracy_limit_meters' => 150,

    /*
    |--------------------------------------------------------------------------
    | Giới hạn hợp lệ khi cấu hình chi nhánh
    |--------------------------------------------------------------------------
    */

    'min_radius_meters' => 20,

    'max_radius_meters' => 2000,

    'max_accuracy_meters' => 100000,

];
