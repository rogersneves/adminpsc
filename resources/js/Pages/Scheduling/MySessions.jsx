import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/Layouts/AppLayout';

const STATUS_LABELS = {
    agendada: 'Agendada',
    confirmada: 'Confirmada',
    realizada: 'Realizada',
    cancelada: 'Cancelada',
    reagendada: 'Reagendada',
    nao_compareceu: 'Não compareceu',
};

function formatDateTime(iso) {
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

export default function MySessions({ sessions }) {
    const { props } = usePage();
    const [reschedulingId, setReschedulingId] = useState(null);
    const [newDate, setNewDate] = useState('');

    function cancel(id) {
        router.post(`/sessoes/${id}/cancelar`, {}, { preserveScroll: true });
    }

    function reschedule(session) {
        if (!newDate) return;
        router.post(`/sessoes/${session.id}/reagendar`, {
            scheduled_at: newDate,
            duration_minutes: session.duration_minutes,
        }, {
            preserveScroll: true,
            onSuccess: () => setReschedulingId(null),
        });
    }

    return (
        <AppLayout title="Minhas sessões">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {props.errors?.session && (
                    <p className="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
                        {props.errors.session}
                    </p>
                )}

                {sessions.length === 0 && <p className="text-sm text-muted-foreground">Nenhuma sessão ainda.</p>}

                {sessions.map((session) => (
                    <Card key={session.id}>
                        <CardHeader>
                            <CardTitle>{session.psychologist_name}</CardTitle>
                            <CardDescription>
                                {formatDateTime(session.scheduled_at)} · {STATUS_LABELS[session.status] ?? session.status}
                                {session.modality === 'online' && ' · Online'}
                            </CardDescription>
                        </CardHeader>
                        {session.modality === 'online' && session.meeting_url && session.status === 'agendada' && (
                            <CardContent className="pb-0">
                                <a
                                    href={session.meeting_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1.5 text-sm font-medium text-primary underline"
                                >
                                    Entrar na teleconsulta
                                </a>
                            </CardContent>
                        )}
                        {session.status === 'agendada' && (
                            <CardContent className="flex flex-col gap-3">
                                {reschedulingId === session.id ? (
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor={`new-date-${session.id}`}>Novo horário</Label>
                                        <Input
                                            id={`new-date-${session.id}`}
                                            type="datetime-local"
                                            value={newDate}
                                            onChange={(e) => setNewDate(e.target.value)}
                                        />
                                        <div className="flex gap-2">
                                            <Button size="sm" onClick={() => reschedule(session)}>Confirmar</Button>
                                            <Button size="sm" variant="ghost" onClick={() => setReschedulingId(null)}>Cancelar</Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex gap-2">
                                        <Button size="sm" variant="outline" onClick={() => { setReschedulingId(session.id); setNewDate(''); }}>
                                            Reagendar
                                        </Button>
                                        <Button size="sm" variant="destructive" onClick={() => cancel(session.id)}>
                                            Cancelar
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        )}
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
