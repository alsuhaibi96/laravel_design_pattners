<?php

namespace App\Http\Controllers;



use App\Logistics\RoadLogistics;
use App\Logistics\SeaLogistics;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Transport;

class ShipmentController extends Controller
{
    public function shipmentMethod(Request $request){

clock(['rquest data'=>$request->all()]);
$data=$request->only('type');

    $factory=match ($data['type']) {
        'truck'=>new RoadLogistics(),
        'ship'=>new SeaLogistics()

    };

    $factory->planDeliver();

    return response()->json(['message'=>'success','data'=>$data]);
    }


}
