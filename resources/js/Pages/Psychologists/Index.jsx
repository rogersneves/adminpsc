import { Link, usePage } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function Index({ psychologists }) {
    const { props } = usePage();

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {props.flash?.status && (
                    <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">
                        {props.flash.status}
                    </p>
                )}

                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Psicólogos</h1>
                    <Link href="/psicologos/criar" className={buttonVariants()}>
                        Cadastrar psicólogo
                    </Link>
                </div>

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
        </div>
    );
}
