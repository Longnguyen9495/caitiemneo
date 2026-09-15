<?php

/*
 * Danh sách ngân hàng cho ô "Ngân hàng" trong hồ sơ nhân sự.
 *
 * Nằm ở config chứ không phải enum hay bảng dữ liệu: đây là danh mục đối
 * ngoại, thỉnh thoảng có ngân hàng đổi tên hoặc sáp nhập, và khi đó việc phải
 * làm là sửa một dòng ở đây rồi deploy — không cần migration, không cần ai
 * vào giao diện nhập lại.
 *
 * `name` chính là giá trị được lưu vào `users.bank_name`, nên nó phải là cái
 * tên mà người đi chuyển khoản gõ vào ứng dụng ngân hàng của họ. `code` và
 * `full_name` chỉ để hiển thị và để tìm kiếm.
 *
 * LƯU Ý KHI SỬA: đổi `name` của một ngân hàng sẽ làm hồ sơ nhân sự đang lưu
 * tên cũ không lưu lại được nữa (validation chỉ nhận giá trị có trong danh
 * sách). Đổi tên thì nhớ cập nhật luôn dữ liệu cũ.
 */
return [

    // Nhóm ngân hàng nhân viên hay dùng nhất, để sẵn ở đầu danh sách.
    ['code' => 'VCB', 'name' => 'Vietcombank', 'full_name' => 'Ngân hàng TMCP Ngoại thương Việt Nam'],
    ['code' => 'TCB', 'name' => 'Techcombank', 'full_name' => 'Ngân hàng TMCP Kỹ thương Việt Nam'],
    ['code' => 'MB', 'name' => 'MB Bank', 'full_name' => 'Ngân hàng TMCP Quân đội'],
    ['code' => 'VBA', 'name' => 'Agribank', 'full_name' => 'Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam'],
    ['code' => 'BIDV', 'name' => 'BIDV', 'full_name' => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam'],
    ['code' => 'CTG', 'name' => 'VietinBank', 'full_name' => 'Ngân hàng TMCP Công thương Việt Nam'],
    ['code' => 'ACB', 'name' => 'ACB', 'full_name' => 'Ngân hàng TMCP Á Châu'],
    ['code' => 'VPB', 'name' => 'VPBank', 'full_name' => 'Ngân hàng TMCP Việt Nam Thịnh Vượng'],
    ['code' => 'TPB', 'name' => 'TPBank', 'full_name' => 'Ngân hàng TMCP Tiên Phong'],
    ['code' => 'STB', 'name' => 'Sacombank', 'full_name' => 'Ngân hàng TMCP Sài Gòn Thương Tín'],
    ['code' => 'VIB', 'name' => 'VIB', 'full_name' => 'Ngân hàng TMCP Quốc tế Việt Nam'],
    ['code' => 'MSB', 'name' => 'MSB', 'full_name' => 'Ngân hàng TMCP Hàng Hải Việt Nam'],
    ['code' => 'HDB', 'name' => 'HDBank', 'full_name' => 'Ngân hàng TMCP Phát triển TP. Hồ Chí Minh'],
    ['code' => 'SHB', 'name' => 'SHB', 'full_name' => 'Ngân hàng TMCP Sài Gòn – Hà Nội'],

    // Phần còn lại xếp theo vần để dễ soát khi thêm bớt.
    ['code' => 'ABB', 'name' => 'ABBANK', 'full_name' => 'Ngân hàng TMCP An Bình'],
    ['code' => 'BAB', 'name' => 'BacA Bank', 'full_name' => 'Ngân hàng TMCP Bắc Á'],
    ['code' => 'BVB', 'name' => 'BaoViet Bank', 'full_name' => 'Ngân hàng TMCP Bảo Việt'],
    ['code' => 'VCCB', 'name' => 'BVBank', 'full_name' => 'Ngân hàng TMCP Bản Việt'],
    ['code' => 'CIMB', 'name' => 'CIMB Bank', 'full_name' => 'Ngân hàng TNHH MTV CIMB Việt Nam'],
    ['code' => 'COOPBANK', 'name' => 'Co-opBank', 'full_name' => 'Ngân hàng Hợp tác xã Việt Nam'],
    ['code' => 'DBS', 'name' => 'DBS Bank', 'full_name' => 'Ngân hàng DBS chi nhánh TP. Hồ Chí Minh'],
    ['code' => 'EIB', 'name' => 'Eximbank', 'full_name' => 'Ngân hàng TMCP Xuất Nhập khẩu Việt Nam'],
    ['code' => 'GPB', 'name' => 'GPBank', 'full_name' => 'Ngân hàng Thương mại TNHH MTV Dầu Khí Toàn Cầu'],
    ['code' => 'HLBVN', 'name' => 'Hong Leong Bank', 'full_name' => 'Ngân hàng TNHH MTV Hong Leong Việt Nam'],
    ['code' => 'HSBC', 'name' => 'HSBC', 'full_name' => 'Ngân hàng TNHH MTV HSBC Việt Nam'],
    ['code' => 'IBKHN', 'name' => 'IBK Hà Nội', 'full_name' => 'Ngân hàng Công nghiệp Hàn Quốc – chi nhánh Hà Nội'],
    ['code' => 'IVB', 'name' => 'Indovina Bank', 'full_name' => 'Ngân hàng TNHH Indovina'],
    ['code' => 'KLB', 'name' => 'KienlongBank', 'full_name' => 'Ngân hàng TMCP Kiên Long'],
    ['code' => 'KBHN', 'name' => 'Kookmin Hà Nội', 'full_name' => 'Ngân hàng Kookmin – chi nhánh Hà Nội'],
    ['code' => 'LPB', 'name' => 'LPBank', 'full_name' => 'Ngân hàng TMCP Lộc Phát Việt Nam'],
    ['code' => 'NAB', 'name' => 'Nam A Bank', 'full_name' => 'Ngân hàng TMCP Nam Á'],
    ['code' => 'NCB', 'name' => 'NCB', 'full_name' => 'Ngân hàng TMCP Quốc Dân'],
    ['code' => 'OCB', 'name' => 'OCB', 'full_name' => 'Ngân hàng TMCP Phương Đông'],
    ['code' => 'PBVN', 'name' => 'Public Bank', 'full_name' => 'Ngân hàng TNHH MTV Public Việt Nam'],
    ['code' => 'PGB', 'name' => 'PGBank', 'full_name' => 'Ngân hàng TMCP Thịnh vượng và Phát triển'],
    ['code' => 'PVCB', 'name' => 'PVcomBank', 'full_name' => 'Ngân hàng TMCP Đại Chúng Việt Nam'],
    ['code' => 'SGICB', 'name' => 'SaigonBank', 'full_name' => 'Ngân hàng TMCP Sài Gòn Công Thương'],
    ['code' => 'SCB', 'name' => 'SCB', 'full_name' => 'Ngân hàng TMCP Sài Gòn'],
    ['code' => 'SEAB', 'name' => 'SeABank', 'full_name' => 'Ngân hàng TMCP Đông Nam Á'],
    ['code' => 'SHBVN', 'name' => 'Shinhan Bank', 'full_name' => 'Ngân hàng TNHH MTV Shinhan Việt Nam'],
    ['code' => 'SCVN', 'name' => 'Standard Chartered', 'full_name' => 'Ngân hàng TNHH MTV Standard Chartered Việt Nam'],
    ['code' => 'UOB', 'name' => 'UOB', 'full_name' => 'Ngân hàng TNHH MTV United Overseas Bank Việt Nam'],
    ['code' => 'VAB', 'name' => 'VietABank', 'full_name' => 'Ngân hàng TMCP Việt Á'],
    ['code' => 'VIETBANK', 'name' => 'VietBank', 'full_name' => 'Ngân hàng TMCP Việt Nam Thương Tín'],
    ['code' => 'VRB', 'name' => 'VRB', 'full_name' => 'Ngân hàng Liên doanh Việt – Nga'],
    ['code' => 'WVN', 'name' => 'Woori Bank', 'full_name' => 'Ngân hàng TNHH MTV Woori Việt Nam'],

    // Ví điện tử liên kết Napas: vẫn nhận được lương chuyển khoản.
    ['code' => 'VTLMONEY', 'name' => 'Viettel Money', 'full_name' => 'Tổng Công ty Dịch vụ số Viettel'],
    ['code' => 'VNPTMONEY', 'name' => 'VNPT Money', 'full_name' => 'Tổng Công ty Dịch vụ số VNPT'],
];
