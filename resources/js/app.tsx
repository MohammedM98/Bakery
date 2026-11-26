import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'المخبز';

// Legacy Blade/Livewire pages load this bundle too but have no Inertia root; skip them.
if (document.querySelector('script[data-page="app"]')) {
    createInertiaApp({
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: async (name) => {
            const pages = import.meta.glob<{ default: ResolvedComponent }>('./Pages/**/*.tsx');
            return (await pages[`./Pages/${name}.tsx`]()).default;
        },
        setup({ el, App, props }) {
            createRoot(el!).render(<App {...props} />);
        },
        progress: { color: 'var(--primary)' },
    });
}
