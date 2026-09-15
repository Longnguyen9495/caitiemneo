<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOnlineBookingNotification extends Notification
{
    public function __construct(private readonly Appointment $appointment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $appointment = $this->appointment->loadMissing('branch');

        return [
            'kind' => 'online_booking',
            'appointment_id' => $appointment->getKey(),
            'branch_id' => $appointment->branch_id,
            'title' => 'Lịch đặt online mới',
            'message' => $appointment->customer_name.' đã gửi yêu cầu đặt lịch.',
            'detail' => sprintf(
                '%s · %s · %s',
                $appointment->branch?->name ?? 'Chưa xác định chi nhánh',
                $appointment->starts_at->format('H:i, d/m/Y'),
                $appointment->customer_phone,
            ),
            'url' => route('admin.appointments.index', [
                'date' => $appointment->starts_at->toDateString(),
            ]),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment->loadMissing([
            'branch',
            'employee',
            'services.service',
        ]);

        $services = $appointment->services
            ->map(fn ($appointmentService): string => $appointmentService->service->name)
            ->join(', ') ?: 'Khách chưa chọn dịch vụ';

        return (new MailMessage)
            ->subject('Lịch đặt online mới: '.$appointment->customer_name)
            ->greeting('Có lịch đặt online mới')
            ->line('Khách hàng: '.$appointment->customer_name)
            ->line('Số điện thoại: '.$appointment->customer_phone)
            ->line('Chi nhánh: '.($appointment->branch?->name ?? 'Chưa xác định'))
            ->line('Thời gian: '.$appointment->starts_at->format('H:i, d/m/Y'))
            ->line('Thời lượng: '.$appointment->duration_minutes.' phút')
            ->line('Nhân viên: '.($appointment->employee?->name ?? 'Tiệm sắp xếp'))
            ->line('Dịch vụ: '.$services)
            ->when($appointment->note, fn (MailMessage $mail): MailMessage => $mail->line('Ghi chú: '.$appointment->note))
            ->action('Mở lịch hẹn', route('admin.appointments.index', [
                'date' => $appointment->starts_at->toDateString(),
            ]));
    }
}
