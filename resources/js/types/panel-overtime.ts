import type { EmployeeOption, OvertimeStatus } from '@/types/public';

export type PanelOvertimeRow = {
    id: number;
    number: string;
    employee: EmployeeOption;
    department: { id: number; name: string };
    date: string;
    start_at: string;
    end_at: string;
    break_minutes: number;
    total_minutes: number;
    status: OvertimeStatus;
    description: string;
    exceeds_daily_limit: boolean;
    exceeds_weekly_limit: boolean;
};

export type DashboardProps = {
    monthLabel: string;
    kpis: {
        pendingCount: number;
        approvedCount: number;
        approvedMinutes: number;
        overLimitCount: number;
    };
    recentPending: PanelOvertimeRow[];
    approvalsUrl: string | null;
};
