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
        public readonly bool $periodInferred,
        public readonly string $periodPhrase,
    ) {}

    public static function fromQuestion(string $question): self
    {
        $text = Str::of($question)->lower()->ascii()->squish()->toString();
        $domains = [];

        foreach (self::keywords() as $domain => $needles) {
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

        [$from, $to, $inferred, $phrase] = self::dateRange($text);

        return new self(array_values(array_unique($domains)), $from, $to, $inferred, $phrase);
    }

    /**
     * Từ khóa nhận diện miền dữ liệu. Có cả dạng viết tắt và cách nói thường
     * ngày vì người dùng gõ "ds hom nay" chứ hiếm khi gõ đủ "doanh số hôm nay".
     *
     * @return array<string, array<int, string>>
     */
    private static function keywords(): array
    {
        return [
            'invoices' => [
                'hoa don', 'doanh thu', 'doanh so', 'ban hang', 'bill', 'thanh toan',
                'dthu', 'thu duoc', 'ban duoc', 'kiem duoc', 'tien ve', 'chot don',
            ],
            'appointments' => [
                'lich hen', 'cuoc hen', 'booking', 'khach hen', 'dat lich', 'lich lam',
                'dat cho', 'ai den', 'co hen', 'lich',
            ],
            'customers' => [
                'khach hang', 'khach moi', 'khach cu', 'khach quen', 'bao nhieu khach',
                'khach', 'nguoi den',
            ],
            'services' => ['dich vu', 'lieu trinh', 'top service', 'lam gi nhieu'],
            'cash' => [
                'thu chi', 'dong tien', 'tien mat', 'khoan thu', 'khoan chi', 'cash',
                'quy tien', 'so quy', 'chi ra', 'thu vao', 'ton quy',
            ],
            'inventory' => [
                'ton kho', 'hang hoa', 'san pham', 'nhap kho', 'xuat kho', 'sap het',
                'con bao nhieu hang', 'vat tu', 'nguyen lieu',
            ],
            'attendance' => [
                'cham cong', 'di muon', 've som', 'tang ca', 'vang mat', 'nhan su',
                'nhan vien', 'di lam', 'nghi lam', 'cong ca',
            ],
            'payroll' => [
                'bang luong', 'tien luong', 'luong', 'payroll', 'hoa hong', 'thuong',
                'tra luong', 'thu nhap nhan vien',
            ],
        ];
    }

    /**
     * Suy ra khoảng thời gian từ câu hỏi.
     *
     * Trả thêm cờ "đã suy đoán" để prompt nói rõ cho model biết khoảng này do hệ
     * thống đoán chứ người dùng không nêu — model sẽ xác nhận lại thay vì trả lời
     * chắc nịch trên một khoảng sai.
     *
     * @return array{CarbonImmutable, CarbonImmutable, bool, string}
     */
    private static function dateRange(string $text): array
    {
        $now = CarbonImmutable::now();

        if (str_contains($text, 'hom nay') || str_contains($text, 'bua nay')) {
            return [$now->startOfDay(), $now->endOfDay(), false, 'hôm nay'];
        }

        if (str_contains($text, 'hom qua')) {
            $day = $now->subDay();

            return [$day->startOfDay(), $day->endOfDay(), false, 'hôm qua'];
        }

        if (str_contains($text, 'hom kia')) {
            $day = $now->subDays(2);

            return [$day->startOfDay(), $day->endOfDay(), false, 'hôm kia'];
        }

        if (str_contains($text, 'ngay mai') || str_contains($text, 'hom sau')) {
            $day = $now->addDay();

            return [$day->startOfDay(), $day->endOfDay(), false, 'ngày mai'];
        }

        // "3 ngay qua", "7 ngay gan day", "30 ngay vua roi"
        if (preg_match('/\b(\d{1,3})\s*(?:ngay|hom)\s*(?:qua|nay|gan day|vua roi|truoc|tro lai day)\b/', $text, $match)) {
            $days = max(1, min(731, (int) $match[1]));

            return [$now->subDays($days - 1)->startOfDay(), $now->endOfDay(), false, "{$days} ngày qua"];
        }

        // "2 tuan qua", "3 thang gan day"
        if (preg_match('/\b(\d{1,2})\s*(tuan|thang|nam)\s*(?:qua|nay|gan day|vua roi|truoc|tro lai day)\b/', $text, $match)) {
            $amount = max(1, (int) $match[1]);
            $unit = $match[2];

            $from = match ($unit) {
                'tuan' => $now->subWeeks($amount),
                'thang' => $now->subMonthsNoOverflow($amount),
                default => $now->subYears($amount),
            };

            $label = match ($unit) {
                'tuan' => "{$amount} tuần qua",
                'thang' => "{$amount} tháng qua",
                default => "{$amount} năm qua",
            };

            return [$from->startOfDay(), $now->endOfDay(), false, $label];
        }

        if (str_contains($text, 'tuan nay')) {
            return [$now->startOfWeek()->startOfDay(), $now->endOfWeek()->endOfDay(), false, 'tuần này'];
        }

        if (str_contains($text, 'tuan truoc') || str_contains($text, 'tuan roi') || str_contains($text, 'tuan vua roi')) {
            $week = $now->subWeek();

            return [$week->startOfWeek()->startOfDay(), $week->endOfWeek()->endOfDay(), false, 'tuần trước'];
        }

        if (str_contains($text, 'thang truoc') || str_contains($text, 'thang roi') || str_contains($text, 'thang vua roi')) {
            $month = $now->subMonthNoOverflow();

            return [$month->startOfMonth()->startOfDay(), $month->endOfMonth()->endOfDay(), false, 'tháng trước'];
        }

        if (str_contains($text, 'thang nay')) {
            return [$now->startOfMonth()->startOfDay(), $now->endOfMonth()->endOfDay(), false, 'tháng này'];
        }

        // "thang 8", "thang 8/2025"
        if (preg_match('/\bthang\s*(\d{1,2})(?:\s*[\/-]\s*(\d{2,4}))?\b/', $text, $match)) {
            $month = (int) $match[1];

            if ($month >= 1 && $month <= 12) {
                $year = isset($match[2]) && $match[2] !== '' ? self::normaliseYear((int) $match[2]) : $now->year;
                $start = CarbonImmutable::create($year, $month, 1);

                return [$start->startOfMonth()->startOfDay(), $start->endOfMonth()->endOfDay(), false, "tháng {$month}/{$year}"];
            }
        }

        if (str_contains($text, 'quy nay')) {
            return [$now->startOfQuarter()->startOfDay(), $now->endOfQuarter()->endOfDay(), false, 'quý này'];
        }

        if (str_contains($text, 'quy truoc') || str_contains($text, 'quy roi')) {
            $quarter = $now->subQuarter();

            return [$quarter->startOfQuarter()->startOfDay(), $quarter->endOfQuarter()->endOfDay(), false, 'quý trước'];
        }

        // "quy 1", "quy 3/2025"
        if (preg_match('/\bquy\s*([1-4])(?:\s*[\/-]\s*(\d{2,4}))?\b/', $text, $match)) {
            $quarter = (int) $match[1];
            $year = isset($match[2]) && $match[2] !== '' ? self::normaliseYear((int) $match[2]) : $now->year;
            $start = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1);

            return [$start->startOfQuarter()->startOfDay(), $start->endOfQuarter()->endOfDay(), false, "quý {$quarter}/{$year}"];
        }

        if (str_contains($text, 'nam nay')) {
            return [$now->startOfYear()->startOfDay(), $now->endOfYear()->endOfDay(), false, 'năm nay'];
        }

        if (str_contains($text, 'nam ngoai') || str_contains($text, 'nam truoc')) {
            $year = $now->subYear();

            return [$year->startOfYear()->startOfDay(), $year->endOfYear()->endOfDay(), false, 'năm ngoái'];
        }

        if (preg_match('/\bngay\s+(\d{1,2})(?:\s+(?:va|,|den)\s+(\d{1,2}))?\s*[\/-]\s*(\d{1,2})(?:\s*[\/-]\s*(\d{2,4}))?\b/', $text, $match)) {
            $year = isset($match[4]) && $match[4] !== '' ? self::normaliseYear((int) $match[4]) : $now->year;
            $first = CarbonImmutable::createSafe($year, (int) $match[3], (int) $match[1]);
            $last = CarbonImmutable::createSafe($year, (int) $match[3], (int) ($match[2] ?: $match[1]));

            if ($first !== null && $last !== null) {
                return [$first->min($last)->startOfDay(), $first->max($last)->endOfDay(), false, 'ngày đã nêu'];
            }
        }

        preg_match_all('/\b(\d{1,2})[\/-](\d{1,2})(?:[\/-](\d{2,4}))?\b/', $text, $matches, PREG_SET_ORDER);

        if ($matches !== []) {
            $dates = collect($matches)
                ->map(function (array $match) use ($now): ?CarbonImmutable {
                    $year = isset($match[3]) && $match[3] !== '' ? self::normaliseYear((int) $match[3]) : $now->year;

                    return CarbonImmutable::createSafe($year, (int) $match[2], (int) $match[1]);
                })
                ->filter()
                ->sort()
                ->values();

            if ($dates->isNotEmpty()) {
                return [$dates->first()->startOfDay(), $dates->last()->endOfDay(), false, 'ngày đã nêu'];
            }
        }

        return [$now->startOfMonth()->startOfDay(), $now->endOfMonth()->endOfDay(), true, 'tháng này'];
    }

    private static function normaliseYear(int $year): int
    {
        return $year < 100 ? 2000 + $year : $year;
    }
}
