import { router, useForm, usePage } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}

export default function Tokens({ tokens, newToken }) {
    const { props } = usePage();
    const { data, setData, post, processing, reset, errors } = useForm({ name: '' });

    const submit = (e) => {
        e.preventDefault();
        post('/api-tokens', { onSuccess: () => reset() });
    };

    const revoke = (token) => {
        if (window.confirm(`Revogar o token "${token.name}"? Aplicações que o usam deixarão de funcionar.`)) {
            router.delete(`/api-tokens/${token.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title="Tokens de API">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {props.flash?.status && (
                    <p className="rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm text-success" role="status">
                        {props.flash.status}
                    </p>
                )}

                <p className="text-sm text-muted-foreground">
                    Tokens para acessar a API REST (v1) em nome da sua conta. Envie no cabeçalho{' '}
                    <code className="rounded bg-muted px-1">Authorization: Bearer &lt;token&gt;</code>.
                </p>

                {newToken && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Seu novo token</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            <code className="block break-all rounded-md border bg-muted p-3 text-sm">{newToken}</code>
                            <p className="text-xs text-muted-foreground">Copie agora — ele não será exibido novamente.</p>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Gerar token</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-2 text-sm">
                            <label className="flex flex-col">
                                Nome (ex.: "App do celular")
                                <input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="mt-1 w-64 rounded-md border px-3 py-2"
                                />
                                {errors.name && <span className="text-destructive">{errors.name}</span>}
                            </label>
                            <button type="submit" disabled={processing} className={buttonVariants({ size: 'sm' })}>
                                Gerar
                            </button>
                        </form>
                    </CardContent>
                </Card>

                {tokens.length === 0 && <p className="text-sm text-muted-foreground">Nenhum token ativo.</p>}
                {tokens.map((token) => (
                    <Card key={token.id}>
                        <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6 text-sm">
                            <div>
                                <p className="font-medium">{token.name}</p>
                                <p className="text-muted-foreground">
                                    Criado em {formatDate(token.created_at)} · Último uso: {formatDate(token.last_used_at)}
                                </p>
                            </div>
                            <button type="button" onClick={() => revoke(token)} className={buttonVariants({ variant: 'ghost', size: 'sm' })}>
                                Revogar
                            </button>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
