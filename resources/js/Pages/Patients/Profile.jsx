import { useMemo, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/Components/InputError';

function calculateAge(birthDate) {
    if (!birthDate) return null;
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
}

export default function Profile({ patient }) {
    const { props } = usePage();
    const [phoneInput, setPhoneInput] = useState('');
    const [guardians, setGuardians] = useState([]);
    const [newGuardian, setNewGuardian] = useState({ name: '', relationship: '', document_number: '', email: '', phone: '' });

    const { data, setData, put, processing, errors } = useForm({
        document_number: '',
        birth_date: patient.birth_date ?? '',
        phones: patient.phones ?? [],
        address: patient.address ?? '',
        guardians: [],
    });

    const age = useMemo(() => calculateAge(data.birth_date), [data.birth_date]);
    const needsGuardian = age !== null && age < 16;
    const hasExistingGuardians = patient.guardians && patient.guardians.length > 0;

    function addPhone() {
        if (!phoneInput.trim()) return;
        setData('phones', [...data.phones, phoneInput.trim()]);
        setPhoneInput('');
    }

    function removePhone(index) {
        setData('phones', data.phones.filter((_, i) => i !== index));
    }

    function addGuardian() {
        if (!newGuardian.name || !newGuardian.relationship) return;
        const updated = [...guardians, newGuardian];
        setGuardians(updated);
        setData('guardians', updated);
        setNewGuardian({ name: '', relationship: '', document_number: '', email: '', phone: '' });
    }

    function removeGuardian(index) {
        const updated = guardians.filter((_, i) => i !== index);
        setGuardians(updated);
        setData('guardians', updated);
    }

    function submit(e) {
        e.preventDefault();
        put('/paciente/perfil');
    }

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                {props.flash?.status && (
                    <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">
                        {props.flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Completar perfil — {patient.display_name}</CardTitle>
                        <CardDescription>Esses campos são opcionais, mas ajudam sua clínica a te atender melhor.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-5">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="document_number">CPF</Label>
                                <Input
                                    id="document_number"
                                    value={data.document_number}
                                    onChange={(e) => setData('document_number', e.target.value)}
                                    placeholder={patient.has_document_number ? 'Já cadastrado — digite para substituir' : ''}
                                />
                                <InputError message={errors.document_number} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="birth_date">Data de nascimento</Label>
                                <Input
                                    id="birth_date"
                                    type="date"
                                    value={data.birth_date}
                                    onChange={(e) => setData('birth_date', e.target.value)}
                                />
                                <InputError message={errors.birth_date} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="address">Endereço</Label>
                                <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                                <InputError message={errors.address} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label>Telefones</Label>
                                <div className="flex gap-2">
                                    <Input
                                        value={phoneInput}
                                        onChange={(e) => setPhoneInput(e.target.value)}
                                        placeholder="(11) 91234-5678"
                                    />
                                    <Button type="button" variant="outline" onClick={addPhone}>Adicionar</Button>
                                </div>
                                <ul className="flex flex-col gap-1">
                                    {data.phones.map((phone, i) => (
                                        <li key={i} className="flex items-center justify-between rounded bg-muted px-2 py-1 text-sm">
                                            {phone}
                                            <button type="button" onClick={() => removePhone(i)} className="text-destructive">remover</button>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {needsGuardian && !hasExistingGuardians && (
                                <div className="flex flex-col gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3">
                                    <p className="text-sm text-amber-800">
                                        Pacientes menores de 16 anos precisam de um responsável legal cadastrado.
                                    </p>

                                    <div className="grid grid-cols-2 gap-2">
                                        <Input placeholder="Nome" value={newGuardian.name} onChange={(e) => setNewGuardian({ ...newGuardian, name: e.target.value })} />
                                        <Input placeholder="Parentesco" value={newGuardian.relationship} onChange={(e) => setNewGuardian({ ...newGuardian, relationship: e.target.value })} />
                                        <Input placeholder="CPF" value={newGuardian.document_number} onChange={(e) => setNewGuardian({ ...newGuardian, document_number: e.target.value })} />
                                        <Input placeholder="E-mail" value={newGuardian.email} onChange={(e) => setNewGuardian({ ...newGuardian, email: e.target.value })} />
                                        <Input placeholder="Telefone" value={newGuardian.phone} onChange={(e) => setNewGuardian({ ...newGuardian, phone: e.target.value })} />
                                    </div>
                                    <Button type="button" variant="outline" onClick={addGuardian}>Adicionar responsável</Button>

                                    <ul className="flex flex-col gap-1">
                                        {guardians.map((g, i) => (
                                            <li key={i} className="flex items-center justify-between rounded bg-white px-2 py-1 text-sm">
                                                {g.name} ({g.relationship})
                                                <button type="button" onClick={() => removeGuardian(i)} className="text-destructive">remover</button>
                                            </li>
                                        ))}
                                    </ul>
                                    <InputError message={errors.birth_date} />
                                </div>
                            )}

                            <Button type="submit" disabled={processing}>Salvar</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
