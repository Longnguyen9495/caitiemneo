<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cái Tiệm Neo đã nhận yêu cầu đặt lịch</title>
</head>
<body style="margin:0;padding:0;background:#f6f0eb;color:#3f302d;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f6f0eb;">
    <tr>
        <td align="center" style="padding:32px 12px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#fffdfb;border-radius:22px;overflow:hidden;box-shadow:0 12px 35px rgba(74,48,42,.10);">
                <tr>
                    <td style="padding:34px 40px;background:#5d4039;color:#fffaf7;text-align:center;">
                        <div style="font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#edc9bd;">Nail care & little joys</div>
                        <div style="margin-top:10px;font-family:Georgia,'Times New Roman',serif;font-size:32px;line-height:1.2;">Cái Tiệm Neo</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:38px 40px 18px;">
                        <div style="display:inline-block;padding:7px 12px;border-radius:999px;background:#f8e5df;color:#8c5146;font-size:12px;font-weight:bold;letter-spacing:.5px;">ĐÃ NHẬN YÊU CẦU</div>
                        <h1 style="margin:18px 0 12px;font-family:Georgia,'Times New Roman',serif;font-size:29px;line-height:1.3;color:#4b342f;">Hẹn gặp {{ $appointment->customer_name }} tại tiệm nhé!</h1>
                        <p style="margin:0;color:#6e5a55;font-size:16px;line-height:1.75;">Cảm ơn bạn đã chọn Cái Tiệm Neo. Tiệm đã nhận thông tin đặt lịch và sẽ liên hệ qua số điện thoại của bạn để xác nhận sớm nhất.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 40px 22px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#fbf5f1;border:1px solid #eeddd6;border-radius:16px;">
                            <tr><td colspan="2" style="padding:22px 24px 12px;font-family:Georgia,'Times New Roman',serif;font-size:20px;color:#5d4039;">Thông tin lịch hẹn</td></tr>
                            <tr><td style="padding:8px 12px 8px 24px;color:#96756d;width:34%;font-size:14px;">Thời gian</td><td style="padding:8px 24px 8px 12px;font-weight:bold;font-size:15px;">{{ $appointment->starts_at->format('H:i, d/m/Y') }}</td></tr>
                            <tr><td style="padding:8px 12px 8px 24px;color:#96756d;font-size:14px;">Chi nhánh</td><td style="padding:8px 24px 8px 12px;font-weight:bold;font-size:15px;">{{ $appointment->branch?->name ?? 'Tiệm sẽ thông báo' }}</td></tr>
                            @if ($appointment->branch?->address)
                                <tr><td style="padding:8px 12px 8px 24px;color:#96756d;font-size:14px;">Địa chỉ</td><td style="padding:8px 24px 8px 12px;font-size:15px;line-height:1.5;">{{ $appointment->branch->address }}</td></tr>
                            @endif
                            <tr><td style="padding:8px 12px 8px 24px;color:#96756d;font-size:14px;">Thời lượng</td><td style="padding:8px 24px 8px 12px;font-size:15px;">Khoảng {{ $appointment->duration_minutes }} phút</td></tr>
                            <tr><td style="padding:8px 12px 8px 24px;color:#96756d;font-size:14px;">Dịch vụ</td><td style="padding:8px 24px 8px 12px;font-size:15px;line-height:1.5;">{{ $services->isNotEmpty() ? $services->join(', ') : 'Tiệm sẽ tư vấn khi xác nhận' }}</td></tr>
                            @if ($appointment->note)
                                <tr><td style="padding:8px 12px 24px 24px;color:#96756d;font-size:14px;vertical-align:top;">Ghi chú</td><td style="padding:8px 24px 24px 12px;font-size:15px;line-height:1.5;">{{ $appointment->note }}</td></tr>
                            @else
                                <tr><td colspan="2" style="height:14px;"></td></tr>
                            @endif
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 40px 34px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-left:4px solid #d99b8d;background:#fff9f6;">
                            <tr><td style="padding:16px 18px;color:#6e5a55;font-size:14px;line-height:1.65;"><strong style="color:#5d4039;">Một chút lưu ý:</strong> Email này xác nhận tiệm đã nhận yêu cầu, chưa phải xác nhận lịch cuối cùng. Tiệm sẽ liên hệ với bạn để chốt thời gian và nhân viên phù hợp.</td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 40px;background:#4b342f;color:#eadbd5;text-align:center;font-size:13px;line-height:1.7;">
                        @if ($appointment->branch?->phone)
                            Cần thay đổi lịch? Gọi tiệm tại <a href="tel:{{ $appointment->branch->phone }}" style="color:#fff4ef;font-weight:bold;text-decoration:none;">{{ $appointment->branch->phone }}</a><br>
                        @endif
                        Cảm ơn bạn đã dành thời gian chăm sóc chính mình ♥
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
