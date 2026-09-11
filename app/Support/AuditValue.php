<?php

namespace App\Support;

/**
 * Renders one audited value for a human reader.
 *
 * Snapshots hold raw scalars and, for invoice lines, nested arrays. Printing an
 * array straight into Blade would either throw or dump unreadable JSON, so the
 * nested case is summarised rather than expanded: the timeline answers "the
 * lines changed" and the full before/after stays in the stored event.
 */
class AuditValue
{
    public static function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Có' : 'Không';
        }

        if (is_array($value)) {
            $count = count($value);

            return $count === 0 ? 'Trống' : $count.' dòng';
        }

        return (string) $value;
    }
}
