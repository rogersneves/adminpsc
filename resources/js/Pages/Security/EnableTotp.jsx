import { useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function EnableTotp({ secret, otpauthUri }) {
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    function submit(e) {
        e.preventDefault();
        post('/security/totp/setup');
    }

    return (
        <GuestLayout
            title="Ativar autenticação por aplicativo"
            description="Adicione esta chave no seu aplicativo autenticador (Google Authenticator, Authy, etc)."
        >
            <div className="flex flex-col gap-4">
                <div className="rounded-lg bg-muted p-3 text-sm">
                    <p className="font-medium">Chave secreta</p>
                    <p className="break-all font-mono text-muted-foreground">{secret}</p>
                </div>
                <div className="rounded-lg bg-muted p-3 text-sm">
                    <p className="font-medium">URI</p>
                    <p className="break-all font-mono text-muted-foreground">{otpauthUri}</p>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="code">Código do app autenticador</Label>
                        <Input
                            id="code"
                            inputMode="numeric"
                            maxLength={6}
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            required
                        />
                        <InputError message={errors.code} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        Confirmar e ativar
                    </Button>
                </form>
            </div>
        </GuestLayout>
    );
}
