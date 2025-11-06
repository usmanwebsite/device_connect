<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SocketServerService;

class StartDeviceServer extends Command
{
    protected $signature = 'device:start-server';
    protected $description = 'Start persistent TCP socket server for device communication';

    public function handle()
    {
        $this->info('🔌 Starting device TCP server... Press Ctrl+C to stop.');
        $server = new SocketServerService();
        $server->startServer();
    }
}
