import type { ReactNode } from 'react';
import { vi } from 'vitest';
import type { AuthUser } from '@/types/auth';

/**
 * Pengganti @inertiajs/react untuk test komponen: usePage membaca props dari `pageProps`.
 */
export const pageProps: { auth: { user: AuthUser | null }; url: string } = {
    auth: { user: null },
    url: '/panel',
};

export function inertiaMock() {
    return {
        usePage: () => ({ props: pageProps, url: pageProps.url }),
        Head: ({ title }: { title?: string }) => <title>{title}</title>,
        Link: ({
            href,
            children,
            className,
            ...rest
        }: {
            href: string | { url: string };
            children: ReactNode;
            className?: string;
            [key: string]: unknown;
        }) =>
            // Seperti Inertia asli: as="button" merender <button>, selain itu <a>.
            rest.as === 'button' ? (
                <button type="button" className={className}>
                    {children}
                </button>
            ) : (
                <a
                    href={typeof href === 'string' ? href : href.url}
                    className={className}
                    aria-current={rest['aria-current'] as 'page' | undefined}
                >
                    {children}
                </a>
            ),
        router: { flushAll: vi.fn(), on: vi.fn(() => () => undefined) },
    };
}

export function makeUser(overrides: Partial<AuthUser> = {}): AuthUser {
    return {
        id: 1,
        name: 'Supervisor Produksi',
        username: 'spv.produksi',
        role: 'SUPERVISOR',
        department: { id: 1, name: 'Produksi' },
        ...overrides,
    };
}
