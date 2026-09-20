<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DockerLogCollector
{
    public function __construct(
        private readonly DockerManager $docker,
        private readonly DockerLogStore $store,
    ) {}

    public function collect(): int
    {
        $collected = 0;

        $containers = $this->docker->containers();

        foreach ($containers as $container) {
            $id = $container['Id'] ?? null;
            if (! is_string($id) || $id === '') {
                continue;
            }

            try {
                $since = $this->store->lastCollectedAt($id);
                $logs = $this->docker->logs($id, config('docker.max_log_lines', 5000), $since ? (string) max(0, $since - 1) : null);
                $collected += $this->store->append($id, $container['Names'][0] ?? $id, $logs);
                $this->store->markCollectedAt($id, now()->timestamp);
            } catch (\Throwable $exception) {
                Log::warning('Docker log collection failed.', [
                    'container' => $id,
                    'exception' => $exception,
                ]);
            }
        }

        try {
            $this->docker->refreshOverview($containers);
        } catch (\Throwable $exception) {
            Log::warning('Docker metrics collection failed.', ['exception' => $exception]);
        }

        $this->store->prune();

        return $collected;
    }
}
