import { router, useForm } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

function NewUnit() {
    const { data, setData, post, processing, reset, errors } = useForm({ name: '', city: '' });

    const submit = (e) => {
        e.preventDefault();
        post('/unidades', { onSuccess: () => reset() });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Nova unidade</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="flex flex-wrap items-end gap-2 text-sm">
                    <label className="flex flex-col">
                        Nome
                        <input
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="mt-1 rounded-md border px-3 py-2"
                        />
                        {errors.name && <span className="text-destructive">{errors.name}</span>}
                    </label>
                    <label className="flex flex-col">
                        Cidade
                        <input
                            type="text"
                            value={data.city}
                            onChange={(e) => setData('city', e.target.value)}
                            className="mt-1 rounded-md border px-3 py-2"
                        />
                    </label>
                    <button type="submit" disabled={processing} className={buttonVariants({ size: 'sm' })}>
                        Criar
                    </button>
                </form>
            </CardContent>
        </Card>
    );
}

function UnitRow({ unit }) {
    const { data, setData } = useForm({ name: unit.name, city: unit.city ?? '', is_active: unit.is_active });

    const save = () => router.put(`/unidades/${unit.id}`, data, { preserveScroll: true });
    const remove = () => {
        if (window.confirm(`Remover a unidade "${unit.name}"?`)) {
            router.delete(`/unidades/${unit.id}`, { preserveScroll: true });
        }
    };

    return (
        <Card>
            <CardContent className="flex flex-wrap items-end gap-3 pt-6 text-sm">
                <label className="flex flex-col">
                    Nome
                    <input value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 rounded-md border px-2 py-1" />
                </label>
                <label className="flex flex-col">
                    Cidade
                    <input value={data.city} onChange={(e) => setData('city', e.target.value)} className="mt-1 rounded-md border px-2 py-1" />
                </label>
                <label className="flex items-center gap-1.5">
                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                    Ativa
                </label>
                <span className="text-muted-foreground">{unit.staff_count} profissional(is)</span>
                <button type="button" onClick={save} className={buttonVariants({ variant: 'outline', size: 'sm' })}>Salvar</button>
                <button type="button" onClick={remove} className={buttonVariants({ variant: 'ghost', size: 'sm' })}>Remover</button>
            </CardContent>
        </Card>
    );
}

export default function Units({ units }) {
    return (
        <AppLayout title="Unidades">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <p className="text-sm text-muted-foreground">Filiais da clínica. Psicólogos e secretárias são vinculados a unidades.</p>
                <NewUnit />
                {units.length === 0 && <p className="text-sm text-muted-foreground">Nenhuma unidade cadastrada.</p>}
                {units.map((unit) => (
                    <UnitRow key={unit.id} unit={unit} />
                ))}
            </div>
        </AppLayout>
    );
}
