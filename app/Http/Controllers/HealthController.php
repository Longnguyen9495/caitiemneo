<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Whether the things the app depends on are actually answering.
 *
 * A page that returns 200 because PHP is running tells you nothing: the shop
 * cannot take a payment if the database is down, and the difference matters at
 * two in the morning. Each dependency is touched for real rather than assumed.
 *
 * The response deliberately names only the component and whether it answered.
 * Connection strings, credentials and driver detail stay out — an endpoint that
 * has to be reachable by a monitor is not a place to describe the internals.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::connection()->getPdo() !== null),
            'cache' => $this->check(function (): bool {
                Cache::put('health:ping', '1', 5);

                return Cache::get('health:ping') === '1';
            }),
            // Ghi rồi xóa một tệp nhỏ: đĩa đầy hoặc mất quyền ghi là sự cố
            // thật, và chỉ kiểm tra "thư mục có tồn tại không" sẽ không thấy.
            'storage' => $this->check(function (): bool {
                Storage::disk('local')->put('health-check.tmp', (string) now()->timestamp);
                $written = Storage::disk('local')->exists('health-check.tmp');
                Storage::disk('local')->delete('health-check.tmp');

                return $written;
            }),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => array_map(fn (bool $ok): string => $ok ? 'ok' : 'failed', $checks),
        ], $healthy ? 200 : 503);
    }

    /**
     * Run one probe, treating any failure as a failure.
     *
     * The exception itself is not returned: a stack trace on a public endpoint
     * is a description of the system for anybody who asks.
     */
    private function check(callable $probe): bool
    {
        try {
            return (bool) $probe();
        } catch (Throwable) {
            return false;
        }
    }
}
