import { useForm, usePage } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

function Usage({ used, max }) {
    return (
        <span>
            {used}
            {max === null ? ' (ilimitado)' : ` / ${max}`}
        </span>
    );
}

export default function Index({ settings, plan }) {
    const { props } = usePage();

    const { data, setData, put, processing, errors } = useForm({
        scheduling: {
            booking_horizon_days: settings['scheduling.booking_horizon_days'],
            minimum_reschedule_notice_hours: settings['scheduling.minimum_reschedule_notice_hours'],
        },
        branding: {
            display_name: settings['branding.display_name'],
            primary_color: settings['branding.primary_color'],
        },
    });

    const submit = (e) => {
        e.preventDefault();
        put('/configuracoes');
    };

    const set = (group, key, value) => setData(group, { ...data[group], [key]: value });

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {props.flash?.status && (
                    <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">
                        {props.flash.status}
                    </p>
                )}

                <h1 className="text-xl font-semibold">Configurações</h1>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Plano {plan.label}</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-neutral-700">
                        <p>Psicólogos: <Usage used={plan.psychologists_used} max={plan.max_psychologists} /></p>
                        <p>Pacientes: <Usage used={plan.patients_used} max={plan.max_patients} /></p>
                        {plan.on_trial && (
                            <p className="mt-1 text-amber-700">
                                Período de avaliação até {new Date(plan.trial_ends_at).toLocaleDateString('pt-BR')}.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Agenda</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-sm">
                            <label className="font-medium">
                                Horizonte de reserva (dias)
                                <input
                                    type="number"
                                    value={data.scheduling.booking_horizon_days}
                                    onChange={(e) => set('scheduling', 'booking_horizon_days', e.target.value)}
                                    className="mt-1 w-full rounded-md border px-3 py-2"
                                />
                                {errors['scheduling.booking_horizon_days'] && (
                                    <span className="text-red-600">{errors['scheduling.booking_horizon_days']}</span>
                                )}
                            </label>
                            <label className="font-medium">
                                Antecedência mínima p/ cancelar/reagendar (h)
                                <input
                                    type="number"
                                    value={data.scheduling.minimum_reschedule_notice_hours}
                                    onChange={(e) => set('scheduling', 'minimum_reschedule_notice_hours', e.target.value)}
                                    className="mt-1 w-full rounded-md border px-3 py-2"
                                />
                                {errors['scheduling.minimum_reschedule_notice_hours'] && (
                                    <span className="text-red-600">{errors['scheduling.minimum_reschedule_notice_hours']}</span>
                                )}
                            </label>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Marca</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-sm">
                            <label className="font-medium">
                                Nome de exibição
                                <input
                                    type="text"
                                    value={data.branding.display_name}
                                    onChange={(e) => set('branding', 'display_name', e.target.value)}
                                    className="mt-1 w-full rounded-md border px-3 py-2"
                                />
                                {errors['branding.display_name'] && (
                                    <span className="text-red-600">{errors['branding.display_name']}</span>
                                )}
                            </label>
                            <label className="font-medium">
                                Cor primária
                                <input
                                    type="color"
                                    value={data.branding.primary_color}
                                    onChange={(e) => set('branding', 'primary_color', e.target.value)}
                                    className="mt-1 block h-10 w-20 rounded-md border"
                                />
                                {errors['branding.primary_color'] && (
                                    <span className="text-red-600">{errors['branding.primary_color']}</span>
                                )}
                            </label>
                        </CardContent>
                    </Card>

                    <button type="submit" disabled={processing} className={buttonVariants() + ' self-start'}>
                        {processing ? 'Salvando…' : 'Salvar configurações'}
                    </button>
                </form>
            </div>
        </div>
    );
}
