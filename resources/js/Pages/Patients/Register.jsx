import { Link, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Register({ tenant }) {
    const { data, setData, post, processing, errors } = useForm({
        display_name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        post(`/c/${tenant.slug}/paciente/registro`);
    }

    return (
        <GuestLayout title={`Cadastro de paciente — ${tenant.name}`} description="Crie sua conta para agendar suas sessões.">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="display_name">Nome</Label>
                    <Input
                        id="display_name"
                        value={data.display_name}
                        onChange={(e) => setData('display_name', e.target.value)}
                        autoFocus
                        required
                    />
                    <InputError message={errors.display_name} />
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="email">E-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="password">Senha</Label>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="password_confirmation">Confirme a senha</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                </div>

                <Button type="submit" disabled={processing} className="mt-2">
                    Criar conta
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    Já tem conta? <Link href="/login" className="underline">Entrar</Link>
                </p>
            </form>
        </GuestLayout>
    );
}
