<?php

namespace App\Console\Commands;

use App\Services\DockerLogCollector;
use Illuminate\Console\Command;

class CollectDockerLogsCommand extends Command
{
    protected $signature = 'docker:collect-logs';

    protected $description = 'Collect Docker container logs into the local archive';

    public function handle(DockerLogCollector $collector): int
    {
        $bytes = $collector->collect();

        $this->info(sprintf('Collected %s bytes of Docker logs.', number_format($bytes)));

        return self::SUCCESS;
    }
}
