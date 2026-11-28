import {
    CalendarDays,
    ClipboardCheck,
    FileSpreadsheet,
    History,
    LayoutDashboard,
    type LucideIcon,
    ScrollText,
    Settings,
    UserPen,
    UserRoundCog,
    Users,
    Warehouse,
} from 'lucide-react';
import type { UserRole } from '@/types/auth';

export type NavItem = {
    label: string;
    icon: LucideIcon;
    roles: UserRole[];
    /** Kosong = halaman belum dibuat; ditampilkan nonaktif dengan label "Segera". */
    href?: string;
};

const both: UserRole[] = ['ADMIN', 'SUPERVISOR'];
const admin: UserRole[] = ['ADMIN'];

// Urutan & isi mengikuti PRD.md §5.
export const navItems: NavItem[] = [
    { label: 'Dashboard', icon: LayoutDashboard, roles: both, href: '/panel' },
    {
        label: 'Persetujuan',
        icon: ClipboardCheck,
        roles: both,
        href: '/panel/approvals',
    },
    {
        label: 'Riwayat lembur',
        icon: History,
        roles: both,
        href: '/panel/overtimes',
    },
    { label: 'Input atas nama', icon: UserPen, roles: both },
    { label: 'Export Excel', icon: FileSpreadsheet, roles: both },
    { label: 'Karyawan', icon: Users, roles: admin },
    { label: 'Departemen', icon: Warehouse, roles: admin },
    { label: 'Pengguna', icon: UserRoundCog, roles: admin },
    { label: 'Kalender libur', icon: CalendarDays, roles: admin },
    { label: 'Pengaturan', icon: Settings, roles: admin },
    { label: 'Audit log', icon: ScrollText, roles: admin },
];
