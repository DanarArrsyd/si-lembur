import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AuthLayout from '@/layouts/auth-layout';
import PanelLayout from '@/layouts/panel-layout';
import PublicLayout from '@/layouts/public-layout';

void createInertiaApp({
    // DESIGN.md §8.1: "{Judul halaman} — SI-LEMBUR"
    title: (title) => (title ? `${title} — SI-LEMBUR` : 'SI-LEMBUR'),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('panel/'):
                return PanelLayout;
            default:
                return PublicLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#1e6fd9',
    },
});
