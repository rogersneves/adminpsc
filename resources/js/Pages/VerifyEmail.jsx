import { router, useForm, usePage } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';

export default function VerifyEmail() {
    const { props } = usePage();
    const { processing } = useForm({});

    function resend() {
        router.post('/email/verification-notification', {}, { preserveScroll: true });
    }

    function logout() {
        router.post('/logout');
    }

    return (
        <GuestLayout
            title="Confirme seu e-mail"
            description="Enviamos um link de verificação para o seu e-mail. Clique nele para ativar sua conta."
            status={props.flash?.status}
        >
            <div className="flex flex-col gap-3">
                <Button type="button" onClick={resend} disabled={processing}>
                    Reenviar e-mail de verificação
                </Button>
                <Button type="button" variant="ghost" onClick={logout}>
                    Sair
                </Button>
            </div>
        </GuestLayout>
    );
}
