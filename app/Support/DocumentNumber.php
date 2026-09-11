<?php

namespace App\Support;

use Illuminate\Support\Str;

final class DocumentNumber
{
    /**
     * Human readable, collision resistant invoice number.
     *
     * The branch code is folded in when one is known, so the shop a document
     * came from is readable at a glance: NEO-CN01-20260911-7QF3KD.
     */
    public static function forInvoice(?string $branchCode = null): string
    {
        return self::build('NEO', $branchCode);
    }

    /** Number for a stock transfer between two branches. */
    public static function forStockTransfer(?string $branchCode = null): string
    {
        return self::build('CK', $branchCode);
    }

    private static function build(string $prefix, ?string $branchCode): string
    {
        $segments = array_filter([
            $prefix,
            $branchCode === null ? null : Str::upper(Str::replace('-', '', $branchCode)),
            now()->format('Ymd'),
            Str::upper(Str::substr(Str::ulid()->toBase32(), -6)),
        ]);

        return implode('-', $segments);
    }
}
