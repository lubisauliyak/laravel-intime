<?php

namespace App\Exports;

use App\Exports\Sheets\GlobalSummarySheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GlobalAttendanceReportExport implements WithMultipleSheets
{
    use Exportable;

    protected $filters;
    protected $user;

    public function __construct(array $filters, $user)
    {
        $this->filters = $filters;
        $this->user = $user;
    }

    public function sheets(): array
    {
        $rekapSheet = new Sheets\GlobalRekapitulasiSheet($this->filters, $this->user);

        return [
            new GlobalSummarySheet($this->filters, $this->user, $rekapSheet->getMeetings()),
            $rekapSheet,
        ];
    }
}
