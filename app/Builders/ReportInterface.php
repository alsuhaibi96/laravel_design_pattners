<?php

namespace App\Builders;


interface ReportInterface{

    public  function reset():void;

    public function setTitle(string $title):self;


    public function setSubTitle(string $subTitle):self;

    public function setSummary(string $summary):self;


    public function setMeta(string $key,mixed $value):self;


    public function getReport();


}
