import { Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';

export default function PatientsList({ patients }) {
    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <h1 className="text-xl font-semibold">Financeiro — pacientes</h1>

                {patients.length === 0 && (
                    <p className="text-sm text-muted-foreground">Nenhum paciente cadastrado ainda.</p>
                )}

                {patients.map((patient) => (
                    <Link key={patient.id} href={`/pacientes/${patient.id}/financeiro`}>
                        <Card className="transition-colors hover:bg-muted/50">
                            <CardHeader>
                                <CardTitle>{patient.display_name}</CardTitle>
                            </CardHeader>
                        </Card>
                    </Link>
                ))}
            </div>
        </div>
    );
}
