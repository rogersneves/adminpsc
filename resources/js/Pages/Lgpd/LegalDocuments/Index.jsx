import { Link, usePage } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function Index({ documents, types }) {
    const { props } = usePage();

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {props.flash?.status && (
                    <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">
                        {props.flash.status}
                    </p>
                )}

                <div>
                    <h1 className="text-xl font-semibold">Documentos legais</h1>
                    <p className="text-sm text-muted-foreground">
                        Política de privacidade e termos de uso. Publicar uma nova versão exige novo aceite dos usuários.
                    </p>
                </div>

                {types.map((type) => {
                    const versions = documents.filter((d) => d.type === type.value);
                    return (
                        <Card key={type.value}>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-base">{type.label}</CardTitle>
                                <Link
                                    href={`/lgpd/documentos/novo?type=${type.value}`}
                                    className={buttonVariants({ variant: 'outline', size: 'sm' })}
                                >
                                    Publicar nova versão
                                </Link>
                            </CardHeader>
                            <CardContent className="text-sm text-neutral-700">
                                {versions.length === 0 ? (
                                    <p className="text-muted-foreground">Nenhuma versão publicada.</p>
                                ) : (
                                    <ul className="flex flex-col gap-1">
                                        {versions.map((d) => (
                                            <li key={d.id} className="flex items-center gap-2">
                                                <span>
                                                    v{d.version} — {d.title}
                                                </span>
                                                {d.is_current && (
                                                    <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                        Atual
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </div>
    );
}
