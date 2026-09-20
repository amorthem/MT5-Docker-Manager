<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DockerManager
{
    public function containers(): array
    {
        return $this->request()->get('/containers/json', ['all' => true])->throw()->json();
    }

    public function images(): array
    {
        return $this->request()->get('/images/json', ['all' => true])->throw()->json();
    }

    public function create(string $name, string $image, array $options = []): array
    {
        $payload = ['Image' => $image];

        if (! empty($options['command'])) {
            $payload['Cmd'] = $options['command'];
        }

        if (! empty($options['env'])) {
            $payload['Env'] = $options['env'];
        }

        if (! empty($options['host_config'])) {
            $payload['HostConfig'] = $options['host_config'];
        }

        if (! empty($options['exposed_ports'])) {
            $payload['ExposedPorts'] = $options['exposed_ports'];
        }

        return $this->request()->post('/containers/create?name='.rawurlencode($name), $payload)->throw()->json();
    }

    public function remove(string $id, bool $force = false): void
    {
        $this->request()->delete('/containers/'.$this->identifier($id), ['force' => $force])->throw();
        Cache::forget('docker:overview');
    }

    public function updateResources(string $id, array $resources): void
    {
        $this->request()->post('/containers/'.$this->identifier($id).'/update', [
            'NanoCpus' => (int) round((float) $resources['cpus'] * 1_000_000_000),
            'Memory' => $this->bytes($resources['memory']),
            'MemorySwap' => -1,
            'MemoryReservation' => $this->bytes($resources['memory_reservation']),
            'RestartPolicy' => ['Name' => $resources['restart']],
        ])->throw();

        Cache::forget('docker:overview');
    }

    public function overview(): array
    {
        return Cache::remember('docker:overview', 5, function (): array {
            $containers = $this->containers();
            $stats = $this->statsForRunningContainers($containers);

            return array_map(function (array $container) use ($stats): array {
                $id = $container['Id'] ?? '';

                return [
                    'id' => $id,
                    'name' => ltrim($container['Names'][0] ?? $id, '/'),
                    'image' => $container['Image'] ?? null,
                    'state' => $container['State'] ?? 'unknown',
                    'status' => $container['Status'] ?? null,
                    'ports' => $this->ports($container['Ports'] ?? []),
                    'metrics' => $this->metricsFromStats($stats[$id] ?? null),
                ];
            }, $containers);
        });
    }

    public function container(string $id): array
    {
        return $this->request()->get('/containers/'.$this->identifier($id).'/json')->throw()->json();
    }

    public function logs(string $id, int $tail = 200, ?string $since = null, ?string $until = null): string
    {
        $query = [
            'stdout' => 1,
            'stderr' => 1,
            'timestamps' => 1,
            'tail' => min(max($tail, 1), config('docker.max_log_lines')),
        ];

        if ($since !== null) {
            $query['since'] = $since;
        }

        if ($until !== null) {
            $query['until'] = $until;
        }

        return $this->decodeLogs($this->request()->get('/containers/'.$this->identifier($id).'/logs', $query)->throw()->body());
    }

    public function archivedLogs(string $id, int $tail = 200, ?string $since = null, ?string $until = null): string
    {
        return app(DockerLogStore::class)->read($this->identifier($id), $tail, $since, $until);
    }

    public function stats(string $id): array
    {
        return $this->request()->get('/containers/'.$this->identifier($id).'/stats', ['stream' => false])->throw()->json();
    }

    public function action(string $id, string $action): void
    {
        if (! in_array($action, ['start', 'stop', 'restart'], true)) {
            throw new RuntimeException('Invalid Docker action.');
        }

        $this->request()->post('/containers/'.$this->identifier($id).'/'.$action)->throw();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('docker.host'))
            ->timeout(config('docker.timeout'))
            ->withOptions(['curl' => [CURLOPT_UNIX_SOCKET_PATH => config('docker.socket')]])
            ->acceptJson();
    }

    private function statsForRunningContainers(array $containers): array
    {
        $running = array_values(array_filter($containers, static fn (array $container): bool => ($container['State'] ?? '') === 'running'));
        if ($running === []) return [];

        $stats = [];
        $batches = array_chunk($running, max(1, (int) config('docker.stats_concurrency', 4)));

        foreach ($batches as $batch) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($batch): array {
                    return array_map(function (array $container) use ($pool) {
                        $id = $container['Id'];

                        return $pool->as($id)->baseUrl(config('docker.host'))
                            ->timeout(config('docker.stats_timeout', 2))
                            ->withOptions(['curl' => [CURLOPT_UNIX_SOCKET_PATH => config('docker.socket')]])
                            ->acceptJson()
                            ->get('/containers/'.$this->identifier($id).'/stats', ['stream' => false]);
                    }, $batch);
                });

                foreach ($responses as $id => $response) {
                    if ($response instanceof Response && $response->successful()) {
                        $stats[$id] = $response->json();
                    }
                }
            } catch (\Throwable $exception) {
                report($exception);
            }

            foreach ($batch as $container) {
                $id = $container['Id'];
                if (isset($stats[$id])) continue;

                try {
                    $stats[$id] = $this->stats($id);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        return $stats;
    }

    private function identifier(string $id): string
    {
        if (! preg_match('/\A[a-f0-9]{12,64}\z/i', $id)) {
            throw new RuntimeException('Invalid Docker container identifier.');
        }

        return $id;
    }

    private function bytes(string $value): int
    {
        preg_match('/\A(\d+(?:\.\d+)?)\s*(b|k|m|g|t|kb|mb|gb|tb)\z/i', trim($value), $matches);
        $number = (float) $matches[1];

        return (int) round($number * match (strtolower($matches[2])) {
            't' => 1024 ** 4,
            'tb' => 1024 ** 4,
            'g' => 1024 ** 3,
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'gb' => 1024 ** 3,
            'k' => 1024,
            'mb' => 1024 ** 2,
            'm' => 1024 ** 2,
            'kb' => 1024,
            'b' => 1,
            default => 1024,
        });
    }

    private function decodeLogs(string $body): string
    {
        $length = strlen($body);
        $offset = 0;
        $decoded = '';

        while ($offset + 8 <= $length) {
            $stream = ord($body[$offset]);
            $frameLength = unpack('Nlength', substr($body, $offset + 4, 4))['length'];

            if (! in_array($stream, [1, 2], true) || $offset + 8 + $frameLength > $length) {
                return $body;
            }

            $decoded .= substr($body, $offset + 8, $frameLength);
            $offset += 8 + $frameLength;
        }

        return $offset === $length ? $decoded : $body;
    }

    private function ports(array $ports): array
    {
        return array_values(array_map(function (array $port): array {
            $public = $port['PublicPort'] ?? null;
            $private = $port['PrivatePort'] ?? null;
            $host = $port['IP'] ?? '127.0.0.1';

            return [
                'host' => $host,
                'public' => $public,
                'private' => $private,
                'type' => $port['Type'] ?? 'tcp',
                'url' => $public === null ? null : sprintf('http://%s:%d', $host === '0.0.0.0' ? 'localhost' : $host, $public),
            ];
        }, array_filter($ports, static fn (array $port): bool => isset($port['PrivatePort']))));
    }

    private function metricsFromStats(?array $stats): array
    {
        if ($stats === null) {
            return ['cpu_percent' => null, 'memory_used' => null, 'memory_limit' => null, 'memory_percent' => null, 'stale' => true];
        }

        $cpuDelta = ($stats['cpu_stats']['cpu_usage']['total_usage'] ?? 0)
            - ($stats['precpu_stats']['cpu_usage']['total_usage'] ?? 0);
        $systemDelta = ($stats['cpu_stats']['system_cpu_usage'] ?? 0)
            - ($stats['precpu_stats']['system_cpu_usage'] ?? 0);
        $cpuCount = $stats['cpu_stats']['online_cpus'] ?? count($stats['cpu_stats']['cpu_usage']['percpu_usage'] ?? []) ?: 1;
        $cpuPercent = $systemDelta > 0 ? ($cpuDelta / $systemDelta) * $cpuCount * 100 : null;

        return [
            'cpu_percent' => $cpuPercent === null ? null : round($cpuPercent, 2),
            'memory_used' => $stats['memory_stats']['usage'] ?? null,
            'memory_limit' => $stats['memory_stats']['limit'] ?? null,
            'memory_percent' => ($stats['memory_stats']['limit'] ?? 0) > 0
                ? round((($stats['memory_stats']['usage'] ?? 0) / $stats['memory_stats']['limit']) * 100, 2)
                : null,
            'stale' => false,
        ];
    }
}