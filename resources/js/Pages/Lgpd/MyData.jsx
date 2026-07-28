import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

function Section({ title, children }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="text-sm text-muted-foreground">{children}</CardContent>
        </Card>
    );
}

function KeyVals({ obj }) {
    return (
        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
            {Object.entries(obj).map(([k, v]) => (
                <div key={k} className="contents">
                    <dt className="text-muted-foreground">{k}</dt>
                    <dd className="break-words">{v === null || v === '' ? '—' : String(Array.isArray(v) ? v.join(', ') : v)}</dd>
                </div>
            ))}
        </dl>
    );
}

export default function MyData({ data }) {
    return (
        <AppLayout
            title="Meus dados"
            actions={
                <a href="/lgpd/meus-dados/download" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
                    Baixar (JSON)
                </a>
            }
        >
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <p className="text-sm text-muted-foreground">
                    Todos os dados pessoais que mantemos sobre você (LGPD, Art. 18).
                </p>

                <Section title="Conta">
                    <KeyVals obj={data.conta} />
                </Section>

                {data.perfil && (
                    <Section title="Perfil">
                        <KeyVals obj={data.perfil} />
                    </Section>
                )}

                {data.responsaveis?.length > 0 && (
                    <Section title="Responsáveis">
                        {data.responsaveis.map((r, i) => (
                            <div key={i} className="mb-2 border-b pb-2 last:border-0">
                                <KeyVals obj={r} />
                            </div>
                        ))}
                    </Section>
                )}

                {data.sessoes?.length > 0 && (
                    <Section title={`Sessões (${data.sessoes.length})`}>
                        <ul className="list-disc pl-5">
                            {data.sessoes.map((s, i) => (
                                <li key={i}>
                                    {s.agendada_para} — {s.status} ({s.modalidade})
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}

                {data.cobrancas?.length > 0 && (
                    <Section title={`Cobranças (${data.cobrancas.length})`}>
                        <ul className="list-disc pl-5">
                            {data.cobrancas.map((c, i) => (
                                <li key={i}>
                                    R$ {c.valor} — venc. {c.vencimento} — {c.status}
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}

                <Section title="Consentimentos">
                    {data.consentimentos.length === 0 ? (
                        <p className="text-muted-foreground">Nenhum registro.</p>
                    ) : (
                        <ul className="list-disc pl-5">
                            {data.consentimentos.map((c, i) => (
                                <li key={i}>
                                    {c.documento} (v{c.versao}) — {c.aceito_em}
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </div>
        </AppLayout>
    );
}
