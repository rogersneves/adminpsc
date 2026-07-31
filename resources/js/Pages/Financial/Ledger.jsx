import { router, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError';
import AppLayout from '@/Layouts/AppLayout';

const STATUS_LABELS = {
    em_aberto: 'Em aberto',
    pago: 'Pago',
    vencido: 'Vencido',
    parcial: 'Parcial',
    cancelado: 'Cancelado',
    estornado: 'Estornado',
};

const STATUS_COLORS = {
    em_aberto: 'bg-muted text-muted-foreground',
    pago: 'bg-success/10 text-success',
    vencido: 'bg-destructive/10 text-destructive',
    parcial: 'bg-warning/10 text-warning',
    cancelado: 'bg-muted text-muted-foreground line-through',
    estornado: 'bg-muted text-muted-foreground',
};

const METHOD_LABELS = {
    dinheiro: 'Dinheiro',
    cartao: 'Cartão',
    transferencia: 'Transferência',
    pix: 'PIX',
};

function formatCurrency(value) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDate(iso) {
    return new Date(`${iso}T00:00:00`).toLocaleDateString('pt-BR');
}

function formatDateTime(iso) {
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function NewChargeForm({ patient, availableSessions }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
        discount_amount: '',
        due_date: '',
        installment_total: '1',
        session_id: '',
    });

    function submit(e) {
        e.preventDefault();
        post(`/pacientes/${patient.id}/financeiro/cobrancas`, { onSuccess: () => reset() });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Nova cobrança</CardTitle>
                <CardDescription>Parcelamento divide o valor igualmente, vencimentos espaçados por mês.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="amount">Valor total (R$)</Label>
                        <Input
                            id="amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                        />
                        <InputError message={errors.amount} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="discount_amount">Desconto (R$)</Label>
                        <Input
                            id="discount_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.discount_amount}
                            onChange={(e) => setData('discount_amount', e.target.value)}
                        />
                        <InputError message={errors.discount_amount} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="due_date">Vencimento (1ª parcela)</Label>
                        <Input
                            id="due_date"
                            type="date"
                            value={data.due_date}
                            onChange={(e) => setData('due_date', e.target.value)}
                        />
                        <InputError message={errors.due_date} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="installment_total">Número de parcelas</Label>
                        <Input
                            id="installment_total"
                            type="number"
                            step="1"
                            min="1"
                            max="60"
                            value={data.installment_total}
                            onChange={(e) => setData('installment_total', e.target.value)}
                        />
                        <InputError message={errors.installment_total} />
                    </div>

                    {availableSessions.length > 0 && (
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="session_id">Vincular a uma sessão (opcional)</Label>
                            <select
                                id="session_id"
                                className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm"
                                value={data.session_id}
                                onChange={(e) => setData('session_id', e.target.value)}
                            >
                                <option value="">Nenhuma</option>
                                {availableSessions.map((session) => (
                                    <option key={session.id} value={session.id}>
                                        {formatDateTime(session.scheduled_at)}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    <Button type="submit" disabled={processing}>Criar cobrança</Button>
                </form>
            </CardContent>
        </Card>
    );
}

function RecordPaymentForm({ charge }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
        method: 'dinheiro',
        paid_at: '',
    });

    function submit(e) {
        e.preventDefault();
        post(`/financeiro/cobrancas/${charge.id}/pagamentos`, { onSuccess: () => reset() });
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2 border-t pt-3">
            <div className="flex flex-col gap-1">
                <Label htmlFor={`amount-${charge.id}`}>Valor (R$)</Label>
                <Input
                    id={`amount-${charge.id}`}
                    type="number"
                    step="0.01"
                    min="0.01"
                    className="w-28"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                />
            </div>

            <div className="flex flex-col gap-1">
                <Label htmlFor={`method-${charge.id}`}>Forma</Label>
                <select
                    id={`method-${charge.id}`}
                    className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm"
                    value={data.method}
                    onChange={(e) => setData('method', e.target.value)}
                >
                    {Object.entries(METHOD_LABELS).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
            </div>

            <Button type="submit" disabled={processing} size="sm">Registrar pagamento</Button>
            <InputError message={errors.amount} />
        </form>
    );
}

function GatewayChargeForm({ charge }) {
    const { data, setData, post, processing } = useForm({ method: 'pix' });

    const submit = (e) => {
        e.preventDefault();
        post(`/financeiro/cobrancas/${charge.id}/gateway`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
            <label className="flex flex-col text-xs font-medium">
                Cobrar via gateway
                <select
                    value={data.method}
                    onChange={(e) => setData('method', e.target.value)}
                    className="mt-1 h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                >
                    <option value="pix">PIX</option>
                    <option value="cartao">Cartão</option>
                    <option value="transferencia">Boleto</option>
                </select>
            </label>
            <Button type="submit" variant="outline" size="sm" disabled={processing}>
                Gerar cobrança online
            </Button>
        </form>
    );
}

function ChargeCard({ charge, canManage }) {
    function cancelCharge() {
        router.delete(`/financeiro/cobrancas/${charge.id}`, { preserveScroll: true });
    }

    function reversePayment(paymentId) {
        router.post(`/financeiro/pagamentos/${paymentId}/estornar`, {}, { preserveScroll: true });
    }

    const canCancel = charge.status === 'em_aberto' || charge.status === 'vencido';

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                <div>
                    <CardTitle>
                        {formatCurrency(charge.total_due)}
                        {charge.installment_total > 1 && (
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                (parcela {charge.installment_number}/{charge.installment_total})
                            </span>
                        )}
                    </CardTitle>
                    <CardDescription>Vencimento {formatDate(charge.due_date)}</CardDescription>
                </div>
                <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_COLORS[charge.status]}`}>
                    {STATUS_LABELS[charge.status]}
                </span>
            </CardHeader>
            <CardContent className="flex flex-col gap-3 text-sm">
                <div className="flex flex-col gap-0.5 text-muted-foreground">
                    <span>Valor bruto: {formatCurrency(charge.amount)}</span>
                    {charge.discount_amount > 0 && <span>Desconto: -{formatCurrency(charge.discount_amount)}</span>}
                    {charge.fine_amount > 0 && <span>Multa: +{formatCurrency(charge.fine_amount)}</span>}
                    {charge.interest_amount > 0 && <span>Juros: +{formatCurrency(charge.interest_amount)}</span>}
                    <span>Pago até agora: {formatCurrency(charge.total_paid)}</span>
                </div>

                {charge.payments.length > 0 && (
                    <div className="flex flex-col gap-1">
                        <strong>Pagamentos</strong>
                        {charge.payments.map((payment) => (
                            <div key={payment.id} className="flex items-center justify-between gap-2">
                                <span className={payment.reversed_at ? 'text-muted-foreground line-through' : ''}>
                                    {formatCurrency(payment.amount)} · {METHOD_LABELS[payment.method]} · {formatDateTime(payment.paid_at)}
                                </span>
                                <div className="flex items-center gap-1">
                                    <a
                                        href={`/financeiro/pagamentos/${payment.id}/recibo`}
                                        className="text-xs text-primary underline"
                                    >
                                        Baixar recibo
                                    </a>
                                    {canManage && !payment.reversed_at && (
                                        <Button type="button" variant="ghost" size="sm" onClick={() => reversePayment(payment.id)}>
                                            Estornar
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {(charge.payment_url || charge.pix_payload) && (
                    <div className="flex flex-col gap-1 rounded-md border border-info/30 bg-info/10 p-2">
                        <strong>Pagamento online</strong>
                        {charge.payment_url && (
                            <a href={charge.payment_url} target="_blank" rel="noreferrer" className="text-primary underline">
                                Abrir link de pagamento
                            </a>
                        )}
                        {charge.pix_payload && (
                            <div className="flex flex-col gap-1">
                                <span className="text-muted-foreground">PIX copia e cola:</span>
                                <code className="block break-all rounded bg-card p-2 text-xs">{charge.pix_payload}</code>
                            </div>
                        )}
                    </div>
                )}

                {canManage && charge.status !== 'pago' && charge.status !== 'cancelado' && (
                    <GatewayChargeForm charge={charge} />
                )}

                {canManage && charge.status !== 'pago' && charge.status !== 'cancelado' && (
                    <RecordPaymentForm charge={charge} />
                )}

                {canManage && canCancel && (
                    <Button type="button" variant="destructive" size="sm" onClick={cancelCharge} className="self-start">
                        Cancelar cobrança
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

export default function Ledger({ patient, charges, availableSessions, canManage }) {
    const { props } = usePage();

    return (
        <AppLayout title={`Financeiro — ${patient.display_name}`}>
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {canManage && <NewChargeForm patient={patient} availableSessions={availableSessions} />}

                <div className="flex flex-col gap-3">
                    <h2 className="text-lg font-medium">Cobranças</h2>
                    {charges.length === 0 && (
                        <p className="text-sm text-muted-foreground">Nenhuma cobrança registrada ainda.</p>
                    )}
                    {charges.map((charge) => (
                        <ChargeCard key={charge.id} charge={charge} canManage={canManage} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
