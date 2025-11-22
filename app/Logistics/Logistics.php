<?php

namespace App\Logistics;


use App\Interfaces\Transport;

abstract class Logistics{

    abstract public function createTransport(): Transport;

    public function planDeliver()
    {
        $transport = $this->createTransport();
        $transport->deliver();

    }
}
