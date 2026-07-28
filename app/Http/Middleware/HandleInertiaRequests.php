<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Modules\Settings\Services\TenantSettings;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'roles' => $request->user()->getRoleNames()->all(),
                        'can' => $this->abilities($request->user()),
                    ]
                    : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            // Chave própria (não aninhada em "notifications") de propósito: a página
            // Notifications/Index usa a prop "notifications" para a lista paginada —
            // se essa shared prop usasse a mesma chave, a prop da página venceria o
            // merge do Inertia e "unreadCount" sumiria silenciosamente nessa página.
            'unreadNotificationsCount' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            // Marca por tenant (Fase 11) — lazy: só resolve quando a página usa.
            'branding' => fn () => $this->branding($request),
        ];
    }

    /**
     * Permissões usadas pela navegação do frontend (sidebar role-aware). Espelham as
     * checagens `can:` das rotas — a UI só esconde o que o backend já protege.
     *
     * @return array<string, bool>
     */
    private function abilities($user): array
    {
        $permissions = [
            'manage-users', 'manage-financial', 'manage-cms', 'manage-legal',
            'manage-clinic-settings', 'view-audit-log', 'platform.manage-tenants',
        ];

        $can = [];
        foreach ($permissions as $permission) {
            $can[$permission] = $user->can($permission);
        }

        return $can;
    }

    /**
     * @return array{display_name: string, primary_color: string}|null
     */
    private function branding(Request $request): ?array
    {
        $tenant = $request->user()?->tenant;

        if ($tenant === null) {
            return null;
        }

        $settings = app(TenantSettings::class);

        return [
            'display_name' => (string) $settings->get($tenant, 'branding.display_name'),
            'primary_color' => (string) $settings->get($tenant, 'branding.primary_color'),
        ];
    }
}
