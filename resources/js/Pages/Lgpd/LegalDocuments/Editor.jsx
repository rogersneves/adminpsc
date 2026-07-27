import { useForm } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';

export default function Editor({ type, type_label, current }) {
    const { data, setData, post, processing, errors } = useForm({
        type,
        title: current?.title ?? type_label,
        content: current?.content ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/lgpd/documentos');
    };

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <div>
                    <h1 className="text-xl font-semibold">Publicar {type_label}</h1>
                    <p className="text-sm text-muted-foreground">
                        {current
                            ? `Isto criará a versão ${current.version + 1}. A versão atual será aposentada e os usuários precisarão aceitar a nova.`
                            : 'Esta será a primeira versão publicada.'}
                    </p>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-3">
                    <label className="text-sm font-medium">
                        Título
                        <input
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                        />
                        {errors.title && <span className="text-sm text-red-600">{errors.title}</span>}
                    </label>

                    <label className="text-sm font-medium">
                        Conteúdo
                        <textarea
                            value={data.content}
                            onChange={(e) => setData('content', e.target.value)}
                            rows={16}
                            className="mt-1 w-full rounded-md border px-3 py-2 font-mono text-sm"
                        />
                        {errors.content && <span className="text-sm text-red-600">{errors.content}</span>}
                    </label>

                    <div className="flex gap-2">
                        <button type="submit" disabled={processing} className={buttonVariants()}>
                            {processing ? 'Publicando…' : 'Publicar versão'}
                        </button>
                        <a href="/lgpd/documentos" className={buttonVariants({ variant: 'ghost' })}>
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    );
}
