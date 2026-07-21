import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/Components/InputError';

const WEEKDAYS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
const TYPES = [
    { value: 'recorrente', label: 'Recorrente (semanal)' },
    { value: 'particular', label: 'Particular (um dia específico)' },
    { value: 'bloqueio', label: 'Bloqueio' },
    { value: 'ferias', label: 'Férias' },
    { value: 'feriado', label: 'Feriado' },
];

export default function Availability({ psychologist, availabilities }) {
    const { props } = usePage();
    const { data, setData, post, processing, errors, reset } = useForm({
        type: 'recorrente',
        weekday: '1',
        date: '',
        start_time: '09:00',
        end_time: '18:00',
        session_duration_minutes: '',
        buffer_minutes: '0',
    });

    const isRecurring = data.type === 'recorrente';

    function submit(e) {
        e.preventDefault();
        post(`/psicologos/${psychologist.id}/disponibilidade`, { onSuccess: () => reset('date') });
    }

    function remove(id) {
        router.delete(`/disponibilidade/${id}`, { preserveScroll: true });
    }

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {props.flash?.status && (
                    <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">
                        {props.flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Disponibilidade — {psychologist.name}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="type">Tipo</Label>
                                <select
                                    id="type"
                                    className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm"
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                >
                                    {TYPES.map((t) => (
                                        <option key={t.value} value={t.value}>{t.label}</option>
                                    ))}
                                </select>
                                <InputError message={errors.type} />
                            </div>

                            {isRecurring ? (
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="weekday">Dia da semana</Label>
                                    <select
                                        id="weekday"
                                        className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm"
                                        value={data.weekday}
                                        onChange={(e) => setData('weekday', e.target.value)}
                                    >
                                        {WEEKDAYS.map((label, i) => (
                                            <option key={i} value={i}>{label}</option>
                                        ))}
                                    </select>
                                    <InputError message={errors.weekday} />
                                </div>
                            ) : (
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="date">Data</Label>
                                    <Input id="date" type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                                    <InputError message={errors.date} />
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-3">
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="start_time">Início</Label>
                                    <Input id="start_time" type="time" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} />
                                    <InputError message={errors.start_time} />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="end_time">Fim</Label>
                                    <Input id="end_time" type="time" value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} />
                                    <InputError message={errors.end_time} />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="session_duration_minutes">Duração da sessão (min)</Label>
                                    <Input
                                        id="session_duration_minutes"
                                        type="number"
                                        placeholder="Padrão do psicólogo"
                                        value={data.session_duration_minutes}
                                        onChange={(e) => setData('session_duration_minutes', e.target.value)}
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="buffer_minutes">Intervalo entre sessões (min)</Label>
                                    <Input
                                        id="buffer_minutes"
                                        type="number"
                                        value={data.buffer_minutes}
                                        onChange={(e) => setData('buffer_minutes', e.target.value)}
                                    />
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>Adicionar</Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-2">
                    {availabilities.map((a) => (
                        <div key={a.id} className="flex items-center justify-between rounded-lg bg-white p-3 text-sm ring-1 ring-foreground/10">
                            <span>
                                {TYPES.find((t) => t.value === a.type)?.label ?? a.type}
                                {a.weekday !== null && ` — ${WEEKDAYS[a.weekday]}`}
                                {a.date && ` — ${a.date}`}
                                {' '}({a.start_time}–{a.end_time})
                            </span>
                            <button type="button" onClick={() => remove(a.id)} className="text-destructive">remover</button>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
