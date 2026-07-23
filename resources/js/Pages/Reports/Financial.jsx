import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const STATUS_LABELS = {
    em_aberto: 'Em aberto',
    pago: 'Pago',
    vencido: 'Vencido',
    parcial: 'Parcial',
    cancelado: 'Cancelado',
    estornado: 'Estornado',
};

function formatCurrency(value) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDate(iso) {
    return new Date(`${iso}T00:00:00`).toLocaleDateString('pt-BR');
}

export default function Financial({ rows }) {
    const { data, setData } = useForm({ from: '', to: '', patient_id: '', status: '' });

    function submit(e) {
        e.preventDefault();
        router.get('/relatorios/financeiro', data, { preserveState: true });
    }

    const query = new URLSearchParams(Object.fromEntries(Object.entries(data).filter(([, v]) => v))).toString();

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-3xl flex-col gap-4">
                <h1 className="text-xl font-semibold">Relatório Financeiro</h1>

                <Card>
                    <CardHeader>
                        <CardTitle>Filtros</CardTitle>
                        <CardDescription>Período pelo vencimento da cobrança.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="from">De</Label>
                                <Input id="from" type="date" value={data.from} onChange={(e) => setData('from', e.target.value)} />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="to">Até</Label>
                                <Input id="to" type="date" value={data.to} onChange={(e) => setData('to', e.target.value)} />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="patient_id">ID do paciente</Label>
                                <Input id="patient_id" value={data.patient_id} onChange={(e) => setData('patient_id', e.target.value)} className="w-64" />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="status">Status</Label>
                                <select
                                    id="status"
                                    className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm"
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(STATUS_LABELS).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <Button type="submit">Filtrar</Button>
                            <a href={`/relatorios/financeiro/pdf?${query}`}><Button type="button" variant="outline">Exportar PDF</Button></a>
                            <a href={`/relatorios/financeiro/excel?${query}`}><Button type="button" variant="outline">Exportar Excel</Button></a>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="p-2">Paciente</th>
                                    <th className="p-2">Total devido</th>
                                    <th className="p-2">Total pago</th>
                                    <th className="p-2">Vencimento</th>
                                    <th className="p-2">Status</th>
                                    <th className="p-2">Parcela</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <tr><td colSpan={6} className="p-2 text-muted-foreground">Nenhuma cobrança encontrada.</td></tr>
                                )}
                                {rows.map((row, i) => (
                                    <tr key={i} className="border-b last:border-0">
                                        <td className="p-2">{row.patient_name}</td>
                                        <td className="p-2">{formatCurrency(row.total_due)}</td>
                                        <td className="p-2">{formatCurrency(row.total_paid)}</td>
                                        <td className="p-2">{formatDate(row.due_date)}</td>
                                        <td className="p-2">{STATUS_LABELS[row.status]}</td>
                                        <td className="p-2">{row.installment_number}/{row.installment_total}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
