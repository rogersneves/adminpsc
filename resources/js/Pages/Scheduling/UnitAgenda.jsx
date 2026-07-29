import { useState } from 'react';
import { router } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

function MeetingLink({ session }) {
    const [url, setUrl] = useState(session.meeting_url ?? '');

    const save = () =>
        router.post(`/sessoes/${session.id}/teleconsulta`, { meeting_url: url || null }, { preserveScroll: true });

    return (
        <div className="mt-2 flex flex-wrap items-center gap-2">
            <input
                type="url"
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                placeholder="Link da teleconsulta (https://…)"
                className="w-64 rounded-md border px-2 py-1 text-sm"
            />
            <button type="button" onClick={save} className={buttonVariants({ variant: 'outline', size: 'sm' })}>
                Salvar link
            </button>
        </div>
    );
}

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

export default function UnitAgenda({ sessions, scoped }) {
    return (
        <AppLayout title="Agenda da unidade">
            <div className="mx-auto flex max-w-3xl flex-col gap-4">
                <p className="text-sm text-muted-foreground">
                    {scoped
                        ? 'Sessões futuras das suas unidades.'
                        : 'Sessões futuras de todas as unidades da clínica.'}
                </p>

                {sessions.length === 0 && <p className="text-sm text-muted-foreground">Nenhuma sessão futura.</p>}

                {sessions.map((session) => (
                    <Card key={session.id}>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {session.patient_name} <span className="font-normal text-muted-foreground">com {session.psychologist_name}</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            <div className="flex flex-wrap gap-x-4 gap-y-1">
                                <span>{formatDateTime(session.scheduled_at)}</span>
                                <span>{STATUS_LABELS[session.status] ?? session.status}</span>
                                {session.modality === 'online' && <span>Online</span>}
                                {session.unit_name && <span>Unidade: {session.unit_name}</span>}
                            </div>
                            {session.modality === 'online' && session.status === 'agendada' && (
                                <MeetingLink session={session} />
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
