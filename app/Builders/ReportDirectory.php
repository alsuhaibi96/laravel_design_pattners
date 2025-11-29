<?php


namespace App\Builders;
use App\DTOs\ReportDTO;

class ReportDirectory
{

    protected  ReportInterface $builder;

    public function __construct(  ReportInterface $builder){
        $this->builder = $builder;
    }

    function buildSimpleReport(string $title, string $summary)
    {
        $this->builder->reset();

        return $this->builder->setTitle($title)
            ->setSummary($summary)->getReport();
    }


}
