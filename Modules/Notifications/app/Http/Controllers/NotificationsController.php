<?php

declare(strict_types=1);

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(15)
            ->through(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
                ...$notification->data,
            ]);

        return Inertia::render('Notifications/Index', ['notifications' => $notifications]);
    }

    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->authorizeOwnership($request, $notification);

        $notification->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * `DatabaseNotification` não tem Policy própria no projeto (não é um Model de
     * negócio com tenant) — a única regra de autorização que importa é "esta
     * notification pertence ao usuário autenticado", checada explicitamente aqui,
     * mesmo padrão de defesa em profundidade de `CurrentTenant::ownsOrFail()`.
     */
    private function authorizeOwnership(Request $request, DatabaseNotification $notification): void
    {
        abort_unless(
            $notification->notifiable_type === $request->user()::class
                && $notification->notifiable_id === $request->user()->id,
            403,
        );
    }
}
