<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelAppointmentAction
{
    public function __construct(private AuditRecorder $auditor) {}

    public function handle(Appointment $appointment, User $actor, string $reason): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor, $reason): Appointment {
            $locked = Appointment::query()
                ->with('services')
                ->lockForUpdate()
                ->findOrFail($appointment->getKey());

            if ($locked->status === AppointmentStatus::Cancelled) {
                return $locked;
            }

            if ($locked->status === AppointmentStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => 'Không thể hủy lịch hẹn đã hoàn tất.',
                ]);
            }

            $before = $locked->auditSnapshot();
            $locked->forceFill([
                'status' => AppointmentStatus::Cancelled,
                'note' => $this->appendReason($locked->note, $reason),
            ])->save();

            $this->auditor->record(
                $locked,
                $actor,
                AuditAction::Cancelled,
                $before,
                $locked->fresh()->load('services')->auditSnapshot(),
                $reason,
            );

            return $locked->fresh()->load('services');
        });
    }

    private function appendReason(?string $note, string $reason): string
    {
        $prefix = trim((string) $note);
        $cancellation = 'Lý do hủy: '.trim($reason);

        return $prefix === '' ? $cancellation : $prefix."\n".$cancellation;
    }
}
