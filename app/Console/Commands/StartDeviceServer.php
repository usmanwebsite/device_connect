<?php

namespace App\Console\Commands;

use App\Services\SocketServerService;
use Illuminate\Console\Command;

class StartDeviceServer extends Command
{
    protected $signature = 'device:start-server {port=18887}';
    protected $description = 'Start the device communication socket server';

    public function handle()
    {
        $port = $this->argument('port');
        $this->info("Starting device socket server on port {$port}...");

        $socketService = app(SocketServerService::class);
        $socketService->startServer($port);
    }
}