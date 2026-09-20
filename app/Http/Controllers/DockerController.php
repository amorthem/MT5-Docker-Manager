<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DockerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Docker/Containers');
    }

    public function show(string $container): Response
    {
        return Inertia::render('Docker/Container', [
            'containerId' => $container,
        ]);
    }
}
