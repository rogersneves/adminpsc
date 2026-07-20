import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function Dashboard({ tenant }) {
    const { props } = usePage();
    const user = props.auth?.user;

    function logout() {
        router.post('/logout');
    }

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Bem-vindo, {user?.name}</CardTitle>
                        <CardDescription>{tenant ? tenant.name : 'Sem tenant resolvido (Super Admin)'}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            Login, verificação de e-mail, MFA e resolução de tenant concluídos com sucesso.
                        </p>
                        <Button className="mt-4" variant="outline" onClick={logout}>
                            Sair
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
