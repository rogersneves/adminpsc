import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/Components/InputError';

const MODALITY_LABELS = { presencial: 'Presencial', online: 'Online', domiciliar: 'Domiciliar' };

function formatDate(dateString) {
    return new Date(dateString + 'T00:00:00').toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
    });
}

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

export default function Book({ psychologist, slotsByDate, modalities }) {
    const { props } = usePage();
    const [selectedSlot, setSelectedSlot] = useState(null);

    const bookForm = useForm({ scheduled_at: '', duration_minutes: '', modality: modalities[0] });
    const waitingListForm = useForm({ from: '', to: '', notes: '' });

    function selectSlot(slot) {
        setSelectedSlot(slot);
        bookForm.setData({
            scheduled_at: slot.starts_at,
            duration_minutes: slot.duration_minutes,
            modality: bookForm.data.modality,
        });
    }

    function submitBooking(e) {
        e.preventDefault();
        bookForm.post(`/agenda/${psychologist.id}/reservar`);
    }

    function submitWaitingList(e) {
        e.preventDefault();
        waitingListForm.post(`/agenda/${psychologist.id}/lista-espera`, {
            onSuccess: () => waitingListForm.reset(),
        });
    }

    const dates = Object.keys(slotsByDate);

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {props.flash?.status && (
                    <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">
                        {props.flash.status}
                    </p>
                )}

                <h1 className="text-xl font-semibold">Agendar com {psychologist.name}</h1>

                {dates.length === 0 && (
                    <Card>
                        <CardContent className="pt-4 text-sm text-muted-foreground">
                            Nenhum horário disponível nos próximos dias. Entre na lista de espera abaixo.
                        </CardContent>
                    </Card>
                )}

                {dates.map((date) => (
                    <div key={date} className="flex flex-col gap-2">
                        <p className="text-sm font-medium capitalize">{formatDate(date)}</p>
                        <div className="flex flex-wrap gap-2">
                            {slotsByDate[date].map((slot) => (
                                <button
                                    key={slot.starts_at}
                                    type="button"
                                    onClick={() => selectSlot(slot)}
                                    className={`rounded-lg border px-3 py-1.5 text-sm ${
                                        selectedSlot?.starts_at === slot.starts_at
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-white'
                                    }`}
                                >
                                    {formatTime(slot.starts_at)}
                                </button>
                            ))}
                        </div>
                    </div>
                ))}

                {selectedSlot && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Confirmar reserva</CardTitle>
                            <CardDescription>
                                {formatDate(selectedSlot.starts_at.slice(0, 10))} às {formatTime(selectedSlot.starts_at)}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitBooking} className="flex flex-col gap-4">
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="modality">Modalidade</Label>
                                    <select
                                        id="modality"
                                        className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm"
                                        value={bookForm.data.modality}
                                        onChange={(e) => bookForm.setData('modality', e.target.value)}
                                    >
                                        {modalities.map((m) => (
                                            <option key={m} value={m}>{MODALITY_LABELS[m] ?? m}</option>
                                        ))}
                                    </select>
                                    <InputError message={bookForm.errors.modality} />
                                    <InputError message={bookForm.errors.scheduled_at} />
                                </div>
                                <Button type="submit" disabled={bookForm.processing}>Confirmar</Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Não encontrou um horário bom?</CardTitle>
                        <CardDescription>Entre na lista de espera para este psicólogo.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitWaitingList} className="flex flex-col gap-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="from">De</Label>
                                    <Input id="from" type="date" value={waitingListForm.data.from} onChange={(e) => waitingListForm.setData('from', e.target.value)} />
                                    <InputError message={waitingListForm.errors.from} />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="to">Até</Label>
                                    <Input id="to" type="date" value={waitingListForm.data.to} onChange={(e) => waitingListForm.setData('to', e.target.value)} />
                                    <InputError message={waitingListForm.errors.to} />
                                </div>
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="notes">Observações</Label>
                                <Input id="notes" value={waitingListForm.data.notes} onChange={(e) => waitingListForm.setData('notes', e.target.value)} />
                            </div>
                            <Button type="submit" variant="outline" disabled={waitingListForm.processing}>
                                Entrar na lista de espera
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
