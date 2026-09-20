<?php

return [
    'socket' => env('DOCKER_SOCKET', '/var/run/docker.sock'),
    'host' => env('DOCKER_API_HOST', 'http://localhost'),
    'timeout' => (int) env('DOCKER_API_TIMEOUT', 5),
    'stats_timeout' => (int) env('DOCKER_STATS_TIMEOUT', 2),
    'max_log_lines' => (int) env('DOCKER_MAX_LOG_LINES', 5000),
];