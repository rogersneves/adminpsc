import { useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/Components/InputError';

function formatDateTime(iso) {
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

export default function Record({ patient, entries, availableSessions }) {
    const { props } = usePage();
    const { data, setData, post, processing, errors, reset } = useForm({
        notes: '',
        therapeutic_objectives: '',
        therapeutic_plan: '',
        session_id: '',
        attachment: null,
    });

    function submit(e) {
        e.preventDefault();
        post(`/pacientes/${patient.id}/prontuario`, {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    }

    function downloadUrl(attachmentId) {
        return `/prontuario/anexos/${attachmentId}/download`;
    }

    const latest = entries[0];

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {props.flash?.status && (
                    <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">
                        {props.flash.status}
                    </p>
                )}

                <h1 className="text-xl font-semibold">Prontuário — {patient.display_name}</h1>

                <Card>
                    <CardHeader>
                        <CardTitle>Nova entrada</CardTitle>
                        <CardDescription>Campos em branco mantêm o valor da versão anterior.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="notes">Anotações</Label>
                                <textarea
                                    id="notes"
                                    rows={4}
                                    className="rounded-lg border border-input bg-transparent p-2.5 text-sm"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder={latest?.notes ?? ''}
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="therapeutic_objectives">Objetivos terapêuticos</Label>
                                <textarea
                                    id="therapeutic_objectives"
                                    rows={2}
                                    className="rounded-lg border border-input bg-transparent p-2.5 text-sm"
                                    value={data.therapeutic_objectives}
                                    onChange={(e) => setData('therapeutic_objectives', e.target.value)}
                                    placeholder={latest?.therapeutic_objectives ?? ''}
                                />
                                <InputError message={errors.therapeutic_objectives} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="therapeutic_plan">Plano terapêutico</Label>
                                <textarea
                                    id="therapeutic_plan"
                                    rows={2}
                                    className="rounded-lg border border-input bg-transparent p-2.5 text-sm"
                                    value={data.therapeutic_plan}
                                    onChange={(e) => setData('therapeutic_plan', e.target.value)}
                                    placeholder={latest?.therapeutic_plan ?? ''}
                                />
                                <InputError message={errors.therapeutic_plan} />
                            </div>

                            {availableSessions.length > 0 && (
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="session_id">Vincular a uma sessão (opcional)</Label>
                                    <select
                                        id="session_id"
                                        className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm"
                                        value={data.session_id}
                                        onChange={(e) => setData('session_id', e.target.value)}
                                    >
                                        <option value="">Nenhuma</option>
                                        {availableSessions.map((session) => (
                                            <option key={session.id} value={session.id}>
                                                {formatDateTime(session.scheduled_at)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="attachment">Anexo (opcional, até 10MB)</Label>
                                <Input
                                    id="attachment"
                                    type="file"
                                    onChange={(e) => setData('attachment', e.target.files[0] ?? null)}
                                />
                                <InputError message={errors.attachment} />
                            </div>

                            <Button type="submit" disabled={processing}>Salvar nova versão</Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-3">
                    <h2 className="text-lg font-medium">Histórico</h2>
                    {entries.map((entry) => (
                        <Card key={entry.id}>
                            <CardHeader>
                                <CardTitle>Versão {entry.version}</CardTitle>
                                <CardDescription>{entry.author} · {formatDateTime(entry.created_at)}</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2 text-sm">
                                {entry.notes && <p><strong>Anotações:</strong> {entry.notes}</p>}
                                {entry.therapeutic_objectives && <p><strong>Objetivos:</strong> {entry.therapeutic_objectives}</p>}
                                {entry.therapeutic_plan && <p><strong>Plano:</strong> {entry.therapeutic_plan}</p>}
                                {entry.attachments.length > 0 && (
                                    <div className="flex flex-col gap-1">
                                        <strong>Anexos:</strong>
                                        {entry.attachments.map((attachment) => (
                                            <a
                                                key={attachment.id}
                                                href={downloadUrl(attachment.id)}
                                                className="text-primary underline"
                                            >
                                                {attachment.name}
                                            </a>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </div>
    );
}
