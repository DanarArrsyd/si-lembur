import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Wordmark } from '@/components/wordmark';
import { status } from '@/routes/public';

type Props = {
    title?: string;
    showStatusLink?: boolean;
    children: ReactNode;
};

/**
 * Layout form publik karyawan (DESIGN.md §4): satu kolom, maks 480px, padding 16px, tanpa sidebar.
 */
export default function PublicLayout({
    title = 'Input lembur',
    showStatusLink = true,
    children,
}: Props) {
    return (
        <div className="min-h-svh bg-canvas">
            <Head title={title} />

            <header className="border-b border-line bg-surface">
                <div className="mx-auto flex max-w-[480px] flex-col gap-2 px-4 py-4">
                    <Wordmark variant="public" />
                    {showStatusLink && (
                        <a
                            href={status.url()}
                            rel="noreferrer"
                            className="inline-flex min-h-12 w-fit items-center rounded-sm text-sm font-semibold text-primary underline"
                        >
                            Cek status pengajuan
                        </a>
                    )}
                </div>
            </header>

            <main className="mx-auto max-w-[480px] px-4 py-5 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
                {children}
            </main>
        </div>
    );
}
