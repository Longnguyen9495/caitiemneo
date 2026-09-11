<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * One id that ties a request's log lines to its audit events.
 *
 * When something goes wrong the question is always "what else happened in that
 * same request". Without a shared id the answer has to be reconstructed from
 * timestamps, which is guesswork the moment two people are working at once.
 *
 * The id is the one the audit trail already uses, so a log line and an
 * `audit_events` row can be joined directly.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sinh ở đây rồi đưa cho recorder, thay vì để vòng đời container
        // quyết định — như vậy mỗi request chắc chắn có id riêng.
        $requestId = (string) Str::uuid();

        app(AuditRecorder::class)->useCorrelationId($requestId);

        // Mọi dòng log trong request này đều mang theo id.
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);

        // Trả về để hỗ trợ có thể hỏi người dùng "mã trên màn hình là gì".
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
