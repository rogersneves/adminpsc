import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
                        <CardContent className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <span>{formatDateTime(session.scheduled_at)}</span>
                            <span>{STATUS_LABELS[session.status] ?? session.status}</span>
                            {session.unit_name && <span>Unidade: {session.unit_name}</span>}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
