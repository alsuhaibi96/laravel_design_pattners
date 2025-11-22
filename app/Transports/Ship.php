<?php

namespace App\Transports;


use App\Interfaces\Transport;
use Illuminate\Support\Facades\Log;

class Ship implements Transport
{

    public function deliver()
    {
        Log::info('Ship delivering');
    }
}
