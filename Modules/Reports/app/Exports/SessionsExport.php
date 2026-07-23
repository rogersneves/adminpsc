<?php

declare(strict_types=1);

namespace Modules\Reports\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SessionsExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Paciente', 'Psicólogo', 'Data/Hora', 'Duração (min)', 'Modalidade', 'Status'];
    }
}
