<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QRCodeScanned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $qrData;
    public string $readerId;
    public bool $isValid;
    public array $validationResult;

    public function __construct(string $qrData, string $readerId, bool $isValid, array $validationResult = [])
    {
        $this->qrData = $qrData;
        $this->readerId = $readerId;
        $this->isValid = $isValid;
        $this->validationResult = $validationResult;
    }
}