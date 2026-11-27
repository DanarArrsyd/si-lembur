import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Wordmark } from '@/components/wordmark';

type Props = {
    title?: string;
    showFormLink?: boolean;
    children: ReactNode;
};

/**
 * Layout form publik karyawan (DESIGN.md §4): satu kolom, maks 480px, padding 16px, tanpa sidebar.
 */
export default function PublicLayout({
    title = 'Input lembur',
    showFormLink = false,
    children,
}: Props) {
    return (
        <div className="min-h-svh bg-canvas">
            <Head title={title} />

            <header className="border-b border-line bg-surface">
                <div className="mx-auto flex max-w-[480px] flex-col gap-2 px-4 py-4">
                    <Wordmark variant="public" />
                    <Link
                        href={showFormLink ? '/' : '/status'}
                        className="inline-flex min-h-12 w-fit items-center rounded-sm text-sm font-semibold text-primary underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    >
                        {showFormLink ? 'Isi lembur' : 'Cek status pengajuan'}
                    </Link>
                </div>
            </header>

            <main className="mx-auto max-w-[480px] px-4 py-5 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
                {children}
            </main>
        </div>
    );
}
