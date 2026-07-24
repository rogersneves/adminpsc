import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

export function NotificationBell() {
    const { props } = usePage();
    const unreadCount = props.unreadNotificationsCount ?? 0;

    return (
        <Link href="/notificacoes" className="relative inline-flex items-center" aria-label="Notificações">
            <Bell className="h-5 w-5" />
            {unreadCount > 0 && (
                <span className="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-medium text-white">
                    {unreadCount > 99 ? '99+' : unreadCount}
                </span>
            )}
        </Link>
    );
}
