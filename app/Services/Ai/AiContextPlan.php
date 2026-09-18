<?php

namespace App\Services\Ai;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class AiContextPlan
{
    /** @param array<int, string> $domains */
    private function __construct(
        public readonly array $domains,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function fromQuestion(string $question): self
    {
        $text = Str::of($question)->lower()->ascii()->toString();
        $domains = [];

        $keywords = [
            'invoices' => ['hoa don', 'doanh thu', 'ban hang', 'bill', 'thanh toan'],
            'appointments' => ['lich hen', 'cuoc hen', 'booking', 'khach hen'],
            'customers' => ['khach hang', 'khach moi', 'khach cu'],
            'services' => ['dich vu', 'lieu trinh', 'top service'],
            'cash' => ['thu chi', 'dong tien', 'tien mat', 'khoan thu', 'khoan chi', 'cash'],
            'inventory' => ['ton kho', 'hang hoa', 'san pham', 'nhap kho', 'xuat kho', 'sap het'],
            'attendance' => ['cham cong', 'di muon', 've som', 'tang ca', 'vang mat', 'nhan su', 'nhan vien'],
            'payroll' => ['bang luong', 'tien luong', 'luong', 'payroll', 'hoa hong'],
        ];

        foreach ($keywords as $domain => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    $domains[] = $domain;
                    break;
                }
            }
        }

        if ($domains === []) {
            $domains = ['overview'];
        }

        [$from, $to] = self::dateRange($text);

        return new self(array_values(array_unique($domains)), $from, $to);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private static function dateRange(string $text): array
    {
        $now = CarbonImmutable::now();

        if (str_contains($text, 'hom nay')) {
            return [$now->startOfDay(), $now->endOfDay()];
        }

        if (str_contains($text, 'hom qua')) {
            $day = $now->subDay();

            return [$day->startOfDay(), $day->endOfDay()];
        }

        if (str_contains($text, 'tuan nay')) {
            return [$now->startOfWeek()->startOfDay(), $now->endOfWeek()->endOfDay()];
        }

        if (str_contains($text, 'thang truoc')) {
            $month = $now->subMonthNoOverflow();

            return [$month->startOfMonth()->startOfDay(), $month->endOfMonth()->endOfDay()];
        }

        if (str_contains($text, 'thang nay')) {
            return [$now->startOfMonth()->startOfDay(), $now->endOfMonth()->endOfDay()];
        }

        if (preg_match('/\bngay\s+(\d{1,2})(?:\s+(?:va|,|den)\s+(\d{1,2}))?\s*[\/-]\s*(\d{1,2})(?:\s*[\/-]\s*(\d{2,4}))?\b/', $text, $match)) {
            $year = isset($match[4]) && $match[4] !== '' ? (int) $match[4] : $now->year;
            $year = $year < 100 ? 2000 + $year : $year;
            $first = CarbonImmutable::createSafe($year, (int) $match[3], (int) $match[1]);
            $last = CarbonImmutable::createSafe($year, (int) $match[3], (int) ($match[2] ?: $match[1]));

            return [$first->min($last)->startOfDay(), $first->max($last)->endOfDay()];
        }

        preg_match_all('/\b(\d{1,2})[\/-](\d{1,2})(?:[\/-](\d{2,4}))?\b/', $text, $matches, PREG_SET_ORDER);

        if ($matches !== []) {
            $dates = collect($matches)->map(function (array $match) use ($now): CarbonImmutable {
                $year = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : $now->year;
                $year = $year < 100 ? 2000 + $year : $year;

                return CarbonImmutable::createSafe($year, (int) $match[2], (int) $match[1]);
            })->sort()->values();

            return [$dates->first()->startOfDay(), $dates->last()->endOfDay()];
        }

        return [$now->startOfMonth()->startOfDay(), $now->endOfMonth()->endOfDay()];
    }
}
