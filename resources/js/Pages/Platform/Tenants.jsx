import { useForm, usePage, router } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

function NewTenant({ plans }) {
    const { data, setData, post, processing, reset, errors } = useForm({ name: '', plan: plans[0]?.value });

    const submit = (e) => {
        e.preventDefault();
        post('/plataforma/tenants', { onSuccess: () => reset('name') });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Provisionar tenant</CardTitle>
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
                        {errors.name && <span className="text-red-600">{errors.name}</span>}
                    </label>
                    <label className="flex flex-col">
                        Plano
                        <select
                            value={data.plan}
                            onChange={(e) => setData('plan', e.target.value)}
                            className="mt-1 rounded-md border px-3 py-2"
                        >
                            {plans.map((p) => (
                                <option key={p.value} value={p.value}>
                                    {p.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <button type="submit" disabled={processing} className={buttonVariants({ size: 'sm' })}>
                        Provisionar
                    </button>
                </form>
            </CardContent>
        </Card>
    );
}

function TenantRow({ tenant, plans, statuses }) {
    const { data, setData } = useForm({ plan: tenant.plan, status: tenant.status });

    const save = () => router.put(`/plataforma/tenants/${tenant.id}`, data, { preserveScroll: true });

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">
                    {tenant.name}{' '}
                    <span className="text-xs font-normal text-muted-foreground">/c/{tenant.slug}</span>
                </CardTitle>
                {tenant.on_trial && (
                    <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                        trial
                    </span>
                )}
            </CardHeader>
            <CardContent className="flex flex-wrap items-end gap-3 text-sm text-neutral-700">
                <span>Psicólogos: {tenant.psychologists}</span>
                <span>Usuários: {tenant.users}</span>
                <label className="flex flex-col">
                    Plano
                    <select
                        value={data.plan}
                        onChange={(e) => setData('plan', e.target.value)}
                        className="mt-1 rounded-md border px-2 py-1"
                    >
                        {plans.map((p) => (
                            <option key={p.value} value={p.value}>
                                {p.label}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="flex flex-col">
                    Status
                    <select
                        value={data.status}
                        onChange={(e) => setData('status', e.target.value)}
                        className="mt-1 rounded-md border px-2 py-1"
                    >
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>
                </label>
                <button type="button" onClick={save} className={buttonVariants({ variant: 'outline', size: 'sm' })}>
                    Salvar
                </button>
            </CardContent>
        </Card>
    );
}

export default function Tenants({ tenants, plans, statuses }) {
    const { props } = usePage();

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-3xl flex-col gap-4">
                {props.flash?.status && (
                    <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">
                        {props.flash.status}
                    </p>
                )}

                <h1 className="text-xl font-semibold">Tenants da plataforma</h1>

                <NewTenant plans={plans} />

                {tenants.map((tenant) => (
                    <TenantRow key={tenant.id} tenant={tenant} plans={plans} statuses={statuses} />
                ))}
            </div>
        </div>
    );
}
