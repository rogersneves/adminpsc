<?php

declare(strict_types=1);

namespace Modules\Reports\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FinancialExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Paciente', 'Valor', 'Desconto', 'Multa', 'Juros', 'Total devido', 'Total pago',
            'Vencimento', 'Status', 'Parcela', 'Total de parcelas',
        ];
    }
}
