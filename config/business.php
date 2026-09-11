<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ngưỡng giảm giá nhân viên được tự quyết
    |--------------------------------------------------------------------------
    |
    | Tính theo phần trăm của tạm tính. Trong ngưỡng này, người có quyền lập
    | hóa đơn tự áp dụng được. Vượt ngưỡng phải là owner hoặc quản lý, vì giảm
    | giá lớn là cách rẻ nhất để thu ít hơn số khách thực trả.
    |
    | Con số mặc định 10% là đề xuất, cần chủ tiệm xác nhận theo thực tế.
    |
    */

    'operator_discount_percent' => (float) env('INVOICE_OPERATOR_DISCOUNT_PERCENT', 10),

    /*
    |--------------------------------------------------------------------------
    | Giảm giá tuyệt đối tối đa cho nhân viên
    |--------------------------------------------------------------------------
    |
    | Áp dụng song song với ngưỡng phần trăm; lấy điều kiện chặt hơn. Dùng để
    | một hóa đơn giá trị lớn không vô tình mở ra mức giảm quá rộng.
    |
    */

    'operator_discount_max' => (int) env('INVOICE_OPERATOR_DISCOUNT_MAX', 100000),

    /*
    |--------------------------------------------------------------------------
    | Số ngày được phép ghi lùi
    |--------------------------------------------------------------------------
    |
    | Ghi bù một phiếu của hôm qua là việc bình thường. Ghi lùi vài tháng thì
    | không: đó là cách chuyển một con số vào kỳ mà sổ sách đã chốt xong. Trong
    | ngưỡng này người dùng tự ghi được, ngoài ngưỡng thì phải sửa qua quy trình
    | điều chỉnh có người duyệt.
    |
    */

    'backdate_days' => (int) env('BACKDATE_ALLOWED_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Số ngày được phép đặt lịch trước
    |--------------------------------------------------------------------------
    |
    | Đủ rộng cho khách đặt trước cả năm, nhưng chặn được lỗi gõ nhầm năm.
    |
    */

    'max_booking_days_ahead' => (int) env('MAX_BOOKING_DAYS_AHEAD', 365),

    /*
    |--------------------------------------------------------------------------
    | Ngưỡng cho bộ dò bất thường
    |--------------------------------------------------------------------------
    |
    | Cờ rủi ro là câu hỏi, không phải lời buộc tội. Ngưỡng đặt hơi rộng là có
    | chủ ý: một hàng đợi đầy cờ vô nghĩa sẽ khiến người ta ngừng đọc, và khi
    | đó nó không bảo vệ được gì nữa.
    |
    */

    'risk' => [

        // Số lần hủy hóa đơn của cùng một người trong một ngày trước khi đáng hỏi.
        'daily_cancellations' => (int) env('RISK_DAILY_CANCELLATIONS', 3),

        // Số lần hủy giao dịch quỹ của cùng một người trong một ngày.
        'daily_voids' => (int) env('RISK_DAILY_VOIDS', 3),

        // Số lần xuất dữ liệu của cùng một người trong một ngày.
        'daily_exports' => (int) env('RISK_DAILY_EXPORTS', 10),

        // Ngoài khung giờ này thì thao tác tiền được coi là ngoài giờ làm việc.
        'business_hours_start' => (int) env('RISK_BUSINESS_HOURS_START', 7),
        'business_hours_end' => (int) env('RISK_BUSINESS_HOURS_END', 23),

        // Giá trị tuyệt đối của chênh lệch kho (theo tiền) đáng để hỏi lại.
        'stock_adjustment_value' => (int) env('RISK_STOCK_ADJUSTMENT_VALUE', 500000),

    ],

];
