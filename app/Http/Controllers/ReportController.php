<?php

namespace App\Http\Controllers;

use App\Builders\ReportDirectory;
use App\Builders\SalesReportBuilder;
use Illuminate\Http\Request;

class ReportController extends Controller
{

    public function index(){
        $salesReportBuilder= new SalesReportBuilder();
        $directory=new ReportDirectory($salesReportBuilder);
       $data= $directory->buildSimpleReport("Report title","Summary baby");

        return json_encode(['data'=>$data]);
    }
}
