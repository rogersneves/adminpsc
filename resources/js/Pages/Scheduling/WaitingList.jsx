import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

export default function WaitingList({ entries }) {
    return (
        <AppLayout title="Lista de espera">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {entries.length === 0 && <p className="text-sm text-muted-foreground">Ninguém na lista de espera.</p>}

                {entries.map((entry) => (
                    <Card key={entry.id}>
                        <CardHeader>
                            <CardTitle>{entry.patient_name}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            <p>Psicólogo: {entry.psychologist_name}</p>
                            <p>Período desejado: {entry.desired_period.from} até {entry.desired_period.to}</p>
                            {entry.desired_period.notes && <p>Obs.: {entry.desired_period.notes}</p>}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
