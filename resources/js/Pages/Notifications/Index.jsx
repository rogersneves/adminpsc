import { Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

function formatDateTime(iso) {
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

export default function Index({ notifications }) {
    function markRead(id) {
        router.patch(`/notificacoes/${id}/lida`, {}, { preserveScroll: true });
    }

    function markAllRead() {
        router.post('/notificacoes/marcar-todas-lidas', {}, { preserveScroll: true });
    }

    const hasUnread = notifications.data.some((notification) => !notification.read_at);

    return (
        <div className="min-h-screen bg-neutral-50 p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                        <CardTitle>Notificações</CardTitle>
                        {hasUnread && (
                            <Button variant="outline" onClick={markAllRead}>
                                Marcar todas como lidas
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {notifications.data.length === 0 && (
                            <p className="text-sm text-muted-foreground">Nenhuma notificação por aqui.</p>
                        )}
                        {notifications.data.map((notification) => (
                            <div
                                key={notification.id}
                                className={`flex flex-col gap-1 rounded-md border p-3 ${notification.read_at ? 'bg-white' : 'bg-blue-50'}`}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="font-medium">{notification.title}</span>
                                    <span className="text-xs text-muted-foreground">{formatDateTime(notification.created_at)}</span>
                                </div>
                                <p className="text-sm text-muted-foreground">{notification.message}</p>
                                <div className="flex items-center gap-2">
                                    {notification.url && (
                                        <Link href={notification.url} className="text-sm underline">
                                            Ver detalhes
                                        </Link>
                                    )}
                                    {!notification.read_at && (
                                        <button
                                            type="button"
                                            onClick={() => markRead(notification.id)}
                                            className="text-sm text-muted-foreground underline"
                                        >
                                            Marcar como lida
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}

                        {(notifications.prev_page_url || notifications.next_page_url) && (
                            <div className="flex justify-between pt-2">
                                <Button
                                    variant="outline"
                                    disabled={!notifications.prev_page_url}
                                    onClick={() => router.get(notifications.prev_page_url)}
                                >
                                    Anterior
                                </Button>
                                <Button
                                    variant="outline"
                                    disabled={!notifications.next_page_url}
                                    onClick={() => router.get(notifications.next_page_url)}
                                >
                                    Próxima
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Link href="/dashboard" className="text-sm underline">
                    Voltar ao painel
                </Link>
            </div>
        </div>
    );
}
