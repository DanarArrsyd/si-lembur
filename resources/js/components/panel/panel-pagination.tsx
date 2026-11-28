import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { PanelOvertimePage } from '@/types/panel-overtime';

export function PanelPagination({
    page,
    label,
    busy,
}: {
    page: Pick<
        PanelOvertimePage,
        'current_page' | 'last_page' | 'prev_page_url' | 'next_page_url'
    >;
    label: string;
    busy: boolean;
}) {
    if (page.last_page <= 1) return null;

    return (
        <nav
            aria-label={label}
            className="flex flex-wrap items-center justify-between gap-2"
        >
            <p className="text-sm text-ink-muted">
                Halaman {page.current_page} dari {page.last_page}
            </p>
            <div className="flex flex-wrap gap-2">
                {page.prev_page_url && !busy && (
                    <Button variant="secondary" size="lg" asChild>
                        <Link href={page.prev_page_url}>Sebelumnya</Link>
                    </Button>
                )}
                {page.next_page_url && !busy && (
                    <Button variant="secondary" size="lg" asChild>
                        <Link href={page.next_page_url}>Berikutnya</Link>
                    </Button>
                )}
            </div>
        </nav>
    );
}
