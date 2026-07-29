import { router, useForm } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

function NewPlan() {
    const { data, setData, post, processing, reset, errors } = useForm({ name: '' });

    const submit = (e) => {
        e.preventDefault();
        post('/convenios', { onSuccess: () => reset() });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Novo convênio</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="flex flex-wrap items-end gap-2 text-sm">
                    <label className="flex flex-col">
                        Nome
                        <input value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 rounded-md border px-3 py-2" />
                        {errors.name && <span className="text-destructive">{errors.name}</span>}
                    </label>
                    <button type="submit" disabled={processing} className={buttonVariants({ size: 'sm' })}>Criar</button>
                </form>
            </CardContent>
        </Card>
    );
}

function PlanRow({ plan }) {
    const { data, setData } = useForm({ name: plan.name, is_active: plan.is_active });

    const save = () => router.put(`/convenios/${plan.id}`, data, { preserveScroll: true });
    const remove = () => {
        if (window.confirm(`Remover o convênio "${plan.name}"?`)) {
            router.delete(`/convenios/${plan.id}`, { preserveScroll: true });
        }
    };

    return (
        <Card>
            <CardContent className="flex flex-wrap items-end gap-3 pt-6 text-sm">
                <label className="flex flex-col">
                    Nome
                    <input value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 rounded-md border px-2 py-1" />
                </label>
                <label className="flex items-center gap-1.5">
                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                    Ativo
                </label>
                <button type="button" onClick={save} className={buttonVariants({ variant: 'outline', size: 'sm' })}>Salvar</button>
                <button type="button" onClick={remove} className={buttonVariants({ variant: 'ghost', size: 'sm' })}>Remover</button>
            </CardContent>
        </Card>
    );
}

export default function HealthPlans({ plans }) {
    return (
        <AppLayout title="Convênios">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <p className="text-sm text-muted-foreground">Convênios aceitos pela clínica. Pacientes e cobranças podem ser vinculados a um convênio.</p>
                <NewPlan />
                {plans.length === 0 && <p className="text-sm text-muted-foreground">Nenhum convênio cadastrado.</p>}
                {plans.map((plan) => (
                    <PlanRow key={plan.id} plan={plan} />
                ))}
            </div>
        </AppLayout>
    );
}
