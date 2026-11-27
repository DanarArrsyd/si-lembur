import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { SidebarNav } from '@/components/panel/sidebar-nav';
import { TopBar } from '@/components/panel/top-bar';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';

type Props = {
    title: string;
    children: ReactNode;
};

/**
 * Layout area Supervisor & Admin (DESIGN.md §5): sidebar 240px di ≥1024px,
 * drawer di bawahnya, top bar 56px, konten di atas canvas dengan gutter 24px.
 *
 * Toast dan tooltip hanya dipakai di panel, jadi provider-nya ada di sini
 * (bukan di app.tsx) agar tidak ikut dimuat form publik.
 */
export default function PanelLayout({ title, children }: Props) {
    const { props, url } = usePage();
    const user = props.auth.user;
    const [menuOpen, setMenuOpen] = useState(false);

    if (!user) {
        return null;
    }

    return (
        <TooltipProvider delayDuration={0}>
            <div className="min-h-svh bg-canvas">
                <Head title={title} />

                <aside className="fixed inset-y-0 left-0 hidden w-[var(--sidebar-width)] lg:block">
                    <SidebarNav role={user.role} currentUrl={url} />
                </aside>

                <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                    <SheetContent
                        side="left"
                        className="w-[var(--sidebar-width)] max-w-[85vw] border-0 bg-sidebar p-0 text-sidebar-ink [&>button]:text-white"
                    >
                        <SheetTitle className="sr-only">Menu</SheetTitle>
                        <SheetDescription className="sr-only">
                            Navigasi area panel
                        </SheetDescription>
                        <SidebarNav
                            role={user.role}
                            currentUrl={url}
                            onNavigate={() => setMenuOpen(false)}
                        />
                    </SheetContent>
                </Sheet>

                <div className="lg:pl-[var(--sidebar-width)]">
                    <TopBar
                        title={title}
                        user={user}
                        onOpenMenu={() => setMenuOpen(true)}
                    />
                    <main className="p-4 lg:p-6">{children}</main>
                </div>
            </div>
            <Toaster />
        </TooltipProvider>
    );
}
