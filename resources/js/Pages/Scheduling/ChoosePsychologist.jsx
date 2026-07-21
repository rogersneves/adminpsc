import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function ChoosePsychologist({ psychologists }) {
    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <h1 className="text-xl font-semibold">Agendar sessão</h1>

                {psychologists.length === 0 && (
                    <p className="text-sm text-muted-foreground">Nenhum psicólogo disponível nesta clínica ainda.</p>
                )}

                {psychologists.map((psychologist) => (
                    <Link key={psychologist.id} href={`/agenda/${psychologist.id}`}>
                        <Card className="transition-colors hover:bg-muted/50">
                            <CardHeader>
                                <CardTitle>{psychologist.name}</CardTitle>
                            </CardHeader>
                            {psychologist.specialties?.length > 0 && (
                                <CardContent className="text-sm text-muted-foreground">
                                    {psychologist.specialties.join(', ')}
                                </CardContent>
                            )}
                        </Card>
                    </Link>
                ))}
            </div>
        </div>
    );
}
