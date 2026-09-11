<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Inclusive from/to window shared by the report screen and every CSV export.
 *
 * Defaults to the running month and is capped at two years so a stray URL cannot
 * ask the database for an unbounded scan.
 */
final class ReportPeriod
{
    public const MAX_DAYS = 731;

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $from = self::parse($request->query('from'), CarbonImmutable::now()->startOfMonth());
        $to = self::parse($request->query('to'), CarbonImmutable::now()->endOfMonth());

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        $from = $from->startOfDay();
        $to = $to->endOfDay();

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $to = $from->addDays(self::MAX_DAYS)->endOfDay();
        }

        return new self($from, $to);
    }

    public function label(): string
    {
        return $this->from->format('d/m/Y').' - '.$this->to->format('d/m/Y');
    }

    /** @return array{from: string, to: string} */
    public function toQuery(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }

    private static function parse(mixed $value, CarbonImmutable $fallback): CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
