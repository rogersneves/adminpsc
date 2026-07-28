import { useForm } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

function Invite({ units }) {
    const { data, setData, post, processing, reset, errors } = useForm({ name: '', email: '', unit_ids: [] });

    const toggleUnit = (id) =>
        setData('unit_ids', data.unit_ids.includes(id) ? data.unit_ids.filter((u) => u !== id) : [...data.unit_ids, id]);

    const submit = (e) => {
        e.preventDefault();
        post('/secretarias', { onSuccess: () => reset() });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Convidar secretária</CardTitle>
            </CardHeader>
            <CardContent>
                {units.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Cadastre ao menos uma unidade antes de convidar uma secretária.</p>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-3 text-sm">
                        <label className="flex flex-col">
                            Nome
                            <input value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 rounded-md border px-3 py-2" />
                            {errors.name && <span className="text-destructive">{errors.name}</span>}
                        </label>
                        <label className="flex flex-col">
                            E-mail
                            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="mt-1 rounded-md border px-3 py-2" />
                            {errors.email && <span className="text-destructive">{errors.email}</span>}
                        </label>
                        <div className="flex flex-col gap-1">
                            <span className="font-medium">Unidades</span>
                            {units.map((unit) => (
                                <label key={unit.id} className="flex items-center gap-2">
                                    <input type="checkbox" checked={data.unit_ids.includes(unit.id)} onChange={() => toggleUnit(unit.id)} />
                                    {unit.name}
                                </label>
                            ))}
                            {errors.unit_ids && <span className="text-destructive">{errors.unit_ids}</span>}
                        </div>
                        <button type="submit" disabled={processing} className={buttonVariants({ size: 'sm' }) + ' self-start'}>
                            Convidar
                        </button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

export default function Secretaries({ secretaries, units }) {
    return (
        <AppLayout title="Secretárias">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <Invite units={units} />
                {secretaries.length === 0 && <p className="text-sm text-muted-foreground">Nenhuma secretária cadastrada ainda.</p>}
                {secretaries.map((s) => (
                    <Card key={s.id}>
                        <CardHeader>
                            <CardTitle className="text-base">{s.name}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            <p>{s.email}</p>
                            <p>Unidades: {s.units.length > 0 ? s.units.map((u) => u.name).join(', ') : '—'}</p>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
