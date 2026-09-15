<?php

namespace App\Notifications;

use App\Models\ShiftRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ShiftRequest $request,
        private string $message,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->email) && config('mail.default') !== 'log') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'shift_request',
            'shift_request_id' => $this->request->getKey(),
            'type' => $this->request->type->value,
            'status' => $this->request->status->value,
            'branch_id' => $this->request->branch_id,
            'work_date' => $this->request->work_date->toDateString(),
            'title' => 'Cập nhật đơn ca làm',
            'message' => $this->message,
            'detail' => 'Ngày làm: '.$this->request->work_date->format('d/m/Y'),
            'url' => route('admin.shift-requests.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cập nhật đơn ca làm')
            ->line($this->message)
            ->action('Mở đơn ca làm', route('admin.shift-requests.index'));
    }
}
