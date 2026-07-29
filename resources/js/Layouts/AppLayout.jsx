import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    Building2,
    Calendar,
    CalendarClock,
    CalendarDays,
    FileText,
    HeartPulse,
    LayoutDashboard,
    LayoutTemplate,
    LogOut,
    MapPin,
    Menu,
    Settings,
    ShieldCheck,
    UserCog,
    Users,
    Wallet,
    X,
} from 'lucide-react';
import { Brandmark } from '@/components/Brandmark';
import { ThemeToggle } from '@/components/ThemeToggle';

function buildNav(user) {
    const roles = user?.roles ?? [];
    const can = user?.can ?? {};
    const has = (r) => roles.includes(r);

    return [
        { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, show: true },
        { label: 'Agendar', href: '/agenda', icon: Calendar, show: has('paciente') },
        { label: 'Minhas sessões', href: '/minhas-sessoes', icon: CalendarClock, show: has('paciente') },
        { label: 'Psicólogos', href: '/psicologos', icon: Users, show: can['manage-users'] || has('psicologo') },
        { label: 'Secretárias', href: '/secretarias', icon: UserCog, show: !!can['manage-users'] },
        { label: 'Unidades', href: '/unidades', icon: MapPin, show: !!can['manage-clinic-settings'] },
        { label: 'Agenda da unidade', href: '/agenda-unidade', icon: CalendarDays, show: !!can['manage-scheduling'] && !has('paciente') },
        { label: 'Financeiro', href: '/financeiro/pacientes', icon: Wallet, show: !!can['manage-financial'] },
        { label: 'Convênios', href: '/convenios', icon: HeartPulse, show: !!can['manage-financial'] },
        { label: 'Relatórios', href: '/relatorios/sessoes', icon: BarChart3, show: has('psicologo') || !!can['manage-financial'] },
        { label: 'Páginas', href: '/cms/paginas', icon: LayoutTemplate, show: !!can['manage-cms'] },
        { label: 'Documentos legais', href: '/lgpd/documentos', icon: FileText, show: !!can['manage-legal'] },
        { label: 'Meus dados', href: '/lgpd/meus-dados', icon: ShieldCheck, show: has('paciente') },
        { label: 'Configurações', href: '/configuracoes', icon: Settings, show: !!can['manage-clinic-settings'] },
        { label: 'Tenants', href: '/plataforma/tenants', icon: Building2, show: !!can['platform.manage-tenants'] },
        { label: 'Notificações', href: '/notificacoes', icon: Bell, show: true },
    ].filter((item) => item.show);
}

function SidebarContent({ nav, currentUrl, onNavigate }) {
    return (
        <div className="flex h-full flex-col">
            <div className="flex h-[72px] items-center px-6">
                <Link href="/dashboard" onClick={onNavigate}>
                    <Brandmark />
                </Link>
            </div>
            <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                {nav.map((item) => {
                    const active = currentUrl === item.href || currentUrl.startsWith(item.href + '/');
                    const Icon = item.icon;
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={onNavigate}
                            className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                                active
                                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                    : 'text-sidebar-foreground/80 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground'
                            }`}
                            aria-current={active ? 'page' : undefined}
                        >
                            <Icon className="h-[18px] w-[18px]" />
                            {item.label}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}

export default function AppLayout({ title, actions, children }) {
    const { props, url } = usePage();
    const user = props.auth?.user;
    const nav = buildNav(user);
    const unread = props.unreadNotificationsCount ?? 0;
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <div className="min-h-screen bg-muted/40">
            {/* Sidebar — desktop */}
            <aside className="fixed inset-y-0 left-0 hidden w-[280px] border-r border-sidebar-border bg-sidebar lg:block">
                <SidebarContent nav={nav} currentUrl={url} />
            </aside>

            {/* Sidebar — mobile drawer */}
            {mobileOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setMobileOpen(false)} />
                    <aside className="absolute inset-y-0 left-0 w-[280px] border-r border-sidebar-border bg-sidebar">
                        <button
                            type="button"
                            onClick={() => setMobileOpen(false)}
                            className="absolute right-3 top-5 text-sidebar-foreground/70"
                            aria-label="Fechar menu"
                        >
                            <X className="h-5 w-5" />
                        </button>
                        <SidebarContent nav={nav} currentUrl={url} onNavigate={() => setMobileOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="lg:pl-[280px]">
                {/* Topbar */}
                <header className="sticky top-0 z-30 flex h-[72px] items-center gap-3 border-b border-border bg-background/80 px-4 backdrop-blur sm:px-6">
                    <button
                        type="button"
                        onClick={() => setMobileOpen(true)}
                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent lg:hidden"
                        aria-label="Abrir menu"
                    >
                        <Menu className="h-5 w-5" />
                    </button>

                    <h1 className="flex-1 truncate text-lg font-semibold text-foreground">{title}</h1>

                    <div className="flex items-center gap-1.5">
                        {actions}
                        <Link
                            href="/notificacoes"
                            className="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            aria-label="Notificações"
                        >
                            <Bell className="h-5 w-5" />
                            {unread > 0 && (
                                <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold text-white">
                                    {unread > 99 ? '99+' : unread}
                                </span>
                            )}
                        </Link>
                        <ThemeToggle />
                        <div className="mx-1 hidden h-6 w-px bg-border sm:block" />
                        <div className="hidden text-right sm:block">
                            <p className="text-sm font-medium leading-tight text-foreground">{user?.name}</p>
                            <p className="text-xs leading-tight text-muted-foreground">{user?.email}</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.post('/logout')}
                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            aria-label="Sair"
                        >
                            <LogOut className="h-5 w-5" />
                        </button>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
                    {props.flash?.status && (
                        <p className="mb-4 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm text-success" role="status">
                            {props.flash.status}
                        </p>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
