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

export type PanelOvertimePage<Row extends PanelOvertimeRow = PanelOvertimeRow> =
    {
        data: Row[];
        current_page: number;
        last_page: number;
        next_page_url: string | null;
        prev_page_url: string | null;
        total: number;
    };

export type ApprovalQueue = PanelOvertimePage;

export type HistoryFilters = {
    month: string;
    status: OvertimeStatus | null;
    employee_id: number | null;
};

export type OvertimeHistoryRow = PanelOvertimeRow & {
    can_cancel_approval: boolean;
};

export type OvertimeHistoryProps = {
    history: PanelOvertimePage<OvertimeHistoryRow>;
    filters: HistoryFilters;
    employees: EmployeeOption[];
    cancellationWindow: { min: string; max: string };
    errors?: { status?: string; overtime_date?: string };
};
