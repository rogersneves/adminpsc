import { Link } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

export default function Index({ psychologists }) {
    return (
        <AppLayout
            title="Psicólogos"
            actions={
                <Link href="/psicologos/criar" className={buttonVariants({ size: 'sm' })}>
                    Cadastrar psicólogo
                </Link>
            }
        >
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {psychologists.length === 0 && (
                    <p className="text-sm text-muted-foreground">Nenhum psicólogo cadastrado ainda.</p>
                )}

                {psychologists.map((psychologist) => (
                    <Card key={psychologist.id}>
                        <CardHeader>
                            <CardTitle>{psychologist.name}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            <p>{psychologist.email}</p>
                            <p>Duração padrão da sessão: {psychologist.default_session_duration_minutes} min</p>
                            {psychologist.specialties?.length > 0 && (
                                <p>Especialidades: {psychologist.specialties.join(', ')}</p>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
