<?php

namespace App\Builders;

use App\DTOs\ReportDTO;

class SalesReportBuilder implements ReportInterface
{

    protected ReportDTO $report;

    public function __construct(){
        $this->reset();
    }

    public function reset():void
    {
        $this->report = new ReportDTO();
    }

    public function setTitle(string $title):self
    {
        $this->report->title = $title;
        return $this;
    }


    public function setSubTitle(string $subTitle):self
    {
        $this->report->subTitle = $subTitle;
        return $this;
    }


    public function setSummary(string $summary):self
    {
        $this->report->summary = $summary;
        return $this;
    }


    public function setMeta(string $key, mixed $value):self
    {
        $this->report->meta[$key] = $value;
        return $this;
    }


    public function getReport()
    {
        $result=$this->report;
        $this->reset();
        return $result;

    }






}
