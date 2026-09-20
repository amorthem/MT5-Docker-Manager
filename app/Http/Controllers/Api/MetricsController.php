<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HostMetrics;
use Illuminate\Http\JsonResponse;
use Throwable;

class MetricsController extends Controller
{
    public function __construct(private readonly HostMetrics $metrics) {}

    public function host(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->metrics->current()]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'data' => [
                    'scope' => env('HOST_METRICS_SCOPE', 'vps-host'),
                    'cpu' => ['used' => null, 'total' => 100],
                    'ram' => ['used' => null, 'total' => 100, 'used_bytes' => null, 'total_bytes' => null, 'used_gb' => null, 'total_gb' => null],
                    'stale' => true,
                    'timestamp' => now()->toIso8601String(),
                ],
                'message' => 'Host metrics are unavailable.',
            ], 503);
        }
    }
}
