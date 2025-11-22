<?php

namespace App\Transports;


use App\Interfaces\Transport;
use Illuminate\Support\Facades\Log;

class Truck implements Transport
{

    public function deliver()
    {
        Log::info('Truck delivering');
    }
}
