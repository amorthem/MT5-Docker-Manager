<?php

namespace App\Services;

class HostMetrics
{
    /**
     * Return host-visible CPU and memory usage.
     * In Docker this reflects the runtime's visible host/cgroup view.
     */
    public function current(): array
    {
        $memory = $this->memory();
        $cpuUsed = $this->cpuUsage();
        $stale = $cpuUsed === null || $memory === null;
        $memory ??= ['used_percent' => null, 'used_bytes' => null, 'total_bytes' => null];

        return [
            'scope' => env('HOST_METRICS_SCOPE', 'vps-host'),
            'cpu' => ['used' => $cpuUsed === null ? null : round($cpuUsed, 2), 'total' => 100.0],
            'ram' => [
                'used' => $memory['used_percent'] === null ? null : round($memory['used_percent'], 2),
                'total' => 100.0,
                'used_bytes' => $memory['used_bytes'],
                'total_bytes' => $memory['total_bytes'],
                'used_gb' => $memory['used_bytes'] === null ? null : round($memory['used_bytes'] / 1024 / 1024 / 1024, 2),
                'total_gb' => $memory['total_bytes'] === null ? null : round($memory['total_bytes'] / 1024 / 1024 / 1024, 2),
            ],
            'stale' => $stale,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function cpuUsage(): ?float
    {
        $first = $this->cpuCounters();
        if ($first === null) return null;

        usleep(250000);

        $second = $this->cpuCounters();
        if ($second === null) return null;

        $totalDelta = $second['total'] - $first['total'];
        $idleDelta = $second['idle'] - $first['idle'];

        return $totalDelta > 0 ? min(100, max(0, (1 - ($idleDelta / $totalDelta)) * 100)) : null;
    }

    /** @return array{total: int, idle: int}|null */
    private function cpuCounters(): ?array
    {
        $contents = @file_get_contents('/proc/stat');
        if ($contents === false || ! preg_match('/^cpu\s+(.+)$/m', $contents, $match)) return null;

        $values = array_map('intval', preg_split('/\s+/', trim($match[1])));
        if (count($values) < 4) return null;

        return [
            'total' => array_sum($values),
            'idle' => $values[3] + ($values[4] ?? 0),
        ];
    }

    /** @return array{used_percent: float, used_bytes: float, total_bytes: float}|null */
    private function memory(): ?array
    {
        $contents = @file_get_contents('/proc/meminfo');
        if ($contents === false) {
            return null;
        }

        preg_match('/^MemTotal:\s+(\d+)\s+kB$/m', $contents, $totalMatch);
        preg_match('/^MemAvailable:\s+(\d+)\s+kB$/m', $contents, $availableMatch);

        $total = ((float) ($totalMatch[1] ?? 0)) / 1024;
        $available = ((float) ($availableMatch[1] ?? 0)) / 1024;

        if ($total <= 0) return null;

        $totalBytes = $total * 1024 * 1024;
        $availableBytes = $available * 1024 * 1024;

        return [
            'used_percent' => (($total - $available) / $total) * 100,
            'used_bytes' => $totalBytes - $availableBytes,
            'total_bytes' => $totalBytes,
        ];
    }

}
