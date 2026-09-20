<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DockerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DockerController extends Controller
{
    public function __construct(private readonly DockerManager $docker) {}

    public function index(): JsonResponse
    {
        return $this->run(fn () => ['data' => $this->docker->containers()]);
    }

    public function overview(): JsonResponse
    {
        return $this->run(fn () => ['data' => $this->docker->overview(), 'timestamp' => now()->toIso8601String()]);
    }

    public function images(): JsonResponse
    {
        return $this->run(fn () => ['data' => array_values(array_filter(array_map(
            static fn (array $image): ?array => isset($image['Id']) ? [
                'id' => $image['Id'],
                'tags' => $image['RepoTags'] ?? [],
                'size' => $image['Size'] ?? null,
                'created' => $image['Created'] ?? null,
            ] : null,
            $this->docker->images(),
        )))]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9_.-]*\z/'],
            'image' => ['required', 'string', 'max:255', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9_.\/:@-]*\z/'],
            'command' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'cpus' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:256'],
            'memory' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/\A\d+(?:\.\d+)?\s*(?:b|k|m|g|t|kb|mb|gb|tb)\z/i'],
            'memory_reservation' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/\A\d+(?:\.\d+)?\s*(?:b|k|m|g|t|kb|mb|gb|tb)\z/i'],
            'restart' => ['sometimes', 'string', 'in:no,on-failure,always,unless-stopped'],
            'vnc_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'api_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'mt5_login' => ['sometimes', 'nullable', 'string', 'max:128'],
            'mt5_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mt5_server' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vnc_user' => ['sometimes', 'nullable', 'string', 'max:128'],
            'vnc_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'api_key_seed' => ['sometimes', 'nullable', 'string', 'max:255'],
            'app_volume' => ['sometimes', 'nullable', 'string', 'max:512', 'regex:/\A\/[a-zA-Z0-9_./-]+\z/'],
            'experts_volume' => ['sometimes', 'nullable', 'string', 'max:512', 'regex:/\A\/[a-zA-Z0-9_./-]+\z/'],
        ]);

        if (isset($validated['memory'], $validated['memory_reservation'])
            && $this->bytes($validated['memory_reservation']) > $this->bytes($validated['memory'])) {
            return response()->json([
                'message' => 'Memory reservation cannot be greater than the memory limit.',
            ], 422);
        }

        $env = collect([
            'MT5_LOGIN' => $validated['mt5_login'] ?? null,
            'MT5_PASSWORD' => $validated['mt5_password'] ?? null,
            'MT5_SERVER' => $validated['mt5_server'] ?? null,
            'VNC_USER' => $validated['vnc_user'] ?? null,
            'VNC_PASSWORD' => $validated['vnc_password'] ?? null,
            'API_KEY_SEED' => $validated['api_key_seed'] ?? null,
        ])->filter(static fn ($value) => $value !== null && $value !== '')->map(
            static fn ($value, $key) => $key.'='.$value,
        )->values()->all();

        $portBindings = [];
        $exposedPorts = [];
        foreach ([['vnc_port', 6901], ['api_port', 8000]] as [$field, $containerPort]) {
            if (! empty($validated[$field])) {
                $key = $containerPort.'/tcp';
                $portBindings[$key] = [['HostPort' => (string) $validated[$field]]];
                $exposedPorts[$key] = new \stdClass();
            }
        }

        $binds = [];
        foreach ([['app_volume', '/root/api'], ['experts_volume', '/opt/wineprefix/drive_c/Metatrader-5/MQL5/Experts']] as [$field, $target]) {
            if (! empty($validated[$field])) $binds[] = $validated[$field].':'.$target;
        }

        $hostConfig = array_filter([
            'NanoCpus' => isset($validated['cpus']) ? (int) round((float) $validated['cpus'] * 1_000_000_000) : null,
            'Memory' => isset($validated['memory']) ? $this->bytes($validated['memory']) : null,
            'MemorySwap' => isset($validated['memory']) ? -1 : null,
            'MemoryReservation' => isset($validated['memory_reservation']) ? $this->bytes($validated['memory_reservation']) : null,
            'RestartPolicy' => ['Name' => $validated['restart'] ?? 'unless-stopped'],
            'PortBindings' => $portBindings ?: null,
            'Binds' => $binds ?: null,
        ], static fn ($value) => $value !== null);

        return $this->run(fn () => [
            'data' => $this->docker->create($validated['name'], $validated['image'], [
                'command' => ! empty($validated['command']) ? preg_split('/\s+/', trim($validated['command'])) : null,
                'env' => $env,
                'host_config' => $hostConfig,
                'exposed_ports' => $exposedPorts,
            ]),
        ], 201);
    }

    public function remove(string $container): JsonResponse
    {
        return $this->run(function () use ($container): array {
            $this->docker->remove($container, true);

            return ['message' => 'Container removed.'];
        });
    }

    public function updateResources(Request $request, string $container): JsonResponse
    {
        $validated = $request->validate([
            'cpus' => ['required', 'numeric', 'min:0.01', 'max:256'],
            'memory' => ['required', 'string', 'regex:/\A\d+(?:\.\d+)?\s*(?:b|k|m|g|t|kb|mb|gb|tb)\z/i'],
            'memory_reservation' => ['required', 'string', 'regex:/\A\d+(?:\.\d+)?\s*(?:b|k|m|g|t|kb|mb|gb|tb)\z/i'],
            'restart' => ['required', 'string', 'in:no,on-failure,always,unless-stopped'],
        ]);

        if ($this->bytes($validated['memory_reservation']) > $this->bytes($validated['memory'])) {
            return response()->json([
                'message' => 'Memory reservation cannot be greater than the memory limit.',
            ], 422);
        }

        return $this->run(function () use ($container, $validated): array {
            $this->docker->updateResources($container, $validated);

            return ['message' => 'Container resources updated.'];
        });
    }

    public function show(string $container): JsonResponse
    {
        return $this->run(fn () => ['data' => $this->docker->container($container)]);
    }

    public function logs(Request $request, string $container): JsonResponse
    {
        $validated = $request->validate([
            'tail' => ['sometimes', 'integer', 'min:1', 'max:5000'],
            'since' => ['sometimes', 'string', 'max:32'],
            'until' => ['sometimes', 'string', 'max:32'],
        ]);

        return $this->run(fn () => ['data' => [
            'container' => $container,
            'source' => 'docker-engine',
            'logs' => $this->docker->logs(
                $container,
                $validated['tail'] ?? 200,
                $validated['since'] ?? null,
                $validated['until'] ?? null,
            ),
        ]]);
    }

    public function metrics(string $container): JsonResponse
    {
        return $this->run(fn () => ['data' => [
            'container' => $container,
            'stats' => $this->docker->stats($container),
            'timestamp' => now()->toIso8601String(),
            'stale' => false,
        ]]);
    }

    public function action(string $container, string $action): JsonResponse
    {
        return $this->run(function () use ($container, $action): array {
            $this->docker->action($container, $action);

            return ['message' => 'Docker action completed.'];
        });
    }

    private function run(callable $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json($callback(), $status);
        } catch (Throwable $exception) {
            report($exception);

            $dockerStatus = $exception instanceof \Illuminate\Http\Client\RequestException
                ? ($exception->response?->status() ?? 503)
                : 503;

            return response()->json([
                'message' => $dockerStatus >= 400 && $dockerStatus < 500
                    ? ($exception->response?->json('message') ?? 'Docker rejected this request.')
                    : 'Docker service is unavailable.',
            ], $dockerStatus >= 400 && $dockerStatus < 500 ? $dockerStatus : 503);
        }
    }

    private function bytes(string $value): int
    {
        preg_match('/\A(\d+(?:\.\d+)?)\s*(b|k|m|g|t|kb|mb|gb|tb)\z/i', trim($value), $matches);
        $number = (float) $matches[1];
        $multiplier = match (strtolower($matches[2])) {
            't' => 1024 ** 4,
            'tb' => 1024 ** 4,
            'g' => 1024 ** 3,
            'gb' => 1024 ** 3,
            'm' => 1024 ** 2,
            'mb' => 1024 ** 2,
            'k' => 1024,
            'kb' => 1024,
            'b' => 1,
        };

        return (int) round($number * $multiplier);
    }
}