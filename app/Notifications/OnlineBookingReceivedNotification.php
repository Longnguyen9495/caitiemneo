<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnlineBookingReceivedNotification extends Notification
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

        return (new MailMessage)
            ->subject('Cái Tiệm Neo đã nhận yêu cầu đặt lịch của bạn')
            ->view('mail.booking.customer', [
                'appointment' => $appointment,
                'services' => $appointment->services
                    ->map(fn ($appointmentService): string => $appointmentService->service->name)
                    ->values(),
            ]);
    }
}
