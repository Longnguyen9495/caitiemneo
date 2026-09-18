<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lịch đặt online mới</title>
</head>
<body style="margin:0;padding:0;background:#f2eeeb;color:#362b28;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f2eeeb;">
    <tr>
        <td align="center" style="padding:30px 12px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:660px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 12px 35px rgba(58,40,35,.12);">
                <tr>
                    <td style="padding:27px 34px;background:#4b342f;color:#fff;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
                            <td><div style="font-family:Georgia,'Times New Roman',serif;font-size:25px;">Cái Tiệm Neo</div><div style="margin-top:5px;color:#dcbeb5;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;">Thông báo nội bộ</div></td>
                            <td align="right"><span style="display:inline-block;padding:7px 11px;border-radius:999px;background:#d99b8d;color:#3f2924;font-size:11px;font-weight:bold;">LỊCH MỚI</span></td>
                        </tr></table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px 34px 16px;">
                        <h1 style="margin:0 0 9px;font-family:Georgia,'Times New Roman',serif;color:#4b342f;font-size:27px;line-height:1.3;">Có yêu cầu đặt lịch online mới</h1>
                        <p style="margin:0;color:#78645e;font-size:15px;line-height:1.65;">Khách đang chờ tiệm liên hệ xác nhận. Vui lòng kiểm tra và sắp xếp nhân viên phù hợp.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 34px 20px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #eadbd5;border-radius:14px;">
                            <tr><td colspan="2" style="padding:20px 22px 10px;color:#9b6357;font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">Thông tin khách hàng</td></tr>
                            <tr><td style="padding:8px 12px 8px 22px;color:#8c746d;width:32%;font-size:14px;">Họ tên</td><td style="padding:8px 22px 8px 12px;font-weight:bold;">{{ $appointment->customer_name }}</td></tr>
                            <tr><td style="padding:8px 12px 8px 22px;color:#8c746d;font-size:14px;">Điện thoại</td><td style="padding:8px 22px 8px 12px;"><a href="tel:{{ $appointment->customer_phone }}" style="color:#8c5146;font-weight:bold;text-decoration:none;">{{ $appointment->customer_phone }}</a></td></tr>
                            <tr><td style="padding:8px 12px 20px 22px;color:#8c746d;font-size:14px;">Email</td><td style="padding:8px 22px 20px 12px;">{{ $appointment->customer_email ?: 'Khách không cung cấp' }}</td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 34px 24px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#fbf5f1;border-radius:14px;">
                            <tr><td colspan="2" style="padding:20px 22px 10px;color:#9b6357;font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">Chi tiết lịch hẹn</td></tr>
                            <tr><td style="padding:8px 12px 8px 22px;color:#8c746d;width:32%;font-size:14px;">Thời gian</td><td style="padding:8px 22px 8px 12px;font-weight:bold;">{{ $appointment->starts_at->format('H:i, d/m/Y') }}</td></tr>
                            <tr><td style="padding:8px 12px 8px 22px;color:#8c746d;font-size:14px;">Chi nhánh</td><td style="padding:8px 22px 8px 12px;">{{ $appointment->branch?->name ?? 'Chưa xác định' }}</td></tr>
                            <tr><td style="padding:8px 12px 8px 22px;color:#8c746d;font-size:14px;">Thời lượng</td><td style="padding:8px 22px 8px 12px;">{{ $appointment->duration_minutes }} phút</td></tr>
                            <tr><td style="padding:8px 12px 8px 22px;color:#8c746d;font-size:14px;">Nhân viên</td><td style="padding:8px 22px 8px 12px;">{{ $appointment->employee?->name ?? 'Chưa phân công' }}</td></tr>
                            <tr><td style="padding:8px 12px 20px 22px;color:#8c746d;font-size:14px;vertical-align:top;">Dịch vụ</td><td style="padding:8px 22px 20px 12px;line-height:1.5;">{{ $services }}</td></tr>
                            @if ($appointment->note)
                                <tr><td style="padding:8px 12px 20px 22px;color:#8c746d;font-size:14px;vertical-align:top;">Ghi chú</td><td style="padding:8px 22px 20px 12px;line-height:1.5;">{{ $appointment->note }}</td></tr>
                            @endif
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:2px 34px 36px;">
                        <a href="{{ $appointmentUrl }}" style="display:inline-block;padding:14px 25px;border-radius:999px;background:#8c5146;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;">Mở lịch hẹn để xử lý →</a>
                    </td>
                </tr>
                <tr><td style="padding:18px 34px;background:#f5ebe6;text-align:center;color:#8b716a;font-size:12px;line-height:1.6;">Email tự động từ hệ thống quản lý Cái Tiệm Neo.</td></tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
