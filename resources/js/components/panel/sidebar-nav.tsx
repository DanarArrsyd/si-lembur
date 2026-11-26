import { Link } from '@inertiajs/react';
import { Wordmark } from '@/components/wordmark';
import { navItems } from '@/components/panel/nav-items';
import { cn } from '@/lib/utils';
import type { UserRole } from '@/types/auth';

type Props = {
    role: UserRole;
    currentUrl: string;
    onNavigate?: () => void;
};

export function SidebarNav({ role, currentUrl, onNavigate }: Props) {
    const items = navItems.filter((item) => item.roles.includes(role));
    const path = currentUrl.split('?')[0];

    return (
        <div className="flex h-full flex-col bg-sidebar">
            <div className="flex h-[var(--topbar-height)] items-center px-4">
                <Wordmark variant="sidebar" />
            </div>
            <nav
                aria-label="Menu utama"
                className="flex-1 overflow-y-auto px-2 py-3"
            >
                <ul className="flex flex-col gap-0.5">
                    {items.map(({ label, icon: Icon, href }) => {
                        const base =
                            'flex min-h-11 items-center gap-3 rounded-md px-3 text-sm lg:min-h-9';

                        if (!href) {
                            return (
                                <li
                                    key={label}
                                    aria-disabled="true"
                                    className={cn(
                                        base,
                                        'cursor-default text-sidebar-ink/60',
                                    )}
                                >
                                    <Icon
                                        className="size-5 shrink-0"
                                        strokeWidth={1.5}
                                        aria-hidden
                                    />
                                    <span className="flex-1">{label}</span>
                                    <span className="text-xs">Segera</span>
                                </li>
                            );
                        }

                        const active = path === href;

                        return (
                            <li key={label}>
                                <Link
                                    href={href}
                                    onClick={onNavigate}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        base,
                                        'transition-colors duration-150',
                                        active
                                            ? 'bg-primary font-semibold text-on-primary'
                                            : 'text-sidebar-ink hover:bg-sidebar-hover hover:text-white',
                                    )}
                                >
                                    <Icon
                                        className="size-5 shrink-0"
                                        strokeWidth={1.5}
                                        aria-hidden
                                    />
                                    <span>{label}</span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </div>
    );
}
