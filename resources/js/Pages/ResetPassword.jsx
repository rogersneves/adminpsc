import { useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email: email ?? '',
        password: '',
        password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        post('/reset-password');
    }

    return (
        <GuestLayout title="Definir nova senha">
            <form onSubmit={submit} className="flex flex-col gap-4">
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
                    <Label htmlFor="password">Nova senha</Label>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoFocus
                        required
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="password_confirmation">Confirme a nova senha</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                </div>

                <Button type="submit" disabled={processing}>
                    Redefinir senha
                </Button>
            </form>
        </GuestLayout>
    );
}
