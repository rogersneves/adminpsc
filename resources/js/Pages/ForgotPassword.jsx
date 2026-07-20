import { useForm, usePage } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ForgotPassword() {
    const { props } = usePage();
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    function submit(e) {
        e.preventDefault();
        post('/forgot-password');
    }

    return (
        <GuestLayout
            title="Recuperar senha"
            description="Informe seu e-mail para receber um link de redefinição."
            status={props.flash?.status}
        >
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

                <Button type="submit" disabled={processing}>
                    Enviar link de redefinição
                </Button>
            </form>
        </GuestLayout>
    );
}
