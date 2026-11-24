<?php

namespace App\Exports;

use App\Exports\Sheets\GroupMemberRekapitulasiSheet;
use App\Exports\Sheets\GroupMemberSummarySheet;
use App\Models\Group;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GroupMemberActivityExport implements WithMultipleSheets
{
    use Exportable;

    protected Group $group;

    protected array $filters;

    protected ?array $memberIds;

    public function __construct(Group $group, array $filters = [], ?array $memberIds = null)
    {
        $this->group = $group;
        $this->filters = $filters;
        $this->memberIds = $memberIds;
    }

    public function sheets(): array
    {
        $rekapSheet = new GroupMemberRekapitulasiSheet($this->group, $this->filters, $this->memberIds);

        return [
            new GroupMemberSummarySheet($this->group, $this->filters, $rekapSheet->getMeetings()),
            $rekapSheet,
        ];
    }
}
