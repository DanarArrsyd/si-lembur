export type DateChoice = {
    value: 'TODAY' | 'YESTERDAY';
    label: string;
    date: string;
};

export type OvertimeSummary = {
    date: string;
    start_at: string;
    end_at: string;
    break_minutes: number;
    total_minutes: number;
    employee: { name: string; nik: string };
    department: string;
    description: string;
};

export type OvertimeReview = OvertimeSummary & {
    day_type: 'HARI_KERJA' | 'HARI_LIBUR';
    exceeds_daily_limit: boolean;
    exceeds_weekly_limit: boolean;
    daily_limit_minutes: number;
    weekly_limit_minutes: number;
};

export type OvertimeSubmission = OvertimeSummary & {
    number: string;
    status: 'PENDING' | 'APPROVED' | 'REJECTED';
};
