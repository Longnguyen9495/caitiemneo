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
        return ['mail'];
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
