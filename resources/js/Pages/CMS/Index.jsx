import { Link, router, usePage } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

function StatusBadge({ status, label }) {
    const styles =
        status === 'publicada'
            ? 'bg-success/10 text-success'
            : 'bg-warning/10 text-warning';

    return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${styles}`}>{label}</span>;
}

export default function Index({ pages, tenantSlug }) {
    const { props } = usePage();

    const publicUrl = (page) =>
        page.is_home ? `/c/${tenantSlug}` : `/c/${tenantSlug}/p/${page.slug}`;

    const remove = (page) => {
        if (window.confirm(`Remover a página "${page.title}"? Ela sairá do ar imediatamente.`)) {
            router.delete(`/cms/paginas/${page.id}`);
        }
    };

    return (
        <AppLayout
            title="Páginas do site"
            actions={
                <Link href="/cms/paginas/criar" className={buttonVariants({ size: 'sm' })}>
                    Nova página
                </Link>
            }
        >
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <p className="text-sm text-muted-foreground">Páginas públicas da clínica, editadas visualmente.</p>

                {pages.length === 0 && (
                    <p className="text-sm text-muted-foreground">Nenhuma página criada ainda.</p>
                )}

                {pages.map((page) => (
                    <Card key={page.id}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {page.title}
                                {page.is_home && (
                                    <span className="rounded-full bg-info/10 px-2 py-0.5 text-xs font-medium text-info">
                                        Página inicial
                                    </span>
                                )}
                                <StatusBadge status={page.status} label={page.status_label} />
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-sm text-muted-foreground">
                            <p>/c/{tenantSlug}{page.is_home ? '' : `/p/${page.slug}`}</p>
                            <div className="flex flex-wrap gap-2">
                                <Link
                                    href={`/cms/paginas/${page.id}/editar`}
                                    className={buttonVariants({ variant: 'outline', size: 'sm' })}
                                >
                                    Editar
                                </Link>
                                {page.status === 'publicada' && (
                                    <a
                                        href={publicUrl(page)}
                                        target="_blank"
                                        rel="noreferrer"
                                        className={buttonVariants({ variant: 'outline', size: 'sm' })}
                                    >
                                        Ver publicada
                                    </a>
                                )}
                                <button
                                    type="button"
                                    onClick={() => remove(page)}
                                    className={buttonVariants({ variant: 'ghost', size: 'sm' })}
                                >
                                    Remover
                                </button>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
