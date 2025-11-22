<?php

namespace App\Logistics;

use App\Interfaces\Transport;
use App\Transports\Truck;

class RoadLogistics extends Logistics
{
    public function createTransport(): Transport
    {
        return new Truck();
    }
}
