import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Brandmark } from '@/components/Brandmark';

export default function GuestLayout({ title, description, status, children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-6 bg-muted/40 px-4 py-12">
            <Brandmark />
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle className="text-xl">{title}</CardTitle>
                    {description && <CardDescription>{description}</CardDescription>}
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {status && (
                        <p className="rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm text-success" role="status">
                            {status}
                        </p>
                    )}
                    {children}
                </CardContent>
            </Card>
            <p className="text-xs text-muted-foreground">Simplificando a gestão da sua clínica de psicologia.</p>
        </div>
    );
}
