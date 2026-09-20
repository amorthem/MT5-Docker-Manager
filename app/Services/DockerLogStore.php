<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DockerLogStore
{
    public function append(string $containerId, string $containerName, string $logs): int
    {
        if ($logs === '') {
            return 0;
        }

        $directory = $this->dateDirectory(now());
        File::ensureDirectoryExists($directory);

        $bytesWritten = 0;
        $remaining = $logs;
        $baseName = $this->baseName($containerName, $containerId);
        $maxBytes = max(1, (int) config('docker.log_chunk_mb', 50)) * 1024 * 1024;

        while ($remaining !== '') {
            $path = $this->currentPath($directory, $baseName);
            $available = max(1, $maxBytes - (is_file($path) ? filesize($path) : 0));
            if (strlen($remaining) <= $available) {
                $chunk = $remaining;
            } else {
                $chunk = substr($remaining, 0, $available);
                $lineBreak = strrpos($chunk, "\n");
                if ($lineBreak !== false) {
                    $chunk = substr($chunk, 0, $lineBreak + 1);
                }
            }

            file_put_contents($path, $chunk, FILE_APPEND | LOCK_EX);
            $bytesWritten += strlen($chunk);
            $remaining = substr($remaining, strlen($chunk));
        }

        return $bytesWritten;
    }

    public function read(string $containerId, int $tail = 200, ?string $since = null, ?string $until = null): string
    {
        $files = glob($this->rootPath().'/*/*_'.$this->shortId($containerId).'-*.log') ?: [];
        sort($files, SORT_STRING);

        $lines = [];
        foreach (array_reverse($files) as $path) {
            $lines = array_merge($this->tailLines($path, $tail), $lines);
            if (count($lines) >= $tail && $since === null && $until === null) {
                break;
            }
        }

        $filtered = array_values(array_filter($lines, fn (string $line): bool => $this->withinRange($line, $since, $until)));

        return implode(PHP_EOL, array_slice($filtered, -$tail)).($filtered === [] ? '' : PHP_EOL);
    }

    public function hasLogs(string $containerId): bool
    {
        return glob($this->rootPath().'/*/*_'.$this->shortId($containerId).'-*.log') !== [];
    }

    public function lastCollectedAt(string $containerId): ?int
    {
        $path = $this->statePath($containerId);
        if (! is_file($path)) {
            return null;
        }

        $state = json_decode((string) file_get_contents($path), true);

        return isset($state['since']) ? (int) $state['since'] : null;
    }

    public function markCollectedAt(string $containerId, int $timestamp): void
    {
        File::ensureDirectoryExists(dirname($this->statePath($containerId)));
        file_put_contents($this->statePath($containerId), json_encode(['since' => $timestamp]), LOCK_EX);
    }

    public function prune(): void
    {
        $retentionDays = max(1, (int) config('docker.log_retention_days', 30));
        $cutoff = now()->subDays($retentionDays)->startOfDay();

        foreach (glob($this->rootPath().'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $date = basename($directory);
            try {
                $parsedDate = CarbonImmutable::createFromFormat('y-m-d', $date);
                if ($parsedDate !== false && $parsedDate->startOfDay()->lessThan($cutoff)) {
                    File::deleteDirectory($directory);
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function currentPath(string $directory, string $baseName): string
    {
        $files = glob($directory.'/'.$baseName.'-*.log') ?: [];
        if ($files === []) {
            return $directory.'/'.$baseName.'-001.log';
        }

        usort($files, static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));
        $current = $files[0];
        $maxBytes = max(1, (int) config('docker.log_chunk_mb', 50)) * 1024 * 1024;

        return filesize($current) >= $maxBytes
            ? $directory.'/'.$baseName.'-'.str_pad((string) (count($files) + 1), 3, '0', STR_PAD_LEFT).'.log'
            : $current;
    }

    private function tailLines(string $path, int $limit): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read Docker log archive.');
        }

        $buffer = '';
        $lines = [];
        fseek($handle, 0, SEEK_END);
        $position = ftell($handle);

        while ($position > 0 && count($lines) <= $limit) {
            $size = min(8192, $position);
            $position -= $size;
            fseek($handle, $position);
            $buffer = fread($handle, $size).$buffer;
            $parts = explode("\n", $buffer);
            $buffer = array_shift($parts);
            $lines = array_merge($parts, $lines);
        }

        fclose($handle);

        if ($buffer !== '') {
            array_unshift($lines, $buffer);
        }

        return array_slice(array_values(array_filter($lines, static fn (string $line): bool => $line !== '')), -$limit);
    }

    private function withinRange(string $line, ?string $since, ?string $until): bool
    {
        $timestamp = strtok($line, ' ');
        if ($timestamp === false || ($since === null && $until === null)) {
            return true;
        }

        try {
            $date = CarbonImmutable::parse($timestamp);
            return ($since === null || $date->greaterThanOrEqualTo(CarbonImmutable::parse($since)))
                && ($until === null || $date->lessThanOrEqualTo(CarbonImmutable::parse($until)));
        } catch (\Throwable) {
            return true;
        }
    }

    private function dateDirectory(CarbonInterface $date): string
    {
        return $this->rootPath().'/'.$date->format('y-m-d');
    }

    private function rootPath(): string
    {
        return storage_path('app/docker-logs');
    }

    private function statePath(string $containerId): string
    {
        return $this->rootPath().'/.state/'.$this->shortId($containerId).'.json';
    }

    private function baseName(string $containerName, string $containerId): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', ltrim($containerName, '/')) ?: 'container';

        return trim($name, '-').'_'.$this->shortId($containerId);
    }

    private function shortId(string $containerId): string
    {
        return substr($containerId, 0, 12);
    }
}
