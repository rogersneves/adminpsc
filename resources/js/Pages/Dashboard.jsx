import { Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { NotificationBell } from '@/Components/NotificationBell';

function formatCurrency(value) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDateTime(iso) {
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function PsychologistDashboard({ data }) {
    return (
        <div className="flex flex-col gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>Agenda de hoje</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-2 text-sm">
                    {data.agenda_today.length === 0 && (
                        <p className="text-muted-foreground">Nenhuma sessão hoje.</p>
                    )}
                    {data.agenda_today.map((session) => (
                        <div key={session.id} className="flex justify-between">
                            <span>{session.patient_name}</span>
                            <span className="text-muted-foreground">{formatDateTime(session.scheduled_at)}</span>
                        </div>
                    ))}
                </CardContent>
            </Card>

            <div className="grid grid-cols-2 gap-4">
                <Card>
                    <CardHeader><CardTitle>Sessões esta semana</CardTitle></CardHeader>
                    <CardContent className="text-2xl font-semibold">{data.sessions_this_week}</CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Receita do mês</CardTitle></CardHeader>
                    <CardContent className="text-2xl font-semibold">{formatCurrency(data.revenue_this_month)}</CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Pendências</CardTitle></CardHeader>
                    <CardContent>
                        <p className="text-2xl font-semibold">{formatCurrency(data.pending_charges_total)}</p>
                        <p className="text-sm text-muted-foreground">{data.pending_charges_count} cobrança(s)</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Pacientes</CardTitle></CardHeader>
                    <CardContent>
                        <p className="text-2xl font-semibold">{data.total_patients_count}</p>
                        <p className="text-sm text-muted-foreground">
                            {data.active_patients_count} ativo(s) · {data.inactive_patients_count} inativo(s)
                        </p>
                    </CardContent>
                </Card>
            </div>

            {data.birthday_patients.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Aniversariantes do mês</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-1 text-sm">
                        {data.birthday_patients.map((patient) => (
                            <span key={patient.id}>{patient.display_name}</span>
                        ))}
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader><CardTitle>Atalhos</CardTitle></CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                    <Link href="/agenda"><Button variant="outline">Agenda</Button></Link>
                    <Link href="/relatorios/sessoes"><Button variant="outline">Relatório de sessões</Button></Link>
                    <Link href="/relatorios/financeiro"><Button variant="outline">Relatório financeiro</Button></Link>
                    <Link href="/relatorios/comparecimento"><Button variant="outline">Relatório de comparecimento</Button></Link>
                </CardContent>
            </Card>
        </div>
    );
}

function PatientDashboard({ data }) {
    return (
        <div className="flex flex-col gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>Próxima sessão</CardTitle>
                </CardHeader>
                <CardContent className="text-sm">
                    {data.next_session ? (
                        <p>{formatDateTime(data.next_session.scheduled_at)}</p>
                    ) : (
                        <p className="text-muted-foreground">Nenhuma sessão agendada.</p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Pendências financeiras</CardTitle></CardHeader>
                <CardContent>
                    <p className="text-2xl font-semibold">{formatCurrency(data.pending_charges_total)}</p>
                    <p className="text-sm text-muted-foreground">{data.pending_charges_count} cobrança(s) em aberto</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Histórico recente</CardTitle></CardHeader>
                <CardContent className="flex flex-col gap-1 text-sm">
                    {data.history.length === 0 && <p className="text-muted-foreground">Nenhuma sessão anterior.</p>}
                    {data.history.map((session) => (
                        <span key={session.id}>{formatDateTime(session.scheduled_at)} — {session.status}</span>
                    ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Atalhos</CardTitle></CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                    <Link href="/agenda"><Button variant="outline">Agendar sessão</Button></Link>
                    <Link href="/minhas-sessoes"><Button variant="outline">Minhas sessões</Button></Link>
                    <Link href={`/pacientes/${data.patient_id}/financeiro`}><Button variant="outline">Situação financeira</Button></Link>
                </CardContent>
            </Card>
        </div>
    );
}

export default function Dashboard({ tenant, role, psychologistDashboard, patientDashboard }) {
    const { props } = usePage();
    const user = props.auth?.user;

    function logout() {
        router.post('/logout');
    }

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                        <div>
                            <CardTitle>Bem-vindo, {user?.name}</CardTitle>
                            <CardDescription>{tenant ? tenant.name : 'Sem tenant resolvido (Super Admin)'}</CardDescription>
                        </div>
                        <NotificationBell />
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" onClick={logout}>Sair</Button>
                    </CardContent>
                </Card>

                {role === 'psicologo' && psychologistDashboard && <PsychologistDashboard data={psychologistDashboard} />}
                {role === 'paciente' && patientDashboard && <PatientDashboard data={patientDashboard} />}
            </div>
        </div>
    );
}
