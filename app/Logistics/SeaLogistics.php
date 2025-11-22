<?php

namespace App\Logistics;


use App\Interfaces\Transport;
use App\Transports\Ship;

class SeaLogistics extends Logistics

{
    public function createTransport():Transport
    {
        return new Ship();
    }
}
