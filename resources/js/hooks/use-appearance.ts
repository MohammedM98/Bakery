import { useSyncExternalStore } from 'react';

export type Appearance = 'light' | 'dark';

// The initial class is set by an inline script in app.blade.php before first paint;
// the <html> class is the single source of truth so every caller stays in sync.
const listeners = new Set<() => void>();

const read = (): Appearance => (document.documentElement.classList.contains('dark') ? 'dark' : 'light');

function subscribe(listener: () => void) {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

function setAppearance(value: Appearance) {
    document.documentElement.classList.toggle('dark', value === 'dark');
    try {
        localStorage.setItem('theme', value);
    } catch {
        // Private mode: the choice just isn't remembered.
    }
    listeners.forEach((l) => l());
}

export function useAppearance() {
    const appearance = useSyncExternalStore(subscribe, read, () => 'light' as const);
    return { appearance, setAppearance };
}
