<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DoorAccessEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action;
    public string $readerId;
    public ?string $passId;
    public bool $success;

    public function __construct(string $action, string $readerId, ?string $passId, bool $success)
    {
        $this->action = $action;
        $this->readerId = $readerId;
        $this->passId = $passId;
        $this->success = $success;
    }
}