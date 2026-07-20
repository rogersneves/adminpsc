import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function GuestLayout({ title, description, status, children }) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-neutral-50 px-4 py-12">
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle className="text-xl">{title}</CardTitle>
                    {description && <CardDescription>{description}</CardDescription>}
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {status && (
                        <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">
                            {status}
                        </p>
                    )}
                    {children}
                </CardContent>
            </Card>
        </div>
    );
}
