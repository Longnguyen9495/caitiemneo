# Thông báo email lịch đặt online

Khi khách gửi form đặt lịch thành công, hệ thống tạo notification email nội bộ cho tất cả tài khoản **Chủ tiệm** và **Quản lý** đang hoạt động, có địa chỉ email.

## Nội dung email

Email bao gồm tên và số điện thoại khách, chi nhánh, thời gian, thời lượng, nhân viên được chọn (nếu có), các dịch vụ, ghi chú và liên kết mở danh sách lịch hẹn trong trang quản trị.

Notification được gửi ngay sau khi lịch hẹn lưu thành công qua Gmail SMTP. Không cần chạy queue worker; với lượng booking của tiệm, thời gian gửi SMTP ngắn và phù hợp để vận hành đơn giản.

## Cấu hình production

Dùng Gmail SMTP: bật Xác minh 2 bước trên Gmail của Cái Tiệm Neo, tạo **App Password** riêng cho ứng dụng này, rồi điền các giá trị dưới đây vào file môi trường của server. Không dùng mật khẩu đăng nhập Gmail thông thường.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=your-caitiemneo-gmail@gmail.com
MAIL_PASSWORD=your-16-character-gmail-app-password
MAIL_FROM_ADDRESS=your-caitiemneo-gmail@gmail.com
MAIL_FROM_NAME="Cái Tiệm Neo"

QUEUE_CONNECTION=database
```

Không commit mật khẩu SMTP vào git.

## Kiểm thử local

Mặc định mailer là `log`; email sẽ được ghi vào log ứng dụng thay vì gửi ra ngoài. Có thể kiểm tra sau khi tạo lịch mới trong file `storage/logs/laravel.log`.
