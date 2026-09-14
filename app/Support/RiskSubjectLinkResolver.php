<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AttendanceRecord;
use App\Models\AuditEvent;
use App\Models\Invoice;
use App\Models\RiskFlag;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Finds the actionable business record behind a risk flag.
 *
 * A flag's subject can be an audit event, which is evidence rather than the
 * place a manager can work. In that case this resolver follows the audit row
 * to its original model and returns a link only when the viewer has access.
 */
final class RiskSubjectLinkResolver
{
    /**
     * @param  Collection<int, RiskFlag>  $flags
     * @return Collection<int, array{url: string, label: string}>
     */
    public function for(User $user, Collection $flags): Collection
    {
        return $flags
            ->mapWithKeys(function (RiskFlag $flag) use ($user): array {
                $target = $this->targetFor($flag);

                if ($target === null || ! $user->can($target['ability'], $target['model'])) {
                    return [];
                }

                return [$flag->getKey() => [
                    'url' => route($target['route'], $target['model']),
                    'label' => $target['label'],
                ]];
            });
    }

    /** @return array{model: Invoice|Appointment|AttendanceRecord, route: string, label: string, ability: string}|null */
    private function targetFor(RiskFlag $flag): ?array
    {
        $type = $flag->subject_type;
        $id = $flag->subject_id;

        if ($type === 'audit_event' && $id !== null) {
            $event = AuditEvent::query()->find($id);

            if ($event === null) {
                return null;
            }

            $type = $event->auditable_type;
            $id = $event->auditable_id;
        }

        return match ($type) {
            'invoice', Invoice::class => $this->target(Invoice::query()->find($id), 'admin.invoices.edit', 'Mở hóa đơn'),
            'appointment', Appointment::class => $this->target(Appointment::query()->find($id), 'admin.appointments.edit', 'Mở lịch hẹn'),
            'attendance_record', AttendanceRecord::class => $this->target(AttendanceRecord::query()->find($id), 'admin.attendance.edit', 'Mở chấm công', 'update'),
            default => null,
        };
    }

    /** @template T of Invoice|Appointment|AttendanceRecord
     * @param  T|null  $model
     * @return array{model: T, route: string, label: string, ability: string}|null
     */
    private function target(Invoice|Appointment|AttendanceRecord|null $model, string $route, string $label, string $ability = 'view'): ?array
    {
        if ($model === null) {
            return null;
        }

        return compact('model', 'route', 'label', 'ability');
    }
}
