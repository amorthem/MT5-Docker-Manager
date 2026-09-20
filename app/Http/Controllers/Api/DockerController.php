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
            'command' => ['sometimes', 'array', 'max:32'],
            'command.*' => ['string', 'max:512'],
        ]);

        return $this->run(fn () => [
            'data' => $this->docker->create($validated['name'], $validated['image'], $validated['command'] ?? null),
        ], 201);
    }

    public function remove(string $container): JsonResponse
    {
        return $this->run(function () use ($container): array {
            $this->docker->remove($container, true);

            return ['message' => 'Container removed.'];
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

            return response()->json(['message' => 'Docker service is unavailable.'], 503);
        }
    }
}