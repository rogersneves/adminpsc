import { Link, useForm, usePage } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Login() {
    const { props } = usePage();
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    function submit(e) {
        e.preventDefault();
        post('/login');
    }

    return (
        <GuestLayout title="Entrar" description="Acesse sua conta AdminPSC." status={props.flash?.status}>
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="email">E-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoFocus
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

                <div className="flex justify-end">
                    <Link href="/forgot-password" className="text-sm text-muted-foreground underline">
                        Esqueceu a senha?
                    </Link>
                </div>

                <Button type="submit" disabled={processing}>
                    Entrar
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    Ainda não tem conta? <Link href="/register" className="underline">Cadastre sua clínica</Link>
                </p>
            </form>
        </GuestLayout>
    );
}
