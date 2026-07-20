import { router, useForm, usePage } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function MfaChallenge({ method }) {
    const { props } = usePage();
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    function submit(e) {
        e.preventDefault();
        post('/mfa/challenge');
    }

    function resend() {
        router.post('/mfa/resend', {}, { preserveScroll: true });
    }

    const description =
        method === 'totp'
            ? 'Digite o código do seu aplicativo autenticador.'
            : 'Enviamos um código de verificação para o seu e-mail.';

    return (
        <GuestLayout title="Verificação em duas etapas" description={description} status={props.flash?.status}>
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="code">Código</Label>
                    <Input
                        id="code"
                        inputMode="numeric"
                        maxLength={6}
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        autoFocus
                        required
                    />
                    <InputError message={errors.code} />
                </div>

                <Button type="submit" disabled={processing}>
                    Verificar
                </Button>

                {method !== 'totp' && (
                    <Button type="button" variant="ghost" onClick={resend}>
                        Reenviar código
                    </Button>
                )}
            </form>
        </GuestLayout>
    );
}
