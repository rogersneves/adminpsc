import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/Components/InputError';

export default function Create() {
    const [specialtyInput, setSpecialtyInput] = useState('');

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        professional_registry: '',
        specialties: [],
        default_session_duration_minutes: 50,
    });

    function addSpecialty() {
        if (!specialtyInput.trim()) return;
        setData('specialties', [...data.specialties, specialtyInput.trim()]);
        setSpecialtyInput('');
    }

    function removeSpecialty(index) {
        setData('specialties', data.specialties.filter((_, i) => i !== index));
    }

    function submit(e) {
        e.preventDefault();
        post('/psicologos');
    }

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto max-w-lg">
                <Card>
                    <CardHeader>
                        <CardTitle>Cadastrar psicólogo</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="name">Nome</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="email">E-mail</Label>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                                <InputError message={errors.email} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="professional_registry">CRP</Label>
                                <Input
                                    id="professional_registry"
                                    value={data.professional_registry}
                                    onChange={(e) => setData('professional_registry', e.target.value)}
                                    required
                                />
                                <InputError message={errors.professional_registry} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="default_session_duration_minutes">Duração padrão da sessão (min)</Label>
                                <Input
                                    id="default_session_duration_minutes"
                                    type="number"
                                    min={10}
                                    max={240}
                                    value={data.default_session_duration_minutes}
                                    onChange={(e) => setData('default_session_duration_minutes', e.target.value)}
                                />
                                <InputError message={errors.default_session_duration_minutes} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label>Especialidades</Label>
                                <div className="flex gap-2">
                                    <Input value={specialtyInput} onChange={(e) => setSpecialtyInput(e.target.value)} placeholder="Ex.: TCC" />
                                    <Button type="button" variant="outline" onClick={addSpecialty}>Adicionar</Button>
                                </div>
                                <ul className="flex flex-col gap-1">
                                    {data.specialties.map((specialty, i) => (
                                        <li key={i} className="flex items-center justify-between rounded bg-muted px-2 py-1 text-sm">
                                            {specialty}
                                            <button type="button" onClick={() => removeSpecialty(i)} className="text-destructive">remover</button>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <Button type="submit" disabled={processing}>Cadastrar</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
