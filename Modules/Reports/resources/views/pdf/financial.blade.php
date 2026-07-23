<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório Financeiro</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        p.meta { color: #555; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Relatório Financeiro</h1>
    <p class="meta">Gerado em {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Paciente</th>
                <th>Valor</th>
                <th>Desconto</th>
                <th>Multa</th>
                <th>Juros</th>
                <th>Total devido</th>
                <th>Total pago</th>
                <th>Vencimento</th>
                <th>Status</th>
                <th>Parcela</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['patient_name'] }}</td>
                    <td>R$ {{ number_format($row['amount'], 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($row['discount_amount'], 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($row['fine_amount'], 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($row['interest_amount'], 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($row['total_due'], 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($row['total_paid'], 2, ',', '.') }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['due_date'])->format('d/m/Y') }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['installment_number'] }}/{{ $row['installment_total'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10">Nenhuma cobrança encontrada para os filtros aplicados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
