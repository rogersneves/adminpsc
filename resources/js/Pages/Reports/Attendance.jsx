import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Attendance({ rows }) {
    const { data, setData } = useForm({ from: '', to: '', patient_id: '' });

    function submit(e) {
        e.preventDefault();
        router.get('/relatorios/comparecimento', data, { preserveState: true });
    }

    const query = new URLSearchParams(Object.fromEntries(Object.entries(data).filter(([, v]) => v))).toString();

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-3xl flex-col gap-4">
                <h1 className="text-xl font-semibold">Relatório de Comparecimento</h1>
                <p className="text-sm text-muted-foreground">
                    Taxa = realizadas / (realizadas + não compareceu). Cancelamentos e reagendamentos não entram no cálculo.
                </p>

                <Card>
                    <CardHeader>
                        <CardTitle>Filtros</CardTitle>
                        <CardDescription>Deixe em branco para não filtrar por esse campo.</CardDescription>
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
                            <Button type="submit">Filtrar</Button>
                            <a href={`/relatorios/comparecimento/pdf?${query}`}><Button type="button" variant="outline">Exportar PDF</Button></a>
                            <a href={`/relatorios/comparecimento/excel?${query}`}><Button type="button" variant="outline">Exportar Excel</Button></a>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="p-2">Paciente</th>
                                    <th className="p-2">Realizadas</th>
                                    <th className="p-2">Não compareceu</th>
                                    <th className="p-2">Taxa</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <tr><td colSpan={4} className="p-2 text-muted-foreground">Nenhum dado encontrado.</td></tr>
                                )}
                                {rows.map((row, i) => (
                                    <tr key={i} className="border-b last:border-0">
                                        <td className="p-2">{row.patient_name}</td>
                                        <td className="p-2">{row.realizada}</td>
                                        <td className="p-2">{row.nao_compareceu}</td>
                                        <td className="p-2">{(row.attendance_rate * 100).toFixed(1)}%</td>
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
