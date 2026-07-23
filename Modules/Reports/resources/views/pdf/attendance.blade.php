<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Comparecimento</title>
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
    <h1>Relatório de Comparecimento</h1>
    <p class="meta">Gerado em {{ now()->format('d/m/Y H:i') }}</p>
    <p class="meta">Taxa = realizadas / (realizadas + não compareceu). Cancelamentos e reagendamentos não entram no cálculo.</p>

    <table>
        <thead>
            <tr>
                <th>Paciente</th>
                <th>Realizadas</th>
                <th>Não compareceu</th>
                <th>Taxa de comparecimento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['patient_name'] }}</td>
                    <td>{{ $row['realizada'] }}</td>
                    <td>{{ $row['nao_compareceu'] }}</td>
                    <td>{{ number_format($row['attendance_rate'] * 100, 1, ',', '.') }}%</td>
                </tr>
            @empty
                <tr><td colspan="4">Nenhum dado encontrado para os filtros aplicados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
