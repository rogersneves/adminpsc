import { Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

export default function MyPatients({ patients }) {
    return (
        <AppLayout title="Meus pacientes">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {patients.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Nenhum paciente ainda — o prontuário fica disponível depois da primeira sessão marcada.
                    </p>
                )}

                {patients.map((patient) => (
                    <Link key={patient.id} href={`/pacientes/${patient.id}/prontuario`}>
                        <Card className="transition-colors hover:bg-muted/50">
                            <CardHeader>
                                <CardTitle>{patient.display_name}</CardTitle>
                            </CardHeader>
                        </Card>
                    </Link>
                ))}
            </div>
        </AppLayout>
    );
}
