import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp, router } from '@inertiajs/react';

/*
 * Tema por tenant (Fase 11 + identidade AdminPSC): a clínica pode escolher a cor
 * primária (branding.primary_color, prop compartilhada do Inertia). Injetamos como
 * override das variáveis CSS --primary/--ring/--sidebar-primary sobre o token da
 * marca, sem rebuild de CSS por cliente (docs/05-UIUX-Design-System.md).
 */
function applyBranding(page) {
    const color = page?.props?.branding?.primary_color;
    const root = document.documentElement;

    if (color) {
        root.style.setProperty('--primary', color);
        root.style.setProperty('--ring', color);
        root.style.setProperty('--sidebar-primary', color);
        root.style.setProperty('--sidebar-ring', color);
    } else {
        ['--primary', '--ring', '--sidebar-primary', '--sidebar-ring'].forEach((v) =>
            root.style.removeProperty(v),
        );
    }
}

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

        return pages[`./Pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        applyBranding(props.initialPage);
        createRoot(el).render(<App {...props} />);
    },
});

// Reaplica ao navegar (caso a marca mude entre páginas).
router.on('navigate', (event) => applyBranding(event.detail.page));
