import { useForm } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function Consent({ documents }) {
    const { data, setData, post, processing, errors } = useForm({ accept: false });

    const submit = (e) => {
        e.preventDefault();
        post('/lgpd/consentimento');
    };

    return (
        <div className="min-h-screen bg-muted/40 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <div>
                    <h1 className="text-xl font-semibold">Antes de continuar</h1>
                    <p className="text-sm text-muted-foreground">
                        Precisamos do seu aceite dos documentos abaixo para seguir usando o sistema.
                    </p>
                </div>

                {documents.map((doc) => (
                    <Card key={doc.id}>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {doc.title}{' '}
                                <span className="text-xs font-normal text-muted-foreground">
                                    ({doc.type_label} · versão {doc.version})
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="max-h-64 overflow-y-auto whitespace-pre-wrap rounded-md border bg-card p-3 text-sm text-muted-foreground">
                                {doc.content}
                            </div>
                        </CardContent>
                    </Card>
                ))}

                <form onSubmit={submit} className="flex flex-col gap-3">
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        <input
                            type="checkbox"
                            checked={data.accept}
                            onChange={(e) => setData('accept', e.target.checked)}
                        />
                        Li e aceito os documentos acima.
                    </label>
                    {errors.accept && <p className="text-sm text-destructive">{errors.accept}</p>}

                    <button type="submit" disabled={processing} className={buttonVariants() + ' self-start'}>
                        {processing ? 'Registrando…' : 'Aceitar e continuar'}
                    </button>
                </form>
            </div>
        </div>
    );
}
