import type { ReactNode } from 'react';
import { Wordmark } from '@/components/wordmark';

type Props = {
    title?: string;
    description?: string;
    children: ReactNode;
};

/**
 * Halaman login: panel gelap dengan wordmark (DESIGN.md §8.1) + form di surface.
 * Di HP panel menjadi header ringkas di atas form.
 */
export default function AuthLayout({
    title = '',
    description = '',
    children,
}: Props) {
    return (
        <div className="grid min-h-svh grid-rows-[auto_1fr] bg-surface lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:grid-rows-1">
            <div className="flex items-end bg-sidebar px-4 py-6 lg:items-center lg:px-12">
                <Wordmark variant="login" />
            </div>

            <main className="flex items-start justify-center px-4 py-8 lg:items-center">
                <div className="w-full max-w-sm">
                    <div className="mb-6 flex flex-col gap-1">
                        <h1 className="text-[22px] leading-[30px] font-bold tracking-[-0.01em]">
                            {title}
                        </h1>
                        <p className="text-sm text-ink-muted">{description}</p>
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
