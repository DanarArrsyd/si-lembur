import { Link, router } from '@inertiajs/react';
import { LogOut, Menu } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
import type { AuthUser } from '@/types/auth';

const roleLabel: Record<AuthUser['role'], string> = {
    ADMIN: 'Admin',
    SUPERVISOR: 'Supervisor',
};

type Props = {
    title: string;
    user: AuthUser;
    onOpenMenu: () => void;
};

export function TopBar({ title, user, onOpenMenu }: Props) {
    const meta = user.department
        ? `${roleLabel[user.role]} · ${user.department.name}`
        : roleLabel[user.role];

    return (
        <header className="sticky top-0 z-30 flex h-[var(--topbar-height)] items-center gap-3 border-b border-line bg-surface px-4 lg:px-6">
            <Button
                variant="ghost"
                size="icon"
                className="size-11 lg:hidden"
                onClick={onOpenMenu}
                aria-label="Buka menu"
                title="Buka menu"
            >
                <Menu className="size-5" strokeWidth={1.5} />
            </Button>

            <h1 className="min-w-0 flex-1 truncate text-[22px] leading-[30px] font-bold tracking-[-0.01em]">
                {title}
            </h1>

            <div className="hidden text-right sm:block">
                <p className="text-sm font-semibold">{user.name}</p>
                <p className="text-xs font-medium text-ink-muted">{meta}</p>
            </div>

            <Button variant="ghost" className="min-h-11 lg:min-h-9" asChild>
                <Link
                    href={logout()}
                    as="button"
                    onClick={() => router.flushAll()}
                >
                    <LogOut strokeWidth={1.5} aria-hidden />
                    Keluar
                </Link>
            </Button>
        </header>
    );
}
