<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Sessões</title>
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
    <h1>Relatório de Sessões</h1>
    <p class="meta">Gerado em {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Paciente</th>
                <th>Psicólogo</th>
                <th>Data/Hora</th>
                <th>Duração</th>
                <th>Modalidade</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['patient_name'] }}</td>
                    <td>{{ $row['psychologist_name'] }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['scheduled_at'])->format('d/m/Y H:i') }}</td>
                    <td>{{ $row['duration_minutes'] }} min</td>
                    <td>{{ $row['modality'] }}</td>
                    <td>{{ $row['status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Nenhuma sessão encontrada para os filtros aplicados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
