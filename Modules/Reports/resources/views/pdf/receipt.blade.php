<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Recibo de Pagamento</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #1a1a1a; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p.meta { color: #555; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background-color: #f2f2f2; width: 35%; }
        .amount { font-size: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Recibo de Pagamento</h1>
    <p class="meta">Emitido em {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <tr><th>Paciente</th><td>{{ $patient->display_name }}</td></tr>
        <tr><th>Valor pago</th><td class="amount">R$ {{ number_format((float) $payment->amount, 2, ',', '.') }}</td></tr>
        <tr><th>Data do pagamento</th><td>{{ $payment->paid_at->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Forma de pagamento</th><td>{{ $payment->method->value }}</td></tr>
        @if ($payment->reversed_at)
            <tr><th>Estornado em</th><td>{{ $payment->reversed_at->format('d/m/Y H:i') }}</td></tr>
        @endif
    </table>

    <h2 style="font-size: 14px; margin-top: 20px;">Cobrança quitada</h2>
    <table>
        <tr><th>Valor original</th><td>R$ {{ number_format((float) $charge->amount, 2, ',', '.') }}</td></tr>
        @if ((float) $charge->discount_amount > 0)
            <tr><th>Desconto</th><td>R$ {{ number_format((float) $charge->discount_amount, 2, ',', '.') }}</td></tr>
        @endif
        @if ((float) $charge->fine_amount > 0)
            <tr><th>Multa</th><td>R$ {{ number_format((float) $charge->fine_amount, 2, ',', '.') }}</td></tr>
        @endif
        @if ((float) $charge->interest_amount > 0)
            <tr><th>Juros</th><td>R$ {{ number_format((float) $charge->interest_amount, 2, ',', '.') }}</td></tr>
        @endif
        <tr><th>Vencimento</th><td>{{ $charge->due_date->format('d/m/Y') }}</td></tr>
        @if ($charge->installment_total > 1)
            <tr><th>Parcela</th><td>{{ $charge->installment_number }} de {{ $charge->installment_total }}</td></tr>
        @endif
    </table>

    <h2 style="font-size: 14px; margin-top: 20px;">Sessão(ões) referente(s)</h2>
    <table>
        @if ($session)
            <tr><th>Data/Hora</th><td>{{ $session->scheduled_at->format('d/m/Y H:i') }}</td></tr>
            <tr><th>Duração</th><td>{{ $session->duration_minutes }} min</td></tr>
            <tr><th>Modalidade</th><td>{{ $session->modality->value }}</td></tr>
        @else
            <tr><td colspan="2">Esta cobrança não está vinculada a uma sessão específica.</td></tr>
        @endif
    </table>
</body>
</html>
